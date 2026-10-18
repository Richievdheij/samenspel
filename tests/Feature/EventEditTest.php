<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/** @return array{games: list<int>, categories: list<int>, platforms: list<int>, title: string, description: string, starts_at: string, ends_at: string, location_mode: string, capacity_mode: string} */
function editedEventInput(): array
{
    return [
        'games' => [Game::factory()->create()->id],
        'categories' => [Category::factory()->create()->id],
        'platforms' => [Platform::query()->where('slug', 'xbox')->sole()->id],
        'title' => 'Halo LAN, second edition',
        'description' => 'Bring your own controller.',
        'starts_at' => now()->addWeeks(3)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeeks(3)->addHours(5)->format('Y-m-d H:i:s'),
        'location_mode' => 'online',
        'capacity_mode' => 'unlimited',
    ];
}

it('fills the creation form with the event for its organiser', function (): void {
    // No sign-ups and no admin role: editing an own event does not need the organise rule.
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create([
        'title' => 'Mario Kart cup',
        'description' => 'Four rounds, one winner.',
        'starts_at' => now()->addWeek()->setTime(19, 30),
    ]);
    $event->platforms()->attach(Platform::query()->where('slug', 'switch')->sole());

    actingAs($organiser)->get(route('events.edit', $event))
        ->assertOk()
        ->assertSeeHtml('action="'.route('events.update', $event).'"')
        ->assertSeeHtml('name="_method" value="PUT"')
        ->assertSeeHtml('value="Mario Kart cup"')
        ->assertSee('Four rounds, one winner.')
        ->assertSeeHtml('value="'.$event->starts_at->format('Y-m-d\TH:i').'"')
        ->assertSeeHtml('name="games[]" value="'.$event->game_id.'" checked')
        ->assertSeeHtml('name="categories[]" value="'.$event->category_id.'" checked')
        ->assertSeeHtml('name="platforms[]" value="'.$event->platforms->sole()->id.'" checked');
});

it('saves every field and keeps the status and sign-ups as they were', function (): void {
    $organiser = User::factory()->create();
    $retired = Game::factory()->manual()->create();
    $event = Event::factory()->for($organiser, 'organizer')->for($retired)->closed()->create();
    $participant = User::factory()->create();
    $event->participants()->attach($participant);
    $input = editedEventInput();
    $input['games'][] = $retired->id;

    actingAs($organiser)->put(route('events.update', $event), $input)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('status', 'Je event is bijgewerkt.');

    $event->refresh();
    expect($event->title)->toBe('Halo LAN, second edition')
        ->and($event->description)->toBe('Bring your own controller.')
        ->and($event->starts_at->format('Y-m-d H:i:s'))->toBe($input['starts_at'])
        ->and($event->ends_at?->format('Y-m-d H:i:s'))->toBe($input['ends_at'])
        ->and($event->is_online)->toBeTrue()
        ->and($event->location)->toBeNull()
        ->and($event->min_participants)->toBeNull()
        ->and($event->max_participants)->toBeNull()
        ->and($event->game_id)->toBe($input['games'][0])
        ->and($event->games->modelKeys())->toEqualCanonicalizing($input['games'])
        ->and($event->category_id)->toBe($input['categories'][0])
        ->and($event->categories->modelKeys())->toBe($input['categories'])
        ->and($event->platforms->modelKeys())->toBe($input['platforms'])
        ->and($event->status)->toBe(EventStatus::Closed)
        ->and($event->participants->modelKeys())->toBe([$participant->id]);
});

it('lets only the organiser edit, also through a direct link or request', function (): void {
    $organiser = User::factory()->create();
    $stranger = User::factory()->admin()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create(['title' => 'Original title']);

    get(route('events.edit', $event))->assertRedirect(route('login'));
    put(route('events.update', $event), editedEventInput())->assertRedirect(route('login'));
    actingAs($stranger)->get(route('events.edit', $event))->assertForbidden();
    actingAs($stranger)->put(route('events.update', $event), editedEventInput())->assertForbidden();
    actingAs($stranger)->get(route('events.show', $event))->assertDontSee(route('events.edit', $event));
    actingAs($organiser)->get(route('events.show', $event))->assertSee(route('events.edit', $event));

    expect($event->refresh()->title)->toBe('Original title');
});

it('checks an edit with the same rules as a new event', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create(['title' => 'Original title']);
    $unrelatedManualGame = Game::factory()->manual()->create();

    actingAs($organiser)->put(route('events.update', $event), [
        ...editedEventInput(),
        'games' => [$unrelatedManualGame->id],
        'categories' => [],
        'platforms' => [],
        'title' => '',
        'starts_at' => now()->subDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->subDays(2)->format('Y-m-d H:i:s'),
        'capacity_mode' => 'limited',
        'min_participants' => 8,
        'max_participants' => 4,
    ])->assertSessionHasErrors(['games.0', 'categories', 'platforms', 'title', 'starts_at', 'ends_at', 'max_participants']);

    expect($event->refresh()->title)->toBe('Original title');
});

it('keeps the address an event already has without asking PDOK again', function (): void {
    Http::fake();
    $organiser = User::factory()->create();
    $reference = 'adr-'.str_repeat('a', 32);
    $event = Event::factory()->for($organiser, 'organizer')->create([
        'location' => 'Stationsplein 1, 1012AB Amsterdam',
        'location_reference' => $reference,
    ]);

    actingAs($organiser)->get(route('events.edit', $event))
        ->assertSeeHtml('value="Stationsplein 1, 1012AB Amsterdam"')
        ->assertSeeHtml('value="'.$reference.'"');
    actingAs($organiser)->put(route('events.update', $event), [
        ...editedEventInput(),
        'location_mode' => 'search',
        'event_address_search' => 'Stationsplein 1, 1012AB Amsterdam',
        'location_reference' => $reference,
    ])->assertSessionHasNoErrors();

    Http::assertNothingSent();
    expect($event->refresh()->location)->toBe('Stationsplein 1, 1012AB Amsterdam')
        ->and($event->location_reference)->toBe($reference)
        ->and($event->is_online)->toBeFalse();
});
