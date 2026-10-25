<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelMissing;

it('lets an admin close any event from the list', function (): void {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->create(['title' => 'Spam party']);

    actingAs($admin)->get(route('events.index'))
        ->assertSeeHtml('action="'.route('events.toggle-status', $event).'"');
    actingAs($admin)->post(route('events.toggle-status', $event), ['status' => 'closed'])
        ->assertSessionHasNoErrors();

    expect($event->refresh()->status)->toBe(EventStatus::Closed);
});

it('lets an admin delete any event after a warning that it is someone else’s', function (): void {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->create();

    actingAs($admin)->get(route('events.show', $event))
        ->assertSeeHtml('commandfor="confirm-event-deletion"')
        ->assertSeeText('Je verwijdert het event van iemand anders, als admin.');
    actingAs($admin)->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('status', 'Het event is verwijderd.');

    assertModelMissing($event);
});

it('keeps editing for the organiser, also for an admin', function (): void {
    $admin = User::factory()->admin()->create();
    $event = Event::factory()->create();

    actingAs($admin)->get(route('events.edit', $event))->assertForbidden();
    actingAs($admin)->get(route('events.show', $event))->assertDontSeeHtml('href="'.route('events.edit', $event).'"');
});

it('does not warn the organiser that the event is someone else’s', function (): void {
    $organiser = User::factory()->admin()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();

    actingAs($organiser)->get(route('events.show', $event))
        ->assertDontSeeText('Je verwijdert het event van iemand anders, als admin.');
});
