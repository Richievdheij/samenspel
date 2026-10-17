<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows a guest the upcoming events, soonest first', function (): void {
    Event::factory()->create(['title' => 'Later event', 'starts_at' => now()->addWeeks(2)]);
    Event::factory()->create(['title' => 'Eerste event', 'starts_at' => now()->addDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertSee('Er staan 2 events gepland.')
        ->assertSeeInOrder(['Eerste event', 'Later event']);
});

it('shows the start and end time on a card, including the end date when it crosses midnight', function (): void {
    $startsAt = now()->addWeek()->setTime(22, 30);
    $endsAt = $startsAt->copy()->addHours(3);
    Event::factory()->create(['starts_at' => $startsAt, 'ends_at' => $endsAt]);

    get(route('events.index'))
        ->assertOk()
        ->assertSeeText('22:30')
        ->assertSeeText($endsAt->translatedFormat('j M H:i'));
});

it('provides a search reset before a query is submitted', function (): void {
    $response = get(route('events.index'))
        ->assertOk()
        ->assertSeeHtml('data-search-input');

    expect($response->getContent())->toMatch('/data-search-reset\s+hidden/');
});

it('leaves out events that have already started', function (): void {
    Event::factory()->create(['title' => 'Voorbij event', 'starts_at' => now()->subDay()]);

    get(route('events.index'))
        ->assertOk()
        ->assertDontSee('Voorbij event')
        ->assertSee('Er zijn nog geen komende events.');
});

it('shows guests the game, category and sign-ups without the location', function (): void {
    Event::factory()
        ->for(Game::factory()->state(['name' => 'Mario Kart']))
        ->for(Category::factory()->state(['name' => 'Casual']))
        ->hasAttached(User::factory()->count(2), [], 'participants')
        ->create(['location' => 'Utrecht', 'max_participants' => 8]);

    get(route('events.index'))
        ->assertOk()
        ->assertSee('Mario Kart')
        ->assertSee('Casual')
        ->assertDontSee('Utrecht')
        ->assertSee('2 / 8 spelers');
});

it('shows the location in the list to a signed-in user', function (): void {
    Event::factory()->create(['location' => 'Utrecht']);

    actingAs(User::factory()->create())->get(route('events.index'))
        ->assertOk()
        ->assertSeeText('Utrecht');
});

