<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lets search engines index the public sign-in pages', function (string $routeName): void {
    get(route($routeName))
        ->assertOk()
        ->assertSeeHtml('<meta name="robots" content="index, follow">')
        ->assertDontSee('noindex');
})->with(['login', 'register', 'password.request']);

it('sends no noindex on a private page, which a crawler never reaches', function (): void {
    get(route('dashboard'))->assertRedirect(route('login'));

    actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('noindex');
});

it('gives the game search in the event filters an id and a label', function (): void {
    get(route('events.index'))
        ->assertOk()
        ->assertSeeHtml('for="game-filter-search"')
        ->assertSeeHtml('type="search" id="game-filter-search"');
});
