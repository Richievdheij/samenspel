/**
 * The environment contract.
 *
 * `make env` renders $(ENV_FILE) from its committed template. The template IS the
 * contract: a key exists here because somebody declared it there.
 *
 * Four kinds of value, three of them declared as exported data below so that the
 * justification for each cannot be quietly omitted. The fourth — "chosen" — is
 * the residue, and the predicate is spelled the same way everywhere: declared in
 * the template with an empty value, not derived, not optional. That is
 * fail-closed, so a secret added to the template tomorrow is required of
 * production without anyone remembering to register it here.
 *
 * Importing this file does not converge anything; the entry point is guarded.
 */

import { chmodSync, readFileSync, writeFileSync } from 'node:fs'
import { execFileSync } from 'node:child_process'
import { readEnvFile, render, unquote } from './lib/dotenv.mjs'
import { pass, fail } from './lib/check.mjs'

/**
 * Random values. Development generates them; production refuses to invent them.
 *
 * Deliberately empty, and that is the honest answer rather than an unfilled slot.
 * A stock Laravel application has exactly one secret of this shape — APP_KEY —
 * and `php artisan key:generate` already owns writing it. Registering APP_KEY
 * here would give one value two writers, which overwrite each other on every
 * run. See `generateApplicationKey` below for how it is handled instead.
 */
export const GENERATED = {}

/**
 * name -> (values, ctx) => string | undefined
 *
 * Recomputed on every run and OVERWRITTEN — the single exception to "never
 * overwrite an existing value". The exception is what makes the rule safe:
 * derived values are calculations, so nothing anyone typed is lost, and a sticky
 * one would leave a rotated password's URLs holding the old value forever.
 *
 * Returning `undefined` means "not derived in this environment, leave it alone".
 *
 * For Laravel most derivation belongs in `config/*.php` instead, where it is
 * recomputed on every boot and cannot go stale. Only values that must physically
 * exist in the file belong here.
 */
export const DERIVED = {
  APP_URL: (values, ctx) => {
    if (ctx.env !== 'local') return undefined

    // Recomputed only while it is still a plain loopback URL — which is what
    // makes it a calculation over APP_PORT rather than a value being taken away
    // from somebody. A developer serving through Herd at
    // https://laravel-starter-template.test has CHOSEN that, and an
    // unconditionally derived value would silently put it back to
    // http://localhost:8000 on every `make env`, breaking their site and
    // contradicting this file's own promise that nothing anyone typed is lost.
    const current = unquote(values.APP_URL ?? '')
    const isDefaultShape =
      current === '' || /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?\/?$/.test(current)
    if (!isDefaultShape) return undefined

    return `http://localhost:${unquote(values.APP_PORT || '') || 8000}`
  },
}

/**
 * A DENYLIST: everything not named here is required. Being optional needs a
 * justification, which is the string.
 *
 * Empty, and truthfully so: every key the production template ships empty is a
 * credential or an address that production genuinely cannot start without.
 */
export const OPTIONAL = {}

/**
 * Environment variables the source reads but no template declares, each with the
 * reason it is allowed to be absent.
 *
 * Every entry here is stock Laravel integration configuration for a service this
 * template does not use. Declaring them would ship a wall of empty
 * credential-shaped keys that nobody fills and everybody learns to scroll past —
 * and `config/*.php` reads each of them behind a driver that is never selected.
 *
 * Add a service, and you delete its group from here and add its keys to BOTH
 * templates. That is the trade this list exists to force.
 */
const group = (reason, keys) => Object.fromEntries(keys.map((key) => [key, reason]))

