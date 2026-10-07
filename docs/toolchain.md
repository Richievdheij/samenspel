# The toolchain

Why this repository is shaped the way it is, and — just as importantly — what it
deliberately does not do.

## The rule everything here is subordinate to

**After the first afternoon, nobody should have to think about the toolchain
again.** The moment a teammate is blocked by something only one person can fix,
the tooling costs more than it returns.

Five rules adopted with it:

1. No gate ships until a teammate has hit it, fixed it alone, and can say what it
   was for. If they need you, the gate's message is wrong — not their
   understanding.
2. Every failure names the command that fixes it. `make format` must clear the
   majority of `make format-check` failures.
3. Warn-only for two weeks, then hard-fail — and only in CI, never in front of a
   commit. A gate that has never been red for anyone but you has not earned the
   right to block.
4. The local gate is capped at two minutes and CI at five. Measured today:
   `make verify` 5s, `make ci` 8s.
5. Everyone adds one Makefile target in their first fortnight, however trivial.
   Someone who has edited a file will read it when it breaks.

The deadline escape hatch is agreed out loud, in advance: in the last 72 hours,
working-but-red beats blocked-and-clean, and nobody has to ask permission to say
so. `git push --no-verify` exists and is documented in the hook itself.

## The gate list

There is one list, in the [Makefile](../Makefile). CI calls its targets one per
step and never restates them; `scripts/check-ci-parity.mjs` refuses a difference
in either direction.

```
verify = commit-messages format-check checks lint analyse arch test build
ci     = verify integration audit secrets-scan
```

| Gate              | Tool                         | What it catches                                     |
| ----------------- | ---------------------------- | --------------------------------------------------- |
| `commit-messages` | scripts/check-commit-message | `type(scope): subject`, over the branch's range     |
| `format-check`    | Pint, Prettier, composer     | Layout, and an invalid or unsorted manifest         |
| `checks`          | scripts/check-\*.mjs         | The repository invariants — see below               |
| `lint`            | eslint (flat config)         | JavaScript correctness                              |
| `analyse`         | Larastan on PHPStan, level 6 | PHP types, and `env()` outside `config/`            |
| `arch`            | Pest `arch()`                | Naming and layering rules a compiler cannot see     |
| `test`            | Pest, SQLite in memory       | Behaviour                                           |
| `build`           | artisan caches + Vite        | That it builds offline, and every Blade file parses |
| `integration`     | Pest against MySQL           | That it works on the engine production runs         |
| `audit`           | composer audit, npm audit    | A vulnerable dependency                             |
| `secrets-scan`    | gitleaks, digest-pinned      | A secret anywhere in history                        |

`make lint` is the JavaScript half and `make analyse` is the PHP half. PHP has no
eslint-shaped tool and does not need one: what eslint's type-aware rules catch,
PHPStan catches, and what its stylistic rules catch, Pint fixes.

## The repository invariants

Each check prints its **coverage**, never "ok". `0 files` is visible and `ok` is
not, and a glob that silently stopped matching otherwise looks exactly like a
clean tree.

| Check                     | The failure it exists for                                                                                     |
| ------------------------- | ------------------------------------------------------------------------------------------------------------- |
| commit grammar self-test  | A grammar that stopped matching reports nothing, forever                                                      |
| `check-env-contract.mjs`  | A key in one template and not the other; a key nobody reads; a read with no default that no template declares |
| `check-env-usage.mjs`     | `env()` in a Blade template — null once the config is cached, with no error                                   |
| `check-migration-hygiene` | A missing `down()`, a duplicate timestamp, a migration filed where it never runs                              |
| `check-doc-links.mjs`     | Documentation pointing at a file that was renamed                                                             |
| `check-ci-parity.mjs`     | A gate CI never runs, or a step `make ci` does not                                                            |

## Decisions worth knowing

**Pest 5, not 4.** `composer.json` requires PHP `^8.4`, which is exactly Pest 5's
floor. Pinning Pest 4 would have bought PHP 8.3 compatibility the constraint had
already given up.

