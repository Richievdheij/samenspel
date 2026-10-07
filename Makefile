# laravel-starter-template — the one front door for this repository.
#
# `make` on its own prints the grouped help below. That list is derived from the
# `##` docstrings on the targets themselves, so it cannot drift away from them.
#
# Requirements: GNU make (macOS ships 3.81 and every construct here works on it),
# awk, git 2.9+, PHP 8.4+, Node 24+.
#
# Windows: use WSL2. That is not a preference — the native Windows make spawns
# cmd.exe for every recipe line and ignores a POSIX SHELL it cannot resolve,
# silently, so `vendor/bin/pint` comes back as "'vendor' is not recognized".
# Measured on a windows-latest runner before that job was removed. Under WSL2 this
# file is running on Linux and none of it is a special case.

.DEFAULT_GOAL := help

# make spawns its OWN shell for every recipe line, so the interpreter is stated
# rather than inherited. Every recipe here is POSIX sh.
SHELL := /bin/sh

# `verify`'s prerequisites are NOT independent: build writes and clears
# bootstrap/cache while test and arch boot the application from the same
# directory. Under `make -j` a suite can load a half-written config cache, or run
# against a cached configuration that bypasses every <env> setting in phpunit.xml.
# The gate takes five seconds serially; there is nothing to win here.
.NOTPARALLEL:

# ─── Environment ────────────────────────────────────────────────────────────
#
# Laravel loads `.env`, never `.env.local`, unless APP_ENV is already set in the
# process environment. Importing the Node `.env.$(ENV)` scheme blindly gives you
# a happily-created `.env.local`, a green `make verify`, and `php artisan`
# reading no configuration at all. Hence the suffix-only-when-not-local form.
ENV      ?= local
ENV_FILE := .env$(if $(filter local,$(ENV)),,.$(ENV))

# `set -a` exports every following assignment, so the recipe's child processes
# inherit them. Written as one line because each recipe line is its own shell.
LOAD_ENV := set -a; . ./$(ENV_FILE); set +a;

# For local this is the create-if-missing FILE target. For any other environment
# it is the phony `env` target, which re-validates every time it runs.
#
# The difference matters: the production path writes the scaffold and then exits 1
# listing every value still empty. A file-existence guard is permanently satisfied
# from then on by a file the script itself declared invalid, so the second run of
# any dependent target proceeds with missing credentials and no complaint.
ENV_GUARD := $(if $(filter local,$(ENV)),$(ENV_FILE),env)

# Placeholders so `make build` and `make verify` run with no infrastructure and
# no real application key. `$$` reaches the shell as a single `$`, so these are
# shell parameter-expansion defaults: a real value in the environment wins.
BUILD_ENV := APP_ENV=production APP_DEBUG=false \
             APP_KEY=$${APP_KEY:-base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=} \
             DB_CONNECTION=$${DB_CONNECTION:-sqlite}

# `make integration` runs against MySQL, and receives ONLY the five database
# variables — never the whole env file. scripts/db-env.mjs explains why at
# length; the short version is that PHPUnit does not overwrite a variable the
# process already has, so sourcing .env would silently defeat every test setting
# in phpunit.xml and the POST half of the suite would fail with 419.
#
# `=` and not `:=`: lazily expanded, so node runs when this gate runs rather than
# on every `make help`.
DB_CREDS = $(shell node scripts/db-env.mjs)

# ─── Logging ────────────────────────────────────────────────────────────────
#
# These are SHELL argument prefixes, used as `@$(log) "message"`. They are not
# make functions: `$(call log,…)` splits its argument on the first comma, so any
# message containing one would die naming no target at all.
log = printf "\033[1;36m▶\033[0m %s\n"
ok  = printf "\033[1;32m✓\033[0m %s\n"
err = printf "\033[1;31m✗\033[0m %s\n"

# ─── .PHONY ─────────────────────────────────────────────────────────────────
#
# Every target is named here. A target left off is compared against a same-named
# *directory* and make quietly does nothing — a green run that ran nothing. In a
# Laravel repo `test build config database routes public storage` are all real
# directories, so this list is load-bearing rather than tidy.
.PHONY: help install env git-hooks status \
        dev stop fresh migrate seed migration erd \
        format format-check lint analyse arch checks rector rector-check \
        test test-one coverage integration browser build clean \
        verify ci audit secrets-scan commit-messages

##@ Workspace

