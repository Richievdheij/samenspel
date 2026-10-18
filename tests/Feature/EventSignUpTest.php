<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

it('signs a user up for an open event and shows that on the event page', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['title' => 'Mario Kart cup', 'max_participants' => 4]);

    actingAs($user)->get(route('events.show', $event))
        ->assertOk()
        ->assertSeeHtml('action="'.route('events.sign-up.store', $event).'"');

    actingAs($user)->from(route('events.show', $event))
        ->post(route('events.sign-up.store', $event))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('status', 'Je bent ingeschreven voor Mario Kart cup.');

    expect($event->participants()->pluck('users.id')->all())->toBe([$user->id]);
    actingAs($user)->get(route('events.show', $event))
        ->assertSeeText('Je bent ingeschreven voor dit event.')
        ->assertSeeText('3 / 4');
});

it('refuses a sign-up for an own, closed, full or started event', function (): void {
    $user = User::factory()->create();
    $ownEvent = Event::factory()->for($user, 'organizer')->create();
    $closedEvent = Event::factory()->closed()->create();
    $fullEvent = Event::factory()->hasAttached(User::factory()->count(2), [], 'participants')->create(['max_participants' => 2]);
    $startedEvent = Event::factory()->create(['starts_at' => now()->subHour()]);

    foreach ([
        [$ownEvent, 'Je organiseert dit event, dus je kunt je er niet voor inschrijven.'],
        [$closedEvent, 'De inschrijving voor dit event is gesloten.'],
        [$fullEvent, 'Dit event is vol.'],
        [$startedEvent, 'Dit event is al begonnen.'],
    ] as [$event, $message]) {
        actingAs($user)->post(route('events.sign-up.store', $event))
            ->assertSessionHasErrors(['sign_up' => $message]);
        expect($event->participants()->whereKey($user->id)->exists())->toBeFalse();
    }

    actingAs($user)->get(route('events.show', $closedEvent))
        ->assertSeeText('De inschrijving voor dit event is gesloten.')
        ->assertDontSeeHtml('action="'.route('events.sign-up.store', $closedEvent).'"');
});

it('refuses a second sign-up, also in the database itself', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create();

    actingAs($user)->post(route('events.sign-up.store', $event))->assertSessionHasNoErrors();
    actingAs($user)->post(route('events.sign-up.store', $event))
        ->assertSessionHasErrors(['sign_up' => 'Je bent al ingeschreven voor dit event.']);

    expect($event->participants()->count())->toBe(1)
        ->and(fn () => $event->participants()->attach($user))->toThrow(UniqueConstraintViolationException::class);
});

it('checks the last place against the current sign-ups, not a stale count', function (): void {
    $event = Event::factory()->create(['max_participants' => 1]);
    $staleEvent = Event::query()->withCount('participants')->findOrFail($event->id);
    $event->participants()->attach(User::factory()->create());

    expect($staleEvent->signUp(User::factory()->create()))->toBe('Dit event is vol.')
        ->and($event->participants()->count())->toBe(1);
});

it('sends a guest to the login page instead of signing up', function (): void {
    $event = Event::factory()->create();

    post(route('events.sign-up.store', $event))->assertRedirect(route('login'));

    expect($event->participants()->count())->toBe(0);
});
