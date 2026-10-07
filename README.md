# laravel-starter-template

A Laravel 13 starter template with one front door, one gate list, and a checked
environment contract. Built so that after the first afternoon nobody has to think
about the toolchain again.

## Three commands

```bash
git clone <this repository> && cd laravel-starter-template
make install
make dev
```

`make install` is the one-time bootstrap: both dependency trees, the git hooks, a
`.env` with a generated application key, a migrated and seeded SQLite database,
and a first asset build. Running it again is safe. It ends by printing the URL
to open.

`make dev` is for active frontend work: it runs Vite with live reload (plus the
queue listener and the log tail) and never touches the database. It, too, starts
by printing the URL. Leave it running in one terminal; Ctrl-C or `make stop`
ends it.

## Opening the site

`make status` shows the URL on its `site` line at any time. Which URL depends on
whether Herd serves the folder:

| Situation                                  | Open                    | Runs without `make dev`?  |
| ------------------------------------------ | ----------------------- | ------------------------- |
| Herd serves the folder (linked or parked)  | `https://<folder>.test` | Yes, with built assets    |
| No Herd, or Herd does not serve the folder | `http://localhost:8000` | No — `make dev` serves it |

**Herd serves a folder only when it is linked, or sits _directly_ inside a parked
directory.** `~/Herd/my-app` is served; `~/Herd/development/my-app` is not, and
`https://my-app.test` then answers Herd's "Site not found". Link it once, from
inside the folder:

```bash
herd link --secure my-app     # serves this folder at https://my-app.test
```

Then set `APP_URL=https://my-app.test` in `.env` so links in mails point at the
site — `make dev` warns while the two differ.

Under Herd there is deliberately no `localhost` address: Herd is the server, and
`make dev` adds only Vite, whose port (`5173`) is not a page to open. While it
runs, Herd's pages load the live assets; stop it and they fall back to the built
ones.

Run one `make dev` per project. A second would fail on Vite's port and, on its
way out, switch off live reload for the first — so `make dev` refuses and says
which process holds the port.

Closing the terminal does not end `make dev`: its queue listener and log tail run
on in the background. `make status` shows a running session on its `make dev`
line, and `make stop` ends all of it — Vite, the queue listener, the log tail and
`artisan serve` — for this folder only.

| After you pull…            | Run                                      |
| -------------------------- | ---------------------------------------- |
| a migration                | `make migrate`                           |
| SCSS or JavaScript changes | `make build`, or keep `make dev` running |

Then, before you push:

```bash
make verify
```

Run `make` on its own for the full list of targets. Every failure names the
command that fixes it, and `make format` clears most formatting failures on its
own.

## Where you work

**Nobody commits to `main`.** `main` is always deployable; `develop` is where work
is integrated; a feature branch is cut from `develop` and merged back into it.

```bash
git switch develop && git pull
git switch -c feature/what-you-are-doing
# ... work ...
make verify          # about seven seconds; the pre-push hook runs it too
git push -u origin feature/what-you-are-doing
```

The full model, including hotfixes and how two people share one branch, is in
[docs/branching.md](docs/branching.md).

## Requirements

| Tool     | Version | Notes                                                      |
| -------- | ------- | ---------------------------------------------------------- |
| PHP      | 8.4+    | Laravel Herd, or any PHP with the extensions Laravel needs |
| Composer | 2.x     |                                                            |
| Node     | 24      | The version is in [.nvmrc](.nvmrc)                         |
| npm      | 11+     |                                                            |
| make     | 3.81+   | macOS ships this; on Windows use WSL2, see below           |

**Windows: use WSL2.** Install it once with `wsl --install`, then clone and work
inside it. Everything on this page then applies unchanged, because WSL2 _is_
Linux.

Git Bash with `choco install make` does not work, and it fails in a way worth
knowing about: the native Windows make spawns `cmd.exe` for every recipe line and
silently ignores a POSIX shell it cannot resolve, so `vendor/bin/pint` comes back
as `'vendor' is not recognized as an internal or external command` — which reads
like a missing dependency and is really the wrong interpreter. This was measured
on a `windows-latest` runner, not assumed.

## The seeded account

`make fresh yes=1` drops every table, migrates and seeds. It creates one known
local account:

```
email:    test@example.com
password: password
```

Log in with them at `/login`; the account's email address is already verified.

These credentials are written down on purpose. A shared, obviously-local account
is safer than five people each inventing one, and one of those reaching
production. It exists only in seed data and only in your local database.

