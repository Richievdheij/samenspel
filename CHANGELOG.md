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
- Elk event in het overzicht toont spel, categorie, plek, het aantal spelers
  ("2 / 8 spelers") en een statuslabel: open, gesloten of vol (US4). Het spel
  staat bovenaan met een icoon, de titel is de grootste tekst, en status en
  spelers staan in een vaste kolom, op elke kaart op dezelfde plek. Bovenaan
  staat hoeveel events er gepland staan.
- Paginering per tien events, met een eigen paginaweergave zonder Tailwind (US4).
  Elk paginanummer is een blokje in de knopstijl; de huidige pagina is groen
  en ingedrukt.

### Gewijzigd

- De teksten spreken van gaming-events en LAN-party's in plaats van
  game-avonden en LAN-sessies.
- Nieuwe stijl voor de hele site: Memphis, line art en isometrisch. Elk vlak
  en elke knop heeft een inktcontour en een harde isometrische diepte. Knoppen
  komen omhoog bij hover en zakken in bij klikken. Een stippenraster en losse
  Memphis-vormen in de paginakop en op de inlogpagina's zorgen voor de
  speelsheid.
- De hele stijl wordt ingesteld vanuit één blok tokens in `_tokens.scss`
  (lijndikte, diepte, kanteling, patroon en vormen), zodat nieuwe pagina's hem
  vanzelf volgen. Uitleg in `docs/design-system.md`, sectie 3b.
- Invoervelden houden in elke toestand dezelfde randdikte. Focus kleurt de rand
  groen met een groene diepte eronder, met een vloeiende overgang.
- Klikken of tikken geeft geen focusrand of grijze tikflits meer. Met het
  toetsenbord blijft de focusrand zichtbaar.
- Home: een echte kop ("Vind je volgende LAN-party."), een gekanteld label en
  twee knoppen (events bekijken, account maken) in plaats van een tweede groot
  logo. Ernaast een isometrische speltafel in line art, met scherm,
  dobbelstenen en een pion, tussen Memphis-vormen.
- Dashboard: een welkomstregel en een verwijzing naar de events in plaats van
  "Je bent ingelogd!".
- Header vernieuwd. Op desktop staan de links in een omlijnde pil met de
  huidige pagina groen gevuld, en het accountmenu is een chip met je initialen
  die indrukt als je hem opent. Het menu heeft iconen en schuift in en uit.
- Mobiel menu vernieuwd: schermbreed, het schuift van links in terwijl het
  infadet, met de links één voor één en een accountblok. Het logo en de
  sluitknop staan op precies dezelfde plek als het logo en de menuknop in de
  header. Sluiten kan ook met Escape, en de pagina erachter scrollt niet mee.
  Zonder JavaScript werkt het menu nog steeds.
- Menu's openen en sluiten in één vast tempo (280ms, token `--duration-menu`),
  heen en terug gelijk. De menuknop en de profielchip bewegen mee met hun menu.
- Meldingen (zoals na het versturen van een resetlink): de sluitknop staat
  rechtsboven in plaats van onder de tekst, met een icoon per soort melding.
- Een terug-link: "Terug naar home" op inloggen, registreren en de
  wachtwoordpagina's, en "Terug naar dashboard" op het profiel.
- De profielpagina selecteert bij openen niet meer automatisch het naamveld.
- Knoppen, links en menu's selecteren geen tekst meer bij snel klikken.
- Op mobiel staan de Memphis-vormen niet meer over het logo van de
  inlogpagina's.
- Een volledige footer op elke pagina, ook op de inlogpagina's: logo,
  omschrijving, de kolommen Ontdekken en Account met werkende links, en een
  copyrightregel.

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
