<?php

declare(strict_types=1);

/*
 * The flash message: its icon, its text and the close button in its own
 * column, so the button sits in the top-right corner rather than under the text.
 */

it('puts the close button after the message, in its own column', function (): void {
    $this->blade('<x-alert variant="success" title="Gelukt">De e-mail is verstuurd.</x-alert>')
        ->assertSeeInOrder(['alert--dismissible', 'Gelukt', 'De e-mail is verstuurd.', 'class="alert__close"'], false)
        ->assertSee('Sluiten');
});

it('leaves the close button out when the message cannot be dismissed', function (): void {
    $this->blade('<x-alert :dismissible="false">Vast bericht</x-alert>')
        ->assertDontSee('alert__close', false)
        ->assertDontSee('alert--dismissible', false);
});
