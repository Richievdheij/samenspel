<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\Role;
use App\Models\Category;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/*
 * The data model in docs/erd.md, held to what the database itself guarantees:
 * the relations, the unique sign-up and what deleting a row takes with it.
 */

it('refuses a second game or category with the same name', function (string $model): void {
    $model::query()->create(['name' => 'LAN']);
    $model::query()->create(['name' => 'LAN']);
})->with([Game::class, Category::class])->throws(UniqueConstraintViolationException::class);

it('connects an event to its organiser and its participants', function (): void {
    $organiser = User::factory()->create();
    $participant = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();

    $event->participants()->attach($participant);

    expect($event->organizer->is($organiser))->toBeTrue()
        ->and($organiser->organizedEvents->modelKeys())->toBe([$event->id])
        ->and($participant->joinedEvents->modelKeys())->toBe([$event->id])
        ->and(DB::table('event_user')->whereNotNull('created_at')->count())->toBe(1);
});

it('refuses a second sign-up for the same event in the database', function (): void {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $event->participants()->attach($user);
    $event->participants()->attach($user);
})->throws(UniqueConstraintViolationException::class);

it('removes the sign-ups when an event is deleted', function (): void {
    $event = Event::factory()->hasAttached(User::factory()->count(2), [], 'participants')->create();

    $event->delete();

    $this->assertDatabaseCount('event_user', 0);
});

it('removes the events a user organised when the account is deleted', function (): void {
    $event = Event::factory()->create();

    $event->organizer->delete();

    $this->assertModelMissing($event);
});

it('refuses to delete a game an event still uses', function (): void {
    $game = Game::factory()->has(Event::factory())->create();

    $game->delete();
})->throws(QueryException::class);

it('opens a new event unless the organiser closes it', function (): void {
    $event = User::factory()->create()->organizedEvents()->create([
        'game_id' => Game::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
        'title' => 'LAN-party',
        'description' => 'Neem je eigen pc mee.',
        'starts_at' => now()->addWeek(),
        'location' => 'Utrecht',
        'max_participants' => 8,
    ]);

    expect($event->refresh()->status)->toBe(EventStatus::Open);
});

it('never makes someone an admin through mass assignment', function (): void {
    $user = User::query()->create([
        'name' => 'Ada',
        'email' => 'ada@example.com',
        'password' => 'secret',
        'role' => 'admin',
    ]);

    expect($user->refresh()->role)->toBe(Role::User);
});
