import { git } from './git.mjs'

/**
 * Resolve the commit range to check.
 *
 * Two git bugs are paid for in this file.
 */
export function branchRange(given) {
  const from = given?.split('..')[0]

  // `^{commit}` and not the bare name. `rev-parse --verify` accepts a push's forty
  // zeroes as a well-formed object name and prints it straight back, so the plain
  // check passes and the range then resolves to nothing — which is
  // indistinguishable from a clean branch. It happens on exactly the first push
  // to a new branch, which is when an unarmed clone lands its first message.
  const exists = from && git(['rev-parse', '--verify', '--quiet', `${from}^{commit}`])

  return exists ? given : resolveRange()
}

function resolveRange() {
  const base = ['origin/main', 'origin/develop', 'main', 'develop'].find((ref) =>
    git(['rev-parse', '--verify', '--quiet', ref]),
  )
  const fork = base ? git(['merge-base', base, 'HEAD']) : ''
  const head = git(['rev-parse', 'HEAD'])

  if (fork && fork !== head) return `${fork}..HEAD`

  // On a base branch the fork point IS HEAD, so there is no branch range. Judge
  // EVERY commit in the repository.
  //
  // There used to be a "commits older than the rule are exempt" fallback here,
  // anchored on the commit that added .gitmessage. It was deleted along with the
  // one commit that needed it: the repository's initial message was reworded to
  // the grammar, so the whole log now satisfies it and the exemption had nothing
  // left to exempt. An exemption kept past its last user is one the next reader
  // has to work out the purpose of before they can trust the gate.
  return 'HEAD'
}

/**
 * THREE dots. Two counts every file the base branch has changed since your branch
 * was cut, so a quiet branch looks enormous and the check reports on other
 * people's work.
 */
export const changedFiles = (range) =>
  git(['diff', '--name-only', range.replace('..', '...')])
    .split('\n')
    .filter(Boolean)

/**
 * Does every endpoint of this range resolve?
 *
 * git() swallows stderr and returns '', so an ERRORED `git log` is otherwise
 * indistinguishable from an empty range — and the check would print a green
 * "0 checked" over a range that does not exist. The concrete case is a rewritten
 * or force-pushed branch, where the sha CI passes in is simply gone.
 */
export function rangeResolves(range) {
  const endpoints = range.split('..').filter(Boolean)
  return endpoints.every(
    (ref) => git(['rev-parse', '--verify', '--quiet', `${ref}^{commit}`]) !== '',
  )
}

/** The subjects in a range, oldest first, as `{ sha, subject }`. */
export function commitsIn(range) {
  const log = git(['log', '--no-merges', '--reverse', '--format=%H%x00%s', range])
  if (!log) return []
  return log
    .split('\n')
    .filter(Boolean)
    .map((line) => {
      const [sha, subject] = line.split('\0')
      return { sha, subject }
    })
}
