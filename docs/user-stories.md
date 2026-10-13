# User stories: Samenspel (eventplanner)

**Rollen:** gast, gebruiker, admin.
**Prioriteit:** M = Must, S = Should, C = Could.
**Criterium:** koppeling naar de verplichte onderdelen uit Toelichting_praktijkopdracht.

## A. Account

**US1 Registreren (M, inloggen, validatie)**
Als gast wil ik een account aanmaken zodat ik events kan organiseren en me kan inschrijven.

- Naam, e-mail en wachtwoord zijn verplicht, e-mail is uniek en geldig
- Foutmeldingen komen van de server, ook met JavaScript uit
- Nieuwe gebruiker krijgt rol `user`

**US2 Inloggen en uitloggen (M, inloggen)**
Als gebruiker wil ik in- en uitloggen zodat mijn gegevens afgeschermd zijn.

- Foute combinatie geeft een duidelijke foutmelding
- Afgeschermde pagina's sturen een gast door naar de loginpagina

**US3 Eigen profiel aanpassen (M, rechten)**
Als gebruiker wil ik mijn naam en e-mail wijzigen in mijn eigen afgeschermde deel.

- Alleen mijn eigen profiel is bereikbaar, nooit dat van een ander
- Validatie als bij registreren

## B. Events

**US4 Events bekijken (M)**
Als gast wil ik alle komende events zien zodat ik kan ontdekken wat er speelt.

- Lijst met titel, spel, categorie, datum, plek, status en aantal inschrijvingen
- Gepagineerd

**US5 Eventdetails bekijken (M)**
Als gast wil ik de details van een event zien.

- Beschrijving, organisator, deelnemerslijst en vrije plekken zichtbaar

**US6 Zoeken (M, zoeken en filteren)**
Als bezoeker wil ik op trefwoord zoeken zodat ik snel een event vind.

- Zoekt in titel en beschrijving
- Zoekterm blijft zichtbaar in het veld na zoeken

**US7 Filteren op spel en categorie (M, zoeken en filteren)**
Als bezoeker wil ik filteren met dropdowns op spel en categorie.

- Te combineren met de zoekterm in een enkele query
- Filters blijven behouden bij pagineren

**US8 Event organiseren (M, validatie, diepere validatie)**
Als gebruiker wil ik een event aanmaken met spel, categorie, datum, plek en maximaal aantal deelnemers.

- Alle velden verplicht, datum ligt in de toekomst, maximum is een positief getal
- Alleen toegestaan als ik me eerder voor minimaal 3 events van anderen heb ingeschreven, geteld met een Eloquent-query op `event_user`
- Anders zie ik een uitleg hoeveel inschrijvingen nog nodig zijn
- Ook afgedwongen op de server, niet alleen door een verborgen knop

**US9 Eigen event bewerken (M, rechten, OWASP Access Control)**
Als organisator wil ik mijn event wijzigen.

- Alleen de organisator kan bewerken, ook niet via deeplink (403)
- Zelfde validatie als bij aanmaken

**US10 Eigen event verwijderen (S, rechten)**
Als organisator wil ik mijn event verwijderen.

- Alleen de organisator, verwijderen via POST met bevestiging
- Inschrijvingen verdwijnen mee

**US11 Status wisselen (M, statusknop)**
Als organisator wil ik een event op open of gesloten zetten met een knop in de lijst.

- Verloopt via POST naar een aparte `toggleStatus`-action in de controller
- Alleen de organisator mag dit
- Een gesloten event accepteert geen nieuwe inschrijvingen

## C. Inschrijven

**US12 Inschrijven (M)**
Als gebruiker wil ik me inschrijven op een open event.

- Niet mogelijk op eigen event, een gesloten event of een vol event
- Dubbel inschrijven kan niet (validatie en unieke index)

**US13 Uitschrijven (S)**
Als gebruiker wil ik me uitschrijven zodat een plek vrijkomt.

**US14 Mijn inschrijvingen (S)**
Als gebruiker wil ik mijn inschrijvingen zien en hoeveel er nog nodig zijn om zelf te mogen organiseren.

