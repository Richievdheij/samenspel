#!/usr/bin/env bash
#
# Prepares a fresh Ubuntu 24.04 server for Samenspel. Run once, from the laptop:
#
#   make provision domain=<domain>
#
# It arrives over SSH on stdin and runs as root. Every step is safe to repeat: a
# package already installed, a user already created or a config already written is
# left as it is, and the database password and APP_KEY are generated only once.
#
# What it does not do is put the application live; `make deploy` does that, and
# is the command for every release after this one.

set -euo pipefail

DOMAIN="${1:?Usage: provision.sh <domain>}"
APP_USER=samenspel
APP_DIR=/var/www/samenspel
REPOSITORY=https://github.com/Richievdheij/samenspel.git
BRANCH=main
PHP_VERSION=8.4

export DEBIAN_FRONTEND=noninteractive

log() { printf '\033[1;36m▶\033[0m %s\n' "$*"; }
ok() { printf '\033[1;32m✓\033[0m %s\n' "$*"; }

# ─── Memory ─────────────────────────────────────────────────────────────────
#
# The Always Free AMD instance has 1 GB. Composer and the Vite build each want
# more than that for a moment, so 2 GB of swap absorbs the peak; swappiness 10
# keeps the running site in RAM the rest of the time.
if [ ! -f /swapfile ]; then
	log "Adding 2 GB of swap"
	fallocate -l 2G /swapfile
	chmod 600 /swapfile
	mkswap /swapfile >/dev/null
	swapon /swapfile
	echo '/swapfile none swap sw 0 0' >>/etc/fstab
fi
echo 'vm.swappiness=10' >/etc/sysctl.d/99-swappiness.conf
sysctl -q -p /etc/sysctl.d/99-swappiness.conf
timedatectl set-timezone Europe/Amsterdam

# ─── Package sources ────────────────────────────────────────────────────────
#
# Ubuntu 24.04 ships PHP 8.3 and MySQL 8.0. The application needs PHP 8.4, and
# MySQL 8.4 is the version CI tests against, so both come from their maintainers'
# repositories. Every source is pinned to its own signing key.
log "Adding the package sources for PHP $PHP_VERSION, MySQL 8.4 and Node 24"
# Set aside until its key is refreshed below, or this first update fails on it.
rm -f /etc/apt/sources.list.d/mysql.list
apt-get update -q
apt-get install -y -q ca-certificates curl gnupg software-properties-common >/dev/null
add-apt-repository -y ppa:ondrej/php >/dev/null
install -d -m 0755 /etc/apt/keyrings
# Always fetched, never kept: MySQL extends this key's expiry by republishing it,
# and a stale copy fails every `apt-get update` with EXPKEYSIG. The fingerprint
# check is what makes refetching it safe.
MYSQL_KEY_FINGERPRINT=BCA43417C3B485DD128EC6D4B7B3B788A8D3785C
curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --dearmor --yes -o /etc/apt/keyrings/mysql.gpg
gpg --show-keys --with-colons /etc/apt/keyrings/mysql.gpg | grep -q "^fpr:::::::::$MYSQL_KEY_FINGERPRINT:" ||
	{ echo 'The MySQL signing key does not have the expected fingerprint' >&2; exit 1; }
echo 'deb [signed-by=/etc/apt/keyrings/mysql.gpg] http://repo.mysql.com/apt/ubuntu noble mysql-8.4-lts' \
	>/etc/apt/sources.list.d/mysql.list
[ -f /etc/apt/keyrings/nodesource.gpg ] ||
	curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
echo 'deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_24.x nodistro main' \
	>/etc/apt/sources.list.d/nodesource.list
apt-get update -q

# An empty root password makes MySQL authenticate root through the Unix socket:
# only `sudo mysql` on this machine gets in, and there is no password to leak.
echo 'mysql-community-server mysql-community-server/root-pass password' | debconf-set-selections
echo 'mysql-community-server mysql-community-server/re-root-pass password' | debconf-set-selections

log "Installing Nginx, PHP $PHP_VERSION, MySQL 8.4, Node 24 and the security tools"
apt-get install -y -q \
	nginx certbot python3-certbot-nginx git unzip make \
	"php$PHP_VERSION-fpm" "php$PHP_VERSION-cli" "php$PHP_VERSION-mysql" "php$PHP_VERSION-mbstring" \
	"php$PHP_VERSION-xml" "php$PHP_VERSION-curl" "php$PHP_VERSION-zip" "php$PHP_VERSION-intl" \
	"php$PHP_VERSION-bcmath" "php$PHP_VERSION-opcache" \
	mysql-server nodejs fail2ban unattended-upgrades iptables-persistent >/dev/null
