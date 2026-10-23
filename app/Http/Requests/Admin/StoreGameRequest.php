<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** Adding a game means choosing one from the catalog, by its IGDB id. */
final class StoreGameRequest extends FormRequest
{
    /** The admin middleware has already checked the role. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'provider_id' => ['required', 'integer', 'min:1'],
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
