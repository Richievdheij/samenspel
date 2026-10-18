<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows the platforms of an event as icons and filters the overview on them', function (): void {
    $switch = Platform::query()->where('slug', 'switch')->sole();
    $mobile = Platform::query()->where('slug', 'mobile')->sole();
    $event = Event::factory()->create(['title' => 'Console night']);
    $event->platforms()->attach([$switch->id, $mobile->id]);

    get(route('events.show', $event))
        ->assertSeeHtml('platform-label--switch')
        ->assertSeeHtml('aria-label="Nintendo Switch"')
        ->assertSeeHtml('platform-label--mobile')
        ->assertDontSeeHtml('>Nintendo Switch</span>');
    get(route('events.index', ['platform' => [$switch->id]]))->assertSee('Console night');
    get(route('events.index', ['platform' => [Platform::query()->where('slug', 'xbox')->sole()->id]]))->assertDontSee('Console night');
});

it('allows a current organiser to add a game after their creation eligibility changes', function (): void {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'organizer')->create();

    Cache::flush();
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([['id' => 42, 'name' => 'Owner edition', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000]]),
    ]);

    actingAs($owner)->postJson(route('events.games.store'), [
        'provider_id' => 42, 'event_id' => $event->id,
    ])->assertOk()->assertJsonPath('game.name', 'Owner edition');
    actingAs($owner)->postJson(route('events.games.store'), [
        'provider_id' => 42, 'event_id' => 999999,
    ])->assertForbidden();
});

it('refreshes only chosen IGDB games and keeps the local title and manual games', function (): void {
    Cache::flush();
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([[
            'id' => 42, 'name' => 'Renamed by IGDB', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000,
            'platforms' => [['name' => 'PC (Microsoft Windows)'], ['name' => 'PlayStation 5']],
            'cover' => ['image_id' => 'co123'],
        ]]),
    ]);
    $game = Game::factory()->create(['name' => 'Curated local title', 'source' => 'igdb', 'provider' => 'igdb', 'provider_id' => 42]);
    $manual = Game::factory()->manual()->create();
    Event::factory()->create(['game_id' => $game->id]);
    Event::factory()->create(['game_id' => $manual->id]);

    expect(Artisan::call('games:sync-igdb', ['--limit' => 10]))->toBe(0);
    expect($game->refresh()->name)->toBe('Curated local title')
        ->and($game->available_platforms)->toBe(['PC (Microsoft Windows)', 'PlayStation 5'])
        ->and($game->cover_image_url)->toBe('https://images.igdb.com/igdb/image/upload/t_cover_big/co123.jpg')
        ->and($game->catalog_synced_at)->not->toBeNull()
        ->and($manual->refresh()->catalog_synced_at)->toBeNull();
    Http::assertSentCount(2);
    expect(Artisan::call('games:sync-igdb', ['--limit' => 10]))->toBe(0);
    Http::assertSentCount(2);
});

it('stops a catalog refresh safely when IGDB is unavailable', function (): void {
    Cache::flush();
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response(['message' => 'provider internal details'], 429),
    ]);
    $game = Game::factory()->create(['source' => 'igdb', 'provider' => 'igdb', 'provider_id' => 42]);
    Event::factory()->create(['game_id' => $game->id]);

    expect(Artisan::call('games:sync-igdb'))->toBe(1)
        ->and(Artisan::output())->not->toContain('provider internal details')
        ->and($game->refresh()->catalog_synced_at)->toBeNull();
});
