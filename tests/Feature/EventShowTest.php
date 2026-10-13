<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\get;

it('lets a guest open an event from the list and see its details', function (): void {
    $organizer = User::factory()->create(['name' => 'Robin Organisator']);
    $participant = User::factory()->create(['name' => 'Sanne Speler']);
    $event = Event::factory()
        ->for($organizer, 'organizer')
        ->hasAttached($participant, [], 'participants')
        ->create([
            'title' => 'Mario Kart toernooi',
            'description' => 'Neem je eigen controller mee.',
            'location' => 'Utrecht',
            'max_participants' => 4,
        ]);

    get(route('events.index'))
        ->assertOk()
        ->assertSee(route('events.show', $event));

    get(route('events.show', $event))
        ->assertOk()
        ->assertSeeText('Mario Kart toernooi')
        ->assertSeeText('Neem je eigen controller mee.')
        ->assertSeeText('Robin Organisator')
        ->assertSeeText('Sanne Speler')
        ->assertSeeText('Vrije plekken')
        ->assertSeeText('3 / 4')
        ->assertSeeText('Utrecht');
});

it('shows an empty participant list and no free places for a full event', function (): void {
    $emptyEvent = Event::factory()->create();
    $fullEvent = Event::factory()
        ->hasAttached(User::factory()->count(2), [], 'participants')
        ->create(['max_participants' => 2, 'status' => EventStatus::Open]);

    get(route('events.show', $emptyEvent))
        ->assertOk()
        ->assertSeeText('Er zijn nog geen deelnemers ingeschreven.');

    get(route('events.show', $fullEvent))
        ->assertOk()
        ->assertSeeText('0 / 2')
        ->assertSeeText('Vol');
});

it('returns not found for an event that does not exist', function (): void {
    get(route('events.show', ['event' => 999999]))->assertNotFound();
});

it('labels the category and keeps the participant preview compact', function (): void {
    $event = Event::factory()
        ->for(Category::factory()->state(['name' => 'Casual']))
        ->hasAttached(User::factory()->count(6), [], 'participants')
        ->create(['max_participants' => 10]);

    $response = get(route('events.show', $event))
        ->assertOk()
        ->assertSeeText('Casual')
        ->assertSeeHtml('category-label')
        ->assertSeeText('4 / 10')
        ->assertSeeText('+2')
        ->assertSeeHtml('href="#event-participants"');

    expect(substr_count($response->getContent() ?: '', 'event-detail__preview-avatar'))->toBe(4);
});
