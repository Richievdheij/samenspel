/**
 * Scan the whole history for secrets.
 *
 * gitleaks runs from a DIGEST-PINNED image rather than a per-machine install. A
 * scanner installed separately on five machines reports differently on five
 * machines, and the one that matters is the one CI runs.
 *
 * This gate is in `make ci` and deliberately NOT in `make verify`: it needs
 * Docker and it scans history, so it is too slow to sit in front of every push.
 * When Docker is unavailable it FAILS LOUDLY rather than passing quietly — a gate
 * that skips itself is indistinguishable from a gate that found nothing.
 */

import { spawnSync } from 'node:child_process'
import { fail, pass } from './lib/check.mjs'

const IMAGE =
  'zricethezav/gitleaks@sha256:c00b6bd0aeb3071cbcb79009cb16a60dd9e0a7c60e2be9ab65d25e6bc8abbb7f'
const VERSION = 'v8.30.1'

const HINT =
  `This gate runs gitleaks ${VERSION} from a digest-pinned image and needs Docker. ` +
  'Before allowlisting a finding, read the header of .gitleaks.toml: a `paths` ' +
  'exemption silently skips the whole file, so exemptions go on the value.'

const docker = spawnSync('docker', ['version', '--format', '{{.Server.Version}}'], {
  encoding: 'utf8',
})

if (docker.status !== 0) {
  fail('secrets scan', HINT, () => {
    console.error(
      '  docker\n    is not available, so the history was NOT scanned — this is a failure, not a skip',
    )
  })
  process.exit(1)
}

// `git` scans commit history, not just the working tree: a secret that was
// committed and then deleted is still in the pack files and still leaked.
// --redact keeps the value itself out of the CI log.
const result = spawnSync(
  'docker',
  [
    'run',
    '--rm',
    '-v',
    `${process.cwd()}:/repo:ro`,
    '-w',
    '/repo',
    IMAGE,
    'git',
    '--no-banner',
    '--redact',
    // Scan the history of THIS branch, not every ref that happens to be fetched.
    //
    // Without it gitleaks walks all refs, so with `fetch-depth: 0` a CI run on
    // main also scans every open Dependabot and feature branch — and a finding on
    // somebody else's branch fails a build that has nothing to do with it. It
    // reduces no coverage: every branch is scanned by its own pull-request run,
    // and nothing reaches main without one.
    '--log-opts',
    'HEAD',
    '--config',
    '/repo/.gitleaks.toml',
    '/repo',
  ],
  { encoding: 'utf8' },
)

const output = `${result.stdout ?? ''}${result.stderr ?? ''}`.trim()

if (result.status === 0) {
  const scanned = /scanned ~?[\d.]+\s*\w*B?/i.exec(output)?.[0] ?? 'the full history'
  const commits = /(\d+) commits scanned/i.exec(output)?.[1]
  pass(
    'secrets scan',
    `gitleaks ${VERSION}, ${commits ? `${commits} commits` : scanned}, no findings`,
  )
} else {
  // No process.exit(1) here. fail() already sets process.exitCode, and exiting
  // immediately after writing gitleaks output to stderr truncates it: on POSIX,
  // stderr to a pipe is async, so `make ci 2>&1 | tee build.log` loses the
  // findings while still exiting non-zero — a red build with an empty reason.
  // This is the rule scripts/lib/check.mjs documents, and it applies here too.
  fail('secrets scan', HINT, () => {
    console.error(output.replace(/^/gm, '  '))
  })
}
