/**
 * Documentation that points at things which exist.
 *
 * Three rules over every tracked markdown file:
 *
 *   1. A relative link resolves to a tracked file.
 *   2. A `#fragment` resolves to a heading in the target document.
 *   3. A backticked repository path resolves to a tracked file or directory.
 *
 * Rule 3 is the one that earns its keep. Documentation goes stale by pointing at
 * a file that was renamed, and nothing else in a toolchain reads prose.
 */

import { readFileSync } from 'node:fs'
import { dirname, posix } from 'node:path'
import { createCheck } from './lib/check.mjs'
import { trackedFiles, trackedWithExtension } from './lib/git.mjs'

/**
 * Documents that describe a different repository. Their links and anchors are
 * still checked — those are internal and must hold — but their backticked paths
 * name files in the system they were written for, not in this one.
 *
 * TOOLCHAIN-BOOTSTRAP.md is the imported specification this toolchain was built
 * from, kept verbatim as the record.
 *
 */
const EXTERNAL_SPECS = new Set(['TOOLCHAIN-BOOTSTRAP.md'])

const isExternal = (document) => EXTERNAL_SPECS.has(document)

const MARKDOWN_LINK = /\[[^\]]*\]\(([^)\s]+)(?:\s+"[^"]*")?\)/g
const BACKTICKED = /`([^`\n]+)`/g
const HEADING = /^#{1,6}\s+(.+?)\s*$/gm

// A first filter on shape: path characters only — no spaces, no glob, no URL, no
// punctuation. Anything looser turns every inline code span into a finding. The
// second filter, where rule 3 runs, requires the path to be repository-rooted.
const LOOKS_LIKE_PATH = /^[\w.@-]+(?:\/[\w.@-]+)*\/?$/

/**
 * Links are looked for in PROSE only.
 *
 * A regular expression in a code span is not a link, and it very much looks like
 * one: `\[["'](\w+)` has the exact shape of `[text](target)`. Scanning raw
 * markdown reports every regex in the documentation as a broken link, which is
 * both wrong and the fastest way to teach people to ignore this check.
 */
const stripFences = (source) => source.replace(/^```[^\n]*\n[\s\S]*?^```[^\n]*$/gm, '')
const stripInlineCode = (source) => source.replace(/`[^`\n]*`/g, '')
const proseOf = (source) => stripInlineCode(stripFences(source))

const check = createCheck(
  'doc links',
  'Every link, anchor and backticked path in the documentation must resolve. Fix the reference, or the file it names.',
)

const tracked = new Set(trackedFiles())
const trackedDirs = new Set()
for (const file of tracked) {
  const parts = file.split('/')
  for (let i = 1; i < parts.length; i++) trackedDirs.add(parts.slice(0, i).join('/'))
}

const documents = trackedWithExtension('.md')
const headingsOf = new Map()

const slugify = (text) =>
  text
    .toLowerCase()
    .replace(/`/g, '')
    .replace(/[^\w\s-]/g, '')
    .trim()
    .replace(/\s+/g, '-')

for (const document of documents) {
  // Fences are stripped — a `# comment` line inside a shell block is not a
  // heading, and treating it as one invents anchors that resolve to nothing.
  //
  // Inline code is NOT stripped here, unlike when looking for links. A renderer
  // slugifies "## The `env` contract" to `the-env-contract`, keeping the words
  // inside the backticks; deleting the span first would register `the-contract`
  // and report every genuine link to that heading as broken.
  const text = stripFences(readFileSync(document, 'utf8'))
  headingsOf.set(document, new Set([...text.matchAll(HEADING)].map((m) => slugify(m[1]))))
}

let linkCount = 0
let pathCount = 0

for (const document of documents) {
  const source = readFileSync(document, 'utf8')
  const prose = proseOf(source)

  // ─── Rules 1 and 2 ────────────────────────────────────────────────────────
  for (const [, target] of prose.matchAll(MARKDOWN_LINK)) {
    if (/^(https?:|mailto:|tel:)/.test(target)) continue
    linkCount++

    const [path, fragment] = target.split('#')

    if (path === '') {
      // A same-document anchor.
      if (fragment && !headingsOf.get(document).has(fragment.toLowerCase())) {
        check.report(document, `#${fragment} matches no heading in this document`)
      }
      continue
    }

    const resolved = posix.normalize(posix.join(dirname(document), path)).replace(/^\.\//, '')
    if (!tracked.has(resolved) && !trackedDirs.has(resolved.replace(/\/$/, ''))) {
      check.report(document, `links to ${path}, which is not a tracked file`)
      continue
    }

    if (
      fragment &&
      headingsOf.has(resolved) &&
      !headingsOf.get(resolved).has(fragment.toLowerCase())
    ) {
      check.report(document, `links to ${path}#${fragment}, but that heading does not exist`)
    }
  }

  // ─── Rule 3 ───────────────────────────────────────────────────────────────
  if (isExternal(document)) continue

  // Inline code spans outside fenced blocks: a path inside a fence is a sample,
  // not a claim about this repository.
  for (const [, candidate] of stripFences(source).matchAll(BACKTICKED)) {
    if (!LOOKS_LIKE_PATH.test(candidate)) continue

    const clean = candidate.replace(/\/$/, '')

    // A string is only treated as a claim about this repository when it is
    // ROOTED at a real top-level entry. Two things get excluded by that, and both
    // should be:
    //
    //   `main.scss`             a bare basename — prose naming a file, not a path
    //   `abstracts/_tokens.scss` relative to the directory under discussion
    //
    // Neither ever claimed to be resolvable from the repository root, and
    // reporting them is how a check earns the reputation of crying wolf. What
    // stays covered is the case that actually rots: a full path like
    // `scripts/check-doc-links.mjs` still naming a file after it was renamed.
    if (!clean.includes('/')) continue
    const root = clean.split('/')[0]
    if (!tracked.has(root) && !trackedDirs.has(root)) continue

    pathCount++
    if (!tracked.has(clean) && !trackedDirs.has(clean)) {
      check.report(document, `mentions \`${candidate}\`, which is not a tracked path`)
    }
  }
}

check.finish(
  `${documents.length} documents, ${linkCount} relative links, ${pathCount} backticked paths ` +
    `(${documents.filter(isExternal).length} external spec excluded from path checking)`,
)
