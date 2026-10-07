<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Table;

/**
 * The User model with email verification switched on.
 *
 * App\Models\User ships with MustVerifyEmail commented out, as Breeze's does, so
 * the verification flow would be untested until somebody enables it. This is
 * that one-line change, made for the tests only: the same table, the same
 * columns, the interface added.
 */
#[Table(name: 'users')]
final class VerifiableUser extends User implements MustVerifyEmail
{
    /**
     * An unverified user, read back through this class.
     */
    public static function unverified(): self
    {
        return self::query()->whereKey(User::factory()->unverified()->create()->getKey())->firstOrFail();
    }
}
