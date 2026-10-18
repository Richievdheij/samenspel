<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('lets the organiser close and reopen sign-ups from the event list', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create(['title' => 'Smash bros avond']);

    actingAs($organiser)->get(route('events.index'))
        ->assertOk()
        ->assertSeeHtml('action="'.route('events.toggle-status', $event).'"')
        ->assertSeeHtml('name="status" value="closed"')
        ->assertSeeHtml('aria-label="Sluit inschrijving voor Smash bros avond"');

    actingAs($organiser)->from(route('events.index', ['page' => 1]))
        ->post(route('events.toggle-status', $event), ['status' => 'closed'])
        ->assertRedirect(route('events.index', ['page' => 1]))
        ->assertSessionHas('status', 'De inschrijving voor Smash bros avond is gesloten.');
    expect($event->refresh()->status)->toBe(EventStatus::Closed);

    actingAs($organiser)->get(route('events.index'))
        ->assertSeeHtml('name="status" value="open"')
        ->assertSeeText('Gesloten');
    actingAs($organiser)->post(route('events.toggle-status', $event), ['status' => 'open'])
        ->assertSessionHas('status', 'De inschrijving voor Smash bros avond is weer open.');
    expect($event->refresh()->status)->toBe(EventStatus::Open);
});

it('keeps the status when the same switch is sent twice', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();

    actingAs($organiser)->post(route('events.toggle-status', $event), ['status' => 'closed']);
    actingAs($organiser)->post(route('events.toggle-status', $event), ['status' => 'closed']);

    expect($event->refresh()->status)->toBe(EventStatus::Closed);
});

it('lets only the organiser switch the status, and only through a POST', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();
    $stranger = User::factory()->admin()->create();

    post(route('events.toggle-status', $event), ['status' => 'closed'])->assertRedirect(route('login'));
    actingAs($stranger)->post(route('events.toggle-status', $event), ['status' => 'closed'])->assertForbidden();
    actingAs($organiser)->get(route('events.toggle-status', $event))->assertMethodNotAllowed();
    actingAs($stranger)->get(route('events.index'))->assertDontSeeHtml(route('events.toggle-status', $event));
    get(route('events.show', $event))->assertDontSeeHtml(route('events.toggle-status', $event));

    expect($event->refresh()->status)->toBe(EventStatus::Open);
});

it('refuses a status that does not exist', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();

    actingAs($organiser)->post(route('events.toggle-status', $event), ['status' => 'full'])
        ->assertSessionHasErrors('status');

    expect($event->refresh()->status)->toBe(EventStatus::Open);
});

it('offers the same switch on the event page', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->closed()->create();

    actingAs($organiser)->get(route('events.show', $event))
        ->assertOk()
        ->assertSeeHtml('action="'.route('events.toggle-status', $event).'"')
        ->assertSeeHtml('name="status" value="open"');
});
