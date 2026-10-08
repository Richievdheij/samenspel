<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Game;
use Illuminate\Database\Seeder;

/**
 * A starting set of games, so the event form has something to choose from.
 * firstOrCreate keeps it safe to run on a database that already has them.
 */
final class GameSeeder extends Seeder
{
    /** @var list<string> */
    public const array NAMES = [
        'Catan',
        'Counter-Strike 2',
        'Mario Kart 8 Deluxe',
        'Minecraft',
        'Rocket League',
        'Super Smash Bros. Ultimate',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Game::query()->firstOrCreate(['name' => $name]);
        }
    }
}