it('shows game art and every game name on a card and event page', function (): void {
    $firstGame = Game::factory()->create(['cover_image_path' => 'covers/first.jpg']);
    $middleGames = Game::factory()->count(4)->create(['cover_image_path' => 'covers/middle.jpg']);
    $lastGame = Game::factory()->create(['cover_image_path' => 'covers/last.jpg']);
    $event = Event::factory()->create(['game_id' => $firstGame->id]);
    $event->games()->sync([$firstGame->id, ...$middleGames->modelKeys(), $lastGame->id]);

    $index = get(route('events.index'))->assertOk();

    $index->assertSeeHtml('class="event-card__game-tile"')
        ->assertSee('covers/last.jpg')
        ->assertSeeHtml('class="game-badge game-badge--compact"')
        ->assertSeeHtml('class="event-card__games-note" aria-label="2 spellen meer"')
        ->assertDontSeeHtml('event-card__games-more');

    get(route('events.show', $event))
        ->assertOk()
        ->assertSeeText('Spellen (6)')
        ->assertSeeText($lastGame->name);
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

it('searches public event titles and keeps the term in the field', function (): void {
    Event::factory()->create(['title' => 'Mario Kart avond', 'description' => 'Samen racen.']);
    Event::factory()->create(['title' => 'Raceavond', 'description' => 'Neem je Mario controller mee.']);
    Event::factory()->create(['title' => 'Schaakavond', 'description' => 'Samen schaken.']);
    Event::factory()->create(['title' => 'Mario van gisteren', 'starts_at' => now()->subDay()]);

    get(route('events.index', ['q' => '  Mario  ']))
        ->assertOk()
        ->assertSeeText('Mario Kart avond')
        ->assertDontSeeText('Raceavond')
        ->assertDontSeeText('Schaakavond')
        ->assertDontSeeText('Mario van gisteren')
        ->assertSeeText('Zoekresultaten voor "Mario" (1)')
        ->assertSeeHtml('content="noindex, follow"')
        ->assertSeeHtml('rel="canonical" href="'.route('events.index').'"')
        ->assertSeeHtml('aria-label="Zoekopdracht wissen"')
        ->assertSeeHtml('href="'.route('events.index').'"')
        ->assertSeeHtml('value="Mario"');
});

it('keeps the search term when paging and explains an empty search result', function (): void {
    Event::factory()->count(11)->sequence(fn ($sequence): array => [
        'title' => 'LAN event '.($sequence->index + 1),
        'starts_at' => now()->addDays($sequence->index + 1),
    ])->create();
    Event::factory()->create(['title' => 'Schaakavond', 'description' => 'Samen schaken.']);

    get(route('events.index', ['q' => 'LAN']))
        ->assertOk()
        ->assertSeeText('LAN event 1')
        ->assertDontSeeText('LAN event 11')
        ->assertSee('q=LAN');

    get(route('events.index', ['q' => 'LAN', 'page' => 2]))
        ->assertOk()
        ->assertSeeText('LAN event 11')
        ->assertDontSeeText('Schaakavond')
        ->assertSeeHtml('value="LAN"');

    get(route('events.index', ['q' => 'onvindbaar']))
        ->assertOk()
        ->assertSeeText('Geen events gevonden voor je zoekopdracht.')
        ->assertSeeHtml('value="onvindbaar"');
});

it('combines game and category filters with a keyword', function (): void {
    $game = Game::factory()->create(['name' => 'Mario Kart']);
    $otherGame = Game::factory()->create(['name' => 'Chess']);
    $category = Category::factory()->create(['name' => 'Casual']);
    $otherCategory = Category::factory()->create(['name' => 'Online']);

    Event::factory()->for($game)->for($category)->create(['title' => 'Mario avond']);
    Event::factory()->for($game)->for($otherCategory)->create(['title' => 'Mario online']);
    Event::factory()->for($otherGame)->for($category)->create(['title' => 'Mario schaak']);
    Event::factory()->for($game)->for($category)->create(['title' => 'Andere avond']);

    get(route('events.index', ['q' => 'Mario', 'game' => $game->id, 'category' => $category->id]))
        ->assertOk()
        ->assertSeeText('Mario avond')
        ->assertDontSeeText('Mario online')
        ->assertDontSeeText('Mario schaak')
        ->assertDontSeeText('Andere avond')
        ->assertSeeHtml('type="radio" name="game" value="'.$game->id.'" checked')
        ->assertSeeHtml('value="'.$category->id.'" checked')
        ->assertSeeHtml('value="Mario"')
        ->assertSeeHtml('aria-label="Spelfilter verwijderen: Mario Kart"')
        ->assertSeeHtml('aria-label="Categoriefilter verwijderen: Casual"')
        ->assertSeeHtml('href="'.e(route('events.index', ['q' => 'Mario', 'category' => [$category->id]])).'"')
        ->assertSeeHtml('href="'.e(route('events.index', ['q' => 'Mario', 'game' => $game->id])).'"');
});

it('preserves both filters and the search term across pages', function (): void {
    $game = Game::factory()->create();
    $category = Category::factory()->create();

    Event::factory()->count(11)->for($game)->for($category)->sequence(fn ($sequence): array => [
        'title' => 'Toernooi '.($sequence->index + 1),
        'starts_at' => now()->addDays($sequence->index + 1),
    ])->create();
    Event::factory()->create(['title' => 'Toernooi van een ander spel']);

    $filters = ['q' => 'Toernooi', 'game' => $game->id, 'category' => $category->id];

    get(route('events.index', $filters))
        ->assertOk()
        ->assertSeeText('Toernooi 1')
        ->assertDontSeeText('Toernooi 11')
        ->assertSee('q=Toernooi')
        ->assertSee('game='.$game->id)
        ->assertSee('category='.$category->id);

    get(route('events.index', [...$filters, 'page' => 2]))
        ->assertOk()
        ->assertSeeText('Toernooi 11')
        ->assertDontSeeText('Toernooi van een ander spel');
});

it('offers clearing the filters without losing the search term', function (): void {
    $game = Game::factory()->create();
    $category = Category::factory()->create();

    get(route('events.index', ['q' => 'Mario', 'game' => $game->id, 'category' => $category->id]))
        ->assertOk()
        ->assertSeeHtml('class="event-empty__clear" href="'.route('events.index', ['q' => 'Mario']).'"')
        ->assertDontSee('event-filters__clear')
        ->assertSeeText('Geen events gevonden met deze zoekopdracht en filters.');
});

it('filters on a single selection and shows a useful empty state', function (): void {
    $game = Game::factory()->create(['name' => 'Mario Kart']);
    $otherGame = Game::factory()->create(['name' => 'Chess']);
    $category = Category::factory()->create(['name' => 'Casual']);

    Event::factory()->for($game)->for($category)->create(['title' => 'Mario avond']);
    Event::factory()->for($otherGame)->for($category)->create(['title' => 'Schaakavond']);

    get(route('events.index', ['game' => $game->id]))
        ->assertOk()
        ->assertSeeText('Mario avond')
        ->assertDontSeeText('Schaakavond');

    get(route('events.index', ['category' => $category->id]))
        ->assertOk()
        ->assertSeeText('Mario avond')
        ->assertSeeText('Schaakavond');

    get(route('events.index', ['game' => 999]))
        ->assertOk()
        ->assertSeeText('Geen events gevonden met deze filters.')
        ->assertSeeHtml('class="card event-empty"')
        ->assertSeeHtml('class="event-empty__clear" href="'.route('events.index').'"');
});

it('combines several categories with a game and keeps them on the next page', function (): void {
    $game = Game::factory()->create(['name' => 'Mario Kart']);
    $casual = Category::factory()->create(['name' => 'Casual']);
    $online = Category::factory()->create(['name' => 'Online']);
    $lan = Category::factory()->create(['name' => 'LAN']);

    Event::factory()->for($game)->count(11)->sequence(fn ($sequence): array => [
        'title' => 'Mario avond '.($sequence->index + 1),
        'description' => 'Samen racen.',
        'category_id' => $sequence->index % 2 === 0 ? $casual->id : $online->id,
        'starts_at' => now()->addDays($sequence->index + 1),
    ])->create();
    Event::factory()->for($game)->for($lan)->create(['title' => 'Mario LAN', 'description' => 'Samen racen.']);

    $filters = ['q' => 'Mario', 'game' => $game->id, 'category' => [$casual->id, $online->id]];

    get(route('events.index', $filters))
        ->assertOk()
        ->assertSeeText('Mario avond 1')
        ->assertDontSeeText('Mario avond 11')
        ->assertDontSeeText('Mario LAN')
        ->assertSeeHtml('aria-label="Categoriefilter verwijderen: Casual"')
        ->assertSeeHtml('aria-label="Categoriefilter verwijderen: Online"')
        ->assertSeeHtml('href="'.e(route('events.index', ['q' => 'Mario', 'game' => $game->id, 'category' => [$online->id]])).'"')
        ->assertSeeHtml('href="'.e(route('events.index', ['q' => 'Mario', 'game' => $game->id, 'category' => [$casual->id]])).'"');

    get(route('events.index', [...$filters, 'page' => 2]))
        ->assertOk()
        ->assertSeeText('Mario avond 11')
        ->assertDontSeeText('Mario LAN')
        ->assertSee('category%5B0%5D='.$casual->id)
        ->assertSee('category%5B1%5D='.$online->id);
});
