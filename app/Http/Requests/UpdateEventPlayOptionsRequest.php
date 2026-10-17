<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

final class UpdateEventPlayOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $event = $this->route('event');
        $user = $this->user();

        return $event instanceof Event && $user instanceof User && $event->user_id === $user->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $event = $this->route('event');
        $currentGameIds = $event instanceof Event ? $event->games()->pluck('games.id')->all() : [];

        return [
            'games' => ['required', 'array', 'min:1'],
            'games.*' => ['required', 'integer', 'distinct', Rule::exists('games', 'id')->where(
                fn (Builder $query): Builder => $query->where(
                    fn (Builder $games): Builder => $games->where('source', Game::SOURCE_CATALOG)->orWhereIn('id', $currentGameIds),
                ),
            )],
            'platforms' => ['required', 'array', 'min:1'],
            'platforms.*' => ['required', 'integer', 'distinct', Rule::exists('platforms', 'id')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'platforms.required' => __('Choose at least one platform.'),
            'platforms.min' => __('Choose at least one platform.'),
            'platforms.*.exists' => __('Choose valid platforms.'),
            'platforms.*.distinct' => __('Choose each platform only once.'),
        ];
    }

    /** @return list<int> */
    public function ids(string $field): array
    {
        $values = $this->validated($field);
        throw_unless(is_array($values), LogicException::class, 'Validated choices must be an array.');

        $ids = [];
        foreach ($values as $value) {
            throw_if(! is_int($value) && ! is_string($value), LogicException::class, 'Validated choice IDs must be integers.');
            $ids[] = (int) $value;
        }

        return $ids;
    }
}
