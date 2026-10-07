# TOOLCHAIN-BOOTSTRAP.md

**What this is.** A portable, executable specification for a repository toolchain: one
`make` front door, one gate list that CI calls instead of restating, an environment
contract, a repo-invariant check harness, a tracked commit hook, optional containers, and
the agent-context files. Every piece below was extracted from a working system
(`FiksUp-sites`, a pnpm/turbo monorepo) where each one exists because a specific incident
already happened. The incidents are recorded so you can decide whether you have the wound
before you copy the bandage.

**How to use it.** Drop this file into the root of the target repository and give an agent
one instruction: _"Execute TOOLCHAIN-BOOTSTRAP.md."_ It is executed top to bottom, never
skimmed for ideas. Fill in §1 first; everything after that is deterministic.

**It is not PHP-specific.** The mechanism is language-neutral. §3 is an adapter table with
fourteen slots; a stack is a filled-in column. `php-laravel` and `node-typescript` are
filled in already. Adding Python, Go, Rust or Java means filling fourteen cells, not
rewriting the document. If a slot cannot be filled for your stack, that is the honest
answer and it goes in §11 as a stated omission.

---

## 0. Execution contract

Rules that bind the executing agent. These are prohibitions, not advice.

1. Every task has an **id**, a read-only **precondition probe**, an **action**, and an
   **acceptance check** whose output must be pasted. A task whose acceptance check fails
   is not done.
2. **No task may be reordered.** The order in §5 is a dependency order, not a category
   order. Reordering is how hooks end up armed after the commits they were meant to check,
   and how a CI file ends up naming targets that do not exist.
3. Nothing outside the files listed in §6 may be edited.
4. **No commit and no push** unless §1's permission answer says so.
5. When a step cannot be completed it goes on the **Blocked list** with its exact resume
   command. It is never approximated and never silently skipped.
6. **Evidence is pasted output.** "All checks pass" with no command, exit code or count is
   not evidence. An agent that cannot paste it did not run it.
7. When a gate the agent itself added fails, the fix is the code or the rule — never a
   lint-disable, never a skipped test, never `--no-verify`, never a widened glob to match
   whatever the tree happens to contain. Each of those turns a red gate green while
   leaving the defect in place.
8. A gate whose tool is **absent** must fail loudly, not pass. It is excluded from
   `verify` and recorded in §11 as declared-but-unproven.

---

## 1. Answers block — fill this in before touching the tree

Ten answers. Write them to `.bootstrap/answers.json` so a second run asks nothing. In a
non-TTY session with no answers file, **print this block and stop** — do not guess.

```
project_name      = <slug>          # Makefile banner, compose project name, default DB name
stack_profile     = php-laravel     # php-laravel | node-typescript | <new, see §3.4>
package_manager   = npm@10          # decides install cmd, frozen flag, lockfile name
monorepo          = no              # no = one manifest at the root (see §3.5)
docker            = yes             # yes | no | later — switches Phase J on/off as a unit
environments      = local,production
ci                = github-actions  # decides workflow path + what the parity check parses
base_branch       = main
commit_grammar    = type(scope): subject   # + issue prefix? see §6.9
contributor_os    = macos,wsl2      # decides whether make may be assumed at all
permissions       = commit-per-phase, no-push, no-global-installs
```

**Why each is asked before the first write:** a decision taken at task 30 invalidates
tasks 1 through 29. `project_name` in particular: changing it later renames a Docker
project and orphans its volumes.

**On `contributor_os`.** If anyone is on native Windows without WSL2, decide now: either
mandate WSL2 (which you want anyway for Docker) or accept `choco install make` / Git Bash.
Do **not** solve it by mirroring every target into package scripts — that recreates the
exact drift the parity check exists to prevent. If you need a language-side entry point,
make it exactly one line: `"verify": "make verify"`.

---

## 2. Preflight probe (read-only, writes nothing)

Run all of these and print a table with one row per artefact: present / absent / version.
Every later decision is create-vs-converge, and that decision is made here, once.

```bash
git rev-parse --is-inside-work-tree; git status --porcelain; git ls-files | wc -l
git config core.hooksPath
make --version; awk --version 2>/dev/null || awk -W version
node --version; npm --version; pnpm --version 2>/dev/null
php --version; composer --version
docker --version; docker compose version
ls -la Makefile .editorconfig .gitattributes .env.example .github/workflows \
      scripts .githooks docker CLAUDE.md .claude 2>/dev/null
```

**Minimum requirements for the language-neutral half:** GNU make (macOS ships 3.81 — every
construct in §6 works there; nothing here uses `.ONESHELL`, which 3.81 lacks), `awk`, `git`
2.9+ (for `core.hooksPath`), and one scripting runtime for the check harness (Node 20+ is
assumed below; §3 slot 13 lets you swap it).

**Dirty tree ⇒ stop and ask.** The agent cannot tell an unfinished experiment from a
deliberate state, and converging files under uncommitted changes makes the diff unreadable
and the rollback unusable.

---

## 3. Stack profile — the adapter table

**This is the section that makes the document reusable.** Everything else in this file is
written against the fourteen slot names below. A stack is a filled-in column.

### 3.1 The fourteen slots

| #   | Slot                | What it must answer                                        | Used by       |
| --- | ------------------- | ---------------------------------------------------------- | ------------- |
| 1   | `INSTALL`           | Install dependencies, frozen when a lockfile exists         | Phase B       |
| 2   | `FORMAT` / `FMT_CK` | Rewrite layout / check layout, exit non-zero on findings     | Phase E       |
| 3   | `LINT`              | Correctness rules that are not types                        | Phase F       |
| 4   | `TYPECHECK`         | Types, or the nearest static-analysis equivalent             | Phase F       |
| 5   | `TEST`              | Unit + feature suite, must report a **count**                | Phase G       |
| 6   | `TEST_DB`           | The same suite against a real database                       | Phase K, CI   |
| 7   | `BUILD`             | Produce the shippable artefact, **offline**                  | Phase K       |
| 8   | `ARCH`              | Layer/boundary rules the compiler cannot see                 | Tier 2        |
| 9   | `AUDIT`             | Fail on a vulnerable dependency                              | Tier 1        |
| 10  | `ENV_FILE`          | The real env filename per environment                        | Phase C, D    |
| 11  | `ENV_READ`          | The regex for "this source reads an env var"                 | Phase H       |
| 12  | `BUILD_ENV`         | Placeholder values so `BUILD` needs no infra                 | Phase C       |
| 13  | `SCRIPT_RUNTIME`    | What the check harness is written in                         | Phase H       |
| 14  | `DEV_SERVERS`       | name → `{command, port}` for the dev stack                   | Tier 1        |

### 3.2 Profile: `php-laravel`

Versions marked **(v)** were verified against current sources during extraction; the rest
are marked in §13 as "check before pinning".

