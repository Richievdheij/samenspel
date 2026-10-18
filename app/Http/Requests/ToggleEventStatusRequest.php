<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The status switch carries the status it switches to, not "flip whatever it is
 * now": a double click or a page left open in two tabs then lands on what the
 * organiser saw, instead of flipping the event back.
 */
final class ToggleEventStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');

        return $user instanceof User && $event instanceof Event && $user->can('toggleStatus', $event);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(EventStatus::class)],
        ];
    }

    public function status(): EventStatus
    {
        return $this->enum('status', EventStatus::class) ?? EventStatus::Open;
    }
}
