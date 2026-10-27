<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use LogicException;

/**
 * A game that an event uses cannot be deleted: the event would lose it. The
 * restrictOnDelete foreign keys refuse it in the database as well.
 */
final class DestroyGameRequest extends FormRequest
{
    /** The admin middleware has already checked the role. */
    public function authorize(): bool
    {
        return true;
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
            $events = $this->game()->selectedEvents()->count();
            if ($events > 0) {
                $validator->errors()->add('game', trans_choice(
                    'This game is used by :count event and cannot be deleted.|This game is used by :count events and cannot be deleted.',
                    $events,
                ));
            }
        }];
    }

    /** The game from the route, whose events after() counts. */
    public function game(): Game
    {
        $game = $this->route('game');
        throw_unless($game instanceof Game, LogicException::class, 'The game route parameter must be bound.');

        return $game;
    }
}
