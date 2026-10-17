<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Game>
 */
class GameFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => Str::title(fake()->unique()->word()),
            'source' => Game::SOURCE_CATALOG,
            'provider' => 'igdb',
            'provider_id' => fake()->unique()->numberBetween(1, 9_999_999),
        ];
    }

    /** A title typed in by hand before only catalog games could be chosen. */
    public function manual(): static
    {
        return $this->state(['source' => 'manual', 'provider' => null, 'provider_id' => null]);
    }
}
