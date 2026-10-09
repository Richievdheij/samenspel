<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Unit tests: no database, no HTTP. These build the model in memory, so they run
 * in milliseconds and fail for one reason only — the behaviour they name.
 *
 * The assertions below are about a real security property rather than a
 * placeholder. Laravel's User model hides `password` and `remember_token` from
 * serialisation and casts the password to `hashed`; both are one careless edit
 * away from leaking a credential into a JSON response or storing it in clear.
 */

it('keeps the password and remember token out of serialisation', function (): void {
    $user = new User(['name' => 'Test User', 'email' => 'test@example.com']);
    $user->password = 'a-plain-password';
    $user->remember_token = 'a-token';

    $serialised = $user->toArray();

    expect($serialised)
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token')
        ->toHaveKey('email');
});

it('casts the password so it is never stored in clear', function (): void {
    $user = new User;
    $user->password = 'a-plain-password';

    expect($user->getAttributes()['password'])
        ->not->toBe('a-plain-password')
        ->toStartWith('$');
});

it('abbreviates the name to at most two initials for the avatar', function (string $name, string $initials): void {
    expect(new User(['name' => $name])->initials())->toBe($initials);
})->with([
    'two names' => ['Ada Lovelace', 'AL'],
    'three names' => ['Richie van der Heij', 'RV'],
    'one name' => ['ada', 'A'],
    'extra spaces' => ['  Grace   Hopper ', 'GH'],
    'accented' => ['Émile Zola', 'ÉZ'],
]);