export const RUNTIME_ONLY = {
  HOME: "Set by the operating system, not by us. Read only when `php artisan dev` starts, to find Herd's configuration.",
  ...group('AWS/S3 is not a configured disk or queue in this template.', [
    'AWS_ACCESS_KEY_ID',
    'AWS_SECRET_ACCESS_KEY',
    'AWS_DEFAULT_REGION',
    'AWS_BUCKET',
    'AWS_URL',
    'AWS_ENDPOINT',
    'SQS_SUFFIX',
    'DYNAMODB_ENDPOINT',
  ]),
  ...group('Memcached is not a configured cache store in this template.', [
    'MEMCACHED_USERNAME',
    'MEMCACHED_PASSWORD',
    'MEMCACHED_PERSISTENT_ID',
  ]),
  ...group('Redis is not a configured cache, queue or session driver in this template.', [
    'REDIS_URL',
    'REDIS_USERNAME',
    'REDIS_PASSWORD',
  ]),
  ...group('Third-party mail transports are not configured; MAIL_MAILER is log or smtp.', [
    'MAIL_URL',
    'MAIL_LOG_CHANNEL',
    'POSTMARK_API_KEY',
    'POSTMARK_MESSAGE_STREAM_ID',
    'RESEND_API_KEY',
  ]),
  ...group('Slack and Papertrail log channels are not in the configured stack.', [
    'LOG_SLACK_WEBHOOK_URL',
    'LOG_STDERR_FORMATTER',
    'PAPERTRAIL_URL',
    'PAPERTRAIL_PORT',
    'SLACK_BOT_USER_OAUTH_TOKEN',
    'SLACK_BOT_USER_DEFAULT_CHANNEL',
  ]),
  ...group('A DSN-style override of the connection this template configures key by key.', [
    'DB_URL',
    'DB_CACHE_CONNECTION',
    'DB_CACHE_LOCK_CONNECTION',
    'DB_CACHE_LOCK_TABLE',
    'DB_QUEUE_CONNECTION',
    'SESSION_CONNECTION',
    'SESSION_STORE',
    'CACHE_STORAGE_DISK',
    'MYSQL_ATTR_SSL_CA',
  ]),
}

/** Keys declared in one template on purpose. Everything else must be in both. */
export const DEV_ONLY = {}
export const PROD_ONLY = {}

export const templateFor = (env) => (env === 'local' ? '.env.example' : `.env.${env}.example`)
export const targetFor = (env) => (env === 'local' ? '.env' : `.env.${env}`)

const isEmpty = (value) => value === undefined || unquote(value) === ''

/**
 * A loopback address carrying a port that no `*_PORT` key declares.
 *
 * Fatal at `make env`, not merely reported in CI, because convergence adds keys
 * and never overwrites: move a port in the template and every existing checkout
 * keeps pointing at the old one, with nothing to notice it.
 */
function undeclaredPorts(values) {
  const declared = new Set(
    Object.entries(values)
      .filter(([key]) => key.endsWith('_PORT'))
      .map(([, value]) => unquote(value))
      .filter(Boolean),
  )
  const found = []
  for (const [key, value] of Object.entries(values)) {
    for (const match of unquote(value).matchAll(/(?:localhost|127\.0\.0\.1|\[::1\]):(\d+)/g)) {
      if (!declared.has(match[1])) found.push({ key, port: match[1] })
    }
  }
  return found
}

/**
 * `php artisan key:generate` is the only writer of APP_KEY. It is invoked once,
 * when the key is empty, and never again — so this function is idempotent and
 * does not fight the artisan command for ownership of the value.
 */
function generateApplicationKey(env) {
  if (env !== 'local') return false
  try {
    execFileSync('php', ['artisan', 'key:generate', '--no-interaction'], { stdio: 'pipe' })
    // Confirmed by reading the file back, not inferred from a zero exit status.
    // artisan can exit 0 having written nothing, and stdio:'pipe' swallows the
    // diagnostic — so the summary would report "APP_KEY generated" over an empty
    // key, and the next command would fail with an unrelated-looking error.
    return !isEmpty(readEnvFile(targetFor(env)).APP_KEY) ? true : null
  } catch {
    // artisan needs vendor/autoload.php. On a clone where dependencies are not
    // installed yet this throws, and an unhandled throw here prints a raw Node
    // buffer dump — pages of byte arrays naming no cause and no cure. The env
    // file has already been written correctly at this point; only the key is
    // missing, so say which command supplies it and carry on.
    return null
  }
}

