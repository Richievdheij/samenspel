<?php

declare(strict_types=1);

/**
 * One browser smoke test, and only one.
 *
 * This is the only test that renders the page in a real browser, so it is the only
 * one that can see the class of failure the others cannot: a stylesheet that 404s,
 * a script that throws on load, markup that is valid PHP and broken HTML.
 *
 * It deliberately does NOT stub Vite. Every other feature test does — the manifest
 * is a build artefact and a test asserting on markup has no business requiring one
 * — but that leaves nothing checking that the built assets actually load. That is
 * exactly the defect this template shipped once: `make verify` ran `test` before
 * `build`, the manifest was missing, and every view test died with a 500. So
 * `make browser` depends on `make build`, and that dependency is the point of this
 * test rather than an inconvenience.
 *
 * Keep this file to ONE test. A browser suite that grows becomes the slow, flaky
 * part of the pipeline that people learn to re-run rather than read.
 */
it('serves the homepage in Dutch with its built assets and a clean console', function (): void {
    $page = visit('/');

    $page
        // A browser exposes no status code, so 200 is asserted the way a browser
        // can: the real page is present and Laravel's error page is not.
        // The Dutch lead sentence rather than config('app.name'): the app name is
        // whatever .env happens to say, while this sentence (the source string is
        // English) can only appear if lang/nl.json was actually loaded. It proves
        // the locale end to end and it is a string rather than mixed, which the
        // analyser is right to insist on.
        ->assertSee("Organiseer gaming-events en LAN-party's, en doe mee.")
        ->assertDontSee('Server Error')
        ->assertDontSee('Whoops')

        // The rendered <html lang>. APP_LOCALE could be nl while the layout still
        // emitted the fallback; only the served document settles that.
        //
        // Read through the DOM rather than assertAttribute('html', ...): the
        // element-locator helper does not resolve a bare `html` selector and waits
        // five seconds for something that is already there.
        ->assertScript('document.documentElement.lang', 'nl')

        // The compiled SCSS did not just get LINKED, it got APPLIED. A missing Vite
        // manifest entry renders a <link> to a 404 and every token falls back to
        // empty, which no server-side assertion and no HTML snapshot would notice.
        ->assertScript("getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim()", '#f0603a')
        ->assertScript('document.styleSheets.length > 0')

        // The header and the content fill the first screen and the footer starts
        // below it — a layout rule only a rendered page with real CSS can show.
        ->assertScript("Math.round(document.querySelector('.site-footer').getBoundingClientRect().top) >= window.innerHeight")

        // app.js is a module: a syntax error or a bad import surfaces here and
        // nowhere else in this suite.
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs()
        ->assertNoBrokenImages();
});
