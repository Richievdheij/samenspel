---
paths:
  - app/**
  - routes/**
  - database/**
  - config/**
description: Conventions for PHP under app, routes, database and config.
---

# PHP

`declare(strict_types=1)` at the top of every file — Pint adds it, so run
`make format` rather than typing it.

## Where code goes

| Concern                           | Home                                    |
| --------------------------------- | --------------------------------------- |
| Turning a request into a response | `app/Http/Controllers`                  |
| Validation                        | `app/Http/Requests`, a FormRequest      |
| Authorisation                     | `app/Policies`, auto-discovered by name |
| Queries and domain behaviour      | the model, or a dedicated action class  |
| JSON shape                        | `app/Http/Resources`                    |
| Configuration                     | `config/*.php`, read with `config()`    |

A controller action longer than a few lines is doing someone else's job.

## Rules the gates enforce

- **`env()` only in `config/`.** Larastan refuses it elsewhere. Once
  `php artisan config:cache` has run — and `make build` runs it — `env()` returns
  null everywhere else. Nothing errors; the value simply arrives empty.
- **`$fillable`, never `$guarded = []`.** Adding a column must not silently make
  it assignable from a request.
- **Every migration has a `down()`**, a unique timestamp, and lives in
  `database/migrations`.
- **Naming**: `*Controller`, `*Request`, `*Policy`, `*Resource`. Models are
  classes in `App\Models` extending Eloquent. `make arch` checks all of it.
- **No `dd`, `dump`, `var_dump`, `print_r`, `ray` or `exit`** anywhere.

## Level 6

PHPStan wants iterable value types: annotate `array` as `array<string, mixed>` or
`list<string>`. Add the annotation; do not widen a signature or add a baseline to
get past it.

## Adding an entity

Generate it, do not hand-write it: `php artisan make:model Post -mfsc --policy --requests`
gives the model, migration, factory, seeder, controller, policy and form requests
in one go, each in the directory this table names. Add the test in the same commit,
so the shape stays intact.

Then run `make erd` and commit `docs/erd.md` with the migration. The diagram is
drawn from the migrations, and `tests/Feature/ErdTest.php` fails while the two
disagree. Edit the prose around the `erd:start` and `erd:end` markers freely;
the block between them is rewritten.

`make arch` enforces the naming from the first class onwards — the directories are
present and empty on purpose.
