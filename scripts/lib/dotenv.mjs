/**
 * The one dotenv parser and renderer in this repository.
 *
 * There is exactly one on purpose. The source system this was extracted from had
 * four copies across four scripts, and only one of them survived a checkout made
 * with `core.autocrlf=true` — the other three matched nothing at all and wrote a
 * `.env` that looked configured and held no value.
 */

import { readFileSync } from 'node:fs'

/**
 * Uppercase-anchored, so a lowercase line or a stray word can never be mistaken
 * for a key. `export ` is tolerated because people paste it in from a shell.
 */
const KEY_LINE = /^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=(.*)$/

/**
 * Strip CRLF explicitly rather than trusting .gitattributes.
 *
 * .gitattributes only governs files added after it, a global `core.autocrlf=true`
 * still hands you CRLF in the working tree, and `.env` is gitignored so it is
 * never normalised at all. A `\r` left in place is invisible: `git status` reports
 * nothing, `. ./.env` executes each bare `\r` and prints "not found", and a JS
 * regex matching `KEY=value` matches nothing, because `.` excludes `\r`.
 */
export const normalise = (text) => text.replace(/\r\n/g, '\n').replace(/\r/g, '\n')

/** `"quoted"` and `'quoted'` collapse to their contents; everything else is literal. */
export function unquote(value) {
  const trimmed = value.trim()
  if (trimmed.length >= 2 && /^(['"]).*\1$/s.test(trimmed)) return trimmed.slice(1, -1)
  return trimmed
}

/** Parse dotenv text into `{ KEY: rawValue }`, preserving the value exactly as written. */
export function parseEnv(text) {
  const values = {}
  for (const line of normalise(text).split('\n')) {
    if (/^\s*#/.test(line)) continue
    const match = KEY_LINE.exec(line)
    if (match) values[match[1]] = match[2].trim()
  }
  return values
}

/** Parse a dotenv file, or `{}` when it does not exist yet. */
export function readEnvFile(path) {
  try {
    return parseEnv(readFileSync(path, 'utf8'))
  } catch (error) {
    if (error.code === 'ENOENT') return {}
    throw error
  }
}

/** Every key the template declares, in the order it declares them. */
export function templateKeys(text) {
  return Object.keys(parseEnv(text))
}

/**
 * Render `resolved` into the template's own layout: comments, blank lines and
 * section headings survive verbatim, and each `KEY=` line takes its resolved
 * value. Keys present in `resolved` but absent from the template are appended
 * rather than dropped, because dropping one would silently delete a value
 * somebody put there by hand.
 */
export function render(templateText, resolved) {
  const seen = new Set()
  const body = normalise(templateText)
    .split('\n')
    .map((line) => {
      if (/^\s*#/.test(line)) return line
      const match = KEY_LINE.exec(line)
      if (!match) return line
      const key = match[1]
      seen.add(key)
      return `${key}=${resolved[key] ?? ''}`
    })

  const extra = Object.keys(resolved).filter((key) => !seen.has(key))
  if (extra.length > 0) {
    body.push(
      '',
      '# Keys this checkout has that the template does not declare.',
      '# Either add them to the template or remove them here.',
    )
    for (const key of extra) body.push(`${key}=${resolved[key]}`)
  }

  return `${body.join('\n').replace(/\n+$/, '')}\n`
}
