<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Whether Laravel Herd — or Valet, which Herd is built on and whose layout it
 * shares — already serves a project directory, and at which URL.
 *
 * Read from Herd's own configuration on disk rather than from the `herd` CLI, so
 * the answer costs a few stat calls instead of a process, and is the same answer
 * Herd's nginx gives: a site is either a symlink in one of the registered paths
 * (`herd link`) or a directory directly inside one (`herd park`). One level
 * only: a project in ~/Herd/development/app is NOT served when ~/Herd is the
 * parked path, which is the commonest reason a fresh clone answers "Site not
 * found".
 *
 * This class is the one place that knowledge lives. `make dev` asks it through
 * AppServiceProvider, and `make status`, `make install` and the `make dev`
 * preflight ask it through scripts/lib/site.mjs — which loads only the
 * autoloader, never the application.
 */
final readonly class Herd
{
    /**
     * @param  list<string>  $configDirectories  Herd/Valet configuration directories, each holding config.json
     */
    public function __construct(private array $configDirectories) {}

    /**
     * The configuration directories Herd on macOS and Valet on macOS or Linux use,
     * relative to a home directory. The same places laravel-vite-plugin looks
     * when it secures Vite with the site's certificate.
     */
    public static function forHome(string $home): self
    {
        return new self([
            $home.'/Library/Application Support/Herd/config/valet',
            $home.'/.config/valet',
            $home.'/.valet',
        ]);
    }

    /**
     * Whether Herd or Valet is installed at all, serving this project or not.
     */
    public function isInstalled(): bool
    {
        return array_any($this->configDirectories, fn (string $configDirectory): bool => is_file($configDirectory.'/config.json'));
    }

    public function servesPath(string $path): bool
    {
        return $this->siteUrl($path) !== null;
    }

    /**
     * The URL Herd serves the project at — https when Herd holds a certificate for
     * the site (`herd secure`, or `herd link --secure`) — or null when it does
     * not serve the project.
     */
    public function siteUrl(string $path): ?string
    {
        $project = realpath($path);

        if ($project === false) {
            return null;
        }

        foreach ($this->configDirectories as $configDirectory) {
            $config = $this->config($configDirectory);

            foreach ($config['paths'] as $registeredPath) {
                $site = $this->linkedSite($project, $registeredPath)
                    ?? ($this->isParkedIn($project, $registeredPath) ? basename($project) : null);

                if ($site !== null) {
                    $host = strtolower($site).'.'.$config['tld'];
                    $secure = is_file($configDirectory.'/Certificates/'.$host.'.crt');

                    return ($secure ? 'https://' : 'http://').$host;
                }
            }
        }

        return null;
    }

    /**
     * @return array{paths: list<string>, tld: string}
     */
    private function config(string $configDirectory): array
    {
        $configFile = $configDirectory.'/config.json';

        if (! is_file($configFile)) {
            return ['paths' => [], 'tld' => 'test'];
        }

        $config = json_decode((string) file_get_contents($configFile), true);

        if (! is_array($config)) {
            return ['paths' => [], 'tld' => 'test'];
        }

        return [
            'paths' => is_array($config['paths'] ?? null) ? array_values(array_filter($config['paths'], is_string(...))) : [],
            'tld' => is_string($config['tld'] ?? null) && $config['tld'] !== '' ? $config['tld'] : 'test',
        ];
    }

    private function isParkedIn(string $project, string $registeredPath): bool
    {
        return realpath($registeredPath) === dirname($project);
    }

    /**
     * The site name a `herd link` gave the project: the name of the symlink.
     */
    private function linkedSite(string $project, string $registeredPath): ?string
    {
        foreach (glob(rtrim($registeredPath, '/').'/*') ?: [] as $entry) {
            if (is_link($entry) && realpath($entry) === $project) {
                return basename($entry);
            }
        }

        return null;
    }
}
