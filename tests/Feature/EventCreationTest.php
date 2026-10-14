<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function createEligibleEventOrganiser(): User
{
    $user = User::factory()->create();

    Event::factory()->count(3)->create()->each(
        fn (Event $event) => $event->participants()->attach($user),
    );

    return $user;
}

/** @return array{games: list<int>, categories: list<int>, title: string, description: string, starts_at: string, ends_at: string, location_mode: string, capacity_mode: string, min_participants: int, max_participants: int} */
function validEventInput(): array
{
    $firstCategory = Category::factory()->create();
    $secondCategory = Category::factory()->create();

    return [
        'games' => [Game::factory()->create()->id, Game::factory()->create()->id],
        'categories' => [$firstCategory->id, $secondCategory->id],
        'title' => 'Mario Kart evening',
        'description' => 'A friendly tournament for local players.',
        'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeek()->addHours(3)->format('Y-m-d H:i:s'),
        'location_mode' => 'search',
        'capacity_mode' => 'limited',
        'min_participants' => 2,
        'max_participants' => 8,
    ];
}

it('requires a signed-in account to open the event form', function (): void {
    get(route('events.create'))->assertRedirect(route('login'));
});

it('explains how many sign-ups are still needed before organising', function (): void {
    $user = User::factory()->create();
    Event::factory()->count(2)->create()->each(
        fn (Event $event) => $event->participants()->attach($user),
    );

    actingAs($user)->get(route('events.create'))
        ->assertOk()
        ->assertSeeText('Je hebt je ingeschreven voor 2 van de 3 events van anderen die je nodig hebt.')
        ->assertSeeText('Schrijf je nog in voor 1 event.')
        ->assertDontSeeHtml('name="title"');
});

it('uses plural wording when three sign-ups are still needed', function (): void {
    actingAs(User::factory()->create())->get(route('events.create'))
        ->assertOk()
        ->assertSeeText('Schrijf je nog in voor 3 events.');
});

it('does not count sign-ups for events the user organises', function (): void {
    $user = User::factory()->create();
    $ownEvent = Event::factory()->for($user, 'organizer')->create();
    $ownEvent->participants()->attach($user);
    Event::factory()->count(2)->create()->each(
        fn (Event $event) => $event->participants()->attach($user),
    );

    expect($user->signUpsForOtherOrganisersCount())->toBe(2);
});

it('only permits an eligible user to submit the create request', function (): void {
    $user = User::factory()->create();

    actingAs($user)->post(route('events.store'), validEventInput())
        ->assertForbidden();

    expect(Event::query()->count())->toBe(0);
});

it('shows the form when the user has signed up for three events organised by others', function (): void {
    actingAs(createEligibleEventOrganiser())->get(route('events.create'))
        ->assertOk()
        ->assertSeeHtml('action="'.route('events.store').'"')
        ->assertSeeHtml('name="title"')
        ->assertSeeText('Titel')
        ->assertSeeHtml('name="starts_at"')
        ->assertSeeHtml('name="games[]"')
        ->assertSeeHtml('name="categories[]"')
        ->assertSeeHtml('data-event-game-chips')
        ->assertSeeHtml('data-event-category-chips')
        ->assertSeeHtml('name="min_participants"')
        ->assertSeeHtml('name="max_participants"');
});

it('lets an admin organise without any sign-ups', function (): void {
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get(route('events.create'))->assertOk()->assertSeeHtml('name="title"');
    actingAs($admin)->post(route('events.store'), validEventInput())->assertRedirect();

    expect(Event::query()->count())->toBe(1);
});

it('creates an open event for the signed-in organiser', function (): void {
    $organiser = createEligibleEventOrganiser();
    $otherUser = User::factory()->create();
    $input = [...validEventInput(), 'user_id' => $otherUser->id, 'status' => 'closed'];

    actingAs($organiser)->post(route('events.store'), $input)
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $event = Event::query()->where('title', 'Mario Kart evening')->sole();

    expect($event->user_id)->toBe($organiser->id)
        ->and($event->status)->toBe(EventStatus::Open)
        ->and($event->location)->toBeNull()
        ->and($event->min_participants)->toBe(2)
        ->and($event->max_participants)->toBe(8)
        ->and($event->games->modelKeys())->toBe($input['games'])
        ->and($event->categories->modelKeys())->toBe($input['categories']);
});

it('stores only a registered address selected from the address search', function (): void {
    $id = 'adr-'.str_repeat('a', 32);
    Http::fake(['api.pdok.nl/*' => Http::response(['response' => ['docs' => [
        ['id' => $id, 'type' => 'adres', 'weergavenaam' => 'Dam 1, 1012JS Amsterdam'],
    ]]], 200)]);
    $input = [...validEventInput(), 'location_mode' => 'search', 'location_reference' => $id];

    actingAs(User::factory()->admin()->create())
        ->post(route('events.store'), $input)
        ->assertSessionHasNoErrors();

    $event = Event::query()->where('title', $input['title'])->sole();
    expect($event->location)->toBe('Dam 1, 1012JS Amsterdam')
        ->and($event->location_reference)->toBe($id);
});

