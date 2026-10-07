<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     *
     * EmailVerificationRequest has already refused a link whose id or hash does
     * not belong to the signed-in user; the `signed` middleware a tampered or
     * expired one. Its fulfill() marks the address verified and dispatches the
     * Verified event — the two steps Breeze writes out here by hand.
     */
    public function __invoke(EmailVerificationRequest $request, #[CurrentUser] User $user): RedirectResponse
    {
        if (! $user->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }
}
