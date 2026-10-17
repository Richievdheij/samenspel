<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\GameCatalog;
use App\Services\GameCatalogUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GameSearchController extends Controller
{
    public function __invoke(Request $request, GameCatalog $catalog): JsonResponse
    {
        $user = $request->user();
        $eventId = $request->query('event_id');
        $eventId = is_string($eventId) && ctype_digit($eventId) ? (int) $eventId : null;
        abort_unless($user instanceof User && $user->canSelectGamesForEvent($eventId), 403);

        $input = $request->validate([
            'q' => ['required', 'string', 'min:3', 'max:80', 'regex:/^[\pL\pN][\pL\pN .:’\'&+_-]*$/uD'],
        ], [
            'q.required' => __('Enter a game title to search.'),
            'q.string' => __('Enter a valid game title.'),
            'q.min' => __('Enter at least 3 characters to search games.'),
            'q.max' => __('Search with at most 80 characters.'),
            'q.regex' => __('Enter a valid game title.'),
        ]);

        try {
            return response()->json(['results' => $catalog->search(trim($input['q']))]);
        } catch (GameCatalogUnavailable) {
            return response()->json(['message' => __('Game search is temporarily unavailable. Try again later.')], 503);
        }
    }
}
