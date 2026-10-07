/**
 * CI parity.
 *
 * The Makefile owns WHICH gates exist; the workflow owns which runner, which
 * service and which `if:` they run under. This check refuses a difference in
 * EITHER direction: a gate `make ci` runs that CI never runs is a gate that only
 * protects the person who remembers to run it, and a step CI runs that `make ci`
 * does not is a failure nobody can reproduce locally.
 *
 * Three limits worth knowing, because each is a way this check can be fooled:
 *  - It only sees `make <target>`. A raw `- run: vendor/bin/pest` pasted into a
 *    job is invisible to it, which is why "every step is a make target" is a rule
 *    enforced socially rather than by this file.
 *  - The target pattern is [a-z][a-z0-9-]*, so `db_seed` or `Build` are invisible.
 *  - Only whole-line comments are stripped, so keep target mentions on their own
 *    comment lines.
 */

import { existsSync, readFileSync } from 'node:fs'
import { createCheck, fail, pass } from './lib/check.mjs'

const MAKEFILE = 'Makefile'
const WORKFLOW = '.github/workflows/ci.yml'

const check = createCheck(
  'CI parity',
  `Every prerequisite of \`make ci\` runs as a step in ${WORKFLOW}, and every \`make\` step there is one of them. Change whichever of the two is wrong.`,
)

const makefile = readFileSync(MAKEFILE, 'utf8')

/**
 * Phase L writes the workflow. Until it exists this check passes and says so,
 * rather than failing the whole harness during the phases that come before it.
 */
if (!existsSync(WORKFLOW)) {
  pass('CI parity', `no workflow at ${WORKFLOW} yet — nothing to compare`)
  process.exit(0)
}

function prerequisitesOf(target) {
  // The negative lookahead keeps `name := value` out. A rule and an assignment
  // share a first token, and reading a variable's contents as a prerequisite list
  // would pass silently while comparing the wrong thing.
  const rule = makefile.match(new RegExp(`^${target}:(?!=)(.*)$`, 'm'))
  if (!rule) {
    fail(
      'CI parity',
      `This check names the target \`${target}\`, which no longer exists in the Makefile.`,
      () => {},
    )
    process.exit(1)
  }
  return rule[1].replace(/##.*$/, '').split(/\s+/).filter(Boolean)
}

// `verify` is itself a chain, and it is verify's own targets that the workflow
// calls one per step — so it is expanded rather than counted as one name.
const required = new Set(
  prerequisitesOf('ci').flatMap((name) => (name === 'verify' ? prerequisitesOf('verify') : [name])),
)

const workflow = readFileSync(WORKFLOW, 'utf8')
const steps = workflow.replace(/^\s*#.*$/gm, '')
const inWorkflow = new Set([...steps.matchAll(/\bmake\s+([a-z][a-z0-9-]*)/g)].map((m) => m[1]))

for (const target of [...required].filter((name) => !inWorkflow.has(name))) {
  check.report(WORKFLOW, `never runs \`make ${target}\`, which \`make ci\` does`)
}
for (const target of [...inWorkflow].filter((name) => !required.has(name))) {
  check.report(MAKEFILE, `\`make ci\` does not run \`${target}\`, which the workflow does`)
}

check.finish(`${required.size} gates in \`make ci\`, ${inWorkflow.size} make steps in the workflow`)
