<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Http\Requests\StoreEventRequest;
use App\Http\Requests\ToggleEventStatusRequest;
use App\Http\Requests\UpdateEventRequest;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
            'platforms' => Platform::query()->orderBy('id')->get(['id', 'name', 'slug']),
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
        $event = $user->organizeEvent($request->eventAttributes(), $request->categoryIds(), $request->gameIds(), $request->platformIds());

        return to_route('events.show', $event)
            ->with('status', __('Your event has been created.'));
    }

    /**
     * The creation form, filled with the event. Only for its organiser, who keeps
     * that right even when they could no longer create a new event.
     */
    public function edit(Event $event): View
    {
        Gate::authorize('update', $event);
        $event->load(['games', 'categories', 'platforms']);

        return view('events.edit', [
            'event' => $event,
            'games' => Game::query()
                ->where(fn ($query) => $query->selectable()->orWhereIn('id', $event->games->modelKeys()))
                ->orderBy('name')
                ->get(['id', 'name', 'platform', 'release_year', 'cover_image_url', 'cover_image_path', 'logo_image_path', 'provider_id']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::query()->orderBy('id')->get(['id', 'name', 'slug']),
        ]);
    }

    /** Save the organiser's changes; the request has checked who sent them. */
    public function update(UpdateEventRequest $request, Event $event): RedirectResponse
    {
        $event->reviseDetails($request->eventAttributes(), $request->categoryIds(), $request->gameIds(), $request->platformIds());

        return to_route('events.show', $event)
            ->with('status', __('Your event has been updated.'));
    }

    /** Open or close sign-ups, then return to the list or page the button was on. */
    public function toggleStatus(ToggleEventStatusRequest $request, Event $event): RedirectResponse
    {
        $event->update(['status' => $request->status()]);

        return back(fallback: route('events.show', $event))->with('status', $event->status === EventStatus::Closed
            ? __('Sign-ups for :title are closed.', ['title' => $event->title])
            : __('Sign-ups for :title are open again.', ['title' => $event->title]));
    }

    /**
     * Delete the organiser's event. The database removes its sign-ups and its
     * game, category and platform labels with it (cascadeOnDelete).
     */
    public function destroy(Event $event): RedirectResponse
    {
        Gate::authorize('delete', $event);
        $event->delete();

        return to_route('events.index')
            ->with('status', __('Your event has been deleted.'));
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
        $platformIds = [];
        $platform = $request->query('platform');
        foreach (is_array($platform) ? $platform : [$platform] as $value) {
            if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
                $platformIds[] = (int) $value;
            }
        }
        $platformIds = array_values(array_unique($platformIds));
        $games = Game::query()->playedInUpcomingEvents()->orderBy('name')->get(['id', 'name']);
        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $platforms = Platform::query()->orderBy('id')->get(['id', 'name', 'slug']);

        return view('events.index', [
            'search' => $search,
            'gameId' => $gameId,
            'categoryIds' => $categoryIds,
            'platformIds' => $platformIds,
            'games' => $games,
            'categories' => $categories,
            'platforms' => $platforms,
            'selectedGame' => $games->firstWhere('id', $gameId) ?? ($gameId === null ? null : Game::query()->find($gameId, ['id', 'name'])),
            'selectedCategories' => $categories->whereIn('id', $categoryIds),
            'selectedPlatforms' => $platforms->whereIn('id', $platformIds),
            'events' => Event::query()
                ->upcoming()
                ->matchingKeyword($search)
                ->when($gameId !== null, fn ($query) => $query->whereHas('games', fn ($games) => $games->where('games.id', $gameId)))
                ->when($categoryIds !== [], fn ($query) => $query->whereHas('categories', fn ($categories) => $categories->whereIn('categories.id', $categoryIds)))
                ->when($platformIds !== [], fn ($query) => $query->whereHas('platforms', fn ($platforms) => $platforms->whereIn('platforms.id', $platformIds)))
                ->with(['games', 'categories', 'platforms'])
                ->withCount('participants')
                ->oldest('starts_at')
                ->paginate(10)
                ->withQueryString(),
        ]);
    }

    /** Show public event facts, and private details only to signed-in users. */
    public function show(Request $request, Event $event): View
    {
        $event->load(['games', 'categories', 'platforms'])
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
