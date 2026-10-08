<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo events by other users, with a few sign-ups, so a fresh local database
 * shows a filled overview. Runs after the games, categories and the test
 * account exist, and only on a database that has no events yet.
 */
final class EventSeeder extends Seeder
{
    public function run(): void
    {
        if (Event::query()->exists()) {
            return;
        }

        $games = Game::query()->get();
        $categories = Category::query()->get();
        $organisers = User::factory()->count(4)->create();

        $events = $organisers->map(fn (User $organiser): Event => Event::factory()
            ->for($organiser, 'organizer')
            ->for($games->random())
            ->for($categories->random())
            ->create());

        foreach ($events as $event) {
            $event->participants()->attach(
                $organisers->except([$event->user_id])->random(2)->modelKeys(),
            );
        }
    }
}
