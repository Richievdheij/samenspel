/**
 * The environment contract check.
 *
 * This is the one check that catches configuration drift nothing else sees. A
 * missing environment variable does not fail a build, a lint or a test — it
 * arrives as null at runtime, in production, on a Friday.
 *
 * Three rules, each catching a different way the contract rots:
 *
 *   A. Template parity. A key added to one template and forgotten in the other
 *      reads as "not needed in that environment" rather than as an omission.
 *   B. Declared but never read. A key nobody reads is either a leftover from a
 *      removed feature or a typo of one that IS read.
 *   C. Read but never declared. A declaration-versus-declaration check is blind
 *      to a variable that nothing declares at all, which is the common case.
 *
 * The contracts themselves live in scripts/env.mjs, so `make env` and this check
 * can never disagree about which key is optional.
 */

import { readFileSync } from 'node:fs'
import { createCheck } from './lib/check.mjs'
import { parseEnv } from './lib/dotenv.mjs'
import { trackedUnder, trackedWithExtension } from './lib/git.mjs'
import { DEV_ONLY, OPTIONAL, PROD_ONLY, RUNTIME_ONLY } from './env.mjs'

const LOCAL_TEMPLATE = '.env.example'
const PROD_TEMPLATE = '.env.production.example'

/**
 * Keys a template declares that no source file reads, each with the reason it is
 * allowed to stay. Everything not listed here must be read by something.
 */
const DECLARED_ONLY = {
  APP_PORT:
    'read out of the env file by scripts/lib/ports.mjs, which `make status` and `make stop` use to probe and free the port. It never goes through env().',
  BCRYPT_ROUNDS:
    'read by vendor/laravel/framework/config/hashing.php. That config file is not published into config/, so the read is real but out of scan range.',
  BROADCAST_CONNECTION:
    'read by vendor/laravel/framework/config/broadcasting.php, likewise unpublished.',
}

/**
 * PHP: env('X') and env('X', default) — the second group tells them apart, which
 * rule C needs. Also $_ENV['X'] and $_SERVER['X'].
 */
const PHP_ENV_CALL = /env\(\s*['"]([A-Z][A-Z0-9_]{2,})['"]\s*(,)?/g
const PHP_SUPERGLOBAL = /\$_(?:ENV|SERVER)\[\s*['"]([A-Z][A-Z0-9_]{2,})['"]\s*\]/g

/** JS: process.env.X, process.env['X'] and import.meta.env.X. */
const JS_ENV =
  /(?:process|import\.meta)\.env(?:\.([A-Z][A-Z0-9_]{2,})|\[\s*['"]([A-Z][A-Z0-9_]{2,})['"]\s*\])/g

const check = createCheck(
  'env contract',
  'The templates are the contract: .env.example and .env.production.example. Register an exception in scripts/env.mjs (RUNTIME_ONLY) or in DECLARED_ONLY in this file, with a reason.',
)

const localValues = parseEnv(readFileSync(LOCAL_TEMPLATE, 'utf8'))
const prodValues = parseEnv(readFileSync(PROD_TEMPLATE, 'utf8'))
const localKeys = Object.keys(localValues)
const prodKeys = Object.keys(prodValues)
const declared = new Set([...localKeys, ...prodKeys])

// ─── Rule A: template parity ────────────────────────────────────────────────
for (const key of localKeys) {
  if (!prodKeys.includes(key) && !(key in DEV_ONLY)) {
    check.report(PROD_TEMPLATE, `${key} is declared in ${LOCAL_TEMPLATE} but not here`)
  }
}
for (const key of prodKeys) {
  if (!localKeys.includes(key) && !(key in PROD_ONLY)) {
    check.report(LOCAL_TEMPLATE, `${key} is declared in ${PROD_TEMPLATE} but not here`)
  }
}

// ─── Scan the source for every environment read ─────────────────────────────
const phpFiles = trackedUnder('app', 'bootstrap', 'config', 'database', 'routes').filter((file) =>
  file.endsWith('.php'),
)
const jsFiles = [
  ...trackedUnder('resources/js', 'scripts'),
  ...trackedWithExtension('vite.config.js'),
].filter((file) => file.endsWith('.js') || file.endsWith('.mjs'))

/** key -> { withDefault: boolean, files: Set<string> } */
const reads = new Map()
const noteRead = (key, file, withDefault) => {
  const entry = reads.get(key) ?? { withDefault: false, files: new Set() }
  entry.withDefault ||= withDefault
  entry.files.add(file)
  reads.set(key, entry)
}

for (const file of phpFiles) {
  const source = readFileSync(file, 'utf8')
  for (const match of source.matchAll(PHP_ENV_CALL)) noteRead(match[1], file, Boolean(match[2]))
  for (const match of source.matchAll(PHP_SUPERGLOBAL)) noteRead(match[1], file, false)
}

for (const file of jsFiles) {
  const source = readFileSync(file, 'utf8')
  for (const match of source.matchAll(JS_ENV)) {
    // Whether the read has a fallback is DETECTED, not assumed. Hardcoding `true`
    // here made rule C dead for every JavaScript file while the coverage line
    // still counted them — a check reporting nothing over a scope it claims to
    // cover, which is the exact failure this harness exists to make visible.
    const after = source.slice(match.index + match[0].length, match.index + match[0].length + 12)
    const hasFallback = /^\s*(\?\?|\|\|)/.test(after)
    noteRead(match[1] ?? match[2], file, hasFallback)
  }
}

// A template value may reference another key as ${OTHER}; that counts as a read.
for (const values of [localValues, prodValues]) {
  for (const value of Object.values(values)) {
    for (const match of value.matchAll(/\$\{([A-Z][A-Z0-9_]{2,})\}/g)) {
      noteRead(match[1], LOCAL_TEMPLATE, true)
    }
  }
}

// ─── Rule B: declared but never read ────────────────────────────────────────
for (const key of declared) {
  if (reads.has(key) || key in DECLARED_ONLY || key in OPTIONAL) continue
  check.report(
    localKeys.includes(key) ? LOCAL_TEMPLATE : PROD_TEMPLATE,
    `${key} is declared but nothing reads it — remove it, or add it to DECLARED_ONLY with a reason`,
  )
}

// ─── Rule C: read without a default and never declared ──────────────────────
for (const [key, entry] of reads) {
  if (declared.has(key) || entry.withDefault || key in RUNTIME_ONLY) continue
  check.report(
    [...entry.files][0],
    `${key} is read with no default and no template declares it — it will arrive as null`,
  )
}

check.finish(
  `${declared.size} declared keys, ${reads.size} read across ${phpFiles.length} PHP and ${jsFiles.length} JS files, ` +
    `${Object.keys(RUNTIME_ONLY).length} runtime-only exceptions`,
)
