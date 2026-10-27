<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameCatalog;
use App\Services\GameCatalogUnavailable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Sleep;

#[Signature('games:sync-igdb {--limit=200 : Maximum games to refresh per run}')]
#[Description('Refresh IGDB details for selected games in upcoming events')]
final class SyncSelectedIgdbGames extends Command
{
    /**
     * Refresh cover, release year and platforms for games in upcoming events that were
     * not synced this week, at most --limit per run. Stops at the first catalog outage.
     */
    public function handle(GameCatalog $catalog): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('The limit must be between 1 and 1000.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays(7);
        $updated = 0;
        $processed = 0;
        $query = Game::query()
            ->where('source', 'igdb')
            ->where('provider', 'igdb')
            ->whereNotNull('provider_id')
            ->whereHas('selectedEvents', fn (Builder $events): Builder => $events->where('starts_at', '>=', now()))
            ->where(fn (Builder $games): Builder => $games->whereNull('catalog_synced_at')->orWhere('catalog_synced_at', '<', $cutoff));

        foreach ($query->lazyById(50) as $game) {
            if ($processed >= $limit) {
                break;
            }
            if ($processed > 0) {
                Sleep::usleep(300_000);
            }
            $providerId = $game->provider_id;
            if ($providerId === null) {
                continue;
            }
            try {
                $details = $catalog->fresh($providerId);
            } catch (GameCatalogUnavailable) {
                $this->error('The game catalog is temporarily unavailable. Try again later.');

                return self::FAILURE;
            }
            $processed++;
            if ($details !== null) {
                $game->forceFill([
                    'cover_image_url' => $details['cover_url'],
                    'release_year' => $details['release_year'],
                    'available_platforms' => $details['platforms'],
                    'catalog_synced_at' => now(),
                ])->save();
                $updated++;
            } else {
                $game->forceFill(['catalog_synced_at' => now()])->save();
            }
        }

        $this->info("Updated {$updated} selected games.");

        return self::SUCCESS;
    }
}