it('rejects unverified address text and accepts a registered address', function (): void {
    $id = 'adr-'.str_repeat('c', 32);
    Http::fake(['api.pdok.nl/*' => Http::response(['response' => ['docs' => [
        [
            'id' => $id,
            'type' => 'adres',
            'weergavenaam' => 'Dam 1, 1012JS Amsterdam',
            'straatnaam' => 'Dam',
            'huisnummer' => 1,
            'postcode' => '1012JS',
            'woonplaatsnaam' => 'Amsterdam',
        ],
    ]]], 200)]);
    $admin = User::factory()->admin()->create();
    $input = [...validEventInput(),
        'location_mode' => 'search',
        'event_address_search' => 'Poepland',
        'location_reference' => 'adr-'.str_repeat('d', 32),
    ];

    actingAs($admin)->post(route('events.store'), $input)->assertSessionHasErrors('location_mode');
    expect(Event::query()->count())->toBe(0);

    $input['event_address_search'] = 'Dam 1, 1012JS Amsterdam';
    $input['location_reference'] = $id;
    actingAs($admin)->post(route('events.store'), $input)->assertSessionHasNoErrors();
    expect(Event::query()->sole()->location)->toBe('Dam 1, 1012JS Amsterdam')
        ->and(Event::query()->sole()->is_online)->toBeFalse();
});

it('stores an online event without a physical location', function (): void {
    $input = [...validEventInput(), 'location_mode' => 'online'];

    actingAs(User::factory()->admin()->create())
        ->post(route('events.store'), $input)
        ->assertSessionHasNoErrors();

    $event = Event::query()->where('title', $input['title'])->sole();
    expect($event->is_online)->toBeTrue()
        ->and($event->location)->toBeNull();
});

it('shows every chosen game and category and filters through their relationships', function (): void {
    $admin = User::factory()->admin()->create();
    $input = validEventInput();

    actingAs($admin)->post(route('events.store'), $input)->assertSessionHasNoErrors();

    $event = Event::query()->where('title', $input['title'])->sole();
    $response = get(route('events.show', $event))->assertOk();
    expect($event->games)->toHaveCount(2);
    foreach ($event->games as $game) {
        $response->assertSeeText($game->name);
    }
    expect($event->categories)->toHaveCount(2);
    foreach ($event->categories as $category) {
        $response->assertSeeText($category->name);
    }

    get(route('events.index', ['category' => [$input['categories'][1]]]))
        ->assertOk()
        ->assertSeeText($event->title);

    get(route('events.index', ['game' => $input['games'][1]]))
        ->assertOk()
        ->assertSeeText($event->title);
});

it('stores an unlimited event without a minimum or maximum', function (): void {
    $admin = User::factory()->admin()->create();
    $input = [...validEventInput(), 'capacity_mode' => 'unlimited'];
    unset($input['min_participants'], $input['max_participants']);

    actingAs($admin)->post(route('events.store'), $input)->assertSessionHasNoErrors();

    $event = Event::query()->where('title', $input['title'])->sole();
    $event->participants()->attach(User::factory()->count(20)->create());

    expect($event->refresh()->min_participants)->toBeNull()
        ->and($event->max_participants)->toBeNull()
        ->and($event->isFull())->toBeFalse();

    get(route('events.index'))->assertOk()->assertSeeText('20 spelers · geen limiet')->assertDontSeeText('Vol');
    get(route('events.show', $event))->assertOk()->assertSeeText('20 spelers · geen limiet');
});

it('validates required fields, future dates and a positive participant limit', function (): void {
    $organiser = createEligibleEventOrganiser();
    $input = validEventInput();
    $input['starts_at'] = now()->subMinute()->format('Y-m-d H:i:s');
    $input['ends_at'] = now()->subHours(2)->format('Y-m-d H:i:s');
    $input['min_participants'] = 0;
    $input['max_participants'] = 0;
    unset($input['games'], $input['categories'], $input['title'], $input['description'], $input['location_mode']);

    actingAs($organiser)->post(route('events.store'), $input)
        ->assertSessionHasErrors(['games', 'categories', 'title', 'description', 'location_mode', 'starts_at', 'ends_at', 'min_participants', 'max_participants']);

    expect(Event::query()->count())->toBe(3);
});

it('rejects duplicate or unknown categories and a maximum below the minimum', function (): void {
    $admin = User::factory()->admin()->create();
    $input = validEventInput();
    $input['categories'] = [$input['categories'][0], $input['categories'][0], 999999];
    $input['min_participants'] = 10;
    $input['max_participants'] = 8;

    actingAs($admin)->post(route('events.store'), $input)
        ->assertSessionHasErrors(['categories.0', 'categories.1', 'categories.2', 'max_participants']);

    expect(Event::query()->count())->toBe(0);
});
