<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyGameRequest;
use App\Http\Requests\Admin\SearchGameCatalogRequest;
use App\Http\Requests\Admin\StoreGameRequest;
use App\Http\Requests\Admin\UpdateGameRequest;
use App\Models\Game;
use App\Services\GameCatalog;
use App\Services\GameCatalogUnavailable;
use App\Services\GameSelection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Game management for admins. Games come from the IGDB catalog, so adding one
 * means choosing it there; an admin can then change the title Samenspel shows
 * and give it an own cover.
 */
final class GameController extends Controller
{
    public function index(SearchGameCatalogRequest $request, GameCatalog $catalog): View
    {
        $search = $request->term();
        $results = [];
        $catalogError = null;
        if ($search !== '') {
            try {
                $results = $catalog->search($search);
            } catch (GameCatalogUnavailable) {
                $catalogError = __('Game search is temporarily unavailable. Try again later.');
            }
        }

        return view('admin.games.index', [
            'search' => $search,
            'results' => $results,
            'catalogError' => $catalogError,
            'addedProviderIds' => Game::query()->where('provider', 'igdb')
                ->whereIn('provider_id', array_column($results, 'id'))
                ->pluck('provider_id')->all(),
            'games' => Game::query()->withCount('selectedEvents')->orderBy('name')->paginate(20)->withQueryString(),
        ]);
    }

    public function store(StoreGameRequest $request, GameCatalog $catalog, GameSelection $selection): RedirectResponse
    {
        try {
            $result = $catalog->find($request->integer('provider_id'));
        } catch (GameCatalogUnavailable) {
            return back()->with('error', __('Game search is temporarily unavailable. Try again later.'));
        }
        if ($result === null) {
            return back()->with('error', __('This game could not be found in the catalog.'));
        }

        $game = $selection->fromCatalog($result);

        return to_route('admin.games.index')->with('status', __(':name has been added.', ['name' => $game->name]));
    }

    public function edit(Game $game): View
    {
        return view('admin.games.edit', ['game' => $game]);
    }

    /** A new cover replaces the old file; "remove" goes back to the catalog cover. */
    public function update(UpdateGameRequest $request, Game $game): RedirectResponse
    {
        $cover = $request->file('cover');
        if (($cover !== null || $request->boolean('remove_cover')) && $game->cover_image_path !== null) {
            Storage::disk('public')->delete($game->cover_image_path);
            $game->cover_image_path = null;
        }
        if ($cover !== null) {
            $game->cover_image_path = $cover->store('games/covers', 'public') ?: null;
        }
        $game->name = $request->string('name')->trim()->toString();
        $game->save();

        return to_route('admin.games.index')->with('status', __(':name has been updated.', ['name' => $game->name]));
    }

    public function destroy(DestroyGameRequest $request, Game $game): RedirectResponse
    {
        if ($game->cover_image_path !== null) {
            Storage::disk('public')->delete($game->cover_image_path);
        }
        $game->delete();

        return to_route('admin.games.index')->with('status', __(':name has been deleted.', ['name' => $game->name]));
    }
}
