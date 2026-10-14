<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\User;
use App\Services\AddressLookup;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use LogicException;

class StoreEventRequest extends FormRequest
{
    /** @var array{id: string, label: string, street: string, number: string, postcode: string, city: string, type: string}|null */
    private ?array $resolvedAddress = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canOrganizeEvents();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'games' => ['required', 'array', 'min:1'],
            'games.*' => ['required', 'integer', 'distinct', Rule::exists('games', 'id')],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'location_mode' => ['required', Rule::in(['search', 'online'])],
            'event_address_search' => ['nullable', 'string', 'max:120'],
            'location_reference' => ['nullable', 'string', 'max:128'],
            'capacity_mode' => ['required', Rule::in(['limited', 'unlimited'])],
            'min_participants' => ['required_if:capacity_mode,limited', 'nullable', 'integer', 'min:1', 'max:65535'],
            'max_participants' => ['required_if:capacity_mode,limited', 'nullable', 'integer', 'min:1', 'gte:min_participants', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('location_mode') === 'online') {
                return;
            }

            $reference = $this->stringInput('location_reference');
            if ($reference === '') {
                if (trim($this->stringInput('event_address_search')) !== '') {
                    $validator->errors()->add('location_mode', __('Choose an address from the suggestions or clear the search field.'));
                }

                return;
            }

            /** @var AddressLookup $addresses */
            $addresses = resolve(AddressLookup::class);

            try {
                $this->resolvedAddress = $addresses->lookup($this->stringInput('location_reference'));
            } catch (ConnectionException|RequestException) {
                $validator->errors()->add('location_mode', __('Address search is temporarily unavailable.'));

                return;
            }

            if ($this->resolvedAddress === null) {
                $validator->errors()->add('location_mode', __('Choose an address from the suggestions or clear the search field.'));
            }
        });
    }

    /** @return list<int> */
    public function categoryIds(): array
    {
        $categories = $this->validated('categories');
        throw_unless(is_array($categories), LogicException::class, 'Validated categories must be an array.');

        $ids = [];
        foreach ($categories as $category) {
            throw_if(! is_int($category) && ! is_string($category), LogicException::class, 'Validated category IDs must be integers.');

            $ids[] = (int) $category;
        }

        return $ids;
    }

    /** @return list<int> */
    public function gameIds(): array
    {
        $games = $this->validated('games');
        throw_unless(is_array($games), LogicException::class, 'Validated games must be an array.');

        $ids = [];
        foreach ($games as $game) {
            throw_if(! is_int($game) && ! is_string($game), LogicException::class, 'Validated game IDs must be integers.');

            $ids[] = (int) $game;
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    public function eventAttributes(): array
    {
        $attributes = $this->safe()->except([
            'games', 'categories', 'capacity_mode', 'location_mode', 'location_reference', 'event_address_search',
        ]);
        $attributes['game_id'] = $this->gameIds()[0];
        $attributes['category_id'] = $this->categoryIds()[0];
        $attributes['location'] = $this->resolvedAddress['label'] ?? null;
        $attributes['location_reference'] = $this->resolvedAddress['id'] ?? null;
        $attributes['is_online'] = $this->validated('location_mode') === 'online';

        if ($this->validated('capacity_mode') === 'unlimited') {
            $attributes['min_participants'] = null;
            $attributes['max_participants'] = null;
        }

        return $attributes;
    }

    private function stringInput(string $key): string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : '';
    }
}
