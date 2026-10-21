<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in start page: the user's sign-ups, and how far they are from
 * organising an event themselves.
 */
final class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        // The same count the create page uses, so the two can never disagree.
        $signUpsCount = $user->signUpsForOtherOrganisersCount();

        return view('dashboard', [
            'upcomingSignUps' => $user->joinedEvents()->upcoming()->withCount('participants')->oldest('starts_at')->get(),
            'pastSignUps' => $user->joinedEvents()->where('starts_at', '<', now())->latest('starts_at')->limit(5)->get(),
            'signUpsCount' => $signUpsCount,
            'signUpsRemaining' => max(0, 3 - $signUpsCount),
            'canOrganize' => $user->canOrganizeEvents(),
        ]);
    }
}