mysql --version | grep -q 'Ver 8\.4' || { echo "Expected MySQL 8.4, got: $(mysql --version)" >&2; exit 1; }

# The installer is checked against the signature Composer publishes, so a
# tampered download stops here instead of running as root.
if [ ! -x /usr/local/bin/composer ]; then
	log "Installing Composer"
	expected="$(curl -fsSL https://composer.github.io/installer.sig)"
	curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
	actual="$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")"
	[ "$expected" = "$actual" ] || { echo 'The Composer installer does not match its signature' >&2; exit 1; }
	php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
	rm /tmp/composer-setup.php
fi

# ─── Security ───────────────────────────────────────────────────────────────
log "Hardening SSH, the firewall and updates"

# Keys only, never root. Oracle's image already refuses passwords; this states it.
cat >/etc/ssh/sshd_config.d/99-samenspel.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
EOF
sshd -t && systemctl reload ssh

# Oracle's image ends INPUT with a REJECT, so a port opened only in the cloud
# console still never reaches Nginx. Insert 80 and 443 above that rule.
for port in 80 443; do
	if ! iptables -C INPUT -p tcp -m state --state NEW --dport "$port" -j ACCEPT 2>/dev/null; then
		reject_line="$(iptables -L INPUT --line-numbers | awk '/REJECT/ { print $1; exit }')"
		iptables -I INPUT "${reject_line:-1}" -p tcp -m state --state NEW --dport "$port" -j ACCEPT
	fi
done
netfilter-persistent save >/dev/null

# fail2ban's Ubuntu defaults already jail repeated SSH failures; it only has to run.
systemctl enable --now fail2ban >/dev/null

# Security updates install themselves every night, including those for PHP,
# which come from the PPA rather than from Ubuntu.
cat >/etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF
cat >/etc/apt/apt.conf.d/51samenspel-upgrades <<'EOF'
Unattended-Upgrade::Origins-Pattern:: "origin=LP-PPA-ondrej-php";
EOF

# ─── MySQL ──────────────────────────────────────────────────────────────────
#
# Small enough for 1 GB next to PHP: no performance schema, a 128 MB buffer pool
# and a connection cap well above what five PHP workers and the queue can open.
# It listens on localhost only, so the database is never reachable from outside.
cat >/etc/mysql/conf.d/samenspel.cnf <<'EOF'
[mysqld]
bind-address = 127.0.0.1
mysqlx = OFF
performance_schema = OFF
innodb_buffer_pool_size = 128M
max_connections = 30
EOF
systemctl restart mysql

# ─── The application user and checkout ──────────────────────────────────────
#
# The site runs as its own user, not as www-data: a second project on this
# server gets a second user, and neither can read the other's .env.
id "$APP_USER" >/dev/null 2>&1 || useradd --create-home --shell /bin/bash "$APP_USER"
install -d -o "$APP_USER" -g "$APP_USER" -m 0755 "$APP_DIR"
if [ ! -d "$APP_DIR/.git" ]; then
	log "Cloning $BRANCH into $APP_DIR"
	sudo -u "$APP_USER" -H git clone --quiet --branch "$BRANCH" "$REPOSITORY" "$APP_DIR"
fi

# ─── Database and environment file ──────────────────────────────────────────
ENV_FILE="$APP_DIR/.env.production"

# Read back a value an earlier run wrote, so a rerun keeps the same password
# and key instead of locking the application out of its own data.
existing() {
	[ -f "$ENV_FILE" ] || return 0
	sed -n "s/^$1=//p" "$ENV_FILE" | head -n 1 | sed 's/^"\(.*\)"$/\1/'
}

DB_PASSWORD="$(existing DB_PASSWORD)"
[ -n "$DB_PASSWORD" ] || DB_PASSWORD="$(openssl rand -hex 24)"
APP_KEY="$(existing APP_KEY)"
[ -n "$APP_KEY" ] || APP_KEY="base64:$(openssl rand -base64 32)"

log "Creating the database and its user"
mysql <<EOF
CREATE DATABASE IF NOT EXISTS samenspel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'samenspel'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
ALTER USER 'samenspel'@'localhost' IDENTIFIED BY '$DB_PASSWORD';
GRANT ALL PRIVILEGES ON samenspel.* TO 'samenspel'@'localhost';
EOF

