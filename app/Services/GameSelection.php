<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use Illuminate\Support\Facades\DB;

final class GameSelection
{
    /** @param array{id: int, name: string, platform: string|null, platforms: list<string>, release_year: int|null, cover_url: string|null} $result */
    public function fromCatalog(array $result): Game
    {
        return DB::transaction(function () use ($result): Game {
            $existing = Game::query()->where('provider', 'igdb')->where('provider_id', $result['id'])->first();
            if ($existing !== null) {
                if ($existing->source === Game::SOURCE_RETIRED_CATALOG) {
                    $existing->forceFill(['source' => 'igdb'])->save();
                }

                return $existing;
            }

            // An IGDB game may run on many platforms; its first result is not its identity.
            $key = Game::normalizedKey($result['name']);
            $candidate = Game::query()->whereNull('platform')->where('normalized_name', $key)->first();
            $candidate ??= Game::query()->whereNull('platform')->whereNull('normalized_name')->get()
                ->first(fn (Game $game): bool => Game::normalizedKey($game->name) === $key);
            if ($candidate !== null && $candidate->provider === null) {
                // Linked to the catalog, the game takes its official title and becomes selectable.
                $candidate->forceFill([
                    'name' => $result['name'],
                    'source' => Game::SOURCE_CATALOG,
                    'provider' => 'igdb',
                    'provider_id' => $result['id'],
                    'cover_image_url' => $candidate->cover_image_url ?? $result['cover_url'],
                    'release_year' => $candidate->release_year ?? $result['release_year'],
                    'available_platforms' => $result['platforms'],
                ])->save();

                return $candidate;
            }

            if ($candidate !== null) {
                $key = hash('sha256', $key.'|igdb|'.$result['id']);
            }

            return Game::query()->createOrFirst(['normalized_name' => $key], [
                'name' => $result['name'],
                'source' => 'igdb',
                'provider' => 'igdb',
                'provider_id' => $result['id'],
                'platform' => null,
                'release_year' => $result['release_year'],
                'available_platforms' => $result['platforms'],
                'cover_image_url' => $result['cover_url'],
            ]);
        });
    }
}
