<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * Cancelling needs a sign-up to cancel, before the event starts. A closed event
 * still allows it: closing stops new sign-ups, it does not hold people to theirs.
 */
final class CancelSignUpRequest extends FormRequest
{
    /** Any signed-in user may try; after() checks there is a sign-up to cancel. */
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $event = $this->event();
            if (! $event->hasParticipant($this->signedInUser())) {
                $validator->errors()->add('sign_up', __('You are not signed up for this event.'));
            } elseif ($event->starts_at->isPast()) {
                $validator->errors()->add('sign_up', __('This event has already started.'));
            }
        }];
    }

    /** The event from the route, already loaded by route model binding. */
    public function event(): Event
    {
        $event = $this->route('event');
        throw_unless($event instanceof Event, LogicException::class, 'The event route parameter must be bound.');

        return $event;
    }

    /** The user cancelling; the auth middleware guarantees there is one. */
    public function signedInUser(): User
    {
        $user = $this->user();
        throw_unless($user instanceof User, LogicException::class, 'Cancelling a sign-up needs a signed-in user.');

        return $user;
    }
}
