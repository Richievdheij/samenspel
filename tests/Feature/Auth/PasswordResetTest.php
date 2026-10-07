<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('renders the reset password link screen', function (): void {
    get('/forgot-password')->assertOk()->assertSeeHtml(route('password.email'));
});

it('sends a reset link to a known address', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    $response = post('/forgot-password', ['email' => $user->email]);

    $response->assertSessionHas('status', 'We hebben je een e-mail gestuurd met een link om je wachtwoord te resetten.');
    Notification::assertSentTo($user, ResetPassword::class);
});

it('writes the reset mail in Dutch', function (): void {
    $user = User::factory()->create();

    $mail = new ResetPassword('a-token')->toMail($user);

    expect($mail->subject)->toBe('Stel je wachtwoord opnieuw in')
        ->and($mail->actionText)->toBe('Wachtwoord resetten');
});

it('renders the reset password screen from the mailed link', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        get('/reset-password/'.$notification->token.'?email='.urlencode($user->email))->assertOk()->assertSeeHtml('value="'.$notification->token.'"')->assertSeeHtml('value="'.$user->email.'"');

        return true;
    });
});

it('resets the password with a valid token', function (): void {
    Notification::fake();
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create();
    $oldRememberToken = $user->remember_token;
    post('/forgot-password', ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        post('/reset-password', [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Je wachtwoord is opnieuw ingesteld.')
            ->assertRedirect(route('login'));

        return true;
    });

    $user->refresh();
    expect(Hash::check('new-password', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe($oldRememberToken);
    Event::assertDispatched(PasswordReset::class);
});

it('refuses to reset the password with an invalid token', function (): void {
    $user = User::factory()->create();

    $response = post('/reset-password', [
        'token' => 'not-a-real-token',
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertSessionHasErrors(['email' => 'Deze link om je wachtwoord te resetten is ongeldig.']);
    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
});
