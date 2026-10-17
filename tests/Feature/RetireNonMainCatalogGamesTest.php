<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

it('hides previously selected add-ons from new choices while preserving their events', function (): void {
    Cache::flush();
    config()->set('services.igdb.client_id', 'test-id');
    config()->set('services.igdb.client_secret', 'test-secret');
    Http::fake([
        'id.twitch.tv/*' => Http::response(['access_token' => 'test-token', 'expires_in' => 3600]),
        'api.igdb.com/*' => Http::sequence()
            ->push([['id' => 10, 'name' => 'Fortnite', 'game_type' => 0, 'involved_companies' => [1], 'first_release_date' => 1500000000]])
            ->push([]),
    ]);
    $main = Game::factory()->create(['name' => 'Fortnite', 'source' => 'igdb', 'provider' => 'igdb', 'provider_id' => 10]);
    $addon = Game::factory()->create(['name' => 'Fortnite: Season 1', 'source' => 'igdb', 'provider' => 'igdb', 'provider_id' => 11]);
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->for($addon)->for($admin, 'organizer')->create();
    $event->games()->sync([$addon->id]);

    expect(Artisan::call('games:retire-non-main'))->toBe(0)
        ->and($main->refresh()->source)->toBe('igdb')
        ->and($addon->refresh()->source)->toBe(Game::SOURCE_RETIRED_CATALOG)
        ->and($event->refresh()->games->modelKeys())->toBe([$addon->id]);

    actingAs($admin)->get(route('events.create'))
        ->assertOk()->assertSeeHtml('name="games[]" value="'.$main->id.'"')
        ->assertDontSeeHtml('name="games[]" value="'.$addon->id.'"');
    actingAs($admin)->post(route('events.store'), ['games' => [$addon->id]])
        ->assertSessionHasErrors('games.0');

    expect(Artisan::call('games:retire-non-main'))->toBe(0);
    Http::assertSentCount(3);
});