| Slot             | Value                                                                                                                        |
| ---------------- | ---------------------------------------------------------------------------------------------------------------------------- |
| `INSTALL`        | `composer install --no-interaction --prefer-dist` (+ `npm ci`)                                                                |
| `FORMAT`         | `vendor/bin/pint` + `npx prettier --write .`                                                                                  |
| `FMT_CK`         | `vendor/bin/pint --test` + `npx prettier --check .` + `composer validate --strict`                                            |
| `LINT`           | — (PHPStan **is** PHP's lint; see note) + `npx eslint .`                                                                      |
| `TYPECHECK`      | `vendor/bin/phpstan analyse --memory-limit=1G --no-progress` + `npx tsc --noEmit`                                             |
| `TEST`           | `php artisan test --parallel`                                                                                                 |
| `TEST_DB`        | `php artisan migrate --force && php artisan test --group=database`                                                            |
| `BUILD`          | `php artisan config:cache route:cache view:cache && npm run build`, then `php artisan config:clear`                           |
| `ARCH`           | Pest `arch()` (naming/shape) · PHPat (type-aware boundaries, rides PHPStan) · Deptrac (layer graph + mermaid diagram)          |
| `AUDIT`          | `composer audit --locked` + `npm audit --audit-level=high`                                                                    |
| `ENV_FILE`       | **`.env`** for local, `.env.production` for production — see the trap below                                                   |
| `ENV_READ`       | `/env\(\s*['"]([A-Z][A-Z0-9_]{2,})['"]/` and `/\$_ENV\[['"]…['"]\]/` over `app/ config/ bootstrap/ routes/ database/`         |
| `BUILD_ENV`      | `APP_ENV=production APP_DEBUG=false APP_KEY=$${APP_KEY:-base64:AAAA…AAA=} DB_CONNECTION=$${DB_CONNECTION:-sqlite}`            |
| `SCRIPT_RUNTIME` | Node (you already have it for the frontend; a second harness in PHP buys nothing and costs a second output format)            |
| `DEV_SERVERS`    | `{ app: 'php artisan serve' :8000, queue: 'php artisan queue:work', vite: 'npm run dev' :5173 }`                              |

**Slot 3 note.** PHP has no eslint-shaped tool and does not need one: what eslint's
type-aware rules catch, PHPStan catches; what its stylistic rules catch, Pint fixes. Skip
PHP_CodeSniffer (overlaps Pint — two tools with an opinion about the same lines) and PHPMD
(produces merely-descriptive findings nobody can pick up). Consequence: `make lint` is
JS-only and `make analyse` is the PHP half. Say so in the Makefile comment.

**Slot 10 trap — this one bites immediately.** The source repo uses
`ENV_FILE := .env.$(ENV)`. Laravel loads `.env`, not `.env.local`, unless `APP_ENV` is
already set in the process environment. Import the Node scheme blindly and you get a
happily-created `.env.development`, a green `make verify` (the guard target existed), and
`php artisan` reading **no configuration at all**. Use:

```make
ENV      ?= local
ENV_FILE := .env$(if $(filter local,$(ENV)),,.$(ENV))
```

**Nine more PHP-specific decisions, each with its reason:**

- **Pint, not php-cs-fixer.** Pint _is_ php-cs-fixer with a Laravel ruleset. Installing
  both recreates the eslint-vs-prettier fight. Presets: `laravel, per, psr12, symfony,
  empty`; config is `pint.json`. `--test` is the CI form, `--dirty` the pre-commit form.
- **PHPStan + Larastan, not Psalm.** Not a maintenance claim — Larastan understands
  Eloquent's magic and container bindings, and decisively, **PHPat runs as a PHPStan
  extension**, so choosing Psalm costs you the architecture tool too. Never run both:
  two tools with overlapping opinions and separate baselines is how a gate stops being
  trusted.
- **Start at PHPStan level 5–6, land on 8.** PHPStan 2.x recalibrated the levels and added
  level 10. Level 6 (missing iterable value types) is the first genuinely painful step on
  student code — hundreds of `array` findings. **Do not generate a baseline on a greenfield
  project**; a day-one baseline is `@ts-ignore` at scale.
- **PHPStan will OOM** on a Laravel app at the default `memory_limit`. The message is a
  fatal allocation error, not a lint failure, and it looks like a broken install. Always
  pass `--memory-limit=1G`. And Larastan **boots the application**, so it fails on a runner
  with no `APP_KEY` — the CI step needs env, not just `make analyse`.
- **Pest over PHPUnit**, and keep `php artisan test` as the entry point so Laravel's
  `.env.testing` handling applies. **Version tension:** Pest v5 requires PHP 8.4; if 8.3 is
  your floor you are on the Pest 4 line — fine, `arch()` is fully present there. Decide the
  PHP floor *before* pinning Pest.
- **`pest --init` overwrites `phpunit.xml`.** Run it before you write database env into
  that file, or the config disappears and the suite silently reverts to SQLite `:memory:`
  — which passes, while testing a different engine than production.
- **Parallel test databases come free.** `php artisan test --parallel` (paratest) creates
  and drops per-worker `*_test_N` databases. That is the honest Laravel equivalent of the
  source repo's `test_<uuid>` wrapper; do not build the wrapper.
- **`env()` returns `null` outside `config/` once `config:cache` has run.** Nothing errors
  — the value just arrives empty. Enforce it structurally with one ast-grep rule
  (`pattern: env($$$A)`, `language: Php`, `ignores: ['config/**']`). And **never run
  `config:cache` during a Docker image build**: it bakes build-time env into the image, so
  every runtime `APP_URL`/`DB_HOST` override is ignored and the app connects to nothing
  with a perfectly healthy container. Cache at container start.
- **Composer *can* pin a transitive dependency.** (Correcting a common claim.) The resolver
  is flat and global: requiring the transitive package directly in the root `composer.json`
  pins it everywhere, and a root `conflict` entry excludes a bad range. What does *not*
  exist is per-major pinning (`pkg@1` / `pkg@2`) — because two majors cannot coexist, that
  whole class of problem is absent. Add `roave/security-advisories:dev-latest` as a
  conflict-only dev requirement so a vulnerable version cannot even resolve.

### 3.3 Profile: `node-typescript` (the source repo)

| Slot        | Value                                                                                    |
| ----------- | ---------------------------------------------------------------------------------------- |
| `INSTALL`   | `pnpm install --frozen-lockfile`                                                          |
| `FORMAT`    | `prettier --write .` / `FMT_CK`: `prettier --check .`                                     |
| `LINT`      | `eslint` flat config, tiered (see §6.6)                                                   |
| `TYPECHECK` | `tsc --noEmit` / `vue-tsc --noEmit`                                                       |
| `TEST`      | `vitest run`                                                                              |
| `BUILD`     | `turbo run build` with `BUILD_ENV` placeholders                                           |
| `ARCH`      | ast-grep rules + `eslint-plugin-boundaries`                                               |
| `AUDIT`     | `pnpm audit --audit-level=high`                                                           |
| `ENV_FILE`  | `.env.$(ENV)`                                                                             |
| `ENV_READ`  | `process.env.X` + aliased `env.X`, narrowed to files that also mention `process.env`      |

### 3.4 Adding a third profile

Fill the fourteen cells. Sketches, to show the shape:

- **python**: `uv sync --frozen` · `ruff format` / `ruff format --check` · `ruff check` ·
  `mypy --strict` · `pytest -q` · `python -m build` · `import-linter` · `pip-audit` ·
  `.env` · `os.environ\[["'](\w+)` and `os.getenv\(`
- **go**: `go mod download` · `gofumpt -l -w .` / `-l .` · `golangci-lint run` · `go vet`
  (types are the compiler) · `go test ./... -count=1` · `go build ./...` ·
  `go-arch-lint` · `govulncheck ./...` · `.env` · `os.Getenv\("(\w+)"\)`
- **rust**: `cargo fetch --locked` · `cargo fmt` / `cargo fmt --check` · `cargo clippy
  -- -D warnings` · `cargo check` · `cargo test` · `cargo build --release` ·
  `cargo-deny` · `cargo audit` · `.env` · `env::var\("(\w+)"\)`
- **java/spring**: `./mvnw -B dependency:go-offline` · `spotless:apply` /
  `spotless:check` · `./mvnw checkstyle:check` · compiler · `./mvnw test` ·
  `./mvnw package -DskipTests` · ArchUnit · `./mvnw dependency-check:check` ·
  `application-<env>.properties` · `@Value\("\$\{([\w.]+)`

**Rule for a new profile:** a slot you cannot fill is an **omission you state in §11**, not
a slot you fake. A `LINT` target that runs nothing and exits 0 reads as coverage on every
future audit.

### 3.5 Monorepo: the default answer is no

A workspace earns its place only when two or more packages **build for each other** — an
emit package consumed by two apps, a design system used by web and mobile, a contracts
package crossing a framework boundary. A Laravel app already _is_ the monorepo: one
`composer.json`, one root `package.json`, PHP in `app/`, TS in `resources/js/`, Vite
building into `public/build`. That is one deployable with two toolchains, which is exactly
the case a workspace does not help.

The cost is concrete, not theoretical: moving the frontend into `packages/web/` means
overriding `laravel-vite-plugin`'s `input`, `buildDirectory` and `hotFile`, teaching
`@vite` where the manifest moved, and re-deriving all of it whenever someone follows a
Laravel doc that assumes the default. Hours of friction, bought for a caching layer with
nothing to cache.

**Skip the task runner (turbo/nx) too**, for the same reason. Its value is a task graph
across packages; with one build and no graph you get a config file, a cache-key trap and
zero speedup. The one turbo lesson that *does* transfer is its strict-env-mode failure
shape: a variable that silently arrives empty and makes integration specs skip — which
reads as a pass. Declare test env in `phpunit.xml`, never via a shell export.

---

## 4. The catalogue — every piece, what it does, and whether you want it

Forty-two pieces from the source system, each with a tier. **T0** = first afternoon.
**T1** = first weekend. **T2** = only once a defect has happened twice. **✗** = do not
port, with the reason.

### 4.1 The Makefile front door

| Piece                                                                         | What it does                                                                                      | Tier |
| ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ---- |
| `.DEFAULT_GOAL := help` + two-pass awk help block                             | `make` alone prints grouped, column-aligned help derived from `##` docstrings — cannot drift        | T0   |
| `##@ Group` headers and the ` — ` detail split                                | Groups the target list; text after ` — ` becomes a dim second line carrying the usage string        | T0   |
| `log` / `ok` as **printf variables**, used `@$(log) "text"`                   | `$(call log,…)` splits on the first comma; any message containing one dies naming no target         | T0   |
| `ENV ?= …` / `ENV_FILE := …` + `$(if $(filter production,$(ENV)),…)`          | One override switches env file, compose service and run mode in a single place                      | T0   |
| `$(ENV_FILE):` as a **create-if-missing file target**                         | ~30 targets can declare `target: $(ENV_FILE)`. Never `real: template` — see §12.4                   | T0   |
| In-recipe argument guards `@[ -n "$(x)" ] \|\| { …; exit 1; }`                | A mistyped argument costs a second, not a container start, and the usage string is where you are    | T0   |
| `yes=1` on anything destructive                                               | `make fresh yes=1` around `migrate:fresh`, which drops every table                                  | T0   |
| `$(if $(x),--flag $(x),--all)` / `$(or $(app),default)` threading             | Optional CLI flags without a second target per variation                                            | T0   |
| `.PHONY` naming **every** target                                              | `test build docs config database routes public storage` are all real Laravel dirs — see §12.1       | T0   |
| Meta-targets `verify` (no service) and `ci` (= verify + service-needing)      | The single list. Ordering is cheapest-first                                                         | T0   |
| Conditional prerequisite `$(if $(TEST_DB_URI),,up)`                           | One target starts compose on a laptop and uses a CI service container otherwise                     | T1   |
| `$(MAKE) --no-print-directory <t>` recursion + `cmd \|\| $(MAKE) … diagnose`  | Turns "bind: address already in use" into "which process, which variable moves it"                  | T2   |
| `flock $(BUILD_SLOT)` around a build                                          | Only when one machine hosts both the CI runner and the deploy                                       | ✗    |

### 4.2 The gate list and CI

| Piece                                                            | What it does                                                                                     | Tier |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ---- |
| Every CI step is `- run: make <target>`, nothing else            | CI owns which runner / service / `if:`; the Makefile owns the list                                 | T0   |
| `scripts/check-ci-parity.mjs`                                    | ~60 lines of regex over two text files; refuses a difference in **either** direction               | T2   |
| Composite setup action reading the manager version from manifest | Four jobs, identical setup, one edit to bump; lockfile and CI cannot disagree about the version    | T1   |
| Path gating (`ci-scope.mjs`) with `code` = **not** documentation  | An allowlist of source dirs silently skips CI for a dir nobody added; the complement fails safe    | T2   |
| Three-dot `git diff base...HEAD` + `fetch-depth: 0`               | Two dots attributes every base-branch commit to your branch; depth-1 has neither sha               | T1   |
| Empty/unreadable diff ⇒ **every** scope true                     | A wrong "yes" costs minutes; a wrong "no" is a green PR that was never checked                     | T1   |
| Cache key including `${{ github.job }}` + cascading restore-keys  | One key per commit ⇒ the first job to finish claims it and every later job saves nothing            | T1   |
| Actions pinned to a 40-char SHA with a `# vN` comment            | A tag is mutable and CI runs with a token                                                          | T1   |
| `concurrency: cancel-in-progress: true` on CI, **false** on release | Five pushes cost one run; but cancelling a release matrix leaves a half-published version         | T1   |
| Top-level `permissions: contents: read`                          | An unstated block inherits the repo default, commonly read-write                                   | T0   |
| DB-backed tests in their **own job** with a service container    | Specs that need a DB skip where there is none, and a skip reads exactly like a pass                | T1   |
| `lint`/`analyse` as a separate job from `build`                  | Serially they sit in front of a build that depends on neither; splitting costs the longest job     | T1   |
| Notify job (`needs:` everything, `if: always()`, webhook via `env:`) | One message per run; post only on failure so a line in the channel means something               | T2   |
| Renovate/Dependabot, weekly, `dependencyDashboardApproval` for majors | Group `laravel/* illuminate/* symfony/*` so the framework moves as one PR                       | T2   |

### 4.3 The check harness

| Piece                                                     | What it does                                                                                | Tier |
| --------------------------------------------------------- | ------------------------------------------------------------------------------------------- | ---- |
| `scripts/lib/check.mjs` — `createCheck` / `pass` / `fail` | 83 lines; one output format for every invariant script                                        | T1   |
| `finish(summary)` states **coverage**, never "ok"          | `116 specs, each mirroring a real source path` is evidence the check ran. See §12.6           | T1   |
| `hint` names **where the convention is written**, once     | Turns a finding from an argument into a repair. One string per check                          | T1   |
| The `checks:` target as the **only** registry              | A new check reaches CI with zero workflow edits                                               | T1   |
| `check-doc-links.mjs`                                      | Markdown links + heading anchors + backticked repo paths resolve. Near-verbatim portable      | T1   |
| env-contract check (both languages' read patterns)         | The one check that catches config drift nothing else sees                                     | T1   |
| `check-test-locations.mjs`                                 | A spec under a path the source tree no longer has = green tests covering the past             | T2   |
| `check-migration-hygiene.mjs`                              | Keep: `down()` present, unique timestamp, no migration outside the migrations dir             | T2   |
| `--cases` self-test flag registered right before the real run | A check whose pattern stopped matching reports nothing forever                              | T2   |
| Allowlists keyed by file+selector, **never by line number** | A line number moves and then silently exempts a different declaration                         | T2   |
| `check-handbook-parity.mjs`                                | Two-language docs carry the same pages and heading count — useful for a NL report + EN docs   | T2   |
| `check-module-shape`, `check-status-mark`, `check-block-headers` | Correct answers to questions your project does not ask                                    | ✗    |

### 4.4 Environment and secrets

| Piece                                                                  | What it does                                                                              | Tier |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | ---- |
| A committed `.env.example` per environment                             | The template **is** the contract                                                            | T0   |
| Four kinds as **exported data**: `DERIVED / GENERATED / OPTIONAL` + chosen-as-residue | A reason string per entry means the justification cannot be omitted            | T1   |
| `converge()` ordering: existing wins → store → generate → derive → render | Each step's position is load-bearing; see §6.8                                            | T1   |
| Never overwrite, with **derived as the single stated exception**        | Derived are calculations, so nothing of anyone's is lost — and a sticky one strands a rotation | T1 |
| One dotenv parser with a CRLF strip and an uppercase-anchored key regex | Four scripts each had a copy and only one survived a CRLF checkout                          | T1   |
| Template-vs-template comparison with `DEV_ONLY`/`PROD_ONLY` + staleness | A variable added to one template and forgotten in the other reads as "not needed here"      | T1   |
| Source scan for env reads, with a `RUNTIME_ONLY` allowlist carrying reasons | Declaration-vs-declaration checks are blind to a variable nothing declares                | T1   |
| Template geometry formatter (rewrite structure, **report** long prose)  | Formatters ignore `.env`, so it is the longest prose no tool owns, and it always drifts     | T2   |
| SOPS + age over a **dotenv** store, committed encrypted                 | Names stay readable ⇒ `git log` audit trail, and a completeness check needs no key          | ✗    |

**On SOPS for a school project: no.** The age private key is never printed, lives at one
path, and its loss is unrecoverable — and the design leans on a shared team password
manager you do not have. Someone will lose that key on the machine that dies in week 9. A
project with three secrets needs each person's local `.env` plus a pinned message in the
team channel. **But do the two-line version of what it protects:** assert in the check
harness that `.env` is gitignored, and know the recovery (`git rm --cached .env`, rotate —
and the value is still in history).

**Do not use Laravel's `php artisan env:encrypt`.** It encrypts the whole file, so the key
names disappear — and readable names are the entire value of the dotenv+SOPS choice.

### 4.5 Git conventions

| Piece                                                       | What it does                                                                            | Tier |
| ----------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ---- |
| Tracked `.githooks/` + `git config core.hooksPath`          | The file that runs is the file in the diff. This is husky's value without the package      | T1   |
| Hook resolves its validator via `git rev-parse --show-toplevel` | Git does not guarantee `$0` is a usable path when it invokes a hook                     | T1   |
| One validator, **two entry points**: message file and `--range` | `core.hooksPath` is *local* config; a clone that skipped install is told nothing         | T1   |
| Lenient grammar: `type(scope): subject`, closed type list, ≤90 chars | Measure your own log before picking the cap; 72 would fail a third of the source repo | T1   |
| `.gitmessage` + `git config commit.template`                | Four lines; the only thing helping someone committing by hand                              | T1   |
| `.gitattributes` `* text=auto eol=lf` + explicit `binary`   | Kills the whole CRLF failure class. See §12.2                                              | T0   |
| Exempting `Merge`/`Revert`/`fixup!` subjects                | Refusing a merge message fails *after* the merge, with a full index                        | T1   |
| Trailer ban by **name**, not `^Word-Word:`                  | A generic pattern refuses a body opening "Note: …", and a check that cries wolf teaches `--no-verify` | T2 |
| `[US-nnn]` issue prefix + `[US-???]` placeholder the hook refuses | Only with a real tracker. Without one, students invent numbers — the cost, not the benefit | ✗ |
| Dutch-word language check                                   | Enforces an English-history policy you may not want                                        | ✗    |
| Running full `verify` from **pre-commit**                   | Taxes the cheapest checkpoint in git and costs you the granular history a grader reads      | ✗    |

**Pre-push, not pre-commit.** A gate whose cost is minutes and whose trigger is "I want to
save my work" teaches people to stop committing.

### 4.6 Local infrastructure

| Piece                                                                              | What it does                                                                    | Tier |
| ---------------------------------------------------------------------------------- | --------------------------------------------------------------------------------- | ---- |
| `COMPOSE :=` variable pinning `-f`, `--project-directory .`, `--env-file`           | See §12.3 — the absence of the middle flag is silent and destructive               | T1   |
| Profiles `infra \| local \| dev \| prod`, `local` = stands in for a hosted service  | Lets a server start `infra` alone. A prod host once ran a mail catcher beside a live API key | T1 |
| `up:` depending on `env` + `bind-mounts`, then the SQL bootstrap                    | Ordering, not content                                                             | T1   |
| `bind-mounts: mkdir -p …` before `up`                                               | Docker creates a missing bind source **as root**. See §12.5                       | T1   |
| Healthcheck on every service + `depends_on: {condition: service_healthy}`           | A new service arrives with one, or a comment saying why it cannot                 | T1   |
| Probe what the service **serves**, chosen so a failure blames the right container   | An HTTP probe that fetches from a dependency restarts the wrong thing             | T1   |
| `${X_BIND:-127.0.0.1}:${X_PORT:-N}:<container>` for every published port            | Only the host side moves; loopback-by-default for anything holding data           | T1   |
| Image digest pinning with the readable tag kept                                     | Reproducible pulls; prod stages still `apk upgrade`                               | T2   |
| `x-defaults: &defaults` with `no-new-privileges` + capped json-file logging         | json-file is unbounded by default and fills the host                              | T2   |
| `restart: 'no'` stated on one-shots; `cap_drop: [ALL]` on app images only           | See §12.10 and §12.11                                                             | T2   |
| Idempotent SQL bootstrap re-applied on every `up`                                   | `docker-entrypoint-initdb.d` only runs on an **empty** volume. See §12.9          | T2   |
| Config template → gitignored generated dir; renderer **hard-fails** on empty var    | An empty substitution renders valid config that silently drops what it allowed    | T2   |
| Multi-stage Dockerfile: manifest-only COPY → install → COPY . . → fresh prod install | See §6.13 for the `--no-scripts` trap                                            | T2   |
| Named (not anonymous) volume for in-container deps + `modules-reset`                | Anonymous volumes are recreated per container; 16 GB of orphans observed          | T2   |
| Traefik + Sablier + nginx + four-profile 1063-line compose                          | Infrastructure for a fleet. Sail is thirty lines and every teammate can google it | ✗    |

### 4.7 Developer orchestration

| Piece                                                            | What it does                                                                                | Tier |
| ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | ---- |
| `make dev` starting **both** processes (app + assets)             | A Laravel dev stack is two processes; one of them missing is a blank page with no error         | T0   |
| `make stop` killing **by listening port**                         | Vite silently takes 5174 when 5173 is busy; you then load a page served by one Vite against a manifest written by another | T0 |
| `make status` — read-only, no prerequisites, **always exits 0**   | The one command that must work when everything else is broken. See §12.7                        | T1   |
| Probe ports from env vars with defaults, never literals           | If the port is a variable so a colleague can move it, a literal probe answers for something else | T1  |
| `lsof -ti tcp:<port> -sTCP:LISTEN`                                | Without `-sTCP:LISTEN` you match your own open connection and the script kills itself           | T1   |
| Bind-probe, not connect-probe, for "is this port free"            | A socket held by a non-accepting process reads as free to a connect. `EACCES` <1024 ≠ conflict  | T2   |
| Detached process groups (`detached: true`, signal `-pgid`) + two locks | The only reliable way to kill a `runner → wrapper → server` chain                            | T2   |
| Claim-the-lock-**before**-you-kill ordering                       | Killing the old run's child is what wakes it; claim after and it tears down what you inherited  | T2   |
| Per-run throwaway database + per-run temp upload dir              | For Laravel, `--parallel` gives you the first half free; the temp dir is `Storage::fake()`      | T1   |
| `output-gate` build-byte reproducibility                          | Exists for generated static sites. A Laravel app renders per request                            | ✗    |

### 4.8 Supply chain and release

| Piece                                                                | What it does                                                                    | Tier |
| -------------------------------------------------------------------- | --------------------------------------------------------------------------------- | ---- |
| Audit at a fixed threshold, **never lowered**                        | Lowering to silence one advisory hides every future one at that severity           | T1   |
| Waiver by **id** with a reason and an "Ends when" clause             | Composer takes the object form `{"CVE-…": "reason"}` — reason and id in one place  | T1   |
| gitleaks from a **digest-pinned** Docker image                       | A scanner installed per machine reports differently per machine                    | T1   |
| Allowlist on **value** or `regexTarget = "line"`, **never on `paths`** | See §12.8 — the natural narrow exemption is the widest possible hole              | T1   |
| Assert with `git check-ignore -q` that path-exempted files are still ignored | An exemption's safety rests on a property of another file                   | T1   |
| `make release version=x.y.z`: guards → verify → annotated tag → push  | Cheap remote checks **before** the five-minute verify                             | T2   |
| Release does **not** deploy                                          | Folded together, a rollback needs a new tag                                       | T2   |
| A host runs a detached **tag**, never a branch                       | A tag cannot move, so `git describe` on the host is still the answer tomorrow      | T2   |
| License gate over the declared production graph, SPDX **prefix** match | Substring `GPL` denies `LGPL-3.0-or-later`. See §12.12                            | T2   |
| Image publish, Grype scan by digest, SBOM, provenance, upgrade drill | Effectively zero rubric value; consumes the hours that would score                 | ✗    |

### 4.9 Agent context (language-neutral, and the cheapest thing here)

| Piece                                                                     | What it does                                                                   | Tier |
| ------------------------------------------------------------------------- | -------------------------------------------------------------------------------- | ---- |
| **One** root `CLAUDE.md`, no nested ones                                   | A nested one loads unconditionally and splits the map                             | T0\* |
| `.claude/rules/<area>.md` with a `paths:` front-matter glob                | The rule enters context only when that directory is touched                       | T0\* |
| The glob-vs-always test                                                    | A rule without `paths:` loads in **every** session, forever                       | T0\* |
| Verify every glob with `git ls-files '<glob>'`                             | A glob matching nothing fails **silently** and looks exactly like a clean tree     | T0\* |
| `.claude/rules/` describes the **work**, never a person                    | It travels with a clone; personal preferences go to your own memory store          | T0\* |
| The four documentation homes table                                         | Module doc / dated spec / rule-while-changing / directory map                      | T1   |
| "The repository keeps no status"                                           | A status in two places goes stale in one while both look authoritative             | T1   |
| The "what done means" checklist                                            | Nine bullets, no stack coupling. "You have seen it run" alone is worth the port    | T0\* |
| `.claude/agents/*.md`, only where judgement a gate cannot encode is needed  | Anything deterministic belongs in a script CI already runs                         | T2   |
| The verify-triage agent's **negative contract**                            | No ignore-comment, no baseline regeneration, no skipped test, no `--no-verify`     | T1   |
| `doc-truth-auditor`: read-only, **two citations** per finding               | A doc agreeing with a doc proves nothing. This is what stops hallucinated findings  | T2   |
| `.mcp.json` committed + `settings.local.json` per-developer opt-in          | A committed server file must not be able to enable itself                          | T2   |

\* T0 **if the team uses coding agents**, which changes the economics entirely: one hour of
setup for context that arrives automatically in every session.

---

## 5. Phases

Each phase: precondition → action → acceptance. Commit per phase only if §1 permits.

| Phase | Name                    | Why here                                                                                     |
| ----- | ----------------------- | ---------------------------------------------------------------------------------------------- |
| **A** | Repo hygiene            | Every later file is text a parser reads back; a CRLF tree defeats env parsing while `git status` stays clean |
| **B** | Manager, manifest, install | Nothing after this may invoke a binary this step did not install                            |
| **C** | Makefile **skeleton**   | Help block, `ENV`/`ENV_FILE`, `log`/`ok`, `.PHONY`, empty `verify:`/`ci:` — so later phases append |
| **D** | Environment contract    | Templates + `env` script + the `$(ENV_FILE):` guard target                                     |
| **E** | Formatting              | **Ignore list written before the first format run**                                            |
| **F** | Lint and types          | Formatter-disable entry last in the lint array                                                 |
| **G** | Tests                   | One real assertion. A green run over zero tests is a **failed** phase                          |
| **H** | Check harness           | `check.mjs` before the first check; `checks:` target before the parity script                   |
| **I** | Git hooks               | Armed **before** the agent's first commit, so its own commits are the hook's first proof        |
| **J** | Containers (if `docker=yes`) | `.dockerignore` **first**; skipped as a unit and recorded as skipped otherwise             |
| **K** | Fill `verify:` and `ci:` | Every target they name must already exist                                                     |
| **L** | CI workflow             | Last. Every step is a target that must already exist                                            |
| **M** | Agent context           | After the tree exists, so no `paths:` glob can match nothing                                    |

**Phase-boundary hazards, resolved:**

- **A → E.** `.gitattributes` only governs files added after it. On a converge run, or with
  a global `core.autocrlf=true`, already-tracked files keep their CRLF. Phase A ends with
  `git add --renormalize .`.
- **B is invalidated by F, G, H, J**, each of which adds dependencies. Each phase runs its
  own install; the §9 second-run proof must expect a lockfile those phases mutated.
- **E before D's output is formatted.** Prettier reformats `ci.yml`, `compose.yml` and any
  rule YAML — and re-quoting a pattern string in a rule file can change what it matches.
  Ignore the rule directory and the env templates explicitly.
- **C's `verify:` placeholder must be appended to, never re-declared.** A second `verify:`
  rule *with a recipe* makes GNU Make warn about overriding commands and **silently discard
  the first**. Put the append marker inside the recipe (§9).
- **I vs the agent's own commits.** If §1 chose an issue-prefix grammar, the agent has no
  issue ids and its own per-phase commits are rejected by the gate it just installed — the
  run deadlocks on itself. Either arm the hook last, or reserve an exempt subject for
  bootstrap commits and prove it with a real commit inside Phase I.
- **H → L.** Registering the parity check in Phase L can turn a `verify` that Phase K
  already declared green red. Either K's acceptance is stated as provisional, or the parity
  check is registered in H and tolerates zero workflows.
- **J needs `.dockerignore` first.** Without it, `COPY . .` ships the host `.env` (real
  credentials), `vendor/` built for the wrong platform, and — the killer —
  `bootstrap/cache/*.php` containing absolute host paths, so the container boots against
  paths that do not exist.

---

## 6. File specifications

Path, why it exists, create-or-converge, content, and the grep assertion to run after
writing it.

### 6.1 `.gitattributes` — create

```
* text=auto eol=lf
*.png binary
*.jpg binary
*.webp binary
*.woff2 binary
*.pdf binary
*.zip binary
```

Assert: `grep -q 'text=auto eol=lf' .gitattributes`. Then `git add --renormalize .`.

### 6.2 `.editorconfig` — create

```
root = true

[*]
charset = utf-8
end_of_line = lf
insert_final_newline = true
indent_style = space
indent_size = 2

[Makefile]
indent_style = tab

[*.md]
trim_trailing_whitespace = false
```

`[Makefile] indent_style = tab` is not decoration: a Makefile with spaces is a hard error
and no editor guesses this.

### 6.3 Makefile preamble — create

```make
.DEFAULT_GOAL := help

ENV      ?= local
ENV_FILE := .env$(if $(filter local,$(ENV)),,.$(ENV))

# `--project-directory .` is not optional. Compose resolves both the project name and
# every relative path from the project directory, which defaults to the file's own
# directory — so without it the project becomes `docker`, orphaning every existing
# container and named volume, and each `./app/...` bind mount resolves under docker/.
COMPOSE := docker compose -f docker/compose.yml --project-directory . --env-file $(ENV_FILE)

# `env_file:` feeds the container; compose-level ${VAR} needs --env-file.
LOAD_ENV := set -a; . ./$(ENV_FILE); set +a;

# Placeholders so `make build` / `make verify` work with no infra running.
BUILD_ENV := APP_ENV=production APP_DEBUG=false \
             APP_KEY=$${APP_KEY:-base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=} \
             DB_CONNECTION=$${DB_CONNECTION:-sqlite}

# Locally these start compose; CI hands them a service container instead.
DB_TARGET_DEPS := $(if $(TEST_DATABASE_URI),,up)
DB_ENV          = $(if $(TEST_DATABASE_URI),,$(LOAD_ENV))

# Pretty logging: `@$(log) "message"`. A SHELL argument, not a make one — `$(call log,…)`
# splits on the first comma and dies on any message containing one.
log = printf "\033[1;36m▶\033[0m %s\n"
ok  = printf "\033[1;32m✓\033[0m %s\n"

.PHONY: help install env up down dev stop status format format-check checks \
        lint typecheck analyse arch test integration build verify ci \
        commit-messages git-hooks fresh audit secrets-scan
```

**`DB_TARGET_DEPS` uses `:=` and `DB_ENV` uses `=`, deliberately.** A prerequisite list must
be expanded when the rule is read; a recipe prefix at recipe time. Swapping them silently
changes which cluster the database targets use.

Assert: `grep -cP '^\t' Makefile` is non-zero (**recipe lines must be tabs**; an agent
writing a Makefile emits spaces roughly as often as tabs, and `missing separator` names a
line that looks fine).

### 6.4 The help target — create, copy **verbatim**

The single most-mistyped block in this document. Note every `$$` — with one `$`, `make`
prints nothing and reports no error.

```make
##@ Workspace

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"} \
		NR == FNR {if ($$0 ~ /^[a-zA-Z0-9_-]+:.*##/ && length($$1) > w) w = length($$1); next} \
		FNR == 1 {printf "\n\033[1m<PROJECT>\033[0m — make <target>\n"} \
		/^##@/ {printf "\n\033[1m%s\033[0m\n", substr($$0, 5); next} \
		/^[a-zA-Z0-9_-]+:.*##/ { \
			text = $$2; sub(/^ /, "", text); detail = ""; \
			if (match(text, / — /)) { \
				detail = substr(text, RSTART + length(" — ")); text = substr(text, 1, RSTART - 1); \
			} \
			printf "  \033[36m%-*s\033[0m  %s\n", w, $$1, text; \
			if (detail != "") printf "  %*s  \033[2m%s\033[0m\n", w, "", detail; \
		}' $(MAKEFILE_LIST) $(MAKEFILE_LIST)
	@printf "\nEnv: \033[36m$(ENV)\033[0m  (override with ENV=production)\n\n"
```

**`$(MAKEFILE_LIST)` twice is on purpose.** Pass one (`NR == FNR`) only measures the column
width `w`; drop the second file and `w` stays 0 and every description collapses onto the
target name.

Assert: `make | grep -c '^  '` ≥ the number of documented targets.

### 6.5 Guards and threading — the four idioms

```make
# Required argument: refuses in one second, prints its own usage.
migration: ## Generate a migration — make migration name=<name>
	@[ -n "$(name)" ] || { printf "\033[1;31m✗\033[0m %s\n" "Usage: make migration name=<name>"; exit 1; }
	@php artisan make:migration $(name)

# Destructive: the same guard plus yes=1.
fresh: up ## Drop every table, migrate and seed — make fresh yes=1
	@[ -n "$(yes)" ] || { printf "\033[1;31m✗\033[0m %s\n" "Usage: make fresh yes=1 (this drops every table)"; exit 1; }
	@$(LOAD_ENV) php artisan migrate:fresh --seed

# Optional argument becomes a flag or its opposite.
test-one: ## Run one test — make test-one [filter=<name>]
	@php artisan test $(if $(filter),--filter "$(filter)",)

# A guard, not a copy rule. NEVER write `.env: .env.example` — see §12.4.
$(ENV_FILE):
	@node scripts/env.mjs $(ENV)
```

**Guard order is load-bearing.** Check the argument that names the *thing* before the one
that names the *environment*, so an unknown target is named even when `ENV` is unset.

### 6.6 `verify` / `ci` — Phase K fills these

```make
verify: commit-messages format-check checks lint typecheck analyse test build ## Everything that needs no service
	@$(ok) "All checks passed"

ci: verify integration audit secrets-scan ## The whole pipeline: verify + everything needing Docker, a DB or the network
	@$(ok) "Everything CI runs has passed"

format-check: ## Check formatting
	@vendor/bin/pint --test
	@composer validate --strict
	@npx prettier --check .

checks: ## The repo invariants (scripts/check-*.mjs)
	@$(log) "Checking the repo invariants…"
	@node scripts/check-env-contract.mjs
	@node scripts/check-doc-links.mjs
	@$(ok) "The repo invariants hold"

analyse: ## Static analysis (PHPStan/Larastan; PHPat rules ride along)
	@vendor/bin/phpstan analyse --memory-limit=1G --no-progress

integration: $(DB_TARGET_DEPS) ## Database-backed tests
	@$(DB_ENV) php artisan migrate --force && php artisan test --group=database

build: ## Build, offline
	@$(BUILD_ENV) php artisan config:cache route:cache view:cache
	@npm run build
	@php artisan config:clear
```

**Ordering is cheapest-first**, with one deliberate pair kept if you adopt ast-grep: rule
tests run **before** the scan, because a green scan from a rule that stopped matching is
indistinguishable from a clean tree.

`checks:` is **serial and fail-fast** — make aborts on the first non-zero recipe line, so a
run shows one failing check even if three are broken. `make -j` does not parallelise recipe
lines inside one target.

### 6.7 `scripts/lib/check.mjs` — create, copy verbatim

```js
import { isAbsolute, relative } from 'node:path'

const ROOT = process.cwd()
const c = {
  ok: (s) => `\x1b[32m${s}\x1b[0m`,
  bad: (s) => `\x1b[31m${s}\x1b[0m`,
  warn: (s) => `\x1b[33m${s}\x1b[0m`,
  dim: (s) => `\x1b[2m${s}\x1b[0m`,
}

export function createCheck(label, hint) {
  const findings = []
  return {
    report(path, message) {
      findings.push({ path: isAbsolute(path) ? relative(ROOT, path) : path, message })
    },
    get count() {
      return findings.length
    },
    finish(summary) {
      if (findings.length === 0) return pass(label, summary)
      fail(label, hint, () => {
        for (const { path, message } of findings) {
          console.error(`  ${c.warn(path)}\n    ${message}`)
        }
      })
    },
  }
}

/** The line a check ends on when it finds nothing. */
export function pass(label, summary) {
  console.log(`${c.ok('✓')} ${label} — ${summary}`)
}

/**
 * The findings, then where the rule lives, then a non-zero exit.
 *
 * `render` writes the findings itself, so a check whose finding is more than a path and a
 * sentence keeps its own layout without restating the header, the hint and the exit.
 */
export function fail(label, hint, render) {
  console.error(`\n${c.bad('✗')} ${label}\n`)
  render()
  console.error(`\n  ${c.dim(hint)}\n`)
  process.exitCode = 1
}
```

**Known bug fixed here:** the source uses `process.exit(1)` immediately after
`console.error`. On POSIX, stderr to a *pipe* is async, so `make checks 2>&1 | tee build.log`
can lose the findings while still exiting 1 — a red build with an empty reason. Set
`process.exitCode` and let the script end.

**Two rules that make the format work:**

1. `summary` states **what was covered**, never "ok". `0 files` is visible; `ok` is not.
   This is the only cheap defence against a check whose pattern silently stopped matching.
2. `hint` names **where the convention is written down**, printed **once** after the
   findings. A check that says a thing is wrong without saying where the convention lives
   is one whose finding gets argued with rather than fixed.

### 6.8 `scripts/env.mjs` — the contract

Three exported objects plus "chosen is the residue":

```js
/** Random values. Development generates them; production refuses to invent them. */
export const GENERATED = {
  SESSION_SECRET: 'signs the session cookie',
}

/** name → (values, ctx) => string. Recomputed every run and OVERWRITTEN. */
export const DERIVED = {
  APP_URL: (v) => `http://localhost:${v.APP_PORT || 8000}`,
}

/** A DENYLIST: everything not here is required. Being optional needs a justification. */
export const OPTIONAL = {
  MAIL_FROM_NAME: 'falls back to the app name',
}
```

There is no `CHOSEN` list. Chosen is the residue, and the predicate is spelled the same way
everywhere: **declared in the template with an empty value**, `!(key in DERIVED)`,
`!(key in OPTIONAL)`. That is fail-closed — a secret added to the template tomorrow is
required with nobody remembering to register it.

`converge()` ordering, each position load-bearing:

```
resolved = { ...parseEnv(template), ...existing }   // the existing file wins
  → (production) overlay the secret store           // so a URI can be composed from a stored password
  → (development) fill only EMPTY generated keys    // reset known PLACEHOLDER literals to '' first
  → run DERIVED over every present key, OVERWRITING // the one exception to "never overwrite"
  → render by walking the template, keeping KEY=value lines + section headings
  → exit 1 listing every empty non-OPTIONAL key (production only)
```

**Why derived is the exception, and why the exception makes the rule work:** derived values
are calculations, so nothing of anyone's is lost — and a sticky one would leave a rotated
password's URIs holding the old value.

**For Laravel, one kind moves home.** `config/*.php` **is** the derivation layer: composing
a DSN from `DB_HOST`/`DB_PORT`/`DB_PASSWORD` belongs there, where it is recomputed on every
boot and cannot go stale. Reserve `DERIVED` for values that must physically exist in the
file because compose interpolates them. And **exclude `APP_KEY` from `GENERATED`** —
`php artisan key:generate` writes `.env` itself, and the two will fight.

Guard the entry point (`if (process.argv[1]?.endsWith('env.mjs'))`) so other scripts can
import the contracts without converging a file.

### 6.9 `.githooks/commit-msg` — create, mode **0755**

```sh
#!/usr/bin/env sh
# Installed by `make git-hooks` (which `make install` runs) via core.hooksPath, so the
# tracked copy is the one that runs — nothing is copied into .git/hooks, where it would
# not survive a clone and could silently drift from this file.
exec node "$(git rev-parse --show-toplevel)/scripts/check-commit-message.mjs" "$1"
```

**Set the executable bit explicitly.** An agent writing this through a file tool produces
mode 644; `core.hooksPath` then finds the file and does not run it, and on some git versions
prints nothing at all:

```bash
chmod +x .githooks/commit-msg
git update-index --chmod=+x .githooks/commit-msg
```

Arming, in the Makefile:

```make
install: git-hooks ## Install dependencies
	composer install --no-interaction --prefer-dist
	npm ci

git-hooks: ## Point git at .githooks and load the commit template
	@git config core.hooksPath .githooks
	@git config commit.template .gitmessage
	@$(ok) "Git hooks armed — commit messages are checked"

commit-messages: ## Check this branch's commit messages
	@node scripts/check-commit-message.mjs --range $(COMMIT_RANGE)
```

The validator has **two entry points**, and the second is not optional:

```js
const rangeIndex = argv.indexOf('--range')
const reports =
  rangeIndex === -1 ? [fromFile(argv[0])] : fromRange(branchRange(argv[rangeIndex + 1]))
```

Acceptance is **an actual rejected commit**, not the presence of the file.

### 6.10 `scripts/lib/branch.mjs` — two paid-for git bugs

```js
export function branchRange(given) {
  const from = given?.split('..')[0]
  // `^{commit}` and not the bare name: `rev-parse --verify` accepts a push's forty zeroes
  // as a well-formed object name and prints it back, so the plain check passes and the
  // range resolves to nothing — which is indistinguishable from a clean branch, and
  // happens on exactly the first push, when an unarmed clone lands its first message.
  const exists = from && git(['rev-parse', '--verify', '--quiet', `${from}^{commit}`])
  return exists ? given : resolveRange()
}

function resolveRange() {
  const base = ['origin/main', 'origin/develop', 'main', 'develop'].find((ref) =>
    git(['rev-parse', '--verify', '--quiet', ref]),
  )
  const fork = base ? git(['merge-base', base, 'HEAD']) : ''
  return fork ? `${fork}..HEAD` : 'HEAD..HEAD'
}

// THREE dots. Two counts every file the base branch changed since your branch was cut.
export const changedFiles = (range) =>
  git(['diff', '--name-only', range.replace('..', '...')]).split('\n').filter(Boolean)
```

### 6.11 `.github/workflows/ci.yml` — Phase L, last

```yaml
name: CI
on:
  pull_request:
  push:
    branches: [main]

permissions:
  contents: read

concurrency:
  group: ci-${{ github.ref }}
  cancel-in-progress: true

jobs:
  commits:
    runs-on: ubuntu-latest
    timeout-minutes: 5
    steps:
      - uses: actions/checkout@<40-char-sha> # v4
        with:
          fetch-depth: 0 # what this reads is history; the default clone is one commit deep
      # No setup step: the check is node and git, and installing the workspace for it
      # would cost a minute to answer a question about text.
      - run: make commit-messages
        env:
          COMMIT_RANGE: >-
            ${{ github.event_name == 'pull_request'
             && format('{0}..{1}', github.event.pull_request.base.sha, github.event.pull_request.head.sha)
             || format('{0}..{1}', github.event.before, github.sha) }}

  verify:
    runs-on: ubuntu-latest
    timeout-minutes: 20
    steps:
      - uses: actions/checkout@<40-char-sha> # v4
      - uses: ./.github/actions/setup # a local action is only resolvable after checkout
      - run: make verify

  integration:
    runs-on: ubuntu-latest
    timeout-minutes: 20
    services:
      postgres:
        image: postgres:17-alpine@sha256:<digest>
        env: { POSTGRES_PASSWORD: postgres, POSTGRES_DB: testing }
        options: >-
          --health-cmd pg_isready --health-interval 10s
          --health-timeout 5s --health-retries 5
        ports: ['5432:5432']
    steps:
      - uses: actions/checkout@<40-char-sha> # v4
      - uses: ./.github/actions/setup
      - run: make integration
        env:
          DB_CONNECTION: pgsql
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: postgres
          DB_PASSWORD: postgres
          APP_KEY: base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=
```

Note the `APP_KEY` on the step: Larastan and every artisan command boot the framework.
Nothing in the Makefile can supply it in CI.

`.github/actions/setup/action.yml` is a composite action holding both toolchains:
`shivammathur/setup-php` (pinned by SHA, with an explicit `extensions:` list and
`coverage: none`), `actions/setup-node` with `cache: npm`, then the installs. **Keep the
`extensions:` list equal to the Dockerfile's `docker-php-ext-install` line** — two places
that will drift, and the failure is `composer install` refusing to resolve with
`requires ext-intl * -> it is missing`, on CI only, after everything worked locally.

### 6.12 `scripts/check-ci-parity.mjs` — the whole mechanism

```js
// `verify` is itself a chain, and it is its targets the workflow calls one per step.
const required = new Set(
  prerequisitesOf('ci').flatMap((n) => (n === 'verify' ? prerequisitesOf('verify') : [n])),
)
// Steps only: a target named in a comment is prose about the workflow, not a step of it.
const steps = workflow.replace(/^\s*#.*$/gm, '')
const inWorkflow = new Set([...steps.matchAll(/\bmake\s+([a-z][a-z0-9-]*)/g)].map((m) => m[1]))

for (const t of [...required].filter((n) => !inWorkflow.has(n)))
  check.report(WORKFLOW, `never runs \`make ${t}\``)
for (const t of [...inWorkflow].filter((n) => !required.has(n)))
  check.report(MAKEFILE, `\`make ci\` does not run \`${t}\`, which the workflow does`)

function prerequisitesOf(target) {
  // The lookahead keeps `name := value` out: a rule and an assignment share a first token,
  // and reading a variable's contents as a prerequisite list would pass silently.
  const rule = makefile.match(new RegExp(`^${target}:(?!=)(.*)$`, 'm'))
  if (!rule) fail('CI parity', 'This check names a target that no longer exists.', () => {})
  return rule[1].replace(/##.*$/, '').split(/\s+/).filter(Boolean)
}
```

**Three limits to know.** It only sees `make <target>`; a raw `- run: vendor/bin/pest`
pasted into a job is invisible — which is why the rule is "**every** step is a target",
enforced socially. The target regex is `[a-z][a-z0-9-]*`, so `db_seed` or `Build` are
invisible too. And it strips only whole-line comments, so keep target mentions on their own
comment lines.

### 6.13 `.dockerignore` — Phase J, **first**

```
.git
node_modules
**/node_modules
vendor
.env
.env.*
!.env.*.example
storage/logs
storage/framework/cache
bootstrap/cache
public/build
public/hot
docker/
```

**`docker/` is excluded deliberately.** BuildKit sends the Dockerfile outside the context,
so ignoring the directory does not hide the file you are building from — and it stops an
edit to compose or an nginx vhost from invalidating the `COPY . .` layer. The consequence is
intentional: config files get **mounted read-only, not baked**, so editing a vhost is a
restart rather than a rebuild.

Dockerfile install order:

```dockerfile
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
 && php artisan package:discover --ansi
```

`--no-scripts` on the first install is the whole trick — without it Composer runs
`package:discover` before the app source exists and fails. **But it also means the image
boots with no package service providers unless `package:discover` runs after the source
COPY**, which is why the line above is there.

---

## 7. Verification sequence

Run in order. Paste command, exit code and one line of real output for each.

1. `git status --porcelain` — every line must be a file §6 names, and nothing else.
2. Re-run the §2 probe: every artefact marked create or converge now reads present.
3. `make` — every target from every executed phase appears with its docstring under a
   group header. This proves the awk block **and** the docstrings.
4. `make env` twice, then `ls -l $ENV_FILE` and `git diff --stat` — second run changes
   nothing, file is mode 0600, no secret in the pasted output.
5. `make format-check; echo $?` — exit 0 over the tree the bootstrap just wrote.
6. `make lint; echo $?` and `make typecheck; echo $?` — exit 0, **and the output names how
   many files were checked**. A silent pass is a zero-file run until proven otherwise.
7. `make test` — exit 0 **and a non-zero test count**. A suite that collected nothing
   exits 0 too.
8. `make checks` — every line reads `✓ <label> — <coverage>`, and **no coverage number is
   0**, because `0 files` is what a typo'd glob prints.
9. §8's deliberate-failure pass, both halves pasted for each gate.
10. `git config core.hooksPath` prints `.githooks`, then a well-formed
    `git commit --allow-empty` succeeds. The hook must be shown both **refusing and
    accepting**.
11. `make commit-messages` — prints `N checked`, proving the second entry point works where
    the hook may never have been armed.
12. `make verify; echo $?` — the whole no-service gate; last 20 lines plus the exit code.
13. If Docker: `make up`, `docker compose ps` showing every service healthy, `make status`
    (exit 0), `make down` leaving no container from any profile.
14. Parity: `grep -c 'run: make' .github/workflows/ci.yml` equals the number of steps in the
    job bodies — a raw command must be shown **not** to exist.
15. **Fresh-tree proof:** `git clone . /tmp/bootstrap-proof && cd /tmp/bootstrap-proof &&
    make install && make verify`. This catches everything that only works on a tree which
    has already been built, and it is the step most often skipped.
16. **Idempotence proof:** re-execute every phase, then `git status --porcelain` and
    `git diff` — both empty, both pasted.

---

## 8. Deliberate-failure proofs

**A gate that has only ever been seen green is indistinguishable from a gate that matches
nothing.** For each: plant, assert exit 1, revert, assert green. Paste both halves.

| Gate         | Plant                                            | Must then                        |
| ------------ | ------------------------------------------------ | -------------------------------- |
| format-check | A mis-indented file                              | `make format-check` exits 1      |
| lint         | An unused import / undefined variable            | `make lint` exits 1              |
| typecheck    | Pass a string where an int is declared           | `make typecheck` exits 1        |
| test         | Invert one assertion                             | `make test` exits 1              |
| checks       | Break a documented path in a markdown link       | `make checks` exits 1           |
| commit-msg   | `git commit --allow-empty -m "bad message"`      | The hook refuses, **by name**    |
| arch rule    | A class in the wrong namespace / a forbidden dep | `make analyse` exits 1          |

**Two hard rules for the plants:**

- **Never plant a credential-shaped string for the secret scanner** on a branch that has
  been pushed, and never let a plant reach a commit. gitleaks scans **history**; a
  committed plant makes the gate permanently red with no clean way back. Plant, run,
  revert, and assert `git status --porcelain` is empty **before** the phase commit.
- **A plant lives in a file created for the purpose** and is deleted in the same step, so
  an interrupted proof cannot leave the tree broken.

**And prove *reach*, not just firing.** A rule test proves the rule fires on its own
hand-written snippet; it says nothing about whether the rule's glob points at real code. In
the source repo five frontend rules named `apps/**` while 37 components lived under
`packages/` — a third of the frontend was green because nothing was looking at it. Plant a
violation in the **real tree**, confirm the scan catches it, delete it. Never infer reach
from a green run.

---

## 9. Idempotence contract

Three write modes, declared per file in §6. There is no fourth and no overwrite.

1. **create-if-absent**
2. **converge** — parse, merge by key, keep every existing value
3. **append-once between markers** — `# >>> bootstrap:<id>` / `# <<< bootstrap:<id>`; a run
   that cannot find its own opening marker **creates** the block, it does not append a
   second copy

Plus:

- Before converging, compare to the hash in `.bootstrap/state.json`. Unchanged ⇒ regenerate
  freely. **Changed ⇒ a human edited it ⇒ ask, never converge.** The agent never restores
  its own version.
- Makefile edits are keyed on `^<target>:`. An existing target is left alone; `.PHONY`
  names are merged and de-duplicated; the help block and variable header are written once
  and never re-emitted. **In-rule insertion needs its marker inside the recipe** (§5).
- List files gain a line only if an equivalent is absent, compared after trimming
  whitespace and a trailing slash — not by naive string search, which duplicates `.env`
  next to `.env*`.
- **Lockfiles are never hand-written, deleted or regenerated to "fix" an install.** A
  mismatch is a Blocked item with a resume command, not a repair.
- **Generated content contains no timestamp, no random id, no hostname, no "generated on"
  date.** Anything that varies between runs destroys the only cheap idempotence test there
  is.
- Out-of-tree state uses commands that are already idempotent (`git config …`).

---

## 10. Failure handling

| Class                 | Required response                                                                                                      |
| --------------------- | ---------------------------------------------------------------------------------------------------------------------- |
| Required tool missing | Stop **that phase only**; Blocked list with the resume command; never substitute a different tool to keep moving         |
| Optional tool missing | Wire the target to **fail loudly**, exclude it from `verify`, record in §11 as declared-but-unproven                    |
| No Docker             | Skip Phase J as a unit; still write env templates; give DB targets a conditional prerequisite; **do not** write the CI job that needs a service container, or parity fails on a job nobody can run |
| Native Windows        | Do not assume make, `lsof`, `flock` or a POSIX shell. Require WSL2 and say so in the README's three commands            |
| No network            | Stop before writing any config naming a package the failed install did not fetch — a lint config naming an absent plugin is a file that looks configured and errors on first use |
| Dirty tree            | Stop and ask                                                                                                            |
| Conflicting config    | Never overwrite. Show the diff, name the two options (converge / adopt-yours-and-skip), let the human choose             |
| Non-TTY, no answers   | Print the answers block with detected defaults and stop. Never invent answers                                           |
| Phase fails midway    | Remove partial files before reporting, so the next probe sees **absent** rather than half-written                       |

**Never install anything outside the project** without the §1 permission — no global
installs, no sudo. Project tools go in as dev dependencies so a colleague gets them from
the lockfile.

---

## 11. Deliberate omissions

State these explicitly so the next person adds them **on purpose** instead of assuming they
exist.

Not set up by this document: release tagging and deployment · container image publishing,
scanning, SBOM and provenance · the encrypted secret store · the upgrade drill · build-output
byte reproducibility · end-to-end browser tests · path-gated CI · the license gate · task-graph
caching · dependency-patch management · generated API documentation.

**Two you should reconsider rather than accept by default:**

- **Accessibility.** The source system has a make target that builds the site, serves it,
  and measures WCAG 1.4.10 / 2.5.8 at 320 and 390 px, wired into CI. Dutch HBO/MBO ICT
  rubrics commonly grade WCAG. If yours does, one Playwright viewport/a11y check is Tier 1,
  not an omission.
- **Seed data.** Migrations without seeds means the recurring team blocker is "I pulled and
  now I have no users to log in as". `make fresh yes=1` should migrate **and** seed a known
  admin account, and the README should say the credentials.

**One you must not omit if you use gitleaks:** its default ruleset fires on a Laravel
`.env.example` (`APP_KEY=base64:…`) and on the placeholder keys in `config/services.php`.
Day one, CI is red on committed template files. Ship a starting allowlist that matches on
**values**, not paths — and read §12.8 before writing it.

---

## 12. The paid-for traps

Each of these was a real incident. This is the highest-value section in the document.

**12.1 `.PHONY` omission is silent.** Leave a target off and make compares it against a
same-named *directory* and quietly does nothing — you get a green that ran nothing. In a
Laravel repo `test build docs config database routes public storage` are all real
directories, so the hazard is worse than in a Node one.

**12.2 CRLF is the silent killer.** Under `core.autocrlf=true` you get CRLF in the working
tree and LF in the index: `git status` reports nothing, `source ./.env` executes each bare
`\r` and prints "not found", and JS regexes matching `KEY=value` match **nothing at all**
because `.` excludes `\r`. A renderer then writes its template back verbatim — a `.env`
that looks configured and holds no value. Two defences, both required: `* text=auto eol=lf`
and an explicit `.replace(/\r\n/g, '\n')` in the parser. A CRLF `#!/usr/bin/env sh` shebang
fails with `bad interpreter: /usr/bin/env sh^M`, naming a path that looks correct.

**12.3 `--project-directory .` is not optional and its absence is silent.** Compose defaults
the project directory to the compose file's own directory, so
`docker compose -f docker/compose.yml up` names the project `docker`, orphaning every
container and named volume, and resolves `./app/...` under `docker/`. Recorded incident: a
script built its own argument list without the flags, its probe failed on every run, a
failed probe read as "no database", and that flag was what decided whether git could
overwrite live editor work.

**12.4 Never write `.env: .env.example`.** As an ordinary prerequisite, every template edit
makes the template newer than your real env file and the next dependent target **overwrites
your secrets with blanks**. Use a bare `$(ENV_FILE):` target whose recipe converges.

**12.5 Docker creates a missing bind source as root.** Services running as an unprivileged
user then fail at the first **write**, not at the mount — so everything looks healthy. Cost
before the fix existed: two days, eight provisioned clients, an empty root-owned tree.
Laravel hits this immediately on `storage/` and `bootstrap/cache`, presenting as a 500 with
a permissions message nobody wrote.

**12.6 A walker returning `[]` for a missing directory passes silently.** That behaviour is
deliberate (a check should report an absent tree, not crash), which is exactly why
`finish(summary)` must include a count. `✓ … — 0 files` is visible; `ok` is not.

**12.7 `make status` must have no prerequisites and always exit 0.** It is the one command
that has to work when everything else is broken, so "the database is down" is an answer,
not a failure. Anything that starts infra, migrates or seeds from a status command makes it
unsafe to run mid-incident. For Laravel: do **not** build it on `php artisan about` alone —
that boots the application, so it fails precisely when the application is the broken thing.

**12.8 A gitleaks `paths` allowlist skips the entire file before any rule runs, and
`condition = "AND"` does not restrain it.** Writing the obvious narrow exemption (this path
AND this exact value) exempted every spec file in two services; a planted `sk_live_…` key
went unreported while the same key elsewhere was caught. Measured on 8.30.1. In Laravel the
tempting-and-wrong path exemptions are `tests/` and `database/seeders/` — both are where a
real API key ends up in a hurry. Allowlist on the **value**, or with
`regexTarget = "line"` and no path. And **verify every allowlist by planting a realistic
secret in the file it covers and confirming the scan still fails.**

**12.9 A database image reads its password (and creates its database) only while the data
volume is empty.** A password rotated afterwards strands every URI built around it. Keep an
idempotent `roles.sql` applied on every `up`, starting with an `ALTER ROLE … WITH PASSWORD`
executed over the container's local socket, so it works whatever the current password is.
Same trap in MySQL's `docker-entrypoint-initdb.d`. Also: every `compose exec` costs ~3 s of
process startup regardless of the query — one exec running a shell loop, not ten execs.

**12.10 `restart: unless-stopped` reacts to a process *exiting*.** It does not act on a
failing healthcheck — plain Docker never does. An `unhealthy` container stays up and in
rotation. A healthcheck is not auto-heal. And state `restart: 'no'` explicitly on one-shot
init containers, or you restart a container whose success condition is having exited.

**12.11 `cap_drop: [ALL]` breaks infra images.** postgres, redis, nginx and the official
php-fpm image start as root and drop to their own user (SETUID/SETGID) or bind :80
(NET_BIND_SERVICE). Only images running as an unprivileged user above port 1024 can drop
everything. `no-new-privileges` is free and goes everywhere.

**12.12 Match licences on the SPDX id *prefix*, never a substring.** A substring test on
`GPL` denies `LGPL-3.0-or-later`, which prebuilt image libraries ship under and every
commercial user lives with. And a dual licence `(MIT OR GPL-2.0)` is two claims: deny only
when **every** term is denied, after splitting on `()`, `OR`, `AND`, `WITH`.

**12.13 A `paths:` glob that matches nothing fails silently.** Rules copied from a sibling
toolkit kept a glob pointing at a directory that never existed in the target repo, so those
rules never fired and the tree looked clean. Verify every glob with `git ls-files '<glob>'`
when you add or move a rule, and re-verify after any directory rename. The same shape
recurs everywhere: an architecture rule naming a namespace that does not yet exist **passes
silently**; a rule-test directory the config does not point at makes the test command find
zero cases and exit 0.

**12.14 Port collisions a PHP project will hit.** 9000 is PHP-FPM's default as much as
MinIO's, so any machine running Herd, Valet, Sail or XAMPP fails the bind. 8000 is
`artisan serve`. 5173 is Vite — and **Vite silently takes 5174 when 5173 is busy**, so a
teammate loads a page served by one Vite against a manifest written by another and gets a
blank screen with no error. Declare every published port as a variable in both templates.

**12.15 Convergence cannot repair a value the template *changed*.** It adds keys but never
overwrites, so moving a port in the template leaves every existing checkout pointing at the
old one. That is why a cross-check ("a loopback port inside any value must be declared by
some `*_PORT`") must be **fatal at `make env`**, not only in the CI gate.

**12.16 Shipping an example value for a generated secret defeats generation permanently.**
Only an *empty* value is ever filled, so a fresh clone inherits `changeme` as if somebody
had chosen it.

**12.17 Never put a comment after a value in an env file.** The file is both sourced as
shell and read by compose; bash folds a trailing `# …` into the value, and the failure is a
URI with a sentence glued to it. Explanations go on the lines above.

**12.18 Never let a formatter touch a template whose placeholders are substituted by exact
literal.** Prettier reads `.mjml` as HTML and rewrites `{{resetUrl}}` to `{{ resetUrl }}`;
the renderer matches `{{key}}` exactly, so the placeholder survives into the sent mail as
visible text with no error anywhere. Password-reset and invitation links both broke, and
the diff looks like whitespace.

**12.19 Porting a rule set without triage is worse than not porting it.** 60 rules from a
sibling repo produced 177 findings, the largest single group being every module in the
tree, because the source repo forbade the layout the target one mandates. On day one that
is a red gate nobody believes and everybody learns to ignore. Judge each rule against local
convention, change the **rule** when they disagree, and write the verdict down — a dropped
rule with no recorded reason gets re-added next year. **Three rules you wrote yourself, for
a defect you have seen twice, are worth more than forty-five you inherited.**

**12.20 Do not give one opinion two homes**, and do not write a weaker rule for something
the compiler already guarantees. Both produce findings that argue with another tool.

---

## 13. Verify before pinning

Verified during extraction: **ast-grep 0.45.2** (empirically — `--lang php` matches
`DB::select($$$ARGS)` and `env($$$A)`; `.blade.php` is picked up by a `**/*.php` glob with
no extra config, and a pattern matched inside an `@if(...)` directive). **PHPStan 2.1.x**,
level 10 added in 2.0. **Larastan 3.x** requires PHPStan 2.x; canonical package
`larastan/larastan`. **Pest v5** requires PHP 8.4; **4.7.x** is the PHP 8.3 line;
`->toBeCasedCorrectly()` added in 4.5. **Deptrac** moved from `qossmic/deptrac` to
`deptrac/deptrac`; config now defaults to `deptrac.php`. **Rector 2.6.6** requires
`phpstan/phpstan ^2.2.10`. **phpat/phpat 0.12.4** — still 0.x, so **pin exactly, no caret**.
**Infection 0.35.4** requires PHP 8.3 + a coverage driver. **setup-php 2.37.2** — be on this
or later, two CVEs land there. **ramsey/composer-install 4.0.0** (v3 is not the newest).
**Laravel 13**, minimum PHP **8.3**. **Sail v1.67.0**, default PHP 8.5. **Composer**
`config.audit.ignore` accepts the object form `{id: "reason"}`.

Check before pinning: Laravel Pint's current version · Deptrac's current version · whether
`composer audit` has a severity threshold (it may fail on **any** advisory, which behaves
differently from a `--audit-level=high` gate) · `composer licenses --no-dev` · the Composer
2.9 `config.policy.abandoned` schema · Infection + Pest adapter compatibility · Blade
formatting (no verified first-party answer; `php artisan view:cache` in the build target at
least gives you a syntax check over every Blade file) · CaptainHook/GrumPHP vs
`core.hooksPath` (a conflict is plausible, not confirmed — the recommendation to use native
git does not depend on resolving it).

**One ast-grep gotcha specific to PHP, measured:** metavariables are uppercase-only, and PHP
variables are also `$`-prefixed, so they collide. `$ID` as a standalone pattern matched
**every node in the file** (it is a wildcard); `$rows` matched only the literal variable.
Lowercase `$foo` is a literal lookup, uppercase `$FOO` is a wildcard — so a rule mentioning
a genuinely all-caps PHP variable silently becomes match-anything.

---

## 14. The rule this whole document is subordinate to

**After day one, nobody on the team should have to think about the toolchain again.** The
moment a teammate is blocked by something only one person can fix, the tooling costs more
than it returns — whatever it is.

The failure mode is specific: a gate you clear in thirty seconds becomes a wall a teammate
cannot climb at all, and their only available response is to route around it. `--no-verify`,
then "just merge it", then "can you look at this?" — and every piece of work queues behind
one person, who becomes the bottleneck and then the villain. That is a team-dynamics
problem, not a tooling problem, which is why more tooling cannot fix it.

Five rules, adopted before the first gate ships:

1. **No gate ships until a teammate has hit it, fixed it alone, and can say what it was
   for.** If they need you, the gate is not ready — its message is wrong, not their
   understanding.
2. **Every failure names the command that fixes it**, and most failures have one.
   `make format` must make the majority of format-check failures disappear.
3. **Warn-only for two weeks, then hard-fail — and only in CI, never in front of a commit.**
   A gate that has never been red for anyone but you has not earned the right to block.
4. **Cap the local gate at two minutes and CI at five.** Past that, people stop running it
   before pushing and find out in a PR, in front of everyone — the most expensive place.
5. **Give the Makefile away.** Require each teammate to add one target in the first
   fortnight, however trivial. Someone who has edited a file will read it when it breaks.

And agree the deadline escape hatch **out loud, in advance**: in the last 72 hours,
working-but-red beats blocked-and-clean, and nobody has to ask permission to say so.