help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"} \
		NR == FNR {if ($$0 ~ /^[a-zA-Z0-9_-]+:.*##/ && length($$1) > w) w = length($$1); next} \
		FNR == 1 {printf "\n\033[1mlaravel-starter-template\033[0m — make <target>\n"} \
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

# NOT `install: $(ENV_FILE)`. That prerequisite would converge the env file before
# composer had run, and `make env` generates the application key with artisan —
# which needs vendor/autoload.php. On a fresh clone that fails before anything is
# installed. The env file is created at the END of the recipe instead, once both
# dependency trees exist. `make env` also writes APP_KEY, and only while it is
# empty: an unconditional `key:generate` here would rotate the key on every
# re-install, logging everyone out and orphaning whatever was encrypted with it.
#
# Everything after that is what lets Herd serve the site with nothing else
# running. The session, cache and queue drivers are all `database`, so without a
# migrated database the first request is a QueryException; and Laravel takes the
# assets from public/hot while Vite runs and from public/build otherwise, so
# without a build every page is a ViteManifestNotFoundException.
#
# Every step is safe to repeat: touch leaves an existing file alone, migrate skips
# what has run, and the seeder only creates what is absent.
#
# The touch mirrors the default in config/database.php. It is explicit because
# artisan only creates a missing SQLite file when prompted or under --force, and
# --force also waives production's confirmation. The database steps are local
# only: a production schema changes through a deliberate `make migrate`.
install: git-hooks ## Install everything a fresh clone needs — and say which URL to open
	@$(log) "Installing PHP dependencies…"
	@composer install --no-interaction --prefer-dist
	@$(log) "Installing frontend dependencies…"
	@npm ci
	@$(MAKE) --no-print-directory env
ifeq ($(ENV),local)
	@$(log) "Preparing the database…"
	@$(LOAD_ENV) [ "$${DB_CONNECTION:-sqlite}" != sqlite ] || touch "$${DB_SQLITE_DATABASE:-database/database.sqlite}"
	@$(LOAD_ENV) php artisan migrate --seed --no-interaction
endif
	@$(log) "Building the frontend assets…"
	@npm run build
	@$(ok) "Installed — 'make dev' adds live Vite, the queue and the logs"
	@node scripts/site.mjs

env: ## Create or converge the environment file — make env [ENV=production]
	@node scripts/env.mjs $(ENV)

git-hooks: ## Point git at .githooks and load the commit template
	@git config core.hooksPath .githooks
	@git config commit.template .gitmessage
	@$(ok) "Git hooks armed — commit messages are checked"

status: ## What is running, read-only — never starts, migrates or seeds anything
	@node scripts/status.mjs

# A guard, not a copy rule. NEVER write `$(ENV_FILE): .env.example` — as an
# ordinary prerequisite, every edit to the template makes it newer than your real
# env file and the next dependent target overwrites your secrets with blanks.
$(ENV_FILE):
	@node scripts/env.mjs $(ENV)

##@ Develop

# Processes only: no migration, no seed, nothing that touches the database. A dev
# loop that changes the schema as a side effect of starting is one you can no
# longer start casually on a branch you are only looking at — `make migrate` is
# the one door for that.
#
# `php artisan dev` runs Vite, the queue listener and the log tail, plus
# `artisan serve` unless Herd already serves this directory. That decision lives
# in AppServiceProvider, next to the command it configures.
#
# scripts/site.mjs runs first: it prints the URL to open, which neither artisan
# nor Herd says anywhere, and refuses when this project's Vite already runs — a
# second Vite fails on the port and its exit handler deletes the first one's
# public/hot, silently ending live reload for the session that was working.
dev: $(ENV_GUARD) ## Live Vite, queue and logs — plus artisan serve when Herd is not serving the site
	@node scripts/site.mjs --dev
	@$(log) "Starting the dev processes — Ctrl-C stops them all"
	@$(LOAD_ENV) php artisan dev

stop: ## End this folder's make dev session and free the project's ports
	@node scripts/ports.mjs --kill

migrate: $(ENV_GUARD) ## Run pending migrations — after pulling one; neither dev nor Herd ever migrates
	@$(LOAD_ENV) php artisan migrate

seed: $(ENV_GUARD) ## Seed the database
	@$(LOAD_ENV) php artisan db:seed

fresh: $(ENV_GUARD) ## Drop every table, migrate and seed — make fresh yes=1
	@[ -n "$(yes)" ] || { $(err) "Usage: make fresh yes=1 (this drops every table)"; exit 1; }
	@$(LOAD_ENV) php artisan migrate:fresh --seed

migration: ## Generate a migration — make migration name=<name>
	@[ -n "$(name)" ] || { $(err) "Usage: make migration name=<name>"; exit 1; }
	@php artisan make:migration "$(name)"

# No env guard: the diagram is drawn from SQLite in memory, never from .env, so
# every machine draws the same one. tests/Feature/ErdTest.php fails until it is
# redrawn after a migration changes.
erd: ## Redraw the Mermaid ERD in docs/erd.md from the migrations
	@php artisan erd:generate

##@ Quality

format: ## Rewrite formatting in place — fixes most format-check failures
	@vendor/bin/pint
	@npx prettier --write . --log-level warn
	@$(ok) "Formatted"

format-check: ## Check formatting, manifests and lockfiles
	@vendor/bin/pint --test
	@composer validate --strict
	@npx prettier --check .

# PHP has no eslint-shaped tool and does not need one: what eslint's type-aware
# rules catch, PHPStan catches, and what its stylistic rules catch, Pint fixes.
# So `lint` is the JavaScript half and `analyse` is the PHP half.
lint: ## Lint JavaScript
	@npx eslint .

# There is deliberately no `typecheck` target. This template has no TypeScript,
# so the frontend has no type checker, and a target that runs nothing and exits 0
# would read as coverage on every future audit. PHP's types are `make analyse`.
# The omission is recorded in docs/toolchain.md.
analyse: ## Static analysis of PHP (Larastan on PHPStan, level 9)
	@vendor/bin/phpstan analyse --memory-limit=1G --no-progress

rector: ## Apply automated refactorings, then reformat
	@vendor/bin/rector process --no-progress-bar
	@$(MAKE) --no-print-directory format

rector-check: ## Refuse code a refactoring would change — make rector fixes it
	@vendor/bin/rector process --dry-run --no-progress-bar

arch: ## Architecture and naming rules (Pest arch())
	@php artisan test --testsuite=Arch

checks: ## The repo invariants (scripts/check-*.mjs)
	@$(log) "Checking the repo invariants…"
	@node scripts/check-commit-message.mjs --cases
	@node scripts/check-line-endings.mjs
	@node scripts/check-env-contract.mjs
	@node scripts/check-env-usage.mjs
	@node scripts/check-migration-hygiene.mjs
	@node scripts/check-doc-links.mjs
	@node scripts/check-ci-parity.mjs
	@$(ok) "The repo invariants hold"

test: ## Run the unit and feature suites against SQLite in memory
	@php artisan test --testsuite=Unit,Feature

test-one: ## Run one test — make test-one filter=<name>
	@[ -n "$(filter)" ] || { $(err) "Usage: make test-one filter=<name>"; exit 1; }
	@php artisan test --filter "$(filter)"

coverage: ## Run the suite with coverage — needs Xdebug or PCOV
	@php artisan test --coverage

browser: build ## One real-browser smoke test — depends on build, and that is the point
	@php artisan test --testsuite=Browser

integration: ## The same suite against MySQL — fails loudly when none is reachable
	@$(DB_CREDS) node scripts/require-database.mjs
	@$(DB_CREDS) php artisan migrate --force
	@$(DB_CREDS) php artisan test --testsuite=Feature

##@ Build

# The artisan caches are written to PROVE they can be written offline, and because
# view:cache compiles every Blade file and is the only syntax check they get. They
# must NEVER survive this target, and the clear therefore runs from a trap rather
# than as a later recipe line.
#
# Without the trap, a failing `npm run build` — or one Ctrl-C — aborts the recipe
# and leaves bootstrap/cache/config.php on disk, baked with BUILD_ENV. Laravel
# then stops reading .env entirely: APP_ENV=production, APP_DEBUG=false and the
# all-zero placeholder APP_KEY become the running configuration, `make dev` serves
# production with a publicly known key, and neither `git status` nor `make status`
# shows anything, because bootstrap/cache is gitignored.
build: ## Build the shippable assets, offline
	@npm run build
	@$(BUILD_ENV) sh -c 'trap "php artisan optimize:clear >/dev/null 2>&1" EXIT INT TERM; \
		php artisan config:cache && php artisan route:cache && php artisan view:cache'
	@$(ok) "Built"

clean: ## Remove build output and caches
	@rm -rf public/build public/hot
	@php artisan optimize:clear
	@$(ok) "Cleaned"

##@ Gates

commit-messages: ## Check this branch's commit messages
	@node scripts/check-commit-message.mjs --range $(COMMIT_RANGE)

audit: ## Fail on a vulnerable dependency — needs the network
	@composer audit --locked
	@npm audit --audit-level=high

secrets-scan: ## Scan the whole history for secrets — needs Docker
	@node scripts/secrets-scan.mjs

# >>> bootstrap:verify — the single gate list. CI calls these targets one per
# step; it never restates what they are. Ordering is cheapest-first, so the run
# fails on the fastest thing that is wrong.
verify: commit-messages format-check checks lint analyse rector-check arch test build ## Everything that needs no service — the gate to run before pushing
	@$(ok) "All checks passed"

ci: verify integration browser audit secrets-scan ## The whole pipeline: verify plus everything needing a database or the network
	@$(ok) "Everything CI runs has passed"
# <<< bootstrap:verify
