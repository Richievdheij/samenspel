<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     *
     * The `updatePassword` error bag keeps these messages out of the profile
     * form on the same page, which also has a field called `password`.
     */
    public function update(Request $request, #[CurrentUser] User $user): RedirectResponse
    {
        $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($request->string('password')->toString()),
        ]);

        return back()->with('status', 'password-updated');
    }
}
