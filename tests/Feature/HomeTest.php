<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('introduces samenspel with the brand logo and icons', function (): void {
    get('/')
        ->assertOk()
        ->assertSee('Vind je volgende LAN-party.')
        ->assertSee("Organiseer gaming-events en LAN-party's, en doe mee.")
        ->assertSeeHtml(route('events.index'))
        ->assertSeeHtml(asset('brand/samenspel-horizontal-light.svg'))
        ->assertSeeHtml(asset('favicon.svg'))
        ->assertSeeHtml(asset('site.webmanifest'))
        ->assertSee('Maak een account aan');
});

it('sends a signed-in user on to the dashboard instead of registration', function (): void {
    actingAs(User::factory()->create())
        ->get('/')
        ->assertSee('Naar je dashboard')
        ->assertDontSee('Maak een account aan');
});

it('ships every logo file the logo component can ask for', function (string $layout, string $variant): void {
    expect(public_path("brand/samenspel-{$layout}-{$variant}.svg"))->toBeFile();
})->with(
    ['horizontal', 'stacked', 'emblem', 'wordmark'],
    ['light', 'dark', 'mono-felt', 'mono-paper', 'mono-black', 'mono-white'],
);

it('draws the isometric scene and the memphis shapes as decoration only', function (): void {
    get('/')
        ->assertOk()
        ->assertSeeHtml('<svg class="iso-scene home__scene" viewBox="125 66 312 262" aria-hidden="true"')
        ->assertSeeHtml('<div class="memphis memphis--hero" aria-hidden="true">');
});
