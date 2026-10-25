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
use Illuminate\Database\UniqueConstraintViolationException;
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
     * Save the edited event and its game, category and platform labels as one
     * database change. The status and the sign-ups stay as they were.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<int>  $categoryIds
     * @param  list<int>  $gameIds
     * @param  list<int>  $platformIds
     */
    public function reviseDetails(array $attributes, array $categoryIds, array $gameIds, array $platformIds): void
    {
        DB::transaction(function () use ($attributes, $categoryIds, $gameIds, $platformIds): void {
            $this->update($attributes);
            $this->categories()->sync($categoryIds);
            $this->games()->sync($gameIds);
            $this->platforms()->sync($platformIds);
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

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->whereKey($user->id)->exists();
    }

    /** Why the user cannot sign up for this event right now, or null when they can. */
    public function signUpRefusal(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return __('You organise this event, so you cannot sign up for it.');
        }
        if ($this->hasParticipant($user)) {
            return __('You are already signed up for this event.');
        }
        if ($this->starts_at->isPast()) {
            return __('This event has already started.');
        }
        if ($this->status === EventStatus::Closed) {
            return __('Sign-ups for this event are closed.');
        }
        if ($this->isFull()) {
            return __('This event is full.');
        }

        return null;
    }

    /**
     * Sign the user up, or return why that is not possible. The event row is
     * locked while the places are counted, so two people cannot both take the
     * last one; the unique pair on event_user stops a double sign-up.
     */
    public function signUp(User $user): ?string
    {
        return DB::transaction(function () use ($user): ?string {
            $event = self::query()->lockForUpdate()->findOrFail($this->id);
            $refusal = $event->signUpRefusal($user);
            if ($refusal !== null) {
                return $refusal;
            }

            try {
                $event->participants()->attach($user);
            } catch (UniqueConstraintViolationException) {
                return __('You are already signed up for this event.');
            }

            return null;
        });
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
     * The offset of Amsterdam time from UTC when the event starts: "UTC+1" in
     * winter and "UTC+2" in summer, so a visitor elsewhere can convert it.
     */
    public function utcOffsetLabel(): string
    {
        $offset = $this->starts_at->copy()->setTimezone(config()->string('app.timezone'))->utcOffset();
        $minutes = abs($offset) % 60;

        return 'UTC'.($offset < 0 ? '-' : '+').intdiv(abs($offset), 60).($minutes > 0 ? sprintf(':%02d', $minutes) : '');
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
     * Match the title or the name of one of the event's games, both public. The
     * description is not searched: it is only shown to signed-in users, and a
     * search result would give it away to a guest.
     *
     * The closure puts the two conditions between brackets, so the OR cannot undo
     * the upcoming, game, category and platform conditions around it.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function matchingKeyword(Builder $query, string $keyword): void
    {
        if ($keyword === '') {
            return;
        }

        $query->where(fn (Builder $matches) => $matches
            ->where('title', 'like', '%'.$keyword.'%')
            ->orWhereHas('games', fn (Builder $games) => $games->where('name', 'like', '%'.$keyword.'%')));
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
