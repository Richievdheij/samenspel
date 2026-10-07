<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;

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
