/**
 * `make stop` — end this project's `make dev` session and free its ports.
 *
 * Two passes. First the session itself, found by working directory, because half
 * of it — the queue listener and the log tail — holds no port and would otherwise
 * run on. Then by LISTENING port rather than by process name, because the thing
 * holding 5173 is frequently not the Vite you started: it is the one from the
 * branch you switched away from an hour ago.
 */

import { rmSync } from 'node:fs'
import { setTimeout as sleep } from 'node:timers/promises'
import { HOT_FILE, devSessionPids, isRunning, killPid, pidsOn, projectPorts } from './lib/ports.mjs'
import { pass } from './lib/check.mjs'

import { fail } from './lib/check.mjs'

const kill = process.argv.includes('--kill')
const ports = projectPorts()

let found = 0
let stopped = 0
const survivors = []
const unprobed = []

// ─── The make dev session ──────────────────────────────────────────────────
const session = devSessionPids()
if (session !== null && session.length > 0) {
  found += session.length

  if (!kill) {
    console.log(`  make dev session: ${session.length} process(es), pid ${session.join(', ')}`)
  } else {
    for (const pid of session) killPid(pid)

    // Vite removes public/hot on SIGTERM, and the port pass below must not race
    // it — so wait for the session to be gone before looking at the ports.
    for (let tries = 0; tries < 30 && session.some(isRunning); tries++) await sleep(100)

    const lingering = session.filter(isRunning)
    for (const pid of lingering) {
      try {
        process.kill(Number(pid), 'SIGKILL')
      } catch {
        // Gone between the check and the signal: the outcome that was wanted.
      }
    }

    stopped += session.length
    console.log(`  stopped the make dev session (${session.length} process(es))`)
  }
}

for (const { name, port, description } of ports) {
  const pids = pidsOn(port)

  // null means the probe tool is missing, not that the port is free. Saying
  // "nothing was listening" here would be a claim this machine cannot support.
  if (pids === null) {
    unprobed.push({ name, port })
    continue
  }

  if (pids.length === 0) continue
  found += pids.length

  if (!kill) {
    console.log(`  ${name} :${port} held by ${pids.join(', ')} (${description})`)
    continue
  }

  for (const pid of pids) {
    if (killPid(pid)) {
      stopped++
      console.log(`  stopped ${name} :${port} (pid ${pid})`)
    } else {
      survivors.push({ name, port, pid })
    }
  }
}

// A Vite that died without running its exit handler — a crash, a kill -9, a closed
// lid — leaves public/hot behind, and every page Herd serves then asks a dead
// server for its CSS and JavaScript: an unstyled site with nothing in any log.
// Only removed once nothing listens on the Vite port. A Vite that was just sent
// SIGTERM above removes its own file on the way out, and can do so between any
// existence check and the removal — so ENOENT is the normal outcome, not a fault.
const vite = ports.find((p) => p.name === 'vite')
if (kill && pidsOn(vite.port)?.length === 0) {
  try {
    rmSync(HOT_FILE)
    console.log(`  removed a stale ${HOT_FILE} — the built assets are served again`)
  } catch (error) {
    if (error.code !== 'ENOENT') throw error
  }
}

// A port that was found and could NOT be freed is a failure, not a quiet success.
// Reporting "nothing was listening" here would contradict the lines printed just
// above it and exit 0, so `make stop && make dev` would then die with EADDRINUSE
// straight after an apparently successful stop.
if (unprobed.length > 0) {
  fail(
    'stop',
    'lsof is required to inspect ports and was not found on PATH. Install it, or stop the process by hand.',
    () => {
      for (const { name, port } of unprobed) {
        console.error(
          `  ${name} :${port}\n    could not be inspected, so whether anything holds it is unknown`,
        )
      }
    },
  )
} else if (survivors.length > 0) {
  fail(
    'stop',
    'The process may belong to another user, or be a system service. Find it with `make status` and stop it by hand.',
    () => {
      for (const { name, port, pid } of survivors) {
        console.error(
          `  ${name} :${port}\n    pid ${pid} is still listening and could not be stopped`,
        )
      }
    },
  )
} else {
  pass(
    'stop',
    found === 0
      ? `no make dev session, and nothing listening on ${ports.map((p) => p.port).join(', ')}`
      : `${stopped} process(es) stopped`,
  )
}
