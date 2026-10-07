/**
 * Port probing, in Node rather than shell so `make dev`, `make stop` and
 * `make status` behave the same on macOS and Linux — and under WSL2, which is
 * Linux and therefore not a special case.
 *
 * Everything that shells out is confined to `pidsOn`; nothing else here has to
 * know how a platform lists its listeners.
 */

import net from 'node:net'
import { execFileSync } from 'node:child_process'
import { realpathSync } from 'node:fs'
import { readEnvFile, unquote } from './dotenv.mjs'

/** Written by laravel-vite-plugin while Vite runs; Laravel serves assets from it. */
export const HOT_FILE = 'public/hot'

/** The ports this project publishes, read from the env file — never literals. */
export function projectPorts(envFile = '.env') {
  const values = readEnvFile(envFile)
  const port = (key, fallback) => Number(unquote(values[key] ?? '')) || fallback

  return [
    { name: 'app', port: port('APP_PORT', 8000), description: 'php artisan serve' },
    { name: 'vite', port: port('VITE_PORT', 5173), description: 'vite dev server' },
  ]
}

/**
 * A BIND probe, not a connect probe.
 *
 * A socket held by a process that is not accepting reads as free to a connect
 * probe, and you then start a second server that fails a second later for a
 * reason the first attempt already knew.
 */
export function isFree(port, host = '127.0.0.1') {
  return new Promise((resolve) => {
    const server = net.createServer()
    server.once('error', (error) => {
      // EACCES on a port below 1024 means "not permitted", not "in use". Reporting
      // it as a conflict sends someone hunting a process that does not exist.
      resolve(error.code === 'EACCES' ? true : false)
    })
    server.once('listening', () => server.close(() => resolve(true)))
    server.listen({ port, host, exclusive: true })
  })
}

/**
 * Process ids LISTENING on a port.
 *
 * Returns `[]` when nothing holds the port, and `null` when the platform's probe
 * tool is not installed at all. The distinction matters: collapsing both to `[]`
 * makes a machine without `lsof` report every port as free, so `make stop` says
 * "nothing was listening" and `make status` shows a clear board while the server
 * you are hunting is still running. Never throws — both callers are meant to keep
 * working when the system is in a bad state.
 */
export function pidsOn(port) {
  try {
    // -sTCP:LISTEN is not optional. Without it this also matches the script's own
    // outbound connection to the port, and `make stop` kills itself.
    const output = execFileSync('lsof', ['-ti', `tcp:${port}`, '-sTCP:LISTEN'], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
    return output.split('\n').filter(Boolean)
  } catch (error) {
    // ENOENT means the binary itself is absent. Any other failure is the tool
    // running and finding nothing — lsof exits 1 when no process matches, which
    // is a normal, empty answer rather than a broken probe.
    return error.code === 'ENOENT' ? null : []
  }
}

/**
 * Can something be reached at host:port?
 *
 * A CONNECT probe, and it is the right tool for exactly one question: is a remote
 * service up. The bind probe above answers "is this port free on this machine",
 * and using it against a remote host is meaningless — binding to an address the
 * machine does not own fails with EADDRNOTAVAIL, which the bind probe reports as
 * "in use", so every remote database would read as reachable whether or not it
 * exists.
 */
export function isReachable(host, port, timeout = 1500) {
  return new Promise((resolve) => {
    const socket = new net.Socket()
    const done = (result) => {
      socket.destroy()
      resolve(result)
    }
    socket.setTimeout(timeout)
    socket.once('connect', () => done(true))
    socket.once('timeout', () => done(false))
    socket.once('error', () => done(false))
    socket.connect(port, host)
  })
}

export function killPid(pid) {
  try {
    process.kill(Number(pid), 'SIGTERM')
    return true
  } catch {
    return false
  }
}

/**
 * Every process of a `make dev` session started in `root`: each `php artisan dev`
 * whose working directory is this project, and everything below it — Vite, the
 * queue listener, the log tail, `artisan serve`.
 *
 * Ports alone cannot find these. The queue listener and the log tail listen on
 * nothing, so stopping a session by port ends Vite and leaves the rest running —
 * and a session whose terminal was closed keeps them running for days, attached
 * to pid 1, polling a database or tailing a log for a folder that may already be
 * in the bin. Matching on the working directory keeps another project's session,
 * started from the same template, out of reach.
 *
 * Returns null when `ps` or `lsof` is missing, for the same reason pidsOn does.
 */
export function devSessionPids(root = process.cwd()) {
  let table
  try {
    table = execFileSync('ps', ['-A', '-o', 'pid=,ppid=,command='], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
  } catch {
    return null
  }

  const processes = table
    .split('\n')
    .map((line) => line.trim().match(/^(\d+)\s+(\d+)\s+(.*)$/))
    .filter(Boolean)
    .map(([, pid, ppid, command]) => ({ pid, ppid, command }))

  const project = realpathSync(root)
  const roots = []
  for (const { pid, command } of processes) {
    if (!/\bphp artisan dev\b/.test(command)) continue
    const cwd = cwdOf(pid)
    if (cwd === null) return null
    if (cwd === project) roots.push(pid)
  }

  const children = new Map()
  for (const { pid, ppid } of processes) {
    children.set(ppid, [...(children.get(ppid) ?? []), pid])
  }

  const session = new Set()
  const queue = [...roots]
  while (queue.length > 0) {
    const pid = queue.shift()
    if (session.has(pid) || pid === String(process.pid)) continue
    session.add(pid)
    queue.push(...(children.get(pid) ?? []))
  }

  return [...session]
}

/** A process's working directory, '' when it has gone, null when lsof is missing. */
function cwdOf(pid) {
  try {
    const output = execFileSync('lsof', ['-a', '-p', pid, '-d', 'cwd', '-Fn'], {
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'ignore'],
    })
    return (output.split('\n').find((line) => line.startsWith('n')) ?? 'n').slice(1)
  } catch (error) {
    return error.code === 'ENOENT' ? null : ''
  }
}

/** Whether a process still exists. Signal 0 checks without sending anything. */
export function isRunning(pid) {
  try {
    process.kill(Number(pid), 0)
    return true
  } catch (error) {
    return error.code === 'EPERM'
  }
}
