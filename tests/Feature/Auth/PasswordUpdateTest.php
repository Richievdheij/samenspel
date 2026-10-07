<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;

it('updates the password', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'password-updated')
        ->assertRedirect('/profile');
    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

it('requires the current password to update the password', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('updatePassword', ['current_password' => 'Het wachtwoord is onjuist.'])
        ->assertRedirect('/profile');
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});

it('shows the error under the password form only', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->followingRedirects()
        ->put('/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertSeeHtml('id="update_password_current_password-error"')->assertDontSeeHtml('id="delete_user_password-error"')->assertDontSeeHtml('data-modal-show');
});
