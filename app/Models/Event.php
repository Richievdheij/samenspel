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
 * @property string $location
 * @property int $max_participants
 * @property EventStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $organizer
 * @property-read Game $game
 * @property-read Category $category
 * @property-read Collection<int, User> $participants
 * @property-read int|null $participants_count set by withCount('participants')
 */
#[Fillable([
    'game_id',
    'category_id',
    'title',
    'description',
    'starts_at',
    'location',
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
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
        $signUps = $this->participants_count ?? $this->participants()->count();

        return $signUps >= $this->max_participants;
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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'max_participants' => 'integer',
            'status' => EventStatus::class,
        ];
    }
}
