<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('offers a guest the login and registration links', function (): void {
    get('/')->assertSeeHtml(route('login'))->assertSeeHtml(route('register'))->assertDontSeeHtml(route('logout'));
});

it('shows a signed-in user the dashboard link and the user menu', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    actingAs($user)->get('/dashboard')
        ->assertSee('Welkom terug, Ada Lovelace.')
        ->assertSee('Ada Lovelace')->assertSee('ada@example.com')->assertSeeHtml(route('profile.edit'))->assertSeeHtml(route('logout'))->assertDontSeeHtml(route('register'));
});

it('marks the current page in the navigation', function (): void {
    $user = User::factory()->create();

    $html = actingAs($user)->get('/dashboard')->getContent();

    expect($html)->toMatch('#href="'.preg_quote(route('dashboard'), '#').'"\s+aria-current="page"#');
});

it('gives a guest a way back from the sign-in pages', function (string $page): void {
    get($page)->assertOk()->assertSee('Terug naar home');
})->with(['/login', '/register', '/forgot-password']);

it('leads from the profile back to the dashboard', function (): void {
    actingAs(User::factory()->create())->get('/profile')
        ->assertSee('Terug naar dashboard')
        ->assertSeeHtml('class="back-link page-header__back" href="'.route('dashboard').'"');
});

it('shows a signed-in user their initials in the user menu', function (): void {
    actingAs(User::factory()->create(['name' => 'Ada Lovelace']))->get('/dashboard')
        ->assertSeeHtml('<span class="avatar" aria-hidden="true">AL</span>');
});

it('renders the drawer close button hidden until javascript can make it work', function (): void {
    get('/')->assertSeeHtml('data-disclosure-close hidden');
});

it('draws the memphis shapes behind a page heading as decoration only', function (): void {
    actingAs(User::factory()->create())->get('/dashboard')
        ->assertSeeHtml('<div class="memphis memphis--header" aria-hidden="true">');
});
