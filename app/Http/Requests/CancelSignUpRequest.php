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

    public function event(): Event
    {
        $event = $this->route('event');
        throw_unless($event instanceof Event, LogicException::class, 'The event route parameter must be bound.');

        return $event;
    }

    public function signedInUser(): User
    {
        $user = $this->user();
        throw_unless($user instanceof User, LogicException::class, 'Cancelling a sign-up needs a signed-in user.');

        return $user;
    }
}
