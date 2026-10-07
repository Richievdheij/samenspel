<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the login screen', function (): void {
    get('/login')->assertOk()->assertSeeHtml('autocomplete="current-password"')->assertSeeHtml(route('password.request'));
});

it('logs a user in and sends them to the dashboard', function (): void {
    $user = User::factory()->create();

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard', absolute: false));
});

it('sends a user back to the page they asked for after logging in', function (): void {
    $user = User::factory()->create();
    get('/profile')->assertRedirect(route('login', absolute: false));

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('profile.edit'));
});

it('rejects a wrong password with a Dutch message', function (): void {
    $user = User::factory()->create();

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    assertGuest();
    $response->assertSessionHasErrors([
        'email' => 'Deze combinatie van e-mailadres en wachtwoord is niet bij ons bekend.',
    ]);
});

it('locks the login out after five failed attempts', function (): void {
    Event::fake([Lockout::class]);
    $this->freezeTime();
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        post('/login', ['email' => $user->email, 'password' => 'wrong-password']);
    }

    $response = post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    assertGuest();
    $response->assertSessionHasErrors([
        'email' => 'Te veel inlogpogingen. Probeer het over 60 seconden opnieuw.',
    ]);
    Event::assertDispatched(Lockout::class);
});

it('logs a user out', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/logout');

    assertGuest();
    $response->assertRedirect('/');
});

it('sends a logged-in user away from the guest pages', function (string $uri): void {
    $user = User::factory()->create();

    actingAs($user)->get($uri)->assertRedirect(route('dashboard'));
})->with(['/login', '/register', '/forgot-password', '/reset-password/a-token']);

it('sends a guest to the login page from the pages behind auth', function (string $uri): void {
    get($uri)->assertRedirect(route('login'));
})->with(['/dashboard', '/profile', '/verify-email', '/confirm-password']);