## Authentication

Registration, login, logout, password reset, email verification, password
confirmation and a profile page (update details, change password, delete the
account) work straight after `make install`. Functionally it is what
`php artisan breeze:install blade` gives you — the same route names, URIs,
middleware and behaviour — written in this template's own Blade components,
SCSS and vanilla JavaScript, with no Breeze, Tailwind or Alpine and no extra
dependency.

| Where                                     | What                                         |
| ----------------------------------------- | -------------------------------------------- |
| `routes/auth.php`                         | Every auth route, named as Breeze names them |
| `app/Http/Controllers/Auth/`              | One controller per flow                      |
| `app/Http/Requests/Auth/LoginRequest.php` | Login, rate-limited to five attempts         |
| `resources/views/auth/`, `profile/`       | The pages, on the guest and app layouts      |
| `lang/{en,nl}/*.php`, `lang/{en,nl}.json` | Every message, including the mails, in both  |

### Turning on email verification

Off by default, as in Breeze. In `app/Models/User.php`, remove the `//` before
`implements` on the class line:

```php
class User extends Authenticatable implements \Illuminate\Contracts\Auth\MustVerifyEmail
```

`make format` then turns the class name into an import. From that moment a new
account is mailed a verification link, `/dashboard` (middleware `verified`)
stays closed until it is followed, and the profile page offers to re-send it.

### Where the mails go locally

`.env.example` sets `MAIL_MAILER=log`, so nothing is sent: the password reset
and verification mails are written to `laravel.log` in `storage/logs/`. Find the link
with:

```bash
grep -o 'https\?://[^ )"<]*reset-password[^ )"<]*' storage/logs/laravel.log | tail -1
grep -o 'https\?://[^ )"<]*verify-email[^ )"<]*' storage/logs/laravel.log | tail -1
```

With Herd Pro, set `MAIL_MAILER=smtp` instead — `MAIL_HOST=127.0.0.1` and
`MAIL_PORT=2525` already point at Herd's mail service — and the mails appear,
rendered and clickable, under **Mail** in Herd.

## Layout

| Path                | What lives there                                              |
| ------------------- | ------------------------------------------------------------- |
| `app/`              | Models, controllers, form requests, policies, resources       |
| `resources/views/`  | Blade: a component layout, partials and anonymous components  |
| `resources/scss/`   | Tokens, base, layout, components, utilities                   |
| `database/`         | Migrations, factories with states, seeders                    |
| `tests/`            | Pest — Unit, Feature and Arch suites                          |
| `scripts/`          | The check harness and the runtime helpers                     |
| `docs/toolchain.md` | Why the toolchain is shaped this way, and what it does not do |
| `docs/erd.md`       | The data model as a Mermaid diagram, generated by `make erd`  |

## What is in the box

No demo. `/` renders `resources/views/home.blade.php`, which exists so a fresh
clone serves something and the layout, SCSS and Vite manifest are exercised by a
real request. Replace it.

The conventions are carried by the structure rather than by example code:
`app/Http/Requests`, `app/Http/Resources` and `app/Policies` are present and
empty, the Blade components and SCSS layers are ready to use, and `make arch`
enforces the naming rules on the first class you add to any of them.

## Styling

No Tailwind. SCSS with design tokens as CSS custom properties on `:root`, BEM
class names, and `@use`/`@forward` layering. Add a token before using a raw value:
a hex code in a component is the thing the token file exists to prevent.

## Licence

**All rights reserved.** This is private work, not open source. There is no
permission to copy, use, modify or redistribute it, and the repository being
technically reachable does not grant one. See [LICENSE](LICENSE).

The Laravel skeleton this is built on stays MIT (© Taylor Otwell) — that part
cannot be relicensed and is not claimed. The LICENSE file says which files those
are.

If you want to use this, ask.

## Documentation

- [docs/branching.md](docs/branching.md) — `main`, `develop` and feature branches:
  what goes where, and why nobody commits to `main`.
- [docs/erd.md](docs/erd.md) — the data model as a Mermaid diagram that GitHub
  renders. `make erd` redraws it after a migration; write your notes around it.
- [docs/toolchain.md](docs/toolchain.md) — the gates, the decisions behind them,
  and the things this template deliberately does not set up.
- [TOOLCHAIN-BOOTSTRAP.md](TOOLCHAIN-BOOTSTRAP.md) — the specification this
  toolchain was built from, kept verbatim as the record.
- [AGENTS.md](AGENTS.md) — the map for coding agents.
