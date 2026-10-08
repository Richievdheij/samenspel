<?php

declare(strict_types=1);

use App\Models\Event;

use function Pest\Laravel\get;

it('shows a guest the upcoming events, soonest first', function (): void {
    Event::factory()->create(['title' => 'Later event', 'starts_at' => now()->addWeeks(2)]);
    Event::factory()->create(['title' => 'Eerste event', 'starts_at' => now()->addDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertSeeInOrder(['Eerste event', 'Later event']);
});

it('leaves out events that have already started', function (): void {
    Event::factory()->create(['title' => 'Voorbij event', 'starts_at' => now()->subDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertDontSee('Voorbij event')
        ->assertSee('Er zijn nog geen komende events.');
});
