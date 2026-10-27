<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A game that events are played with, managed by an admin.
 *
 * @property int $id
 * @property string $name
 * @property string|null $cover_image_path
 * @property string|null $cover_image_url
 * @property string|null $normalized_name
 * @property string $source
 * @property string|null $provider
 * @property int|null $provider_id
 * @property string|null $platform
 * @property int|null $release_year
 * @property list<string>|null $available_platforms
 * @property Carbon|null $catalog_synced_at
 * @property string|null $logo_image_path
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Event> $events
 */
#[Fillable(['name', 'normalized_name', 'source', 'provider', 'provider_id', 'platform', 'release_year', 'available_platforms', 'cover_image_path', 'logo_image_path', 'cover_image_url'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    public const string SOURCE_RETIRED_CATALOG = 'retired_catalog';

    public const string SOURCE_CATALOG = 'igdb';

    /**
     * A hash of the title and platform that ignores case, spacing and punctuation but
     * keeps + and #, so one game is stored once under the unique normalized_name.
     */
    public static function normalizedKey(string $name, ?string $platform = null): string
    {
        $title = mb_strtolower(trim($name));
        // Keep symbols that distinguish actual titles (for example C++ and C#).
        $title = preg_replace('/[^\pL\pN+#]+/u', ' ', $title) ?? $title;
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);
        $platformKey = $platform === null ? '' : mb_strtolower(trim($platform));

        return hash('sha256', $title.'|'.$platformKey);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** @return BelongsToMany<Event, $this> */
    public function selectedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

    /**
     * Games at least one upcoming event is played with, whatever their source: the
     * choices that make sense in the public game filter.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function playedInUpcomingEvents(Builder $query): void
    {
        $query->whereIn('id', DB::table('event_game')
            ->whereIn('event_id', Event::query()->upcoming()->select('id'))
            ->select('game_id'));
    }

    /**
     * Games a new event can choose: main games from the catalog, under their official
     * title. Retired catalog entries and earlier hand-typed titles stay
     * attached to the events that already use them.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function selectable(Builder $query): void
    {
        $query->where('source', self::SOURCE_CATALOG);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['available_platforms' => 'array', 'catalog_synced_at' => 'datetime'];
    }
}
