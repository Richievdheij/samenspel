<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * Written by hand rather than by `pest --init`, deliberately.
 *
 * `pest --init` overwrites phpunit.xml. This project's phpunit.xml carries the
 * test database configuration, and losing it does not fail — the suite quietly
 * falls back to SQLite in memory and stays green while testing a different engine
 * than the one production runs. A green suite that proves the wrong thing is
 * worse than a red one.
 */

// Feature tests get the framework and a fresh schema per test. RefreshDatabase
// wraps each test in a transaction and rolls it back, so order never matters and
// one test cannot leave state for the next.
//
// withoutVite() is not optional here, and the reason is worth knowing: the layout
// calls @vite(...), which reads public/build/manifest.json. That file is a BUILD
// artefact, so on a fresh clone — and on every CI checkout — it does not exist
// when the suite runs, and every view-rendering test dies with a 500 and a
// ViteManifestNotFoundException. Stubbing the directive is Laravel's own answer:
// a test asserting on markup has no business requiring a production asset build.
// `make build` is what proves the manifest is actually produced.
uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

// Unit tests get the framework but no database. If a unit test needs a table, it
// is a feature test wearing the wrong hat.
uses(TestCase::class)->in('Unit');

// Browser tests get the framework and the database, and DELIBERATELY do not get
// withoutVite(). They are the only tests that load the real built assets, which is
// the entire reason `make browser` depends on `make build`.
uses(TestCase::class, RefreshDatabase::class)->in('Browser');
