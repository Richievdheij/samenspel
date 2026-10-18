<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;

use function Pest\Laravel\actingAs;

/**
 * The status switch in a real browser: the feature tests cover the request, not
 * where its message lands. The search bar is pulled up against the page header,
 * and once slid over the flash message right above it on both screen sizes.
 */
it('closes sign-ups from the list and shows the message clear of the search bar', function (): void {
    $organiser = User::factory()->create();
    Event::factory()->for($organiser, 'organizer')->create(['title' => 'LAN night']);
    actingAs($organiser);

    $gapBetweenMessageAndSearch = 'document.querySelector(".event-search").getBoundingClientRect().top - document.querySelector(".alert").getBoundingClientRect().bottom';

    $page = visit('/events')
        ->click('button[aria-label="Sluit inschrijving voor LAN night"]')
        ->assertSee('De inschrijving voor LAN night is gesloten.')
        ->assertNoJavaScriptErrors();
    expect($page->script($gapBetweenMessageAndSearch))->toBeGreaterThanOrEqual(0);

    $mobile = visit('/events')->on()->mobile()
        ->click('button[aria-label="Heropen inschrijving voor LAN night"]')
        ->assertSee('De inschrijving voor LAN night is weer open.');
    expect($mobile->script($gapBetweenMessageAndSearch))->toBeGreaterThanOrEqual(0);
});