**PHPStan level 9, and no baseline.** Measured rather than assumed: levels 7, 8, 9
and 10 all reach zero errors on this tree, and getting there took exactly one real
fix -- `config/filesystems.php` called `rtrim()` on `env('APP_URL')`, which is
`bool|string` because Laravel's `env()` returns a bool for the literal strings
"true" and "false". It is narrowed with a fallback rather than cast: `(string) true`
is `"1"`, and the public disk would have served every file from `1/storage`.

9 rather than 10, deliberately. Both are clean today only because there is barely
any code yet; the difference shows in what they demand of code written later, and
level 10 treats every `mixed` as explicit -- which on a template other people build
on turns an ordinary `$request->input('x')` into a wall a teammate cannot climb.
Raising to 10 is one line in `phpstan.neon` and is free today. A day-one baseline
is an ignore comment applied to the whole repository, so there is none.

**`.env`, never `.env.local`.** Laravel loads `.env` unless `APP_ENV` is already
set in the process environment. `ENV_FILE` therefore takes a suffix only when the
environment is not local.

**APP_KEY is not a GENERATED value.** `php artisan key:generate` already owns
writing it. Registering it in `scripts/env.mjs` would give one value two writers,
overwriting each other on every run.

**`make integration` receives five variables, not the env file.** PHPUnit does not
overwrite a variable the process already has — which is why forcing
`DB_CONNECTION=mysql` works, and equally why sourcing `.env` would export
`APP_ENV=local` and silently defeat every setting in `phpunit.xml`. CSRF switches
back on and the POST half of the suite fails with 419.
`tests/Feature/DatabaseConnectionTest.php` fails loudly if the driver is not the
one asked for.

**`phpunit.xml` is hand-edited.** `pest --init` overwrites it. Losing the database
configuration does not fail: the suite quietly reverts to SQLite in memory and
stays green while testing a different engine than production.

**Vite runs with `strictPort`.** Without it a busy 5173 moves silently to 5174,
and you load a page served by one Vite against a manifest written by another — a
blank screen with nothing in any log.

**Laravel Boost is pinned EXACTLY, not with a caret.** `laravel/boost` is
`2.8.0` rather than `^2.8` because it is beta: a minor release can change the
guidelines it writes into AGENTS.md and CLAUDE.md, and a tool that edits the
repository's own instructions should not do so on an unreviewed upgrade. Bump it
deliberately with `composer require --dev laravel/boost:<version>` and read the
diff.

`php artisan boost:install` appends a guidelines block to BOTH AGENTS.md and
CLAUDE.md. AGENTS.md is kept under 60 lines by hand, so that block lives in
CLAUDE.md alone; `boost:update` will re-append it to AGENTS.md, and it should be
removed from there again.

**Boost's skill files are generated, and not tracked.** They were committed once and
that was a mistake: the vendored Laravel guidance includes a teaching example
holding a realistic-looking Stripe live key, and gitleaks scans HISTORY -- so one
commit of documentation put a permanent finding in the log that no later deletion
could remove. They are now gitignored like `vendor/`, boost.json records which
ones are wanted, and one command brings them back:

```bash
php artisan boost:install --skills
```

The alternative was a value-based allowlist entry, which the scanner supports and
this repository documents. Untracking beat it because it removes the finding
rather than agreeing to ignore it, and it also removed a second exception the
documentation-link check had needed for the same files. The cost is that a fresh
clone has no skill files until somebody runs that command.

**CI runs on ubuntu-latest only, and Windows means WSL2.** There was briefly a
second job on `windows-latest`, and it earned its keep before it was removed: it
found `ext-sockets` missing from the composite action's extension list, and it
found that the native Windows make spawns `cmd.exe` for every recipe line and
silently ignores a POSIX `SHELL` it cannot resolve. That second one has no clean
fix — `SHELL := bash.exe`, an absolute path, `MAKESHELL`, and MSYS2's make were
all tried and all failed identically.

