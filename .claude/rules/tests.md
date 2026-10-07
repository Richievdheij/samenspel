---
paths:
  - tests/**
  - phpunit.xml
description: Conventions for the Pest suites and the test environment.
---

# Tests

Pest 5, entered through `php artisan test` so Laravel's own environment handling
applies. Three suites:

| Suite     | Command     | Gets                                      |
| --------- | ----------- | ----------------------------------------- |
| `Unit`    | `make test` | The framework, no database                |
| `Feature` | `make test` | The framework and a fresh schema per test |
| `Arch`    | `make arch` | `arch()` rules, no application            |

A unit test that needs a table is a feature test wearing the wrong hat. Bindings
are in `tests/Pest.php`.

## Rules

- **A test asserts something that can fail.** `expect(true)->toBeTrue()` is not a
  test, and PHPStan reports it as a tautology.
- **A new test fails without the change and passes with it.** If you did not see
  it red, you do not know what it covers.
- **Never skip a test to make a gate green.** Fix the code or fix the test.
- **Use factory states**, so a test says what it means:
  `Post::factory()->draft()->create()` rather than a literal `null`.

## The test environment

Declared in `phpunit.xml` and nowhere else. A variable exported from a shell
arrives empty in some runners, and the specs that need it then skip — which reads
exactly like a pass.

**Do not run `pest --init`.** It overwrites `phpunit.xml`. Losing the database
configuration does not fail: the suite quietly falls back to SQLite in memory and
stays green while testing a different engine than production.

`tests/Feature/DatabaseConnectionTest.php` guards precisely that. It compares the
live driver against `EXPECTED_DB_DRIVER`, which is deliberately **not** declared in
`phpunit.xml` so it arrives from the process environment — `make integration` sets
it to `mysql`. Leave it alone; it is the reason the MySQL gate cannot silently
become a second SQLite run.
