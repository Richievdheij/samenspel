/**
 * Commit message grammar: `type(scope): subject`.
 *
 * The grammar is a recommendation: a message written another way gets a warning
 * and is still accepted, because the developer decided a commit must never be
 * blocked over its wording. Crediting an AI tool is the one thing still refused.
 *
 * Two entry points, and the second is not optional:
 *
 *   node scripts/check-commit-message.mjs <file>       the commit-msg hook
 *   node scripts/check-commit-message.mjs --range <r>  CI, and `make commit-messages`
 *
 * core.hooksPath is LOCAL git configuration. A clone that never ran `make install`
 * has no hook armed and is told nothing, so the range form is what actually holds
 * the line — the hook is only the fast feedback.
 *
 * `--cases` runs the grammar against known-good and known-bad messages. A check
 * whose pattern silently stopped matching reports nothing, forever, and looks
 * exactly like a clean history.
 */

import { readFileSync } from 'node:fs'
import { createCheck, fail, pass, warn } from './lib/check.mjs'
import { branchRange, commitsIn, rangeResolves } from './lib/branch.mjs'
import { git } from './lib/git.mjs'

const TYPES = [
  'feat',
  'fix',
  'docs',
  'style',
  'refactor',
  'perf',
  'test',
  'build',
  'ci',
  'chore',
  'revert',
]

// A closed type list, an optional scope, and a subject. The cap is 90 rather than
// the customary 72: measure your own log before choosing one, because 72 would
// reject a third of the messages in most real repositories.
const MAX_SUBJECT = 90
const GRAMMAR = new RegExp(`^(${TYPES.join('|')})(\\([a-z0-9][a-z0-9._/-]*\\))?!?: .+$`)

/**
 * Messages that are refused on principle would be refused AFTER the operation
 * that produced them, with a full index and no clean way back. A merge is the
 * clearest case: rejecting its message leaves the merge done and uncommittable.
 */
const EXEMPT = /^(Merge\b|Revert\b|fixup!|squash!|amend!)/

const HINT =
  'Format: type(scope): subject — e.g. `feat(auth): add password reset`. ' +
  `Types: ${TYPES.join(', ')}. Subject max ${MAX_SUBJECT} characters. ` +
  'No AI tool is ever credited as co-author. See .gitmessage.'

