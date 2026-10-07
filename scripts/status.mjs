/**
 * `make status` — what is set up, what is running, what is missing.
 *
 * Three properties this script must never lose:
 *
 *   1. It has NO prerequisites and ALWAYS exits 0. It is the one command that has
 *      to work when everything else is broken, so "the database is down" is an
 *      answer rather than a failure.
 *   2. It starts nothing, migrates nothing and seeds nothing. A status command
 *      with a side effect is unsafe to run in the middle of an incident, which is
 *      exactly when it gets run.
 *   3. It does NOT boot the application. `php artisan about` would be shorter and
 *      would fail precisely when the application is the broken thing — reporting
 *      a stack trace instead of the configuration you asked about. The site row
 *      asks App\Support\Herd through the autoloader alone (scripts/lib/site.mjs),
 *      which needs vendor/ and PHP but no working application.
 */

import { existsSync } from 'node:fs'
import {
  HOT_FILE,
  devSessionPids,
  isFree,
  isReachable,
  pidsOn,
  projectPorts,
} from './lib/ports.mjs'
import { readEnvFile, unquote } from './lib/dotenv.mjs'
import { git } from './lib/git.mjs'
import { site } from './lib/site.mjs'

const c = {
  ok: (s) => `\x1b[32m${s}\x1b[0m`,
  bad: (s) => `\x1b[31m${s}\x1b[0m`,
  warn: (s) => `\x1b[33m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
  bold: (s) => `\x1b[1m${s}\x1b[0m`,
}

const rows = []
const row = (label, state, detail = '') => rows.push({ label, state, detail })
const mark = { yes: c.ok('●'), no: c.bad('○'), warn: c.warn('◐'), bad: c.bad('●') }

// ─── Toolchain ──────────────────────────────────────────────────────────────
row(
  'php',
  existsSync('vendor/autoload.php') ? mark.yes : mark.no,
  existsSync('vendor/autoload.php') ? 'vendor/ installed' : "run 'make install'",
)

row(
  'node',
  existsSync('node_modules') ? mark.yes : mark.no,
  existsSync('node_modules') ? 'node_modules/ installed' : "run 'make install'",
)

// ─── Environment ────────────────────────────────────────────────────────────
const envExists = existsSync('.env')
const env = envExists ? readEnvFile('.env') : {}
row('.env', envExists ? mark.yes : mark.no, envExists ? '' : "run 'make env'")

const key = unquote(env.APP_KEY ?? '')
row('APP_KEY', key ? mark.yes : mark.no, key ? 'set' : "empty — run 'make env'")

row(
  'locale',
  mark.yes,
  `${unquote(env.APP_LOCALE ?? 'en')} (fallback ${unquote(env.APP_FALLBACK_LOCALE ?? 'en')}), ${unquote(env.APP_TIMEZONE ?? 'UTC')}`,
)

// ─── Database ───────────────────────────────────────────────────────────────
const connection = unquote(env.DB_CONNECTION ?? '')
if (connection === 'sqlite') {
  const file = 'database/database.sqlite'
  row(
    'database',
    existsSync(file) ? mark.yes : mark.warn,
    existsSync(file) ? `sqlite — ${file}` : `sqlite — ${file} missing, run 'make migrate'`,
  )
} else if (connection) {
  const host = unquote(env.DB_HOST ?? '127.0.0.1')
  const port = Number(unquote(env.DB_PORT ?? '3306')) || 3306
  // A CONNECT probe, not a bind probe, and not a login: it must answer without
  // credentials, and it must answer correctly for a host this machine does not
  // own. A bind probe against a remote address fails with EADDRNOTAVAIL, which
  // reads as "in use" and would report every remote database as reachable.
  const listening = await isReachable(host === 'localhost' ? '127.0.0.1' : host, port)
  row(
    'database',
    listening ? mark.yes : mark.bad,
    `${connection} — ${host}:${port} ${listening ? 'reachable' : 'not reachable'}`,
  )
} else {
  row('database', mark.no, 'DB_CONNECTION is not set')
}

// ─── Site ───────────────────────────────────────────────────────────────────
// The URL to open. Neither `php artisan dev` nor Herd prints it, and under Herd
// no server listens on APP_PORT at all — so without this row the board shows a
// free :8000 and no answer to "where is my site".
const where = site()
if (where === null) {
  row('site', mark.no, "unknown until 'make install' has run")
} else if (where.servedBy === 'herd') {
  row('site', mark.yes, `${where.url} — served by Herd`)
} else if (where.herdInstalled) {
  row(
    'site',
    mark.warn,
    `${where.url} while 'make dev' runs — Herd does not serve this folder: 'herd link --secure'`,
  )
} else {
  row('site', mark.yes, `${where.url} while 'make dev' runs (php artisan serve)`)
}

// A session whose terminal was closed keeps running, attached to pid 1; this row
// is how it is found again. `make stop` ends it.
const session = devSessionPids()
row(
  'make dev',
  session === null ? mark.warn : session.length ? mark.yes : c.dim('○'),
  session === null
    ? 'unknown — ps or lsof is not on PATH'
    : session.length
      ? `running, ${session.length} process(es) — 'make stop' ends it`
      : 'not running',
)

// ─── Ports ──────────────────────────────────────────────────────────────────
for (const { name, port, description: purpose } of projectPorts()) {
  // Under Herd nothing is meant to listen on APP_PORT; "free" is then the
  // healthy answer, and saying it is for `php artisan serve` reads as missing.
  const description =
    name === 'app' && where?.servedBy === 'herd' ? 'not used — Herd serves the site' : purpose
  const pids = pidsOn(port)
  const free = await isFree(port)
  // pids === null means the probe tool is absent, so "which process" is unknown —
  // distinct from "no process", which is what an empty list means.
  const who =
    pids === null
      ? 'an unidentified process (no port probe on PATH)'
      : pids.length
        ? `pid ${pids.join(', ')}`
        : 'another process'
  row(
    name,
    free ? c.dim('○') : mark.yes,
    free ? `:${port} free (${description})` : `:${port} in use by ${who}`,
  )
}

// ─── Build output ───────────────────────────────────────────────────────────
const manifest = 'public/build/manifest.json'
row(
  'assets',
  existsSync(manifest) ? mark.yes : mark.warn,
  existsSync(manifest) ? 'built' : "no manifest — run 'make build' or 'make dev'",
)

// public/hot with no Vite behind it points every page at a dead dev server.
const vitePort = projectPorts().find((p) => p.name === 'vite').port
if (existsSync(HOT_FILE) && (await isFree(vitePort))) {
  row('vite', mark.warn, `${HOT_FILE} is stale, Vite is not running — run 'make stop'`)
}

// ─── Git ────────────────────────────────────────────────────────────────────
const hooksPath = git(['config', 'core.hooksPath'])
row(
  'git hooks',
  hooksPath === '.githooks' ? mark.yes : mark.no,
  hooksPath === '.githooks' ? '.githooks armed' : "not armed — run 'make git-hooks'",
)

const branch = git(['rev-parse', '--abbrev-ref', 'HEAD']) || 'unknown'
const dirty = git(['status', '--porcelain']).split('\n').filter(Boolean).length
row('branch', mark.yes, `${branch}${dirty ? `, ${dirty} uncommitted change(s)` : ', clean'}`)

// ─── Render ─────────────────────────────────────────────────────────────────
const width = Math.max(...rows.map((r) => r.label.length))
console.log(`\n${c.bold('laravel-starter-template')}\n`)
for (const { label, state, detail } of rows) {
  console.log(`  ${state} ${label.padEnd(width)}  ${c.dim(detail)}`)
}
console.log()

// Always. See property 1 above.
process.exit(0)
