<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreGameSelectionRequest;
use App\Models\Game;
use App\Services\GameCatalog;
use App\Services\GameCatalogUnavailable;
use App\Services\GameSelection;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

final class GameSelectionController extends Controller
{
    public function __invoke(StoreGameSelectionRequest $request, GameCatalog $catalog, GameSelection $selection): JsonResponse
    {
        try {
            $result = $catalog->find($request->integer('provider_id'));
        } catch (GameCatalogUnavailable) {
            return response()->json(['message' => __('Game search is temporarily unavailable. Try again later.')], 503);
        }
        if ($result === null) {
            return response()->json(['message' => __('This game could not be found in the catalog.')], 404);
        }

        return response()->json(['game' => $this->gameData($selection->fromCatalog($result)), 'message' => __('Game selected. You can now save your event.')]);
    }

    /** @return array{id: int, name: string, platform: string|null, provider_id: int|null, platforms: list<string>, release_year: int|null, cover_url: string|null} */
    private function gameData(Game $game): array
    {
        return [
            'id' => $game->id,
            'name' => $game->name,
            'platform' => $game->platform,
            'provider_id' => $game->provider_id,
            'platforms' => $game->available_platforms ?? [],
            'release_year' => $game->release_year,
            'cover_url' => $game->cover_image_path ? Storage::disk('public')->url($game->cover_image_path) : ($game->logo_image_path ? Storage::disk('public')->url($game->logo_image_path) : $game->cover_image_url),
        ];
    }
}
