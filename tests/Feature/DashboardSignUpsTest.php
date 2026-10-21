<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists the upcoming sign-ups soonest first and the earlier ones apart', function (): void {
    $user = User::factory()->create();
    $later = Event::factory()->create(['title' => 'Later LAN', 'starts_at' => now()->addWeeks(3)]);
    $sooner = Event::factory()->closed()->create(['title' => 'Sooner LAN', 'starts_at' => now()->addDays(2)]);
    $past = Event::factory()->create(['title' => 'Old LAN', 'starts_at' => now()->subWeek()]);
    Event::factory()->create(['title' => 'Not joined LAN']);
    $user->joinedEvents()->attach([$later->id, $sooner->id, $past->id]);

    actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Mijn inschrijvingen (2)', 'Sooner LAN', 'Later LAN', 'Eerdere inschrijvingen', 'Old LAN'])
        ->assertSeeHtml('href="'.route('events.show', $sooner).'"')
        ->assertSeeText('Gesloten')
        ->assertDontSeeText('Not joined LAN');
});

it('points to the events when there are no upcoming sign-ups', function (): void {
    actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText('Je bent nog niet ingeschreven voor een komend event.')
        ->assertSeeHtml('href="'.route('events.index').'"');
});

it('shows the organise counter from the create page, counted with the same query', function (): void {
    $user = User::factory()->create();
    $user->joinedEvents()->attach(Event::factory()->count(2)->create());

    expect($user->signUpsForOtherOrganisersCount())->toBe(2);
    actingAs($user)->get(route('dashboard'))
        ->assertSeeText('Je hebt je ingeschreven voor 2 van de 3 events van anderen die je nodig hebt.')
        ->assertSeeText('Schrijf je nog in voor 1 event.')
        ->assertDontSeeHtml('href="'.route('events.create').'"');
    actingAs($user)->get(route('events.create'))
        ->assertSeeText('Je hebt je ingeschreven voor 2 van de 3 events van anderen die je nodig hebt.');

    $user->joinedEvents()->attach(Event::factory()->create());
    actingAs($user)->get(route('dashboard'))
        ->assertSeeText('Je mag nu zelf events organiseren.')
        ->assertSeeHtml('href="'.route('events.create').'"');
});

it('keeps the dashboard for signed-in users', function (): void {
    get(route('dashboard'))->assertRedirect(route('login'));
});
