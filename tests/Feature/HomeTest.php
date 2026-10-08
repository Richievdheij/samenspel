<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('introduces samenspel with the brand logo and icons', function (): void {
    get('/')
        ->assertOk()
        ->assertSee('Organiseer game-avonden en LAN-sessies, en speel mee.')
        ->assertSeeHtml(asset('brand/samenspel-stacked-light.svg'))
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
