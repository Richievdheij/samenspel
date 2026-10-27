# OTAP: van mijn laptop naar een productieserver

OTAP staat voor Ontwikkeling, Test, Acceptatie en Productie: vier omgevingen
waar dezelfde code doorheen gaat, elk met een eigen configuratie en een eigen
database. Een wijziging bereikt de echte gebruikers pas als hij in de eerdere
omgevingen heeft gewerkt.

## De vier omgevingen in Samenspel

| Omgeving   | Waar                                           | `APP_ENV`    | Database                       |
| ---------- | ---------------------------------------------- | ------------ | ------------------------------ |
| Ontwikkel  | Mijn laptop, via Laravel Herd                  | `local`      | MySQL of SQLite, met seed-data |
| Test       | `php artisan test` en GitHub Actions (CI)      | `testing`    | SQLite in het geheugen         |
| Acceptatie | Een kopie van productie, voor de opdrachtgever | `staging`    | Eigen database, testdata       |
| Productie  | Een eigen server bij Oracle Cloud (Amsterdam)  | `production` | Eigen MySQL-database           |

- **Ontwikkel**: `APP_DEBUG=true`, zodat een fout de volledige stacktrace laat
  zien. De seeders maken testaccounts en demo-events aan.
- **Test**: `phpunit.xml` zet de testomgeving. Elke test krijgt een lege database
  (`RefreshDatabase`), dus tests beïnvloeden elkaar niet. GitHub Actions draait bij
  elke push naar `main` en `develop` dezelfde controles (`.github/workflows/ci.yml`), ook tegen
  MySQL 8.4, de versie van productie.
- **Acceptatie**: dezelfde instellingen als productie, maar met testdata. Hier
  controleert de opdrachtgever een nieuwe versie voordat hij live gaat. Voor dit
  schoolproject heb ik die omgeving niet ingericht.
- **Productie**: de echte gebruikers en de echte gegevens. Hier wordt nooit
  `migrate:fresh` gedraaid en geen testdata geseed. De enige uitzondering is
  `CategorySeeder`, eenmalig: die maakt de vier startcategorieën aan en slaat over
  wat er al is. Samenspel staat live op
  <https://samenspel.158-178-149-229.sslip.io>; hoe die server is ingericht staat
  onderaan.

## Waar de configuratie staat

- **`.env`** bevat alles wat per omgeving verschilt: de database, `APP_KEY`,
  `APP_DEBUG`, mailinstellingen en de IGDB-sleutels. Elke omgeving heeft een eigen
  `.env`, en die staat nooit in git.
- **`.env.example`** en **`.env.production.example`** zijn de sjablonen, zonder
  geheimen. `make env ENV=production` maakt hieruit een productie-`.env` en weigert
  zolang er nog een verplichte waarde leeg is.
- **`config/*.php`** leest de `.env` met `env('NAAM', standaard)`. De rest van de
  code gebruikt `config('app.timezone')` en nooit `env()` direct, omdat `env()` na
  `config:cache` leeg terugkomt.

## Wat er anders moet in productie, en waarom

| Instelling              | Productie                  | Reden                                                                            |
| ----------------------- | -------------------------- | -------------------------------------------------------------------------------- |
| `APP_ENV`               | `production`               | Laravel vraagt dan bevestiging voor gevaarlijke commando's zoals `migrate:fresh` |
| `APP_DEBUG`             | `false`                    | Een foutpagina toont anders code, queries en wachtwoorden uit `.env`             |
| `APP_KEY`               | Nieuw, eenmalig gemaakt    | Versleutelt sessies en cookies; een gelekte sleutel maakt die leesbaar           |
| `APP_URL`               | Het echte `https://`-adres | Links en redirects wijzen anders naar de verkeerde host                          |
| `DB_*`                  | Eigen productiedatabase    | Nooit de ontwikkeldatabase of seed-data                                          |
| `SESSION_SECURE_COOKIE` | `true`                     | De sessiecookie gaat alleen over HTTPS                                           |
| `SESSION_ENCRYPT`       | `true`                     | De sessie-inhoud staat versleuteld in de database                                |
| `LOG_LEVEL`             | `warning`                  | Geen debugregels met mogelijk privégegevens in de logbestanden                   |
| `MAIL_*`                | Een echte SMTP-server      | Lokaal vangt Herd de mail op; in productie moet hij echt verstuurd worden        |

## Eisen aan de server

- PHP 8.4 met de extensies die Laravel nodig heeft (onder andere `pdo_mysql`,
  `mbstring`, `openssl`, `fileinfo`), Composer, en Node om de assets te bouwen.
- MySQL 8.4, met een eigen databasegebruiker die alleen rechten heeft op de
  Samenspel-database.
- Een webserver (Nginx of Apache) waarvan de document root **`public/`** is.
  Alleen `public/index.php` en de gebouwde assets zijn dan via een URL bereikbaar,
  niet `.env` of de broncode.
