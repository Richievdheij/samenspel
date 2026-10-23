<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

function fakeIgdbCatalog(): void
{
    Cache::flush();
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::response([[
            'id' => 1020, 'name' => 'Grand Theft Auto V', 'game_type' => 0, 'involved_companies' => [1],
            'first_release_date' => 1379376000, 'total_rating_count' => 3000, 'platforms' => [['name' => 'PC (Microsoft Windows)']],
        ]]),
    ]);
}

it('keeps game management for signed-in admins only', function (): void {
    $game = Game::factory()->create();
    $user = User::factory()->create();

    get(route('admin.games.index'))->assertRedirect(route('login'));
    actingAs($user)->get(route('admin.games.index'))->assertForbidden();
    actingAs($user)->post(route('admin.games.store'), ['provider_id' => 1020])->assertForbidden();
    actingAs($user)->get(route('admin.games.edit', $game))->assertForbidden();
    actingAs($user)->put(route('admin.games.update', $game), ['name' => 'Taken over'])->assertForbidden();
    actingAs($user)->delete(route('admin.games.destroy', $game))->assertForbidden();
    actingAs($user)->get(route('dashboard'))->assertDontSeeHtml('href="'.route('admin.games.index').'"');

    expect($game->refresh()->exists)->toBeTrue()
        ->and($game->name)->not->toBe('Taken over');
});

it('lists the games with how many events use them, and links the admin there', function (): void {
    $admin = User::factory()->admin()->create();
    $used = Game::factory()->create(['name' => 'Fortnite']);
    Event::factory()->count(2)->for($used)->create();
    Game::factory()->create(['name' => 'Tetris']);

    actingAs($admin)->get(route('dashboard'))->assertSeeHtml('href="'.route('admin.games.index').'"');
    actingAs($admin)->get(route('admin.games.index'))
        ->assertOk()
        ->assertSeeInOrder(['Fortnite', '2 events', 'Tetris', '0 events']);
});

it('adds a game from the catalog search, once', function (): void {
    fakeIgdbCatalog();
    $admin = User::factory()->admin()->create();

    actingAs($admin)->get(route('admin.games.index', ['q' => 'Grand Theft']))
        ->assertOk()
        ->assertSeeText('Grand Theft Auto V')
        ->assertSeeHtml('name="provider_id" value="1020"');
    actingAs($admin)->post(route('admin.games.store'), ['provider_id' => 1020])
        ->assertRedirect(route('admin.games.index'))
        ->assertSessionHas('status', 'Grand Theft Auto V is toegevoegd.');
    actingAs($admin)->post(route('admin.games.store'), ['provider_id' => 1020]);

    expect(Game::query()->where('provider_id', 1020)->sole())
        ->name->toBe('Grand Theft Auto V')
        ->source->toBe(Game::SOURCE_CATALOG);
    actingAs($admin)->get(route('admin.games.index', ['q' => 'Grand Theft']))->assertSeeText('Al toegevoegd');
});

it('refuses a search outside the catalog alphabet before it reaches IGDB', function (): void {
    fakeIgdbCatalog();

    actingAs(User::factory()->admin()->create())->get(route('admin.games.index', ['q' => 'x"; fields *']))
        ->assertSessionHasErrors('q');

    Http::assertNothingSent();
});

it('renames a game and replaces or removes its cover', function (): void {
    Storage::fake('public');
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create(['name' => 'GTA5']);

    actingAs($admin)->get(route('admin.games.edit', $game))->assertOk()->assertSeeHtml('value="GTA5"');
    actingAs($admin)->put(route('admin.games.update', $game), [
        'name' => 'Grand Theft Auto V',
        'cover' => UploadedFile::fake()->image('cover.png', 300, 400),
    ])->assertSessionHasNoErrors()->assertRedirect(route('admin.games.index'));

    $covers = Storage::disk('public')->files('games/covers');
    expect($game->refresh()->name)->toBe('Grand Theft Auto V')
        ->and($covers)->toHaveCount(1)
        ->and($game->cover_image_path)->toBe($covers[0]);

    actingAs($admin)->put(route('admin.games.update', $game), ['name' => 'Grand Theft Auto V', 'remove_cover' => '1']);
    expect($game->refresh()->cover_image_path)->toBeNull()
        ->and(Storage::disk('public')->files('games/covers'))->toBeEmpty();

    actingAs($admin)->put(route('admin.games.update', $game), ['name' => '', 'cover' => UploadedFile::fake()->create('notes.pdf', 10)])
        ->assertSessionHasErrors(['name', 'cover']);
});

it('deletes an unused game and refuses one that events use', function (): void {
    $admin = User::factory()->admin()->create();
    $unused = Game::factory()->create();
    $used = Game::factory()->create();
    Event::factory()->for($used)->create();

    actingAs($admin)->delete(route('admin.games.destroy', $unused))
        ->assertRedirect(route('admin.games.index'))
        ->assertSessionHasNoErrors();
    actingAs($admin)->delete(route('admin.games.destroy', $used))
        ->assertSessionHasErrors(['game' => 'Dit spel wordt gebruikt door 1 event en kan niet worden verwijderd.']);

    expect(Game::query()->find($unused->id))->toBeNull()
        ->and(Game::query()->find($used->id))->not->toBeNull();
});
