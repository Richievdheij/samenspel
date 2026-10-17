<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    Cache::flush();
});

it('protects search and game selection with organiser eligibility', function (): void {
    get(route('events.games.search', ['q' => 'mario']))->assertRedirect(route('login'));
    actingAs(User::factory()->create())->get(route('events.games.search', ['q' => 'mario']))->assertForbidden();
    actingAs(User::factory()->create())->postJson(route('events.games.store'), ['provider_id' => 42])->assertForbidden();
    expect(Game::query()->count())->toBe(0);
});

it('validates search before any provider request and handles missing credentials safely', function (): void {
    config()->set('services.igdb.client_id');
    config()->set('services.igdb.client_secret');
    Http::fake();
    $admin = User::factory()->admin()->create();
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'ab']))
        ->assertUnprocessable()->assertJsonPath('message', 'Vul minstens 3 tekens in om games te zoeken.');
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'mario"; limit 500;']))->assertUnprocessable();
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'mario']))
        ->assertStatus(503)->assertJsonMissingPath('client_secret');
    Http::assertNothingSent();
});

it('searches IGDB with a server-side token, caches results and returns safe cover URLs', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([['id' => 42, 'name' => 'Mario Kart', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000,
            'platforms' => [['name' => 'Switch']], 'cover' => ['image_id' => 'co123']]]),
    ]);
    $admin = User::factory()->admin()->create();
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'Mario Kart']))
        ->assertOk()->assertJsonPath('results.0.cover_url', 'https://images.igdb.com/igdb/image/upload/t_cover_big/co123.jpg')
        ->assertJsonPath('results.0.platforms', ['Switch']);
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'Mario Kart']))->assertOk();
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.igdb.com/v4/games'
        && str_contains($request->body(), 'search "Mario Kart";')
        && str_contains($request->body(), 'game_type = 0 & parent_game = null & version_parent = null & involved_companies != null & first_release_date != null')
        && $request->hasHeader('Authorization', 'Bearer test-token'));
});

it('offers only playable main games and refuses direct selection of seasons, packs and editions', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    $released = ['involved_companies' => [1], 'first_release_date' => 1500000000, 'total_rating_count' => 10];
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::sequence()
            ->push([
                ['id' => 10, 'name' => 'Fortnite', 'game_type' => 0, ...$released],
                ['id' => 11, 'name' => 'Fortnite Season 6', 'game_type' => 7, 'parent_game' => 10, ...$released],
                ['id' => 12, 'name' => 'Fortnite Pack', 'game_type' => 13, ...$released],
                ['id' => 13, 'name' => 'Fortnite Collector Edition', 'game_type' => 0, 'version_parent' => 10, ...$released],
                ['id' => 14, 'name' => 'Fortnite: Lockjaw Starter Quest Pack', 'game_type' => 0, ...$released],
                ['id' => 15, 'name' => 'Fortnite: Season Two', 'game_type' => 0, ...$released],
                ['id' => 16, 'name' => 'Fortnite Deluxe Edition', 'game_type' => 0, ...$released],
                ['id' => 17, 'name' => 'Fortnite Tokyo', 'game_type' => 0, 'game_status' => 6, ...$released],
                ['id' => 18, 'name' => 'Fortnite Remix', 'game_type' => 0, 'involved_companies' => [1]],
            ])
            ->push([['id' => 11, 'name' => 'Fortnite Season 6', 'game_type' => 7, 'parent_game' => 10]]),
    ]);
    $admin = User::factory()->admin()->create();

    actingAs($admin)->getJson(route('events.games.search', ['q' => 'Fortnite']))
        ->assertOk()->assertJsonCount(1, 'results')->assertJsonPath('results.0.name', 'Fortnite');
    actingAs($admin)->postJson(route('events.games.store'), ['provider_id' => 11])
        ->assertNotFound();

    expect(Game::query()->count())->toBe(0);
    Http::assertSent(fn ($request): bool => $request->url() === 'https://api.igdb.com/v4/games'
        && str_contains($request->body(), 'where id = 11 & game_type = 0 & parent_game = null & version_parent = null & involved_companies != null & first_release_date != null;'));
});

