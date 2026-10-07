import { execFileSync } from 'node:child_process'

/** Run git and return trimmed stdout, or '' when the command fails. */
export function git(args) {
  try {
    return execFileSync('git', args, {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    }).trim()
  } catch {
    return ''
  }
}

/**
 * Every tracked file.
 *
 * The checks walk this rather than the filesystem, so vendor/, node_modules/ and
 * anything gitignored are excluded by construction rather than by a list of
 * exclusions that has to be kept in step with .gitignore.
 */
export const trackedFiles = () => git(['ls-files']).split('\n').filter(Boolean)

/** Tracked files whose path ends with one of `extensions`. */
export const trackedWithExtension = (...extensions) =>
  trackedFiles().filter((file) => extensions.some((extension) => file.endsWith(extension)))

/** Tracked files under any of `directories`. */
export const trackedUnder = (...directories) =>
  trackedFiles().filter((file) =>
    directories.some((directory) => file === directory || file.startsWith(`${directory}/`)),
  )
