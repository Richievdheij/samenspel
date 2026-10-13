<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    /**
     * The upcoming events, soonest first, ten to a page. Open to guests.
     *
     * The game, category and sign-up count come along in the same few queries,
     * not one extra query per card.
     */
    public function index(Request $request): View
    {
        $term = $request->query('q');
        $search = is_string($term) ? trim($term) : '';
        $game = $request->query('game');
        $category = $request->query('category');
        $gameId = is_string($game) && ctype_digit($game) ? (int) $game : null;
        $categoryIds = [];
        foreach (is_array($category) ? $category : [$category] as $value) {
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $categoryIds[] = (int) $value;
            }
        }
        $categoryIds = array_values(array_unique($categoryIds));
        $games = Game::query()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);

        return view('events.index', [
            'search' => $search,
            'gameId' => $gameId,
            'categoryIds' => $categoryIds,
            'games' => $games,
            'categories' => $categories,
            'selectedGame' => $games->firstWhere('id', $gameId),
            'selectedCategories' => $categories->whereIn('id', $categoryIds),
            'events' => Event::query()
                ->upcoming()
                ->matchingKeyword($search)
                ->when($gameId !== null, fn ($query) => $query->where('game_id', $gameId))
                ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
                ->with(['game', 'category'])
                ->withCount('participants')
                ->oldest('starts_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /** Show public event facts, and private details only to signed-in users. */
    public function show(Request $request, Event $event): View
    {
        $event->load(['game', 'category'])
            ->loadCount('participants');

        if ($request->user() !== null) {
            $event->load('organizer');
        }

        return view('events.show', [
            'event' => $event,
            'participantPreview' => $request->user() === null ? null : $event->participants()->orderBy('users.name')->limit(4)->get(['users.id', 'users.name']),
            'participants' => $request->user() === null ? null : $event->participants()
                ->orderBy('users.name')
                ->paginate(8, ['users.id', 'users.name'], 'participants_page')
                ->fragment('event-participants'),
        ]);
    }
}
