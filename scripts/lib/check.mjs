import { isAbsolute, relative } from 'node:path'

const ROOT = process.cwd()
const c = {
  ok: (s) => `\x1b[32m${s}\x1b[0m`,
  bad: (s) => `\x1b[31m${s}\x1b[0m`,
  warn: (s) => `\x1b[33m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
}

export function createCheck(label, hint) {
  const findings = []
  return {
    report(path, message) {
      findings.push({ path: isAbsolute(path) ? relative(ROOT, path) : path, message })
    },
    get count() {
      return findings.length
    },
    finish(summary) {
      if (findings.length === 0) return pass(label, summary)
      fail(label, hint, () => {
        for (const { path, message } of findings) {
          console.error(`  ${c.warn(path)}\n    ${message}`)
        }
      })
    },
  }
}

/**
 * The line a check ends on when it finds nothing.
 *
 * `summary` states what was COVERED, never "ok". `0 files` is visible and `ok` is
 * not, and a check whose glob silently stopped matching is otherwise
 * indistinguishable from a clean tree.
 */
export function pass(label, summary) {
  console.log(`${c.ok('✓')} ${label} — ${summary}`)
}

/**
 * Findings that inform but do not fail: printed in yellow, exit code untouched.
 * For a convention the team has chosen to recommend rather than enforce.
 */
export function warn(label, hint, render) {
  console.error(`\n${c.warn('!')} ${label}\n`)
  render()
  console.error(`\n  ${c.dim(hint)}\n`)
}

/**
 * The findings, then where the rule lives, then a non-zero exit.
 *
 * `render` writes the findings itself, so a check whose finding is more than a
 * path and a sentence keeps its own layout without restating the header, the
 * hint and the exit.
 *
 * `hint` names WHERE THE CONVENTION IS WRITTEN DOWN, printed once after the
 * findings. A check that says a thing is wrong without saying where the rule
 * lives produces an argument rather than a repair.
 *
 * process.exitCode, never process.exit(1): on POSIX, stderr to a pipe is async,
 * so `make checks 2>&1 | tee build.log` can lose every finding while still
 * exiting non-zero — a red build with an empty reason.
 */
export function fail(label, hint, render) {
  console.error(`\n${c.bad('✗')} ${label}\n`)
  render()
  console.error(`\n  ${c.dim(hint)}\n`)
  process.exitCode = 1
}