- HTTPS met een certificaat (bijvoorbeeld Let's Encrypt).
- `storage/` en `bootstrap/cache/` zijn schrijfbaar voor de webserver-gebruiker:
  daar schrijft Laravel logs, sessies, gecachete views en uploads.

## Een deployment, stap voor stap

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- `--no-dev` laat testtools zoals Pest en Larastan weg van de server.
- `npm run build` maakt de gecomprimeerde CSS en JavaScript in de map public/build; in
  productie draait geen Vite-devserver.
- `migrate --force` voert alleen de nieuwe migrations uit. `--force` is nodig
  omdat Laravel in productie anders om bevestiging vraagt.
- `storage:link` maakt de link public/storage naar storage/app/public, zodat geüploade covers zichtbaar
  zijn.
- De drie `cache`-commando's zetten configuratie, routes en views in één bestand,
  zodat Laravel ze niet bij elk verzoek opnieuw hoeft in te lezen.

Daarnaast heeft de server twee achtergrondtaken nodig:

- Een cronjob die elke minuut `php artisan schedule:run` draait. Die start onder
  andere `games:sync-igdb`, elke nacht om 03:30 (`routes/console.php`).
- Een queue-worker (`php artisan queue:work`, bewaakt door een procesbewaker zoals
  Supervisor of systemd), omdat `QUEUE_CONNECTION=database` taken in de database
  zet.

## De productieserver van Samenspel

Samenspel draait op een gratis server uit het "Always Free"-aanbod van Oracle
Cloud, in de regio Amsterdam:

| Onderdeel   | Keuze                                                              |
| ----------- | ------------------------------------------------------------------ |
| Server      | `VM.Standard.E2.1.Micro`: 1 CPU, 1 GB RAM, 2 GB swap, 47 GB schijf |
| Systeem     | Ubuntu 24.04 LTS                                                   |
| Software    | Nginx, PHP 8.4 (FPM), MySQL 8.4, Node 24, Composer                 |
| Adres       | `samenspel.158-178-149-229.sslip.io`                               |
| HTTPS       | Let's Encrypt via Certbot, vernieuwt zichzelf                      |
| Achtergrond | systemd voor de queue-worker, cron voor de scheduler en de backup  |

`sslip.io` is een gratis DNS-dienst: elke naam met een IP-adres erin wijst naar
dat adres. Zo heeft de server een echte hostnaam, en dus een HTTPS-certificaat,
zonder dat ik een domein hoef te kopen. Met een eigen domein verandert alleen
`APP_URL` en het certificaat.

### Twee commando's

Alles wat op de server gebeurt, staat in twee scripts in `scripts/server/`. Ze
gaan via SSH naar de server, dus de versie die draait is altijd die uit de repo.

| Commando                        | Wanneer                      | Wat het doet                                                                                                |
| ------------------------------- | ---------------------------- | ----------------------------------------------------------------------------------------------------------- |
| `make provision domain=<adres>` | Eén keer, op een lege server | Installeert de software, beveiligt de server, maakt de database en `.env.production`, regelt HTTPS          |
| `make deploy`                   | Na elke push naar `main`     | Haalt `main` op en voert de stappen hierboven uit, met de site in onderhoudsmodus zolang de migraties lopen |

Beide scripts zijn veilig om opnieuw te draaien: wat al bestaat blijft staan, en
het databasewachtwoord en de `APP_KEY` worden maar één keer gemaakt. De server
wordt bereikt via een SSH-alias in `~/.ssh/config` op mijn laptop:

```text
Host samenspel
  HostName 158.178.149.229
  User ubuntu
  IdentityFile ~/.ssh/samenspel_oracle
  IdentitiesOnly yes
```

### Hoe de server beveiligd is

- **Netwerk**: alleen poort 22 (SSH), 80 en 443 staan open, zowel in de Security
  List van Oracle als in de firewall op de server zelf. MySQL luistert alleen op
  `127.0.0.1` en is van buitenaf niet te bereiken.
- **SSH**: alleen met een sleutel, nooit met een wachtwoord en nooit als root.
  `fail2ban` blokkeert een IP-adres na herhaalde mislukte pogingen.
- **Updates**: beveiligingsupdates van Ubuntu en PHP installeren zich elke nacht
  zelf (`unattended-upgrades`).
- **Eigen gebruiker**: de site draait als gebruiker `samenspel`, met een eigen
  PHP-FPM-pool. Een tweede project op deze server krijgt een eigen gebruiker en kan
  deze `.env` (rechten `600`) niet lezen.
- **Database**: de gebruiker `samenspel` heeft alleen rechten op de database
  `samenspel`. Root komt alleen binnen via `sudo mysql` op de server zelf, zonder
  wachtwoord dat kan uitlekken.
- **Webserver**: de document root is `public/`, en Nginx weigert alles wat met een
  punt begint, zoals `.env` en `.git`. Hij stuurt http door naar https en zet HSTS
  en de headers `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` en
  `Permissions-Policy`. De versies van Nginx en PHP staan niet in de antwoorden.

### Backups

Elke nacht om 04:15 maakt `/usr/local/sbin/samenspel-backup` een `mysqldump` van
de database in `/var/backups/samenspel/`. Een backup blijft twee weken bewaard.

### Wat nog openstaat

- **Mail**: er is nog geen SMTP-account, dus `MAIL_MAILER=log`. Een mail voor
  "wachtwoord vergeten" komt in `storage/logs/` terecht in plaats van in een inbox.
  `make env ENV=production` blijft daarom `MAIL_HOST`, `MAIL_USERNAME` en
  `MAIL_PASSWORD` als leeg melden, totdat die in `.env.production` zijn ingevuld.
- **Acceptatie**: er is geen aparte acceptatieserver (zie boven).
