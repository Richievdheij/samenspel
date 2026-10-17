<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A game night or LAN session.
 *
 * user_id is deliberately not fillable: the organiser is whoever is signed in,
 * set through $user->organizedEvents()->create(...), never a value a request
 * can choose.
 *
 * @property int $id
 * @property int $user_id
 * @property int $game_id
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $location
 * @property string|null $location_reference
 * @property bool $is_online
 * @property int|null $min_participants
 * @property int|null $max_participants
 * @property EventStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $organizer
 * @property-read Game $game
 * @property-read Collection<int, Game> $games
 * @property-read Category $category
 * @property-read Collection<int, Category> $categories
 * @property-read Collection<int, Platform> $platforms
 * @property-read Collection<int, User> $participants
 * @property-read int|null $participants_count set by withCount('participants')
 */
#[Fillable([
    'game_id',
    'category_id',
    'title',
    'description',
    'starts_at',
    'ends_at',
    'location',
    'location_reference',
    'is_online',
    'min_participants',
    'max_participants',
    'status',
])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Every game in this event. game_id remains its primary game.
     *
     * @return BelongsToMany<Game, $this>
     */
    public function games(): BelongsToMany
    {
        return $this->belongsToMany(Game::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Every category shown on the event. category_id remains its primary category.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    /** @return BelongsToMany<Platform, $this> */
    public function platforms(): BelongsToMany
    {
        return $this->belongsToMany(Platform::class);
    }

    /**
     * @param  list<int>  $gameIds
     * @param  list<int>  $platformIds
     */
    public function updatePlayOptions(array $gameIds, array $platformIds): void
    {
        DB::transaction(function () use ($gameIds, $platformIds): void {
            $this->games()->sync($gameIds);
            $this->platforms()->sync($platformIds);
            $this->update(['game_id' => $gameIds[0]]);
        });
    }

    /**
     * The users signed up for this event, through the event_user pivot table.
     *
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    /**
     * Whether every place is taken. "Full" is derived, never stored: it follows
     * from the sign-ups against max_participants. Uses the count from
     * withCount('participants') when the query loaded it.
     */
    public function isFull(): bool
    {
        if ($this->max_participants === null) {
            return false;
        }

        $signUps = $this->participants_count ?? $this->participants()->count();

        return $signUps >= $this->max_participants;
    }

    /** Duration is unknown for events created before an end time was recorded. */
    public function durationInMinutes(): ?int
    {
        if ($this->ends_at === null || $this->ends_at->lessThanOrEqualTo($this->starts_at)) {
            return null;
        }

        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    /**
     * Only events that have not started yet.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('starts_at', '>=', now());
    }

    /**
     * Match public event titles. Descriptions may contain private details.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingKeyword(Builder $query, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $query->where('title', 'like', '%'.$keyword.'%');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'min_participants' => 'integer',
            'max_participants' => 'integer',
            'status' => EventStatus::class,
        ];
    }
}
