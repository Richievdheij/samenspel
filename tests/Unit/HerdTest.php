<?php

declare(strict_types=1);

use App\Support\Herd;
use Illuminate\Support\Facades\File;

/*
 * `make dev` leaves `php artisan serve` out when Herd (or Valet, which shares the
 * layout) already serves the project. These build a throwaway Herd configuration
 * directory, so they pin down the two ways a site can be served — linked and
 * parked — without depending on whatever the machine running them has installed.
 */

beforeEach(function (): void {
    $this->root = sys_get_temp_dir().'/herd-test-'.bin2hex(random_bytes(6));
    $this->config = $this->root.'/config/valet';
    $this->projects = $this->root.'/projects';

    File::ensureDirectoryExists($this->config.'/Sites');
    File::ensureDirectoryExists($this->projects.'/app');

    $this->writePaths = function (array $paths): void {
        File::put($this->config.'/config.json', json_encode(['paths' => $paths, 'tld' => 'test'], JSON_THROW_ON_ERROR));
    };
});

afterEach(function (): void {
    File::deleteDirectory($this->root);
});

it('serves a project linked into the Sites directory', function (): void {
    ($this->writePaths)([$this->config.'/Sites']);
    symlink($this->projects.'/app', $this->config.'/Sites/my-site');

    expect(new Herd([$this->config])->servesPath($this->projects.'/app'))->toBeTrue();
});

it('serves a project inside a parked directory', function (): void {
    ($this->writePaths)([$this->config.'/Sites', $this->projects.'/']);

    expect(new Herd([$this->config])->servesPath($this->projects.'/app'))->toBeTrue();
});

it('does not serve a project that is neither linked nor parked', function (): void {
    ($this->writePaths)([$this->config.'/Sites']);
    symlink($this->root, $this->config.'/Sites/something-else');

    expect(new Herd([$this->config])->servesPath($this->projects.'/app'))->toBeFalse();
});

it('does not serve anything when Herd is not installed', function (): void {
    expect(new Herd([$this->root.'/missing'])->servesPath($this->projects.'/app'))->toBeFalse();
});

it('gives the https URL of a linked site Herd holds a certificate for', function (): void {
    ($this->writePaths)([$this->config.'/Sites']);
    symlink($this->projects.'/app', $this->config.'/Sites/My-Site');
    File::ensureDirectoryExists($this->config.'/Certificates');
    File::put($this->config.'/Certificates/my-site.test.crt', '');

    expect(new Herd([$this->config])->siteUrl($this->projects.'/app'))->toBe('https://my-site.test');
});

it('gives the http URL of a parked site under the configured tld', function (): void {
    File::put($this->config.'/config.json', json_encode(['paths' => [$this->projects], 'tld' => 'localhost'], JSON_THROW_ON_ERROR));

    expect(new Herd([$this->config])->siteUrl($this->projects.'/app'))->toBe('http://app.localhost');
});

it('gives no URL for a project two levels below a parked directory', function (): void {
    File::ensureDirectoryExists($this->projects.'/development/app');
    ($this->writePaths)([$this->projects]);

    expect(new Herd([$this->config])->siteUrl($this->projects.'/development/app'))->toBeNull();
});

it('knows Herd is installed even when it does not serve the project', function (): void {
    ($this->writePaths)([$this->config.'/Sites']);

    $herd = new Herd([$this->config]);

    expect($herd->isInstalled())->toBeTrue()
        ->and($herd->siteUrl($this->projects.'/app'))->toBeNull()
        ->and(new Herd([$this->root.'/missing'])->isInstalled())->toBeFalse();
});
