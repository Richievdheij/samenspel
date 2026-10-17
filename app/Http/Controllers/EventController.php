<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreEventRequest;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    /** Show the creation form and the organiser eligibility progress. */
    public function create(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $signUpsCount = $user->signUpsForOtherOrganisersCount();

        return view('events.create', [
            'games' => Game::query()->selectable()->orderBy('name')->get(['id', 'name', 'platform', 'release_year', 'cover_image_url', 'cover_image_path', 'logo_image_path', 'provider_id']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'signUpsCount' => $signUpsCount,
            'signUpsRemaining' => max(0, 3 - $signUpsCount),
            'canOrganize' => $user->canOrganizeEvents(),
        ]);
    }

    /** Persist a validated event under the signed-in organiser. */
    public function store(StoreEventRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $event = $user->organizeEvent($request->eventAttributes(), $request->categoryIds(), $request->gameIds());

        return to_route('events.show', $event)
            ->with('status', __('Your event has been created.'));
    }

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
                ->when($gameId !== null, fn ($query) => $query->whereHas('games', fn ($games) => $games->where('games.id', $gameId)))
                ->when($categoryIds !== [], fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds)))
                ->with(['games', 'categories'])
                ->withCount('participants')
                ->oldest('starts_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /** Show public event facts, and private details only to signed-in users. */
    public function show(Request $request, Event $event): View
    {
        $event->load(['games', 'categories'])
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
