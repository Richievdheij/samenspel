<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Upcoming events by three other organisers, to show the application with. A new
 * account cannot organise until it has signed up for three events of other
 * people, so without these there is nothing to sign up for.
 *
 * Not part of DatabaseSeeder, which seeds no games or events on purpose. Run it
 * yourself with `php artisan db:seed --class=DemoSeeder`. Games only come from the
 * IGDB catalog, so it uses the games an admin has already added through Spellen
 * beheren. Running it again adds nothing.
 */
final class DemoSeeder extends Seeder
{
    /** @var list<array{name: string, email: string}> */
    public const array ORGANISERS = [
        ['name' => 'Sanne de Vries', 'email' => 'sanne@example.com'],
        ['name' => 'Daan Bakker', 'email' => 'daan@example.com'],
        ['name' => 'Fatima El Amrani', 'email' => 'fatima@example.com'],
    ];

    /**
     * Two events per organiser, in the order of ORGANISERS. The other two
     * organisers sign up for each event, so the beginners' tournament, with room
     * for two, shows as full. The retro afternoon is closed by its organiser.
     *
     * @var list<array{title: string, description: string, days: int, online: bool, max: int|null, closed: bool}>
     */
    private const array EVENTS = [
        ['title' => 'Vrijdagavond racen', 'description' => 'Een avond racen op de bank. Neem je eigen controller mee.', 'days' => 3, 'online' => false, 'max' => 8, 'closed' => false],
        ['title' => 'Online co-op sessie', 'description' => 'Samen de campagne spelen, met voice chat.', 'days' => 6, 'online' => true, 'max' => null, 'closed' => false],
        ['title' => 'LAN-weekend', 'description' => 'Twee dagen LAN. Neem je eigen pc en een verlengsnoer mee.', 'days' => 10, 'online' => false, 'max' => 16, 'closed' => false],
        ['title' => 'Toernooi voor beginners', 'description' => 'Een rustig toernooi, ook als je net begint.', 'days' => 13, 'online' => false, 'max' => 2, 'closed' => false],
        ['title' => 'Retro-middag', 'description' => 'Oude klassiekers op de originele consoles.', 'days' => 17, 'online' => false, 'max' => 6, 'closed' => true],
        ['title' => 'Online toernooi', 'description' => 'Knock-out toernooi, wedstrijden van twintig minuten.', 'days' => 21, 'online' => true, 'max' => 32, 'closed' => false],
    ];

    public function run(): void
    {
        $this->call(CategorySeeder::class);

        $games = Game::query()->selectable()->orderBy('name')->get()->all();
        if ($games === []) {
            $this->command->warn('No games yet. Add a few through Spellen beheren as admin@example.com, then run this seeder again.');

            return;
        }

        $categories = Category::query()->orderBy('name')->get()->all();
        $platforms = Platform::query()->orderBy('id')->get()->all();
        $organisers = array_map(fn (array $organiser): User => User::query()->firstOrCreate(
            ['email' => $organiser['email']],
            ['name' => $organiser['name'], 'password' => 'password'],
        ), self::ORGANISERS);

        foreach (self::EVENTS as $index => $details) {
            $organiser = $organisers[intdiv($index, 2)];
            if ($organiser->organizedEvents()->where('title', $details['title'])->exists()) {
                continue;
            }

            $game = $games[$index % count($games)];
            $category = $categories[$index % count($categories)];
            $startsAt = now()->addDays($details['days'])->setTime(19, 30);

            $event = $organiser->organizeEvent([
                'game_id' => $game->id,
                'category_id' => $category->id,
                'title' => $details['title'],
                'description' => $details['description'],
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(4),
                'is_online' => $details['online'],
                'min_participants' => $details['max'] === null ? null : 1,
                'max_participants' => $details['max'],
            ], [$category->id], [$game->id], [$platforms[$index % count($platforms)]->id]);

            foreach (array_filter($organisers, fn (User $other): bool => ! $other->is($organiser)) as $participant) {
                $event->signUp($participant);
            }

            if ($details['closed']) {
                $event->update(['status' => EventStatus::Closed]);
            }
        }
    }
}