it('ranks the searched game above spin-offs and hides unrated near matches', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    $game = fn (int $id, string $name, int $ratings): array => ['id' => $id, 'name' => $name, 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000, 'total_rating_count' => $ratings];
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([
            $game(1, 'Five Nights in Minecraft', 0),
            $game(2, 'Minecraft: Story Mode', 136),
            $game(3, 'Minecraft PSP', 0),
            $game(4, 'Minecraft: Java Edition', 2092),
            $game(5, 'LEGO Minecraft', 40),
        ]),
    ]);

    actingAs(User::factory()->admin()->create())->getJson(route('events.games.search', ['q' => 'minecraft']))
        ->assertOk()
        ->assertJsonPath('results.*.name', ['Minecraft: Java Edition', 'Minecraft: Story Mode', 'LEGO Minecraft']);
});

it('no longer accepts hand-typed game titles', function (): void {
    Http::fake();
    actingAs(User::factory()->admin()->create())
        ->postJson(route('events.games.store'), ['source' => 'manual', 'title' => 'ff', 'platform' => 'fsfs'])
        ->assertUnprocessable()->assertJsonValidationErrors('provider_id');

    expect(Game::query()->count())->toBe(0);
    Http::assertNothingSent();
});

it('offers only catalog games to a new event', function (): void {
    $catalog = Game::factory()->create();
    $manual = Game::factory()->manual()->create();

    actingAs(User::factory()->admin()->create())->get(route('events.create'))
        ->assertOk()
        ->assertSeeHtml('name="games[]" value="'.$catalog->id.'"')
        ->assertDontSeeHtml('name="games[]" value="'.$manual->id.'"');
    actingAs(User::factory()->admin()->create())->post(route('events.store'), ['games' => [$manual->id]])
        ->assertSessionHasErrors('games.0');
});

it('links an existing hand-typed game to IGDB under its official title', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    $game = Game::factory()->manual()->create(['name' => 'mario kart']);
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([['id' => 42, 'name' => 'Mario Kart', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000, 'cover' => ['image_id' => 'co123']]]),
    ]);
    $admin = User::factory()->admin()->create();
    actingAs($admin)->postJson(route('events.games.store'), ['provider_id' => 42])->assertOk()->assertJsonPath('game.id', $game->id);
    actingAs($admin)->postJson(route('events.games.store'), ['provider_id' => 42])->assertOk()->assertJsonPath('game.id', $game->id);
    expect(Game::query()->count())->toBe(1)
        ->and($game->refresh()->provider_id)->toBe(42)
        ->and($game->name)->toBe('Mario Kart')
        ->and($game->source)->toBe(Game::SOURCE_CATALOG);
});

it('rejects unknown provider IDs and provider outages without storing a game', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::sequence()->push([])->push(['message' => 'secret internal error'], 500),
    ]);
    $admin = User::factory()->admin()->create();
    actingAs($admin)->postJson(route('events.games.store'), ['provider_id' => 999])->assertNotFound();
    actingAs($admin)->getJson(route('events.games.search', ['q' => 'Mario']))->assertStatus(503)->assertDontSee('secret internal error');
    expect(Game::query()->count())->toBe(0);
});

it('treats malformed catalog responses as a temporary outage', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([['unexpected' => 'payload']]),
    ]);

    actingAs(User::factory()->admin()->create())
        ->getJson(route('events.games.search', ['q' => 'Mario']))
        ->assertStatus(503)
        ->assertJsonMissingPath('results');
});

it('persists a selected catalog game as the primary and linked event game', function (): void {
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([['id' => 42, 'name' => 'Mario Kart', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000, 'platforms' => [['name' => 'PlayStation 5'], ['name' => 'PC (Microsoft Windows)']]]]),
    ]);
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create();
    $gameId = actingAs($admin)->postJson(route('events.games.store'), ['provider_id' => 42])->json('game.id');

    actingAs($admin)->post(route('events.store'), [
        'games' => [$gameId],
        'categories' => [$category->id],
        'platforms' => [Platform::query()->where('slug', 'switch')->value('id')],
        'title' => 'Mario Kart night',
        'description' => 'An online event.',
        'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        'location_mode' => 'online',
        'capacity_mode' => 'unlimited',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $event = Event::query()->where('title', 'Mario Kart night')->sole();
    expect($event->game_id)->toBe($gameId)
        ->and($event->games->modelKeys())->toBe([$gameId])
        ->and($event->game->platform)->toBeNull()
        ->and($event->game->available_platforms)->toBe(['PlayStation 5', 'PC (Microsoft Windows)']);
});