/** @returns {string|null} the reason this subject is refused, or null if it is fine. */
export function reasonToRefuse(subject) {
  if (!subject || subject.trim() === '') return 'is empty'
  if (EXEMPT.test(subject)) return null

  if (!GRAMMAR.test(subject)) {
    const type = subject.split(/[(:]/)[0]
    if (TYPES.includes(type)) return `has the type \`${type}\` but no \`: \` before the subject`
    return `does not start with one of ${TYPES.join(', ')} followed by \`: \``
  }

  if (subject.length > MAX_SUBJECT) {
    return `is ${subject.length} characters; the limit is ${MAX_SUBJECT}`
  }

  const text = subject.slice(subject.indexOf(': ') + 2)
  if (/^[A-Z][a-z]/.test(text)) return 'starts with a capital letter; subjects are lower case'
  if (text.endsWith('.')) return 'ends with a full stop'

  return null
}

/**
 * An AI tool credited in the message: a `Co-authored-by:` trailer for Claude or an
 * anthropic.com address, or the "Generated with Claude Code" footer. The author
 * of every commit here is the developer who made it, never the tool they used.
 *
 * Each pattern is anchored to the start of a line, so prose that merely mentions
 * the footer — the commit introducing this rule, for one — is not refused. The
 * name has to be Claude alone or Claude plus a model or product word, so a human
 * co-author called Claude Dupont is still welcome. git accepts whitespace before
 * the colon of a trailer, and so does this.
 */
const AI_ATTRIBUTION = [
  /^co-authored-by\s*:\s*claude(\s+(opus|sonnet|haiku|fable|code|instant|\d)\b[^<]*)?\s*</i,
  /^co-authored-by\s*:.*@anthropic\.com\s*>/i,
  /^(\p{Extended_Pictographic}\s*)?generated with \[?claude code\]?/iu,
]

/**
 * The last commit the attribution rule does not judge. It and every commit before
 * it predate the rule, and 39 of them carry a Claude trailer. Rewriting published
 * history to remove those was considered and declined, so the range check starts
 * after this commit instead of failing on every base branch forever.
 */
const ATTRIBUTION_RULE_ANCHOR = '8523a35'

/** @returns {string|null} the reason this message is refused, or null if it is fine. */
export function reasonToRefuseAttribution(lines) {
  const credit = lines
    .map((line) => line.trim())
    .find((line) => AI_ATTRIBUTION.some((pattern) => pattern.test(line)))
  return credit ? `credits an AI tool (${JSON.stringify(credit)}); remove that line` : null
}

// Nothing below this line runs on import, so `reasonToRefuse` above is genuinely
// importable — scripts/env.mjs guards its entry point the same way, and for the
// same reason. Without the guard this file exports a function that no caller can
// reach, because importing it executes the CLI and exits.
const isEntryPoint = process.argv[1]?.endsWith('check-commit-message.mjs')

// ─── --cases: the self-test ─────────────────────────────────────────────────
if (isEntryPoint && process.argv.includes('--cases')) {
  const cases = [
    ['feat(auth): add password reset', null],
    ['fix: correct the off-by-one in the paginator', null],
    ['chore(deps)!: drop php 8.3', null],
    ['Merge branch main into feature/x', null],
    ['revert: feat(auth): add password reset', null],
    ['added a thing', 'refused'],
    ['feat add a thing', 'refused'],
    ['feat(auth): Add password reset', 'refused'],
    ['feat(auth): add password reset.', 'refused'],
    ['', 'refused'],
    [`feat(auth): ${'x'.repeat(MAX_SUBJECT)}`, 'refused'],
  ]

  const attributionCases = [
    [['fix: a thing', '', 'Co-authored-by: Jan <jan@example.com>'], null],
    [['fix: a thing', '', 'Co-authored-by: Claude Dupont <claude@example.com>'], null],
    [['fix: a thing', '', 'Mention claude in the body without crediting it'], null],
    [['fix: a thing', '', 'Refuse the "Generated with Claude Code" footer.'], null],
    [['fix: a thing', '', 'Co-authored-by : Claude <noreply@anthropic.com>'], 'refused'],
    [['fix: a thing', '', '    Co-Authored-By: Claude Code <noreply@anthropic.com>'], 'refused'],
    [['fix: a thing', '', 'Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>'], 'refused'],
    [['fix: a thing', '', 'co-authored-by: Someone <bot@anthropic.com>'], 'refused'],
    [
      ['fix: a thing', '', '🤖 Generated with [Claude Code](https://claude.com/claude-code)'],
      'refused',
    ],
  ]

  const wrong = [
    ...cases.filter(([subject, expected]) => {
      const actual = reasonToRefuse(subject) === null ? null : 'refused'
      return actual !== expected
    }),
    ...attributionCases.filter(([lines, expected]) => {
      const actual = reasonToRefuseAttribution(lines) === null ? null : 'refused'
      return actual !== expected
    }),
  ]

  if (wrong.length > 0) {
    fail(
      'commit grammar self-test',
      'The grammar in this file no longer does what its cases say.',
      () => {
        for (const [subject, expected] of wrong) {
          console.error(
            `  ${JSON.stringify(subject)}\n    expected ${expected ?? 'accepted'}, got the opposite`,
          )
        }
      },
    )
  } else {
    const all = [...cases, ...attributionCases]
    pass(
      'commit grammar self-test',
      `${all.length} cases, ${all.filter((c) => c[1]).length} of them refusals`,
    )
  }
  process.exit(process.exitCode ?? 0)
}

// ─── The two real entry points ──────────────────────────────────────────────
const argv = process.argv.slice(2)
const rangeIndex = argv.indexOf('--range')

const check = createCheck('commit messages', HINT)

if (!isEntryPoint) {
  // Imported, not invoked. Do nothing.
} else if (rangeIndex === -1) {
  const file = argv[0]
  if (!file) {
    fail('commit messages', 'Usage: check-commit-message.mjs <file> | --range <range>', () => {})
    process.exit(1)
  }

  // The first line git will KEEP — not literally line one.
  //
  // git strips every `#` line before storing the message, and `make git-hooks`
  // installs .gitmessage as commit.template, whose first line is exactly such a
  // comment. Reading line one verbatim therefore rejects the template this
  // repository hands people, so `git commit` with no -m refuses every time and
  // the only way through is --no-verify. Validating text git is about to discard
  // is wrong even without the template.
  //
  // The same goes for everything below the scissors line `git commit --verbose`
  // adds: git cuts it, so a diff that happens to contain a trailer is not judged.
  //
  // Both use git's comment character, which core.commentChar may change from `#`.
  // `auto` lets git pick one per message, and is read as `#`.
  const configured = git(['config', 'core.commentChar'])
  const comment = [...configured].length === 1 ? configured : '#'
  const escaped = comment.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
  const allLines = readFileSync(file, 'utf8').replace(/\r\n/g, '\n').split('\n')
  const scissors = allLines.findIndex((line) => new RegExp(`^${escaped} -+ >8 -+$`).test(line))
  const kept = (scissors === -1 ? allLines : allLines.slice(0, scissors))
    .map((line) => line.trim())
    .filter((line) => !line.startsWith(comment))
  const subject = kept.find((line) => line !== '') ?? ''

  const refused = reasonToRefuseAttribution(kept)
  const format = reasonToRefuse(subject)

  if (refused) {
    fail('commit message', HINT, () => {
      console.error(`  ${JSON.stringify(subject)}\n    ${refused}`)
    })
  } else {
    if (format) {
      warn('commit message format', HINT, () => {
        console.error(`  ${JSON.stringify(subject)}\n    ${format}`)
      })
    }
    pass('commit message', format ? 'accepted, with a format warning' : 'accepted')
  }
} else {
  const range = branchRange(argv[rangeIndex + 1])

  // A range whose endpoints do not resolve must FAIL, not report "0 checked".
  // git errors are swallowed into an empty result, so an unresolvable range and a
  // clean branch look identical — and the unresolvable one is the interesting
  // case: a force-push leaves CI passing a sha that no longer exists.
  if (!rangeResolves(range)) {
    fail('commit messages', HINT, () => {
      console.error(`  ${range}\n    does not resolve, so no message was checked at all`)
    })
    process.exitCode = 1
  } else {
    const commits = commitsIn(range)
    const formatWarnings = commits
      .map(({ sha, subject }) => ({ sha, subject, reason: reasonToRefuse(subject) }))
      .filter(({ reason }) => reason !== null)

    if (formatWarnings.length > 0) {
      warn('commit message format', HINT, () => {
        for (const { sha, subject, reason } of formatWarnings) {
          console.error(`  ${sha.slice(0, 8)}\n    ${JSON.stringify(subject)} ${reason}`)
        }
      })
    }

    // Merges too: the grammar exempts them, but a merge message can carry a
    // trailer like any other. If the anchor does not resolve (a shallow clone),
    // nothing is exempt and the old trailers fail — closed, not silently open.
    const predatesAttributionRule = new Set(
      git(['rev-list', ATTRIBUTION_RULE_ANCHOR]).split('\n').filter(Boolean),
    )
    const everyCommit = git(['rev-list', '--reverse', range]).split('\n').filter(Boolean)

    for (const sha of everyCommit.filter((candidate) => !predatesAttributionRule.has(candidate))) {
      const message = git(['log', '-1', '--format=%B', sha])
      const reason = reasonToRefuseAttribution(message.split('\n'))
      if (reason)
        check.report(sha.slice(0, 8), `${JSON.stringify(message.split('\n')[0])} ${reason}`)
    }

    check.finish(`${commits.length} checked in ${range}`)
  }
}