- Toont de teller uit US8, berekend met dezelfde query

## D. Beheer (admin)

**US15 Spellen beheren (M, rollen)**
Als admin wil ik spellen toevoegen, wijzigen en verwijderen.

- Alleen toegankelijk voor ingelogde admin (middleware)
- Spel met bestaande events kan niet verwijderd worden

**US16 Categorieën beheren (M, rollen)**
Als admin wil ik categorieën beheren, met dezelfde regels als US15.

**US17 Events modereren (S, rollen)**
Als admin wil ik elk event kunnen sluiten of verwijderen bij misbruik.

- Bewuste uitzondering op "alleen organisator", vastgelegd in de policy

## E. Kwaliteit en oplevering

**US18 Beveiliging aantoonbaar (M, OWASP)**
Als ontwikkelaar wil ik per OWASP-onderdeel kunnen aanwijzen waar het in de code zit.

- Broken Access Control: policies en middleware
- Injection: Eloquent en parameter binding, geen ruwe queries met invoer
- CSRF-token op elke POST, Blade-escaping tegen XSS
- Korte toelichting in `docs/security.md`

**US19 OTAP kunnen uitleggen (M, OTAP)**
Als ontwikkelaar wil ik OTAP en de inrichting van een productieserver kunnen uitleggen aan de hand van mijn eigen project.

- Verschil tussen de omgevingen (ontwikkel, test, acceptatie, productie) uitgelegd
- `.env` per omgeving, `APP_ENV=production` en `APP_DEBUG=false` met reden
- Aparte productiedatabase, migrations draaien en config, route en view cachen
- Webserver wijst naar `public/`, `storage` en `bootstrap/cache` zijn schrijfbaar
- De OTAP-uitleg wordt bij US19 in een apart document vastgelegd
- Een echte deployment op een VPS is optioneel (Could) en gaat alleen door als er tijd over is

**US20 Voortgang traceerbaar (M, Git)**
Als student wil ik kleine, gerichte commits en een bijgehouden `CHANGELOG.md`.

- Commit per afgerond blok, branchenaam of kaartnummer in de beschrijving

## F. Eventuitbreidingen (later, Could)

Deze uitbreidingen zijn optioneel en geen onderdeel van de Must-scope. Pak ze pas op
nadat US1 t/m US20 zijn afgerond.

**US21 Wachtlijst voor volle events (C)**
Als bezoeker wil ik me op een wachtlijst zetten wanneer een event vol is.

- De wachtlijst is alleen beschikbaar als het event vol is en open staat
- Als een plek vrijkomt, wordt de eerstvolgende persoon op de wachtlijst uitgenodigd
- De uitnodiging verloopt na een ingestelde termijn; daarna krijgt de volgende persoon de kans

**US22 Aanmelddeadline instellen (C)**
Als organisator wil ik een deadline instellen waarna inschrijven automatisch sluit.

- De deadline kan alleen in de toekomst liggen
- Na de deadline is inschrijven niet meer mogelijk, ook niet via een directe aanvraag
- De eventpagina toont de deadline en de gesloten aanmeldstatus

**US23 Deelnemers informeren over eventwijzigingen (C)**
Als deelnemer wil ik bericht krijgen als belangrijke gegevens van een event veranderen.

- Deelnemers krijgen een melding bij wijzigingen in datum, tijd, locatie of status
- De melding vermeldt wat er is veranderd
- Een organisator kan een event annuleren en deelnemers daarover informeren

**US24 Event toevoegen aan agenda (C)**
Als deelnemer wil ik een event aan mijn agenda kunnen toevoegen.

- De eventpagina biedt een agenda-export aan
- De export bevat titel, datum, begin- en eindtijd, locatie en beschrijving
- De export werkt met gangbare agenda-apps via een `.ics`-bestand

## Bewuste keuzes

- Een nieuwe gebruiker kan niet meteen organiseren. Daarom komen er seeders met voorbeeldevents, zodat je direct kunt inschrijven en de regel kunt demonstreren.
- Admins zijn vrijgesteld van de organiseerregel in US8, anders zijn ze niet te testen. Dit leg ik uit in het mondeling.
