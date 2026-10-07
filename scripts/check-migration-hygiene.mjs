/**
 * Migration hygiene.
 *
 * Three rules, each for a failure that only shows up when it is expensive:
 *
 *   1. A missing down(). `migrate:rollback` fails halfway and leaves a schema no
 *      migration describes, on the machine you were trying to rescue.
 *   2. A duplicate timestamp. Two migrations sharing a prefix run in an order
 *      that depends on the filesystem, so a foreign key can be created before its
 *      table on one machine and after it on another.
 *   3. A migration outside database/migrations. It is never run, and the column
 *      it adds is missing everywhere but the laptop it was written on.
 */

import { readFileSync } from 'node:fs'
import { basename } from 'node:path'
import { createCheck } from './lib/check.mjs'
import { trackedFiles } from './lib/git.mjs'

const MIGRATION_DIR = 'database/migrations'
const TIMESTAMPED = /^(\d{4}_\d{2}_\d{2}_\d{6})_[a-z0-9_]+\.php$/

const check = createCheck(
  'migration hygiene',
  'Migrations live in database/migrations, are named <timestamp>_snake_case.php, and every one has a down(). Generate them with `make migration name=<name>`.',
)

const all = trackedFiles()

// Directly in the migrations directory — no deeper. Laravel's migrator globs that
// one directory and does not recurse, so database/migrations/archive/x.php never
// loads. Treating it as a migration would exempt from every rule below exactly
// the file that silently does not run.
const isMigration = (file) =>
  file.startsWith(`${MIGRATION_DIR}/`) &&
  file.endsWith('.php') &&
  !file.slice(MIGRATION_DIR.length + 1).includes('/')

const migrations = all.filter(isMigration)

// Rule 3: a file that looks like a migration but sits somewhere the migrator will
// never look — including a subdirectory of the migrations directory itself.
for (const file of all) {
  if (isMigration(file)) continue
  if (/\d{4}_\d{2}_\d{2}_\d{6}_.*\.php$/.test(file)) {
    check.report(
      file,
      `looks like a migration but is not directly in ${MIGRATION_DIR}, which the migrator does not recurse into — so it will never run`,
    )
  }
}

const timestamps = new Map()

for (const file of migrations) {
  const name = basename(file)
  const match = TIMESTAMPED.exec(name)

  if (!match) {
    check.report(
      file,
      'is not named <YYYY_MM_DD_HHMMSS>_snake_case.php, so its run order is undefined',
    )
    continue
  }

  // Rule 2: duplicate timestamps.
  const seen = timestamps.get(match[1])
  if (seen) {
    check.report(
      file,
      `shares the timestamp ${match[1]} with ${seen}, so their order depends on the filesystem`,
    )
  } else {
    timestamps.set(match[1], file)
  }

  // Rule 1: a down() that actually exists.
  const source = readFileSync(file, 'utf8')
  if (!/function\s+down\s*\(/.test(source)) {
    check.report(file, 'has no down(), so migrate:rollback cannot undo it')
  }
}

check.finish(`${migrations.length} migrations, ${timestamps.size} distinct timestamps`)
