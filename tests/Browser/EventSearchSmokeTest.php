<?php

declare(strict_types=1);

it('shows and uses the search reset while typing before submitting', function (): void {
    visit('/events')
        ->type('[data-search-input]', 'Mario')
        ->assertVisible('[data-search-reset]')
        ->click('[data-search-reset]')
        ->assertValue('[data-search-input]', '')
        ->assertNoJavaScriptErrors();
});