So the job is gone. One runner OS halves the Actions minutes, make is native
there, and the platform it covered is not one anybody on this project develops on.
Windows contributors use WSL2, which _is_ Linux, so nothing here needs a special
case: no `ifeq ($(OS),Windows_NT)`, no netstat fallback, no `choco install make`.
All of it has been removed rather than left as dead code implying support that was
measured not to work.

What stays is the part that was never platform-specific: `.gitattributes` pinning
`eol=lf`, and `check-line-endings.mjs` catching a CRLF file the moment it is
committed, from whichever machine committed it.

**Pre-push, not pre-commit.** A gate costing minutes on "I want to save my work"
teaches people to stop committing, and the granular history is what a reviewer
reads.

## Deliberately not set up

Stated so the next person adds them **on purpose** rather than assuming they
exist.

Release tagging and deployment · container image publishing, scanning, SBOM and
provenance · an encrypted secret store · the dependency upgrade drill ·
build-output byte reproducibility · end-to-end browser tests · path-gated CI · a
licence gate · task-graph caching · automated dependency updates · generated API
documentation · queue and scheduler supervision.

Specific omissions with their reasons:

- **Containers (Phase J), skipped as a unit.** `docker=no`. There is no compose
  file, no bind mounts and no container targets. The way in is Sail, which is
  **not** a dependency here — so the command does not exist until you add it:

  ```bash
  composer require laravel/sail --dev
  php artisan sail:install
  ```

  Sail is about thirty lines of compose that every teammate can look up, which is
  why it beats a hand-written stack. `make secrets-scan` is the one gate that
  touches Docker, as a tool runner rather than a runtime, and it fails loudly
  rather than skipping when Docker is absent.

- **No automated dependency updates.** Dependabot was configured and then removed
  deliberately. On a private repository every one of its pull requests spends
  Actions minutes from a metered quota, and an update nobody reviews is worse than
  no update at all. This is NOT the same as being unprotected: `make audit` runs
  `composer audit --locked` and `npm audit --audit-level=high` on every CI run and
  fails on a vulnerable dependency, and `roave/security-advisories` stops a known
  vulnerable version from resolving in the first place. What is absent is the
  robot that opens the bump, not the gate that catches the problem. Upgrade with
  `composer update` and `npm update`, deliberately, and read the diff.
- **No `typecheck` target.** There is no TypeScript here, so there is no frontend
  type checker. A target that runs nothing and exits 0 reads as coverage on every
  future audit, so this is an omission rather than a faked slot. PHP's types are
  `make analyse`.
- **PHP 8.5 locally.** Requested, but Herd has no `php85` binary on this machine
  and the project permits no global installs. `^8.4` already admits 8.5, so
  nothing in the toolchain changes. To resume: install PHP 8.5 in Herd, then
  `herd isolate 8.5` in this directory.
- **No PHPat and no Deptrac.** Pest's `arch()` covers the naming and layering this
  layout actually relies on. Three rules written for a mistake you have seen beat
  forty-five inherited from another repository.
- **`GENERATED` and `OPTIONAL` in `scripts/env.mjs` are empty**, and truthfully so.
  A stock Laravel app has one secret of that shape — `APP_KEY` — and artisan owns
  it; every key the production template ships empty is genuinely required.
- **Accessibility is partially covered, not gated.** The layout ships a skip link,
  a visible focus ring, `aria-current`, labelled fields with
  `aria-invalid`/`aria-describedby`, and a reduced-motion rule. There is no
  automated WCAG check. If your assessment rubric grades WCAG, one Playwright
  viewport/a11y check is the cheapest thing you could add next.

## What "done" means

- [ ] `make verify` passes, and you have seen it pass.
- [ ] You have seen the change run in the application, not only in a test.
- [ ] A new test fails without your change and passes with it.
- [ ] No gate was widened, skipped, baselined or `--no-verify`'d to get there.
- [ ] Anything a future reader would ask "why?" about has the answer next to it.
- [ ] Names follow the conventions `make arch` enforces.
- [ ] Configuration is read through `config()`, not `env()`.
- [ ] Anything you decided not to do is written down, not silently dropped.
