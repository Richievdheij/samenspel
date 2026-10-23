<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin-only routes. Runs after `auth`, so a guest has already been sent to the
 * login page; a signed-in user without the admin role gets a 403.
 */
final class EnsureUserIsAdmin
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isAdmin(), 403);

        return $next($request);
    }
}
