<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the registration screen', function (): void {
    get('/register')->assertOk()->assertSeeHtml('autocomplete="new-password"');
});

it('registers a new user, logs them in and announces it', function (): void {
    Event::fake([Registered::class]);

    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
    $user = User::query()->where('email', 'test@example.com')->firstOrFail();
    expect($user->name)->toBe('Test User');
    Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
});

it('refuses an email address that already has an account', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    assertGuest();
    assertDatabaseCount('users', 1);
    $response->assertSessionHasErrors(['email' => 'Dit e-mailadres is al in gebruik.']);
});

it('refuses a password that was not confirmed', function (): void {
    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'something-else',
    ]);

    assertGuest();
    assertDatabaseCount('users', 0);
    $response->assertSessionHasErrors(['password' => 'De bevestiging van wachtwoord komt niet overeen.']);
});

it('refuses an email address with capitals', function (): void {
    $response = post('/register', [
        'name' => 'Test User',
        'email' => 'Test@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    assertGuest();
    $response->assertSessionHasErrors(['email' => 'Het veld e-mailadres mag alleen kleine letters bevatten.']);
});
