<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeding is not optional decoration. Migrations without seeds means the
     * recurring team blocker is "I pulled and now I have no account to log in
     * with", and the answer is always the same person.
     *
     * `make fresh yes=1` runs this. The credentials are in README.md, on purpose:
     * a known local account that everyone shares is safer than five people each
     * inventing one and one of them reaching production.
     *
     * `make install` seeds too, and running it again on an existing checkout is
     * normal — so every record here is created only when it is absent.
     */
    public function run(): void
    {
        $this->call([GameSeeder::class, CategorySeeder::class]);

        if (! User::query()->where('email', 'test@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        }

        if (! User::query()->where('email', 'admin@example.com')->exists()) {
            User::factory()->admin()->create([
                'name' => 'Admin',
                'email' => 'admin@example.com',
            ]);
        }

        $this->call(EventSeeder::class);
    }
}
