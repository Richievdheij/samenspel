/**
 * Where the site can be opened, as a URL.
 *
 * Herd's layout is read by App\Support\Herd and nowhere else, so this asks that
 * class rather than re-reading Herd's configuration in a second language. It loads
 * the Composer autoloader and that one class — never the application — so it
 * keeps `make status` independent of a bootable app.
 *
 * Returns null only when the question cannot be asked: no vendor/ yet, or no PHP.
 */

import { execFileSync } from 'node:child_process'
import { existsSync } from 'node:fs'
import { homedir } from 'node:os'
import { projectPorts } from './ports.mjs'

const ASK_HERD = `
require 'vendor/autoload.php';
$herd = App\\Support\\Herd::forHome($argv[1]);
echo json_encode(['url' => $herd->siteUrl(getcwd()), 'installed' => $herd->isInstalled()]);
`

/**
 * @returns {{ url: string, servedBy: 'herd' | 'artisan serve', herdInstalled: boolean } | null}
 */
export function site(envFile = '.env') {
  if (!existsSync('vendor/autoload.php')) return null

  let answer
  try {
    answer = JSON.parse(
      execFileSync('php', ['-r', ASK_HERD, '--', process.env.HOME || homedir()], {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'ignore'],
      }),
    )
  } catch {
    return null
  }

  if (typeof answer.url === 'string') {
    return { url: answer.url, servedBy: 'herd', herdInstalled: true }
  }

  const app = projectPorts(envFile).find((p) => p.name === 'app')
  return {
    url: `http://localhost:${app.port}`,
    servedBy: 'artisan serve',
    herdInstalled: answer.installed === true,
  }
}
