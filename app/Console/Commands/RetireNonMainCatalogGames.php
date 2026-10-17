<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\GameCatalog;
use App\Services\GameCatalogUnavailable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Sleep;

#[Signature('games:retire-non-main')]
#[Description('Hide existing IGDB add-ons and incomplete entries from new event choices')]
final class RetireNonMainCatalogGames extends Command
{
    public function handle(GameCatalog $catalog): int
    {
        $checked = 0;
        $retired = 0;
        $games = Game::query()->where('source', 'igdb')->where('provider', 'igdb')->whereNotNull('provider_id');

        foreach ($games->lazyById(50) as $game) {
            if ($checked > 0) {
                Sleep::usleep(300_000);
            }

            $providerId = $game->provider_id;
            if ($providerId === null) {
                continue;
            }

            try {
                $result = $catalog->find($providerId);
            } catch (GameCatalogUnavailable) {
                $this->error('The game catalog is temporarily unavailable. Try again later.');

                return self::FAILURE;
            }

            $checked++;
            if ($result === null) {
                $game->forceFill(['source' => Game::SOURCE_RETIRED_CATALOG])->save();
                $retired++;
            }
        }

        $this->info("Checked {$checked} catalog entries; hidden {$retired} from new choices.");

        return self::SUCCESS;
    }
}
