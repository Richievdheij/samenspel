<?php

declare(strict_types=1);

/**
 * The authentication round trip in a real browser, as one test.
 *
 * The feature tests cover every controller decision; what they cannot see is the
 * part the browser does. The user menu is a <details> that app.js enhances, so
 * logging out means opening it first — a menu that never opens, or a script
 * that throws on the pages behind auth, fails here and nowhere else.
 *
 * Like HomepageSmokeTest, keep this to ONE test.
 */
it('registers, logs out, logs back in and opens the profile', function (): void {
    $page = visit('/register');

    $page
        ->type('name', 'Ada Lovelace')
        ->type('email', 'ada@example.com')
        ->type('password', 'a-long-password')
        ->type('password_confirmation', 'a-long-password')
        ->click('main form button[type="submit"]')
        ->assertPathIs('/dashboard')
        ->assertSee('Welkom terug')

        ->click('.dropdown__toggle')
        ->assertSee('ada@example.com')
        ->click('.dropdown__menu button[type="submit"]')
        ->assertPathIs('/')

        ->navigate('/login')
        ->type('email', 'ada@example.com')
        ->type('password', 'a-long-password')
        ->click('main form button[type="submit"]')
        ->assertPathIs('/dashboard')

        ->click('.dropdown__toggle')
        ->click('.dropdown__menu a')
        ->assertPathIs('/profile')
        ->assertValue('#name', 'Ada Lovelace')

        ->click('[commandfor="confirm-user-deletion"][command="show-modal"]')
        ->assertScript('document.getElementById("confirm-user-deletion").matches(":modal")')
        ->assertNoJavaScriptErrors();
});
