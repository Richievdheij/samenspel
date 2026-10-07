# Agent guide

Laravel 13 on PHP 8.4. Blade + Vite + SCSS, no Tailwind, no frontend framework.
Pest 5, Larastan level 9, Pint for PHP layout, Prettier for everything else.

## Make is the front door

Run `make` on its own for every target with its description. Do not invent a
command a target already wraps — the Makefile is the single list, and CI calls the
same targets one per step.

| Need               | Command                                  |
| ------------------ | ---------------------------------------- |
| Set up a clone     | `make install`                           |
| Run it             | `make dev` · `make status` · `make stop` |
| Find the site URL  | `make status` — the `site` line          |
| Check your work    | `make verify` (about 7 seconds)          |
| Fix formatting     | `make format`                            |
| Apply refactorings | `make rector`                            |
| Everything CI runs | `make ci`                                |
| Redraw the ERD     | `make erd`                               |

**Evidence is pasted output.** "All checks pass" with no command, exit code or
count is not evidence.

## Branching

Nobody commits to `main`. Work on `develop`, or on a `feature/<name>` branch cut
from it. See [docs/branching.md](docs/branching.md).

## Commit grammar

`type(scope): subject` — lower case, imperative, no full stop, at most 90
characters. Types: feat, fix, docs, style, refactor, perf, test, build, ci, chore,
revert. This is the recommended form, not a gate: the commit-msg hook and
`make commit-messages` warn about a message written another way and still accept
it. A commit is never blocked over its wording.

Never credit an AI tool: no `Co-authored-by: Claude` trailer and no "Generated with
Claude Code" line, in commits or pull requests. The developer is the author of
every commit. Both entry points refuse such a message.

## Never suppress a gate

When a gate fails, the fix is the code or the rule. Never a `@phpstan-ignore`, a
regenerated baseline, an `eslint-disable`, a skipped test, `--no-verify`, a
lowered PHPStan level, or a widened glob — each turns a red gate green and leaves
the defect exactly where it was. If a finding genuinely is not a defect, add a
narrow exception **carrying its reason**: `ignoreErrors` in `phpstan.neon`,
`RUNTIME_ONLY` in `scripts/env.mjs`, `DECLARED_ONLY` in
`scripts/check-env-contract.mjs`, or a value-based entry in `.gitleaks.toml` —
never a path-based one.

## Locale

The app runs `nl` with an `en` fallback, in `Europe/Amsterdam`. User-facing text
goes through `__('English source string')`; the Dutch translation lives in
`lang/nl.json` and `lang/en.json` holds the same keys. A test asserts the two
match in both directions, so a string added to one and forgotten in the other
fails the build. Never hardcode Dutch in a template.

## More

Per-directory conventions are in `.claude/rules/`, loaded only when you touch that
directory. Why the toolchain looks like this: [docs/toolchain.md](docs/toolchain.md).
Laravel Boost's framework guidelines live in [CLAUDE.md](CLAUDE.md).
