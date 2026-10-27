<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * A sign-up has no fields; what is checked is the event: not the user's own, not
 * already joined, not started, not closed and not full. Every reason is shown as
 * a message under the sign-up button.
 */
final class SignUpForEventRequest extends FormRequest
{
    /** Any signed-in user may try; after() decides whether the event takes them. */
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
            $refusal = $this->event()->signUpRefusal($this->signedInUser());
            if ($refusal !== null) {
                $validator->errors()->add('sign_up', $refusal);
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

    /** The user signing up; the auth middleware guarantees there is one. */
    public function signedInUser(): User
    {
        $user = $this->user();
        throw_unless($user instanceof User, LogicException::class, 'A sign-up needs a signed-in user.');

        return $user;
    }
}
