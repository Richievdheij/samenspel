/**
 * `env()` outside config/.
 *
 * This is the Laravel trap that costs a whole afternoon. Once `php artisan
 * config:cache` has run — which `make build` does, and which every production
 * deploy does — `env()` returns null everywhere except config/. Nothing errors.
 * No exception is thrown, no log line appears. The value simply arrives empty,
 * and the feature that depended on it is quietly off in production and fine on
 * every laptop.
 *
 * The rule: read configuration through `config('...')`, and let config/*.php be
 * the only place that calls env().
 *
 * SCOPE, and why it is this narrow: Larastan ships the same rule
 * (larastan.noEnvCallsOutsideOfConfig) and it already covers every path in
 * phpstan.neon — app, bootstrap, config, database, routes and tests. Running a
 * second opinion over those files would give one rule two homes, two messages and
 * two places to change it.
 *
 * So this check owns exactly what Larastan cannot see: Blade templates. They are
 * not in PHPStan's paths, they are compiled PHP, and `env()` in a view fails in
 * precisely the same silent way.
 *
 * Implemented in Node rather than with ast-grep on purpose: ast-grep would need a
 * global install, which this project's permissions forbid, and a single
 * unambiguous call shape gains nothing from an AST.
 */

import { readFileSync } from 'node:fs'
import { createCheck } from './lib/check.mjs'
import { trackedUnder } from './lib/git.mjs'

// `env(` preceded by a character that cannot be part of an identifier, so
// `something_env(` and `->env(` are not matched.
const ENV_CALL = /(^|[^\w$>:])env\s*\(/g

// Blade only. Everything else is Larastan's — see the note above.
const SCANNED = ['resources/views']

const check = createCheck(
  'env() in Blade',
  'Read configuration through config() in a template, never env(). PHP outside views is covered by Larastan (larastan.noEnvCallsOutsideOfConfig).',
)

const files = trackedUnder(...SCANNED).filter((file) => file.endsWith('.blade.php'))

for (const file of files) {
  const lines = readFileSync(file, 'utf8').split('\n')
  lines.forEach((line, index) => {
    // A commented-out example is prose, not a call.
    if (/^\s*(\/\/|#|\*)/.test(line)) return
    ENV_CALL.lastIndex = 0
    if (ENV_CALL.test(line)) {
      check.report(
        `${file}:${index + 1}`,
        'calls env() in a template — it returns null once the config is cached',
      )
    }
  })
}

check.finish(`${files.length} Blade templates under ${SCANNED.join(', ')} (PHP is Larastan's)`)
