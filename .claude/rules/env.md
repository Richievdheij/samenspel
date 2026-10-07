---
paths:
  - .env.example
  - .env.production.example
  - scripts/env.mjs
  - scripts/check-env-contract.mjs
description: The environment contract — how to add, change or remove a variable.
---

# The environment contract

The templates **are** the contract. `make env` renders a real env file from one;
`make checks` verifies the two against each other and against the source.

## Adding a variable

1. Add it to **both** templates. `make checks` refuses a key that exists in one
   and not the other — that asymmetry otherwise reads as "not needed there"
   instead of "forgotten".
2. Read it in `config/*.php` through `env('NAME', $default)`, and everywhere else
   through `config()`.
3. If it is genuinely optional in production, register it in `OPTIONAL` in
   `scripts/env.mjs` **with the reason**. Otherwise it is required, automatically:
   declared-with-an-empty-value is the predicate, and it is fail-closed, so a
   secret added tomorrow is required of production with nobody remembering to
   register it.
4. If it is a port, it must be a `*_PORT` key. `make env` is **fatal** on a
   loopback port inside any value that no `*_PORT` declares — convergence adds
   keys and never overwrites, so moving a port in a template would otherwise leave
   every existing checkout pointing at the old one.

## Rules that bite

- **Never put a comment after a value.** The file is sourced as shell; a trailing
  `# …` is folded into the value and you get a URI with a sentence glued to it.
  Explanations go on the lines above.
- **Never ship an example value for something meant to be generated.** Only an
  _empty_ value is ever filled, so a fresh clone would inherit `changeme` as
  though somebody had chosen it. `APP_KEY` is empty on purpose.
- **`APP_KEY` is not in `GENERATED`.** `php artisan key:generate` already owns it;
  two writers for one value overwrite each other on every run.
- **Derived values are overwritten every run** and are the single exception to
  "never overwrite". They are calculations, so nothing anyone typed is lost — and
  a sticky one would leave a rotated password's URLs holding the old value.
- Most derivation belongs in `config/*.php` instead, where it is recomputed on
  every boot and cannot go stale. `DERIVED` is only for values that must
  physically exist in the file.

## The three failures the check reports

| Finding                          | What to do                                                    |
| -------------------------------- | ------------------------------------------------------------- |
| declared in one template only    | Add it to the other, or register it in `DEV_ONLY`/`PROD_ONLY` |
| declared but nothing reads it    | Remove it, or add it to `DECLARED_ONLY` with the reason       |
| read with no default, undeclared | Declare it in both templates, or add it to `RUNTIME_ONLY`     |

Every exception is a named entry with a reason string. An exception without one is
indistinguishable next year from an unpaid debt.