export function converge(env) {
  const template = templateFor(env)
  const target = targetFor(env)

  const templateValues = readEnvFile(template)
  if (Object.keys(templateValues).length === 0) {
    fail('env', `The template ${template} declares no keys — is it missing?`, () => {})
    return
  }

  // The existing file wins. Order is load-bearing from here down.
  const existing = readEnvFile(target)
  const resolved = { ...templateValues, ...existing }

  // Development fills only EMPTY generated keys; production never invents one.
  for (const key of Object.keys(GENERATED)) {
    if (env !== 'local') continue
    if (isEmpty(resolved[key])) resolved[key] = randomSecret()
  }

  // Derived last, overwriting, because they are calculations over the rest.
  //
  // `wasDerived` records what this run actually computed, and the required-value
  // check below consults that rather than the DERIVED registry. Consulting the
  // registry would exempt a key that is derived in one environment and chosen in
  // another — APP_URL is exactly that — so production would accept it empty and
  // boot with no application URL. The predicate has to be fail-closed per run.
  const wasDerived = new Set()
  for (const [key, derive] of Object.entries(DERIVED)) {
    const value = derive(resolved, { env })
    if (value !== undefined) {
      resolved[key] = value
      wasDerived.add(key)
    }
  }

  // Checked over the TEMPLATE as well as the resolved values, and this is the
  // whole point rather than belt-and-braces. Convergence lets the existing file
  // win, so a port MOVED in the template never reaches `resolved` on a checkout
  // that already has an env file — and that is exactly the case this guard exists
  // for. Checking only the resolved values makes it a gate that can never fail on
  // the change it was written to catch.
  const collisions = [
    ...undeclaredPorts(templateValues).map((c) => ({ ...c, where: template })),
    ...undeclaredPorts(resolved).map((c) => ({ ...c, where: target })),
  ].filter((c, index, all) => all.findIndex((o) => o.key === c.key && o.port === c.port) === index)

  if (collisions.length > 0) {
    fail(
      'env',
      'Declare the port as a *_PORT key in both templates, then reference it. See .env.example.',
      () => {
        for (const { key, port, where } of collisions) {
          console.error(
            `  ${where} → ${key}\n    uses loopback port ${port}, which no *_PORT key declares`,
          )
        }
      },
    )
    return
  }

  writeFileSync(target, render(readFileSync(template, 'utf8'), resolved), 'utf8')
  chmodSync(target, 0o600)

  let generatedKey = false
  if (isEmpty(resolved.APP_KEY) && env === 'local') {
    generatedKey = generateApplicationKey(env)
  }

  const missing = Object.entries(readEnvFile(target))
    .filter(([key, value]) => isEmpty(value) && !wasDerived.has(key) && !(key in OPTIONAL))
    .map(([key]) => key)

  if (env !== 'local' && missing.length > 0) {
    fail('env', `Fill these in ${target}. Production never invents a credential.`, () => {
      for (const key of missing) console.error(`  ${key}\n    required, still empty`)
    })
    return
  }

  const notes = [`${Object.keys(resolved).length} keys`, 'mode 0600']
  if (generatedKey === true) notes.push('APP_KEY generated')
  if (generatedKey === null) notes.push("APP_KEY still empty, run 'make install' first")
  if (missing.length > 0) notes.push(`${missing.length} still empty`)
  pass('env', `${target} from ${template} — ${notes.join(', ')}`)
}

function randomSecret() {
  return Buffer.from(crypto.getRandomValues(new Uint8Array(32))).toString('base64')
}

if (process.argv[1]?.endsWith('env.mjs')) {
  converge(process.argv[2] || 'local')
}
