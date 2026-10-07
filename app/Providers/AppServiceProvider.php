<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Herd;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->leaveServingToHerd();
    }

    /**
     * `php artisan dev` starts `artisan serve` by default. When Herd already
     * serves this directory that second server is redundant — and it claims
     * APP_PORT, so two Herd projects running `make dev` side by side collide on
     * it. Vite, the queue listener and the log tail still run.
     */
    private function leaveServingToHerd(): void
    {
        if (! $this->app->runningConsoleCommand('dev')) {
            return;
        }

        if (Herd::forHome((string) getenv('HOME'))->servesPath(base_path())) {
            DevCommands::except('server');
        }
    }
}
