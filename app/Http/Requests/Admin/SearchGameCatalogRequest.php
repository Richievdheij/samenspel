<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\GameCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/** The optional catalog search on the game management page. */
final class SearchGameCatalogRequest extends FormRequest
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
            'q' => ['nullable', 'string', 'min:3', 'max:80', 'regex:'.GameCatalog::TITLE_PATTERN],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'q.string' => __('Enter a valid game title.'),
            'q.min' => __('Enter at least 3 characters to search games.'),
            'q.max' => __('Search with at most 80 characters.'),
            'q.regex' => __('Enter a valid game title.'),
        ];
    }

    /** The validated search term, trimmed; '' when the admin did not search. */
    public function term(): string
    {
        $term = $this->validated('q');

        return is_string($term) ? trim($term) : '';
    }
}
