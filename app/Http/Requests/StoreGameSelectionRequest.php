<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreGameSelectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $eventId = $this->input('event_id');
        $eventId = is_int($eventId) || (is_string($eventId) && ctype_digit($eventId)) ? (int) $eventId : null;

        return $user instanceof User && $user->canSelectGamesForEvent($eventId);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'min:1'],
            'event_id' => ['nullable', 'integer', 'min:1', Rule::exists('events', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'provider_id.required' => __('Choose a game from the catalog.'),
            'provider_id.integer' => __('Choose a game from the catalog.'),
            'provider_id.min' => __('Choose a game from the catalog.'),
        ];
    }
}
