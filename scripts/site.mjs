/**
 * Says where to open the site — the line `make install` ends on and `make dev`
 * starts with, because neither `php artisan dev` nor Herd prints it.
 *
 *   node scripts/site.mjs         print the URL, and what is missing if anything
 *   node scripts/site.mjs --dev   the same, but first refuse when this project's
 *                                 Vite is already running
 *
 * Why --dev refuses: Vite runs with strictPort, so a second `make dev` fails to
 * bind — and laravel-vite-plugin's exit handler then deletes public/hot, the file
 * the FIRST, still-running Vite wrote. Every page silently falls back to the
 * built assets and live reload stops, with nothing in any log. Refusing before
 * anything starts is the only point at which the first session can be protected.
 */

import { basename } from 'node:path'
import { fail } from './lib/check.mjs'
import { readEnvFile, unquote } from './lib/dotenv.mjs'
import { isFree, pidsOn, projectPorts } from './lib/ports.mjs'
import { site } from './lib/site.mjs'

const HINT = "README.md, 'Opening the site' — and 'make status' shows what holds a port"
const c = {
  bold: (s) => `\x1b[1m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
  warn: (s) => `\x1b[33m${s}\x1b[0m`,
}

const where = site()

if (process.argv.includes('--dev')) {
  const vite = projectPorts().find((p) => p.name === 'vite')

  if (!(await isFree(vite.port))) {
    const pids = pidsOn(vite.port)
    fail('make dev is already running', HINT, () => {
      console.error(
        `  Vite's port :${vite.port} is held by ${pids?.length ? `pid ${pids.join(', ')}` : 'another process'}.`,
      )
      console.error('  A second make dev would break the first one: its live reload stops.')
      console.error(`\n  Use the session that is running${where ? ` — open ${where.url}` : ''} —`)
      console.error("  or stop it first with 'make stop'.")
    })
  }
}

if (process.exitCode !== 1) {
  if (where === null) {
    console.log(c.warn("  Cannot tell the site's URL yet: run 'make install' first."))
  } else {
    console.log(`\n  ${c.bold('Open')} ${c.bold(where.url)}  ${c.dim(`(${where.servedBy})`)}\n`)

    if (where.servedBy !== 'herd' && where.herdInstalled) {
      const name = basename(process.cwd()).toLowerCase()
      console.log(
        c.warn('  Herd is installed but does not serve this folder, so php artisan serve does.'),
      )
      console.log(c.warn(`  To use Herd instead, run here:  herd link --secure ${name}`))
      console.log(c.warn(`  then set APP_URL=https://${name}.test in .env.`))
      console.log()
    }

    const appUrl = unquote(readEnvFile('.env').APP_URL ?? '').replace(/\/+$/, '')
    if (where.servedBy === 'herd' && appUrl !== where.url) {
      console.log(c.warn(`  APP_URL is ${appUrl || 'empty'}: links in mails will point there.`))
      console.log(c.warn(`  Set APP_URL=${where.url} in .env.`))
      console.log()
    }
  }
}
