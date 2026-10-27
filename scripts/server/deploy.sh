#!/usr/bin/env bash
#
# Puts the newest main live on the server. Run from the laptop, after a push:
#
#   make deploy
#
# It arrives over SSH on stdin and runs as the login user, who has sudo. The
# application commands run as the samenspel user, who owns the checkout. These
# are the steps docs/otap.md describes, in that order.

set -euo pipefail

APP_USER=samenspel
APP_DIR=/var/www/samenspel
BRANCH=main
PHP_VERSION=8.4

log() { printf '\033[1;36m▶\033[0m %s\n' "$*"; }
ok() { printf '\033[1;32m✓\033[0m %s\n' "$*"; }
as_app() { sudo -u "$APP_USER" -H "$@"; }

cd "$APP_DIR"

# The server never has changes of its own, so it follows main exactly.
log "Fetching $BRANCH"
as_app git fetch --quiet origin "$BRANCH"
as_app git reset --quiet --hard "origin/$BRANCH"
log "Now at $(as_app git log -1 --format='%h %s')"

log "Installing PHP dependencies, without the development tools"
as_app composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --quiet

log "Building the frontend assets"
as_app npm ci --no-audit --no-fund --loglevel=error
as_app npm run build --silent

# Visitors see the maintenance page while the schema changes, and the trap brings
# the site back up even when a step below fails.
as_app php artisan down --retry=15 >/dev/null
trap 'as_app php artisan up >/dev/null' EXIT

log "Migrating the database"
as_app php artisan migrate --force
[ -L public/storage ] || as_app php artisan storage:link
as_app php artisan optimize >/dev/null

log "Reloading PHP and restarting the queue worker"
sudo systemctl reload "php$PHP_VERSION-fpm"
as_app php artisan queue:restart >/dev/null
sudo systemctl restart samenspel-queue

ok "Deployed $(as_app git log -1 --format='%h') to $(as_app sed -n 's/^APP_URL=//p' .env)"
