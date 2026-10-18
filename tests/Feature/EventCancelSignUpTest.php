<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;

it('cancels a sign-up so the place becomes free again', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->create(['title' => 'Mario Kart cup', 'max_participants' => 1]);
    $event->participants()->attach($user);

    actingAs($user)->get(route('events.show', $event))
        ->assertSeeHtml('action="'.route('events.sign-up.destroy', $event).'"');

    actingAs($user)->from(route('events.show', $event))
        ->delete(route('events.sign-up.destroy', $event))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('events.show', $event))
        ->assertSessionHas('status', 'Je bent uitgeschreven voor Mario Kart cup. Je plek is vrijgekomen.');

    expect($event->participants()->count())->toBe(0);
    actingAs(User::factory()->create())->post(route('events.sign-up.store', $event))->assertSessionHasNoErrors();
});

it('only cancels an existing sign-up for an event that has not started', function (): void {
    $user = User::factory()->create();
    $notSignedUp = Event::factory()->create();
    $started = Event::factory()->create(['starts_at' => now()->subHour()]);
    $started->participants()->attach($user);

    actingAs($user)->delete(route('events.sign-up.destroy', $notSignedUp))
        ->assertSessionHasErrors(['sign_up' => 'Je bent niet ingeschreven voor dit event.']);
    actingAs($user)->delete(route('events.sign-up.destroy', $started))
        ->assertSessionHasErrors(['sign_up' => 'Dit event is al begonnen.']);

    expect($started->participants()->whereKey($user->id)->exists())->toBeTrue();
});

it('lets a participant of a closed event still cancel', function (): void {
    $user = User::factory()->create();
    $event = Event::factory()->closed()->create();
    $event->participants()->attach($user);

    actingAs($user)->delete(route('events.sign-up.destroy', $event))->assertSessionHasNoErrors();

    expect($event->participants()->count())->toBe(0);
});

it('sends a guest to the login page instead of cancelling', function (): void {
    $event = Event::factory()->create();

    delete(route('events.sign-up.destroy', $event))->assertRedirect(route('login'));
});
