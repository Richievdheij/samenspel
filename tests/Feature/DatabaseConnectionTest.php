<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * The guard against the quietest failure in this whole toolchain.
 *
 * phpunit.xml pins DB_CONNECTION=sqlite for the ordinary suite. If `make
 * integration` ever stops overriding that — because phpunit.xml was regenerated,
 * or because a runner forces its own value — the MySQL gate silently runs against
 * SQLite instead. It stays green. It proves nothing. Nobody finds out until
 * production meets a query SQLite accepted and MySQL does not.
 *
 * EXPECTED_DB_DRIVER is deliberately a variable phpunit.xml does NOT declare, so
 * it reaches this test straight from the process environment. `make integration`
 * sets it to mysql; everywhere else it defaults to sqlite.
 */
it('runs against the database the environment asked for', function (): void {
    // getenv(), not env(): the PROCESS environment is precisely what is being
    // asserted on here. Laravel's env() helper would be the wrong tool as well as
    // the wrong layer — it returns null once the config is cached, which is why
    // Larastan forbids it outside config/.
    $expected = getenv('EXPECTED_DB_DRIVER') ?: 'sqlite';

    expect(DB::connection()->getDriverName())->toBe($expected);
});

/**
 * The second half of the same guard, and it has already failed once.
 *
 * config/database.php was briefly hardcoded to database_path('database.sqlite'),
 * which made phpunit.xml's :memory: setting dead configuration. The suite went on
 * passing while running against the developer's real on-disk database — slower,
 * and mutating local data. Nothing reported it, because a green suite that ran
 * somewhere unexpected looks exactly like a green suite.
 */
it('never runs against the developer database', function (): void {
    $connection = DB::connection();

    if ($connection->getDriverName() === 'sqlite') {
        expect($connection->getDatabaseName())->toBe(':memory:');
    } else {
        expect($connection->getDatabaseName())->not->toBe(database_path('database.sqlite'));
    }
});
