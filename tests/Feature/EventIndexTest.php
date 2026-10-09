<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;

use function Pest\Laravel\get;

it('shows a guest the upcoming events, soonest first', function (): void {
    Event::factory()->create(['title' => 'Later event', 'starts_at' => now()->addWeeks(2)]);
    Event::factory()->create(['title' => 'Eerste event', 'starts_at' => now()->addDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertSee('Er staan 2 events gepland.')
        ->assertSeeInOrder(['Eerste event', 'Later event']);
});

it('leaves out events that have already started', function (): void {
    Event::factory()->create(['title' => 'Voorbij event', 'starts_at' => now()->subDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertDontSee('Voorbij event')
        ->assertSee('Er zijn nog geen komende events.');
});

it('shows the game, category, location and sign-ups of each event', function (): void {
    Event::factory()
        ->for(Game::factory()->state(['name' => 'Mario Kart']))
        ->for(Category::factory()->state(['name' => 'Casual']))
        ->hasAttached(User::factory()->count(2), [], 'participants')
        ->create(['location' => 'Utrecht', 'max_participants' => 8]);

    get(route('events.index'))
        ->assertOk()
        ->assertSee('Mario Kart')
        ->assertSee('Casual')
        ->assertSee('Utrecht')
        ->assertSee('2 / 8 spelers');
});

it('labels an event open, closed or full', function (EventStatus $status, int $signUps, string $state, string $label): void {
    Event::factory()
        ->hasAttached(User::factory()->count($signUps), [], 'participants')
        ->create(['status' => $status, 'max_participants' => 2]);

    $response = get(route('events.index'))
        ->assertSeeHtml('status-pill--'.$state)
        ->assertSeeText($label);

    foreach (array_diff(['open', 'closed', 'full'], [$state]) as $otherState) {
        $response->assertDontSeeHtml('status-pill--'.$otherState);
    }
})->with([
    'open' => [EventStatus::Open, 1, 'open', 'Open'],
    'closed' => [EventStatus::Closed, 0, 'closed', 'Gesloten'],
    'full' => [EventStatus::Open, 2, 'full', 'Vol'],
    'closed and full' => [EventStatus::Closed, 2, 'closed', 'Gesloten'],
]);

it('spreads the events over pages of ten', function (): void {
    Event::factory()->count(10)->sequence(fn ($sequence): array => [
        'title' => 'Event '.($sequence->index + 1),
        'starts_at' => now()->addDays($sequence->index + 1),
    ])->create();
    Event::factory()->create(['title' => 'Elfde event', 'starts_at' => now()->addMonths(3)]);

    get(route('events.index'))->assertDontSee('Elfde event')->assertSee('Volgende');
    get(route('events.index', ['page' => 2]))->assertSee('Elfde event')->assertSee('Vorige');
});
