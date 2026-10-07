/**
 * No tracked text file may contain a carriage return.
 *
 * This is the only automated evidence that .gitattributes is doing its job, and
 * it is worth the most on Windows: git there defaults to core.autocrlf=true, and
 * a CRLF checkout breaks this repository in ways that report the wrong cause.
 *
 *   Makefile        a recipe line ending in \r hands the \r to the shell as part
 *                   of the command, so `php artisan migrate\r` is "command not
 *                   found" naming a command that looks perfectly correct.
 *   .githooks/*     a CRLF shebang fails with `bad interpreter: /usr/bin/env sh^M`,
 *                   naming a path that exists.
 *   scripts/*.mjs   `.` in a JavaScript regex excludes \r, so a KEY=value pattern
 *                   matches nothing at all and the env renderer writes a file that
 *                   looks configured and holds no value.
 *
 * `git status` reports none of this, because the index is LF either way.
 *
 * CI runs on Linux only, so this check is the standing guard rather than a
 * platform test: it catches a CRLF file the moment it is committed, from whichever
 * machine committed it, instead of waiting for the machine that cannot cope.
 */

import { readFileSync } from 'node:fs'
import { createCheck } from './lib/check.mjs'
import { git, trackedFiles } from './lib/git.mjs'

const check = createCheck(
  'line endings',
  'Every text file is LF. .gitattributes declares it; run `git add --renormalize .` to fix a checkout that already has CRLF in it.',
)

// Ask git which files it considers binary rather than guessing from extensions —
// .gitattributes is already the single source of truth for that.
const isBinary = (file) => /: binary: set$/m.test(git(['check-attr', 'binary', '--', file]))

const files = trackedFiles().filter((file) => !isBinary(file))
let scanned = 0

for (const file of files) {
  let contents
  try {
    contents = readFileSync(file)
  } catch {
    continue // a path in the index but not on disk is not this check's business
  }

  scanned++
  const index = contents.indexOf(0x0d)
  if (index !== -1) {
    const line = contents.subarray(0, index).toString('utf8').split('\n').length
    check.report(`${file}:${line}`, 'contains a carriage return; this file must be LF')
  }
}

check.finish(`${scanned} tracked text files, all LF`)
