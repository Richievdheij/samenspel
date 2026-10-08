# Changelog

Alle noemenswaardige wijzigingen aan Samenspel, nieuwste bovenaan. Elke regel
noemt de Trello-kaart waar hij bij hoort, als die er is.

De indeling volgt [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/):
**Toegevoegd** voor nieuwe functies, **Gewijzigd** voor aanpassingen aan iets wat
al bestond, **Opgelost** voor bugfixes en **Verwijderd** voor wat eruit gaat.

## [Unreleased]

### Toegevoegd

- `CHANGELOG.md` om de voortgang bij te houden (US20).
- Overzicht van komende events op `/events`, eerstvolgende bovenaan, ook voor
  gasten. Link "Events" in de navigatie (US4).

### Gewijzigd

- De teksten spreken van gaming-events en LAN-party's in plaats van
  game-avonden en LAN-sessies.

## 2026-10-08: Datamodel

### Toegevoegd

- Tabellen `games` en `categories`, met een unieke naam per spel en categorie.
- Tabel `events` met organisator, spel, categorie, datum, plek, maximaal aantal
  deelnemers en status (open of gesloten).
- Kolom `role` op `users`: elke nieuwe gebruiker is `user`, een admin wordt
  alleen bewust aangemaakt.
- Tussentabel `event_user` voor inschrijvingen, met een unieke index zodat
  dubbel inschrijven in de database onmogelijk is.
- Seeders met spellen, categorieën, een admin-account en vier demo-events.
- Tests voor de relaties en de databaseregels.

### Gewijzigd

- `docs/erd.md` beschrijft het gebouwde datamodel.

## 2026-10-08: Projectinrichting en huisstijl

### Toegevoegd

- ERD van het datamodel in `docs/erd.md`, met uitleg hoe je het leest.
- Design tokens voor kleuren, typografie, afstanden en vormen in
  `resources/scss/abstracts/_tokens.scss`.
- Logo, favicons en het `<x-logo>`-component; het logo staat in de header en
  op de inlogpagina's.
- Design system in `docs/design-system.md`.

### Gewijzigd

- Projectnaam overal van "Laravel Starter Template" naar Samenspel.
- Homepagina toont wat Samenspel is in plaats van een link naar Laravel.
- README herschreven voor Samenspel.

## 2026-10-07: Start

### Toegevoegd

- Laravel 13-project met registreren, inloggen, uitloggen, wachtwoord resetten
  en een eigen profielpagina (US1, US2, US3).
- Databaseconfiguratie voor MySQL-database `samenspel`.
