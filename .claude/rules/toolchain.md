---
paths:
  - Makefile
  - scripts/**
  - .github/**
  - .githooks/**
description: Conventions for the Makefile, the check harness, the hooks and CI.
---

# Toolchain

## Makefile

- **Recipe lines are tabs.** Spaces give `missing separator`, naming a line that
  looks fine.
- **Every target goes in `.PHONY`.** Left off, make compares the target against a
  same-named _directory_ and quietly does nothing — a green run that ran nothing.
  `test build config database routes public storage` are all real directories
  here.
- **Every target has a `## docstring`**, because `make` derives its help from
  them. Text after `—` becomes the dim second line; put the usage string there.
- `@$(log) "…"` and `@$(ok) "…"`. These are shell prefixes, not make functions:
  `$(call log,…)` splits on the first comma and dies on any message containing one.
- Destructive targets require `yes=1`; targets taking an argument guard it and
  print their own usage.
- Adding a gate means adding it to `verify` or `ci` **and** to the workflow.
  `make checks` refuses either half alone.

## The check harness

Every check uses `createCheck` from `scripts/lib/check.mjs`, so there is one
output format.

- **`finish(summary)` states coverage, never "ok".** `0 files` is visible; `ok` is
  not. A glob that silently stopped matching otherwise looks like a clean tree.
- **`hint` names where the convention is written down**, once, after the findings.
  A check that says a thing is wrong without saying where the rule lives produces
  an argument rather than a repair.
- **`process.exitCode`, never `process.exit(1)`.** On POSIX, stderr to a pipe is
  async, so `make checks 2>&1 | tee build.log` can lose every finding while still
  exiting non-zero — a red build with an empty reason.
- Walk `git ls-files`, not the filesystem: `vendor/` and `node_modules/` are then
  excluded by construction rather than by a list that drifts from `.gitignore`.
- An allowlist entry carries a **reason**, and is keyed on a file and a selector —
  never on a line number, which moves and then exempts something else.
- **Do not give one opinion two homes.** Larastan already owns `env()` outside
  `config/`, so `check-env-usage.mjs` owns only Blade, which PHPStan cannot see.

## Hooks

Tracked in `.githooks/`, armed by `make git-hooks` through `core.hooksPath`, so
the hook that runs is the hook in the diff. A new hook needs mode 100755 in the
**index**:

```bash
chmod +x .githooks/<name> && git update-index --chmod=+x .githooks/<name>
```

A hook at 644 is found and silently not run.

`core.hooksPath` is local configuration, so a clone that never ran `make install`
has no hook and is told nothing. Every hook therefore has a second entry point
that CI calls — that is what actually holds the line.

## CI

- **Every step is `run: make <target>`.** A raw command is invisible to the parity
  check, which is why the rule is enforced socially as well.
- **Pin every action to a 40-character SHA** with a `# vN` comment. A tag is
  mutable and these run with a token.
- `fetch-depth: 0` on any job reading history — the default clone is one commit
  deep.
- Cache keys include `github.job`; with one key per commit the first job to finish
  claims it and every later job saves nothing.
