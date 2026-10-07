/**
 * Emit ONLY the database credentials, as a shell command prefix.
 *
 * `make integration` used to source the whole env file. That is wrong, and the
 * way it is wrong is worth writing down because it is invisible:
 *
 * PHPUnit's <env name="X" value="y"/> does NOT overwrite a variable the process
 * already has. That is exactly why forcing DB_CONNECTION=mysql here works at
 * all — but it cuts both ways. Sourcing .env also exports APP_ENV=local,
 * SESSION_DRIVER=database and CACHE_STORE=database, so every test setting in
 * phpunit.xml is silently ignored: CSRF verification switches back on and the
 * whole POST half of the suite fails with 419, for a reason that has nothing to
 * do with the database the gate was meant to test.
 *
 * So: five keys, and nothing else crosses over.
 *
 * Precedence is process environment first, then the local env file, then a
 * default. That one order serves both callers — CI supplies DB_* through the
 * service container's env, a laptop supplies them through .env.
 */

import { readEnvFile, unquote } from './lib/dotenv.mjs'

const file = readEnvFile('.env')

const resolve = (key, fallback) => {
  const fromProcess = process.env[key]
  if (fromProcess !== undefined && fromProcess !== '') return fromProcess
  const fromFile = unquote(file[key] ?? '')
  return fromFile !== '' ? fromFile : fallback
}

/** Single-quote for the shell, escaping any embedded quote. A password may contain anything. */
const quote = (value) => `'${String(value).replace(/'/g, `'\\''`)}'`

const values = {
  // Read by tests/Feature/DatabaseConnectionTest.php. Deliberately not declared
  // in phpunit.xml, so it arrives from here and nowhere else.
  EXPECTED_DB_DRIVER: 'mysql',
  DB_CONNECTION: 'mysql',
  DB_HOST: resolve('DB_HOST', '127.0.0.1'),
  DB_PORT: resolve('DB_PORT', '3306'),
  DB_DATABASE: resolve('DB_DATABASE', 'samenspel'),
  DB_USERNAME: resolve('DB_USERNAME', 'root'),
  DB_PASSWORD: resolve('DB_PASSWORD', ''),
}

process.stdout.write(
  Object.entries(values)
    .map(([key, value]) => `${key}=${quote(value)}`)
    .join(' '),
)
