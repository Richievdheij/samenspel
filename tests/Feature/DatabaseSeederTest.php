<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\GameSeeder;

/*
 * `make install` runs `migrate --seed`, and running `make install` again on a
 * checkout that already has a database is an ordinary thing to do. A seeder that
 * blindly inserts the shared account then dies on the unique email index, and
 * the install fails halfway with a constraint violation naming no cause.
 */

it('can seed a database that has already been seeded', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::query()->where('email', 'test@example.com')->count())->toBe(1);
});

it('seeds the games, categories, admin and demo events only once', function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(Game::query()->count())->toBe(count(GameSeeder::NAMES))
        ->and(Category::query()->count())->toBe(count(CategorySeeder::NAMES))
        ->and(User::query()->where('email', 'admin@example.com')->sole()->role)->toBe(Role::Admin)
        ->and(Event::query()->count())->toBe(4);
});
