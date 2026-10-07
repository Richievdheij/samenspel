<?php

declare(strict_types=1);

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

use Tests\Fixtures\VerifiableUser;

it('displays the profile page', function (): void {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    actingAs($user)->get('/profile')->assertOk()->assertSeeHtml('value="Ada Lovelace"')->assertSeeHtml('id="confirm-user-deletion"')->assertDontSeeHtml('data-modal-show');
});

it('updates the profile information and withdraws the verification of a new address', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status', 'profile-updated')
        ->assertRedirect('/profile');
    $user->refresh();
    expect($user->name)->toBe('Test User')
        ->and($user->email)->toBe('test@example.com')
        ->and($user->email_verified_at)->toBeNull();
});

it('shows "Saved." after an update, not the status key', function (): void {
    $user = User::factory()->create();

    actingAs($user)->followingRedirects()->patch('/profile', [
        'name' => 'Test User',
        'email' => $user->email,
    ])
        ->assertSee('Opgeslagen.')
        ->assertDontSee('profile-updated');
});

it('keeps the verification when the email address is unchanged', function (): void {
    $user = User::factory()->create();

    actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('refuses an email address that belongs to another account', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create();

    actingAs($user)->patch('/profile', [
        'name' => 'Test User',
        'email' => 'taken@example.com',
    ])->assertSessionHasErrors(['email' => 'Dit e-mailadres is al in gebruik.']);

    expect($user->refresh()->email)->not->toBe('taken@example.com');
});

it('offers to re-send the verification email for an unverified address', function (): void {
    $user = VerifiableUser::unverified();

    actingAs($user)->get('/profile')->assertSee('Je e-mailadres is nog niet geverifieerd.')->assertSeeHtml('form="send-verification"');
});

it('deletes the account', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)->delete('/profile', [
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect('/');
    assertGuest();
    assertModelMissing($user);
});

it('requires the correct password to delete the account', function (): void {
    $user = User::factory()->create();

    $response = actingAs($user)
        ->from('/profile')
        ->delete('/profile', [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrorsIn('userDeletion', ['password' => 'Het wachtwoord is onjuist.'])
        ->assertRedirect('/profile');
    assertModelExists($user);
});

it('reopens the delete dialog with the error after a wrong password', function (): void {
    $user = User::factory()->create();

    actingAs($user)
        ->from('/profile')
        ->followingRedirects()->delete('/profile', ['password' => 'wrong-password'])->assertSeeHtml('data-modal-show')->assertSeeHtml('id="delete_user_password-error"')->assertDontSeeHtml('id="update_password_password-error"');
});
