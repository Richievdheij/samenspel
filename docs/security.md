# Beveiliging: de OWASP Top 10 in Samenspel

Per onderdeel van de OWASP Top 10 (editie 2021) staat hier wat Laravel zelf al
regelt en wat ik in Samenspel heb toegevoegd, met de plek in de code. De editie
2025 schuift de nummers op (Injection wordt A05, Security Misconfiguration A02),
maar de onderwerpen blijven grotendeels hetzelfde.

## A01 Broken Access Control

Toegang wordt op drie lagen gecontroleerd, telkens op de server:

1. **Ingelogd of niet**: de `auth`-middleware op de routes in `routes/web.php`.
   Een gast die `/events/create` opent, gaat naar de loginpagina.
2. **Rol**: de `admin`-middleware (`app/Http/Middleware/EnsureUserIsAdmin.php`)
   staat voor alle `/admin`-routes. Een gewone gebruiker krijgt een 403.
3. **Eigenaar van het item**: `app/Policies/EventPolicy.php`. Alleen de
   organisator mag een event bewerken; sluiten en verwijderen mag ook een admin
   (moderatie, US17). De controle zit in `Gate::authorize()` in de controller en in
   `authorize()` van de FormRequests, dus ook een deeplink naar
   `/events/{id}/edit` van een ander geeft een 403. De knoppen verbergen met
   `@can` in Blade is alleen gemak; de beveiliging zit op de server.

Daarnaast:

- **Mass assignment**: `#[Fillable]` op de modellen. `user_id` staat niet in de
  lijst van `Event` en `role` niet in die van `User`, dus een aangepast formulier
  kan geen ander als organisator invullen of zichzelf admin maken.
- **Geen id in de profiel-URL**: `ProfileController` gebruikt `#[CurrentUser]`.
  Er is geen `/profile/{id}` waarmee je het profiel van een ander kunt openen.
- **CSRF**: elke POST, PUT en DELETE heeft `@csrf` in het formulier. Laravel
  controleert het token in de `web`-middlewaregroep en geeft een 419 zonder geldig
  token.

## A02 Cryptographic Failures

- Wachtwoorden worden met bcrypt gehasht: `Hash::make()` bij registreren en de
  cast `'password' => 'hashed'` in `User`. Er staat nooit een wachtwoord leesbaar
  in de database.
- `APP_KEY` versleutelt en ondertekent de sessie en cookies. Hij staat alleen in
  `.env`, nooit in git.
- In productie staan `SESSION_ENCRYPT=true` en `SESSION_SECURE_COOKIE=true` (zie
  `.env.production.example`): de sessiecookie gaat alleen over HTTPS.

## A03 Injection (SQL en XSS)

- **SQL-injectie**: alle queries lopen via Eloquent of de query builder. Die
  gebruiken prepared statements met parameter binding: de zoekterm in
  `Event::matchingKeyword()` wordt als waarde meegestuurd, nooit in de SQL-tekst
  geplakt. Er staat geen `DB::raw`, `whereRaw` of `DB::select` met invoer in de
  code.
- **XSS**: Blade's `{{ }}` escapet alle uitvoer met `htmlspecialchars`. De views
  gebruiken nergens `{!! !!}`, dus een eventtitel als `<script>` verschijnt als
  tekst.
- **Query-injectie richting IGDB**: een zoekterm voor de gamecatalogus moet aan
  `GameCatalog::TITLE_PATTERN` voldoen, zodat niemand eigen IGDB-querytaal
  (`fields *`, `limit 500`) kan meesturen.

## A04 Insecure Design

De regels van het domein staan op de server, niet alleen in de interface:

- Organiseren mag pas na drie inschrijvingen bij anderen
  (`User::canOrganizeEvents()`), gecontroleerd in
  `StoreEventRequest::authorize()`. Een verborgen knop alleen zou te omzeilen zijn.
- Bij inschrijven wordt de eventrij vergrendeld (`lockForUpdate()` in
  `Event::signUp()`), zodat twee mensen niet tegelijk de laatste plek krijgen. De
  unieke index op `event_user (event_id, user_id)` maakt dubbel inschrijven in de
  database zelf onmogelijk.
- De statusknop stuurt de nieuwe status mee in plaats van "draai om", zodat een
  dubbele klik het event niet terugzet.

## A05 Security Misconfiguration

- `APP_DEBUG=false` in productie: anders toont een foutpagina de stacktrace,
  queries en omgevingswaarden.
- `.env` staat in `.gitignore`; de templates `.env.example` en
  `.env.production.example` bevatten geen geheimen.
- De webserver wijst naar `public/`, zodat `.env`, `storage/` en de broncode niet
  via een URL bereikbaar zijn. Zie [otap.md](otap.md).
- De productieserver laat alleen SSH, HTTP en HTTPS door, accepteert SSH alleen
  met een sleutel, installeert beveiligingsupdates zelf en stuurt http door naar
  https met HSTS en beveiligingsheaders. `scripts/server/provision.sh` legt dat
  vast; [otap.md](otap.md) somt het op.

## A06 Vulnerable and Outdated Components

- `composer.lock` en `package-lock.json` leggen de exacte versies vast.
- `make audit` draait `composer audit` en `npm audit` en faalt bij een bekende
  kwetsbaarheid. CI (GitHub Actions) draait dit bij elke push naar `main` en
  `develop`, en Dependabot stelt elke week updates voor.

## A07 Identification and Authentication Failures

- **Brute force**: na vijf foute pogingen per e-mailadres en IP volgt een
  wachttijd (`LoginRequest::MAX_ATTEMPTS`). De sleutel is e-mail plus IP, zodat
  niemand andermans account kan blokkeren.
- **Geen accounts raden**: een fout e-mailadres en een fout wachtwoord geven
  dezelfde melding.
- **Session fixation**: na inloggen krijgt de sessie een nieuw id
  (`AuthenticatedSessionController::store()`); bij uitloggen wordt de sessie
  ongeldig gemaakt en het CSRF-token vernieuwd.
- Een account verwijderen vraagt het huidige wachtwoord (`current_password`).

## A08 Software and Data Integrity Failures

- De lockfiles zorgen dat productie precies de geteste versies installeert.
- Cookies zijn ondertekend met `APP_KEY`, dus een aangepaste cookie wordt
  geweigerd.
- Een upload (eigen cover van een spel) moet een afbeelding zijn: `image`,
  `mimes:jpg,jpeg,png,webp` en maximaal 2 MB in `UpdateGameRequest`.

## A09 Security Logging and Monitoring Failures

- Productie logt per dag een nieuw bestand (`LOG_STACK=daily`) vanaf niveau
  `warning`, in `storage/logs/`.
- Na te veel loginpogingen verstuurt Laravel het `Lockout`-event, waar een melding
  aan gekoppeld kan worden.

## A10 Server-Side Request Forgery

De server roept twee externe API's aan: PDOK voor adressen en IGDB voor games.
De adressen van die API's staan vast in `config/services.php`; de gebruiker levert
alleen een zoekterm of id die als queryparameter meegaat, nooit een URL. Een
adres wordt bovendien opnieuw bij PDOK opgezocht (`AddressLookup::lookup()`), zodat
de opgeslagen locatie nooit alleen tekst van de gebruiker is. De zoekroutes hebben
een limiet per minuut (`throttle` in `routes/web.php`).
