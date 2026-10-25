# Changelog

Alle noemenswaardige wijzigingen aan Samenspel, nieuwste bovenaan. Elke regel
noemt de Trello-kaart waar hij bij hoort, als die er is.

De indeling volgt [Keep a Changelog](https://keepachangelog.com/nl/1.1.0/):
**Toegevoegd** voor nieuwe functies, **Gewijzigd** voor aanpassingen aan iets wat
al bestond, **Opgelost** voor bugfixes en **Verwijderd** voor wat eruit gaat.

## [Unreleased]

### Toegevoegd

- Admins beheren de categorieën via **Categorieën beheren** in het
  gebruikersmenu: toevoegen met een unieke naam, hernoemen en verwijderen zolang
  geen event de categorie gebruikt. Dezelfde admin-middleware als bij de
  spellen (US16).
- Admins beheren de spellen via **Spellen beheren** in het gebruikersmenu: een
  spel toevoegen uit de IGDB-catalogus, de getoonde titel wijzigen, een eigen
  cover uploaden en een spel verwijderen zolang geen event het gebruikt. De
  pagina's zijn alleen bereikbaar voor admins, via middleware (US15).
- Het dashboard toont **Mijn inschrijvingen**: komende events, eerstvolgende
  bovenaan, met datum en status, en de laatste eerdere inschrijvingen. Daaronder
  staat hoeveel inschrijvingen nog nodig zijn om zelf te organiseren, met
  dezelfde teller als bij het aanmaken van een event (US14).
- Een gebruiker schrijft zich uit voor een event via **Uitschrijven** op de
  eventpagina, zolang het nog niet begonnen is; de plek komt vrij (US13).
- Een gebruiker schrijft zich in voor een open event via **Inschrijven** op de
  eventpagina. Dat kan niet voor een eigen, gesloten, vol of al begonnen event,
  en niet twee keer; de pagina zegt waarom (US12).
- De organisator zet de inschrijving van een eigen event open of dicht met een
  knop onder de kaart in het eventoverzicht en op de eventpagina. De knop stuurt
  een POST naar een eigen `toggleStatus`-actie; een ander krijgt een 403 (US11).
- De organisator verwijdert een eigen event via **Event verwijderen** op de
  eventpagina, na bevestiging in een venster dat noemt hoeveel inschrijvingen
  meeverdwijnen. Inschrijvingen en labels gaan mee; een ander krijgt een 403 (US10).
- De organisator bewerkt een eigen event via **Event bewerken** op de
  eventpagina: hetzelfde formulier in drie stappen als bij aanmaken, vooraf
  ingevuld en met dezelfde controles. Status en inschrijvingen blijven staan, en
  een ongewijzigd adres wordt niet opnieuw bij PDOK opgevraagd. Iemand anders
  krijgt een 403, ook via een directe link of een eigen verzoek (US9).
- Het eventoverzicht filtert op een of meer platformen. Actieve filters staan
  als chips boven de lijst, met een link **Filters wissen** (US4).
- Het gamefilter in het eventoverzicht heeft een zoekveld en toont alleen
  spellen waarmee een komend event gespeeld wordt (US4).
- Een event krijgt een of meer platformen, zoals PC, PlayStation, Xbox, Switch
  of mobiel. Eventkaarten en de eventpagina tonen ze als gekleurde icoontjes (US8).
- `php artisan games:sync-igdb` ververst dagelijks om 03:30 cover, releasejaar
  en platformen van gekozen catalogusgames, zonder de lokale titel te
  overschrijven (US8).
- Spellen voor een event komen uit de IGDB-catalogus, onder hun officiële titel.
  De keuzelijst toont de bekende spellen direct; vanaf drie tekens zoekt hij in
  de catalogus, en een gekozen spel blijft aangevinkt staan, zoals bij
  categorieën (US8).
- Alleen echte hoofdspellen zijn kiesbaar: geen DLC, seizoenen, pakketten,
  bundels, speciale edities, geannuleerde of offline titels. De zoekresultaten
  zetten het gezochte spel bovenaan, vóór spin-offs en fanprojecten (US8).
- `php artisan games:retire-non-main` toetst eerder gekozen catalogusgames
  opnieuw en verbergt ongeschikte voor nieuwe events (US8).
- Datumkiezer voor eventaanmaak met snelle toekomstige periodes, twee maanden op
  desktop en één maand op mobiel, plus toepassen en annuleren (US8).
- Gamecovers en logo's kunnen als lokale afbeeldingen aan een spel worden
  gekoppeld; eventkaarten hebben passende placeholders zolang die ontbreken (US8).
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

### Opgelost

- Een melding boven het eventoverzicht, zoals na het sluiten van de inschrijving
  of het verwijderen van een event, wordt niet meer half bedekt door de
  zoekbalk (US11).

### Gewijzigd

- De tijdzone op de eventpagina noemt het verschil met UTC op de startdag, zoals
  "Amsterdamse tijd (UTC+1)" in de winter en "(UTC+2)" in de zomer (US5).
- De filters in het eventoverzicht staan in drie even brede kolommen, zodat een
  lange keuze ze niet verschuift; de keuzelijsten zijn hoger en scrollen binnen
  zichzelf (US4).
- Het formulier voor een nieuw event heeft drie stappen: spellen en platformen,
  eventgegevens, en wanneer en waar. Elke stap controleert zijn velden voordat
  je verdergaat; zonder JavaScript blijft het één pagina (US8).
- De tijdkiezer gebruikt gecentreerde keuzelijsten met een pijltje, en toepassen
  en annuleren hebben de gewone knopstijl (US8).
- Eventkaarten en de eventpagina tonen de covers van de spellen uit de catalogus; meerdere
  spellen staan als kleine covers onder de titel en de volledige lijst klapt uit
  op de eventpagina (US8).
- Eventkaarten combineren de covers van alle gekozen spellen, tonen bij veel
  spellen een `+N`-markering en begrenzen titels tot drie regels; op de
  eventpagina kan de volledige spellenlijst worden uitgeklapt (US8).
- De datumstempel gebruikt weer de groene achtergrond en de spelersteller en
  andere kaarticonen zijn gecentreerd (US8).
- Eventkaarten hebben een beeldvlak met datum en status, informatie en een
  duidelijke detailactie; op desktop staan ze in twee kolommen en op mobiel in
  één kolom (US8).
- Locatiekeuzes bij eventaanmaak tonen weer een selectiecirkel en een korte
  uitleg bij adres en online (US8).
- De datumkiezer houdt begin en einde als concept zichtbaar en bewaart ze pas
  bij toepassen; de kalender navigeert met correct uitgelijnde weken (US8).
- Eventkaarten tonen één spel als brede gamekop en meerdere spellen als compacte
  logomarkeringen. Categorieën staan op een eigen regel onder de titel (US8).
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

### Verwijderd

- Zelf een speltitel intypen bij een event. Eerder ingetypte titels blijven bij
  hun events staan, maar zijn niet meer kiesbaar voor nieuwe events (US8).
- De voorbeeldgames en demo-events uit de seeders: een nieuwe installatie start
  met alleen categorieën en de twee accounts (US8).

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
