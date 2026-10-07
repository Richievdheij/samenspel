/**
 * `make integration` refuses to run without a real database.
 *
 * It never skips. A specification that skips because its database is missing
 * reports exactly like one that ran and passed, and the whole point of the
 * integration gate is to prove the application works on the engine production
 * uses rather than on the one the test suite defaults to.
 *
 * The schema is created when it does not exist. That is a deliberate side effect
 * and the only one: a test database is disposable, and the alternative is every
 * contributor running the same CREATE DATABASE by hand once.
 */

import { execFileSync } from 'node:child_process'
import { fail, pass } from './lib/check.mjs'

const env = {
  connection: process.env.DB_CONNECTION ?? 'mysql',
  host: process.env.DB_HOST ?? '127.0.0.1',
  port: process.env.DB_PORT ?? '3306',
  database: process.env.DB_DATABASE ?? '',
  username: process.env.DB_USERNAME ?? '',
  password: process.env.DB_PASSWORD ?? '',
}

const HINT =
  'Start MySQL and set DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD — ' +
  'locally they come from .env, and in CI from the service container. ' +
  'This gate never silently falls back to SQLite: that would pass while proving nothing.'

if (env.connection === 'sqlite') {
  fail('integration database', HINT, () => {
    console.error(
      '  DB_CONNECTION\n    is sqlite, but the integration gate exists to test the production engine',
    )
  })
  process.exit(1)
}

if (!env.database) {
  fail('integration database', HINT, () => {
    console.error('  DB_DATABASE\n    is empty, so there is no schema to migrate into')
  })
  process.exit(1)
}

// PHP rather than a Node driver: it is already a hard dependency, it uses the
// very extension Laravel will use, and it therefore proves the credentials work
// rather than merely that the port is open.
const probe = `
$host = getenv('DB_HOST'); $port = getenv('DB_PORT');
$user = getenv('DB_USERNAME'); $pass = getenv('DB_PASSWORD');
$name = getenv('DB_DATABASE');
try {
    $pdo = new PDO("mysql:host={$host};port={$port}", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    $pdo->exec("CREATE DATABASE IF NOT EXISTS \`{$name}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo $version;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
`

try {
  const version = execFileSync('php', ['-r', probe], {
    encoding: 'utf8',
    env: { ...process.env },
    stdio: ['ignore', 'pipe', 'pipe'],
  }).trim()

  pass(
    'integration database',
    `${env.connection} ${version} at ${env.host}:${env.port}, schema ${env.database}`,
  )
} catch (error) {
  fail('integration database', HINT, () => {
    const detail = (error.stderr ?? '').toString().trim() || error.message
    console.error(`  ${env.host}:${env.port}\n    ${detail}`)
  })
  process.exit(1)
}
