<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use LogicException;

/**
 * Editing an event checks the same fields as creating one. Two things differ:
 * only the organiser may send it, and the games the event already has stay valid
 * choices, even when the catalog has since retired them.
 */
final class UpdateEventRequest extends StoreEventRequest
{
    /** Only the event's organiser may edit it; everyone else gets a 403. */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('update', $this->event());
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        $currentGameIds = $this->event()->games()->pluck('games.id')->all();

        return [
            ...parent::rules(),
            'games.*' => ['required', 'integer', 'distinct', Rule::exists('games', 'id')->where(
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $games): Builder => $games->where('source', Game::SOURCE_CATALOG)->orWhereIn('id', $currentGameIds),
                ),
            )],
        ];
    }

    /** The address the event already has needs no new lookup at PDOK. */
    protected function unchangedAddress(string $reference): ?array
    {
        $event = $this->event();

        if ($event->location_reference !== $reference || $event->location === null) {
            return null;
        }

        return ['id' => $reference, 'label' => $event->location];
    }

    /** The event being edited, from the route. */
    private function event(): Event
    {
        $event = $this->route('event');
        throw_unless($event instanceof Event, LogicException::class, 'The event route parameter must be bound.');

        return $event;
    }
}
