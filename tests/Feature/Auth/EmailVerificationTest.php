<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;

use Tests\Fixtures\VerifiableUser;

/**
 * Every test here acts as a VerifiableUser: App\Models\User ships with
 * MustVerifyEmail switched off, as Breeze's does.
 */
function verificationUrl(User $user, string $hash): string
{
    return URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->getKey(), 'hash' => $hash],
    );
}

it('renders the verification prompt for an unverified user', function (): void {
    $user = VerifiableUser::unverified();

    actingAs($user)->get('/verify-email')->assertOk()->assertSeeHtml(route('verification.send'));
});

it('sends an already verified user from the prompt to the dashboard', function (): void {
    $user = User::factory()->create();

    actingAs($user)->get('/verify-email')->assertRedirect(route('dashboard', absolute: false));
});

it('verifies the email address from the signed link', function (): void {
    Event::fake([Verified::class]);
    $user = VerifiableUser::unverified();

    $response = actingAs($user)->get(verificationUrl($user, sha1($user->email)));

    Event::assertDispatched(Verified::class);
    expect($user->refresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('dashboard', absolute: false).'?verified=1');
});

it('does not verify the email address with an invalid hash', function (): void {
    Event::fake([Verified::class]);
    $user = VerifiableUser::unverified();

    actingAs($user)->get(verificationUrl($user, sha1('wrong-email')))->assertForbidden();

    Event::assertNotDispatched(Verified::class);
    expect($user->refresh()->hasVerifiedEmail())->toBeFalse();
});

it('re-sends the verification link', function (): void {
    Notification::fake();
    $user = VerifiableUser::unverified();

    $response = actingAs($user)->from('/verify-email')->post('/email/verification-notification');

    $response
        ->assertSessionHas('status', 'verification-link-sent')
        ->assertRedirect('/verify-email');
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('confirms on the prompt that the link was re-sent', function (): void {
    Notification::fake();
    $user = VerifiableUser::unverified();

    actingAs($user)->from('/verify-email')->followingRedirects()->post('/email/verification-notification')
        ->assertSee('Er is een nieuwe verificatielink verstuurd naar het e-mailadres dat je bij de registratie hebt opgegeven.')
        ->assertDontSee('verification-link-sent');
});

it('writes the verification mail in Dutch', function (): void {
    $user = VerifiableUser::unverified();

    $mail = (new VerifyEmail)->toMail($user);

    expect($mail->subject)->toBe('Verifieer je e-mailadres')
        ->and($mail->actionText)->toBe('E-mailadres verifiëren');
});

it('keeps an unverified user out of the dashboard', function (): void {
    $user = VerifiableUser::unverified();

    actingAs($user)->get('/dashboard')->assertRedirect(route('verification.notice'));
});
