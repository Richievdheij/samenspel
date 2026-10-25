<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;

it('gives a fresh account upcoming events of others to sign up for, once', function (): void {
    Game::factory()->count(2)->create();
    $this->seed(DatabaseSeeder::class);

    $this->seed(DemoSeeder::class);
    $this->seed(DemoSeeder::class);

    $tester = User::query()->where('email', 'test@example.com')->sole();
    $events = Event::query()->upcoming()->withCount('participants')->get();

    expect($events)->toHaveCount(count(DemoSeeder::ORGANISERS) * 2)
        ->and($events->pluck('user_id')->unique())->toHaveCount(count(DemoSeeder::ORGANISERS))
        ->and($events->every(fn (Event $event): bool => $event->participants_count === 2))->toBeTrue()
        ->and($events->filter(fn (Event $event): bool => $event->isFull()))->toHaveCount(1)
        ->and($events->where('status', EventStatus::Closed))->toHaveCount(1)
        ->and($tester->signUpsForOtherOrganisersCount())->toBe(0)
        ->and($tester->canOrganizeEvents())->toBeFalse();
});

it('adds no events while there are no catalog games', function (): void {
    $this->seed(DemoSeeder::class);

    expect(Event::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});
