<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An open event in the coming weeks. Choose the organiser with
 * `->for($user, 'organizer')`.
 *
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_id' => Game::factory(),
            'category_id' => Category::factory(),
            'title' => rtrim(fake()->sentence(3), '.'),
            'description' => fake()->paragraph(),
            'starts_at' => fake()->dateTimeBetween('+1 day', '+2 months'),
            'location' => fake()->city(),
            'max_participants' => fake()->numberBetween(4, 16),
            'status' => EventStatus::Open,
        ];
    }

    /**
     * Indicate that the organiser has closed sign-ups.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EventStatus::Closed,
        ]);
    }
}
