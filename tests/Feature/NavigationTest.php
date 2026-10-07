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
        ->assertSee('Je bent ingelogd!')
        ->assertSee('Ada Lovelace')->assertSee('ada@example.com')->assertSeeHtml(route('profile.edit'))->assertSeeHtml(route('logout'))->assertDontSeeHtml(route('register'));
});

it('marks the current page in the navigation', function (): void {
    $user = User::factory()->create();

    $html = actingAs($user)->get('/dashboard')->getContent();

    expect($html)->toMatch('#href="'.preg_quote(route('dashboard'), '#').'"\s+aria-current="page"#');
});
