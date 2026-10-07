<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;

it('renders the confirm password screen', function (): void {
    $user = User::factory()->create();

    actingAs($user)->get('/confirm-password')->assertOk()->assertSeeHtml('autocomplete="current-password"');
});

it('confirms the password', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/confirm-password', [
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('auth.password_confirmed_at')
        ->assertRedirect(route('dashboard', absolute: false));
});

it('does not confirm a wrong password', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->post('/confirm-password', [
        'password' => 'wrong-password',
    ]);

    $response
        ->assertSessionHasErrors(['password' => 'Het opgegeven wachtwoord is onjuist.'])
        ->assertSessionMissing('auth.password_confirmed_at');
});
