<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;

it('deletes the event together with its sign-ups and labels, and keeps the people', function (): void {
    $organiser = User::factory()->create();
    $participant = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();
    $event->participants()->attach($participant);
    $event->platforms()->attach(Platform::query()->firstOrFail());

    actingAs($organiser)->delete(route('events.destroy', $event))
        ->assertRedirect(route('events.index'))
        ->assertSessionHas('status', 'Je event is verwijderd.');

    assertModelMissing($event);
    assertModelExists($participant);
    foreach (['event_user', 'event_game', 'category_event', 'event_platform'] as $pivot) {
        expect(DB::table($pivot)->where('event_id', $event->id)->count())->toBe(0);
    }
});

it('refuses a delete from other users, also through a direct request', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();

    delete(route('events.destroy', $event))->assertRedirect(route('login'));
    actingAs(User::factory()->create())->delete(route('events.destroy', $event))->assertForbidden();

    assertModelExists($event);
});

it('asks the organiser to confirm before the event is deleted', function (): void {
    $organiser = User::factory()->create();
    $event = Event::factory()->for($organiser, 'organizer')->create();
    $event->participants()->attach(User::factory()->count(2)->create());

    actingAs($organiser)->get(route('events.show', $event))
        ->assertOk()
        ->assertSeeHtml('commandfor="confirm-event-deletion"')
        ->assertSeeHtml('<dialog')
        ->assertSeeHtml('action="'.route('events.destroy', $event).'"')
        ->assertSeeHtml('name="_method" value="DELETE"')
        ->assertSeeText('Dit verwijdert het event definitief, samen met 2 inschrijvingen.');
    actingAs(User::factory()->create())->get(route('events.show', $event))
        ->assertOk()
        ->assertDontSeeHtml('confirm-event-deletion');
    get(route('events.show', $event))->assertDontSeeHtml('confirm-event-deletion');
});
