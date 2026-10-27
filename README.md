<p align="center">
  <img src="public/brand/samenspel-stacked-light.svg" alt="Samenspel" width="240">
</p>

<p align="center">
  <a href="https://github.com/Richievdheij/samenspel/actions/workflows/ci.yml"><img src="https://github.com/Richievdheij/samenspel/actions/workflows/ci.yml/badge.svg?branch=main" alt="CI"></a>
  <a href="https://github.com/Richievdheij/samenspel/releases/latest"><img src="https://img.shields.io/github/v/release/Richievdheij/samenspel" alt="Laatste release"></a>
  <img src="https://img.shields.io/badge/licentie-alle%20rechten%20voorbehouden-lightgrey" alt="Licentie: alle rechten voorbehouden">
</p>

# Samenspel

**Live:** <https://samenspel.158-178-149-229.sslip.io>

Samenspel is een webapplicatie waarmee je gaming-events en LAN-party's organiseert
en eraan meedoet.

- Gebruikers maken een event aan met een spel, categorie, datum, plek en een
  maximaal aantal deelnemers, en schrijven zich in op events van anderen.
- Bezoekers zoeken op titel en spelnaam en filteren op spel, categorie en
  platform.
- Alleen de organisator kan zijn eigen event wijzigen en openen of sluiten; een
  admin mag elk event sluiten of verwijderen bij misbruik.
- Wie zelf een event wil organiseren, moet zich eerst voor minimaal drie events
  van anderen hebben ingeschreven.
- Een admin beheert de spellen en categorieën.

De applicatie is gebouwd in Laravel.

## Techniek

| Onderdeel | Keuze                                                           |
| --------- | --------------------------------------------------------------- |
| Backend   | Laravel 13 op PHP 8.4                                           |
| Frontend  | Blade, SCSS met design tokens, Vite. Geen Tailwind of framework |
| Database  | MySQL, of SQLite lokaal                                         |
| Tests     | Pest 5, Larastan level 9, Pint en Prettier                      |
| Taal      | Nederlands (`nl`) met Engels als fallback                       |

## Aan de slag

Klonen en draaien mag alleen voor de beoordeling van dit schoolproject, of met
schriftelijke toestemming; zie [Licentie](#licentie).

Nodig: PHP 8.4+, Composer 2, Node 24 (zie [.nvmrc](.nvmrc)), npm 11+ en make. Op
Windows werk je in WSL2.

```bash
git clone <deze repository> && cd samenspel
make install
make dev
```

`make install` zet alles in één keer klaar: de PHP- en npm-dependencies, de git
hooks, een `.env` met applicatiesleutel, een gemigreerde en geseede database en
een eerste build van de assets. Opnieuw draaien kan geen kwaad. Aan het eind
staat de URL die je opent.

`make dev` start Vite met live reload, de queue en de logs. `make status` laat op
de regel `site` zien op welke URL de applicatie draait, `make stop` stopt alles.

Met Laravel Herd draait de site op `https://samenspel.test`. Herd serveert een map
alleen als die gelinkt is of direct in een geparkeerde map staat; zo niet, link
hem dan één keer vanuit de projectmap:

```bash
herd link --secure samenspel
```

### Testaccounts

`make fresh yes=1` leegt de database, migreert en seedt. De seed zet de
categorieën klaar, plus twee accounts om op `/login` mee in te loggen:

| Rol       | E-mail              | Wachtwoord |
| --------- | ------------------- | ---------- |
| Gebruiker | `test@example.com`  | `password` |
| Admin     | `admin@example.com` | `password` |

Deze accounts bestaan alleen in de lokale seed-data.

Spellen komen alleen uit de IGDB-catalogus, dus de seed maakt geen spellen of
events aan. Voor een demo voeg je als admin eerst een paar spellen toe via
**Spellen beheren** en draai je daarna:

```bash
php artisan db:seed --class=DemoSeeder
```

Dat maakt drie organisatoren (wachtwoord `password`) met elk twee komende events,
waaronder een vol en een gesloten event. Het testaccount heeft nog geen
inschrijvingen, zodat je kunt laten zien dat organiseren pas na drie
inschrijvingen kan.

## Dagelijks werk

| Wat                           | Commando                            |
| ----------------------------- | ----------------------------------- |
| Alle make-targets             | `make`                              |
| Na een pull met een migration | `make migrate`                      |
| Controleren voor een push     | `make verify` (ongeveer 7 seconden) |
| Formatting herstellen         | `make format`                       |
| ERD opnieuw genereren         | `make erd`                          |
| Alles wat CI draait           | `make ci`                           |

Niemand commit direct op `main`. Werk gebeurt op `develop` of op een
`feature/<naam>`-branch die daarvan is afgesplitst. Commits volgen
`type(scope): onderwerp`, bijvoorbeeld `feat(events): add sign-up`. Zie
[docs/branching.md](docs/branching.md).

## Huisstijl

De kleuren, lettertypes, afstanden en vormen staan als design tokens (CSS custom
properties) in `resources/scss/abstracts/_tokens.scss`. Een component gebruikt
altijd een token, nooit een losse hex-waarde. Het logo zet je met `<x-logo />` op
een pagina; alle logobestanden staan in `public/brand/`.

| Token            | Kleur     | Gebruik                     |
| ---------------- | --------- | --------------------------- |
| `--color-paper`  | `#F6F1E7` | Achtergrond van de pagina   |
| `--color-felt`   | `#1F4D3A` | Koppen, navigatie, links    |
| `--color-accent` | `#F0603A` | Primaire knoppen (vulkleur) |
| `--color-ink`    | `#1B1F1C` | Tekst                       |

Het volledige design system, inclusief contrastwaarden en logo-regels, staat in
[docs/design-system.md](docs/design-system.md).

## Documentatie

- [docs/erd.md](docs/erd.md): het datamodel als ERD, met uitleg hoe je het leest.
- [docs/design-system.md](docs/design-system.md): kleuren, typografie, componenten
  en het logo.
- [docs/branching.md](docs/branching.md): `main`, `develop` en feature-branches.
- [docs/toolchain.md](docs/toolchain.md): de controles en de keuzes erachter.
- [docs/otap.md](docs/otap.md): de omgevingen en de productieserver.
- [docs/security.md](docs/security.md): de OWASP Top 10 in Samenspel.
- [CHANGELOG.md](CHANGELOG.md): wat er per release is veranderd.
- [AGENTS.md](AGENTS.md): de afspraken voor codeerassistenten.

## Licentie

**Alle rechten voorbehouden.** Dit is privéwerk, geen open source: kopiëren,
hergebruiken, aanpassen of verspreiden mag niet zonder schriftelijke toestemming.
Zie [LICENSE](LICENSE). Het Laravel-skelet waarop dit gebouwd is blijft MIT
(© Taylor Otwell). Bricolage Grotesque valt onder de SIL Open Font License, zie
`resources/fonts/bricolage-grotesque/OFL.txt`.