# Only the values this server decides. scripts/env.mjs then renders the full file
# from .env.production.example around them and lists whatever is still empty.
# MAIL_MAILER=log until an SMTP account exists: password-reset mail then lands in
# storage/logs instead of failing, and env.mjs keeps listing the MAIL_* still open.
if [ ! -f "$ENV_FILE" ]; then
	sudo -u "$APP_USER" tee "$ENV_FILE" >/dev/null <<EOF
APP_KEY=$APP_KEY
APP_URL=https://$DOMAIN
DB_HOST=127.0.0.1
DB_DATABASE=samenspel
DB_USERNAME=samenspel
DB_PASSWORD=$DB_PASSWORD
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@$DOMAIN
EOF
fi
(cd "$APP_DIR" && sudo -u "$APP_USER" node scripts/env.mjs production) || true
sudo -u "$APP_USER" ln -sfn .env.production "$APP_DIR/.env"

# ─── PHP-FPM ────────────────────────────────────────────────────────────────
#
# One pool for this site, running as its user. `ondemand` starts workers only
# when requests arrive, which is what a 1 GB machine can afford.
cat >"/etc/php/$PHP_VERSION/fpm/pool.d/samenspel.conf" <<EOF
[samenspel]
user = $APP_USER
group = $APP_USER
listen = /run/php/samenspel.sock
listen.owner = www-data
listen.group = www-data
pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 30s
pm.max_requests = 500
EOF
rm -f "/etc/php/$PHP_VERSION/fpm/pool.d/www.conf"

cat >"/etc/php/$PHP_VERSION/fpm/conf.d/99-samenspel.ini" <<'EOF'
expose_php = Off
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
opcache.enable = 1
opcache.memory_consumption = 64
opcache.validate_timestamps = 1
opcache.revalidate_freq = 2
EOF
systemctl restart "php$PHP_VERSION-fpm"

# ─── Nginx ──────────────────────────────────────────────────────────────────
#
# The document root is public/, so .env, the source and vendor/ have no URL.
# Certbot adds the HTTPS server block and the redirect to this file below.
if [ ! -f /etc/nginx/sites-available/samenspel ]; then
	cat >/etc/nginx/sites-available/samenspel <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name $DOMAIN;
    root $APP_DIR/public;
    index index.php;
    charset utf-8;
    client_max_body_size 10M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ ^/index\.php(/|$) {
        fastcgi_pass unix:/run/php/samenspel.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOF
fi
cat >/etc/nginx/conf.d/samenspel-hardening.conf <<'EOF'
server_tokens off;
gzip_types text/css application/javascript application/json image/svg+xml;
EOF
ln -sfn /etc/nginx/sites-available/samenspel /etc/nginx/sites-enabled/samenspel
rm -f /etc/nginx/sites-enabled/default
nginx -t -q
systemctl reload nginx

# A Let's Encrypt certificate, the HTTP-to-HTTPS redirect and HSTS. The certbot
# package installs a timer that renews it before it expires; nothing to schedule.
if [ ! -d "/etc/letsencrypt/live/$DOMAIN" ]; then
	log "Requesting the HTTPS certificate for $DOMAIN"
	certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos --register-unsafely-without-email \
		--redirect --hsts --quiet
fi

# ─── Background work ────────────────────────────────────────────────────────
#
# QUEUE_CONNECTION=database puts jobs in a table, so something has to take them
# out. systemd restarts the worker whenever it exits, which is also how
# `php artisan queue:restart` hands it new code after a deploy.
cat >/etc/systemd/system/samenspel-queue.service <<EOF
[Unit]
Description=Samenspel queue worker
After=network.target mysql.service

[Service]
User=$APP_USER
Group=$APP_USER
WorkingDirectory=$APP_DIR
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable samenspel-queue >/dev/null

# The scheduler (games:sync-igdb at 03:30) and a nightly database backup that
# keeps two weeks. The backup runs as root, which MySQL lets in over the socket.
cat >/usr/local/sbin/samenspel-backup <<'EOF'
#!/bin/sh
set -eu
install -d -m 0700 /var/backups/samenspel
mysqldump --single-transaction --quick samenspel | gzip >"/var/backups/samenspel/samenspel-$(date +%F).sql.gz"
find /var/backups/samenspel -name 'samenspel-*.sql.gz' -mtime +14 -delete
EOF
chmod 0755 /usr/local/sbin/samenspel-backup
cat >/etc/cron.d/samenspel <<EOF
* * * * * $APP_USER cd $APP_DIR && php artisan schedule:run >>/dev/null 2>&1
15 4 * * * root /usr/local/sbin/samenspel-backup
EOF

ok "Server prepared for https://$DOMAIN — run 'make deploy' to put the application live"
