# Gamecatalogus voor events

Een event kiest alleen games uit de IGDB-catalogus, onder hun officiële titel. De
games die al lokaal bekend zijn staan direct in de keuzelijst; vanaf drie tekens
zoekt de lijst daarnaast in IGDB en toont maximaal tien extra resultaten. Een
gekozen game blijft aangevinkt in de lijst staan, zoals bij categorieën. De
browser ziet alleen titel, beschikbare platformen, releasejaar, cover-URL en de
IGDB-ID. Alleen de Laravel-backend kent de Twitch Client ID en Client Secret. Een
gekozen game wordt lokaal bewaard; de catalogus wordt niet integraal geïmporteerd.

Zelf een titel intypen kan niet meer: zo kwamen eerder namen als "test" in de
lijst. Eerder handmatig toegevoegde games (bron `manual`) blijven aan hun events
gekoppeld, maar zijn niet meer kiesbaar voor nieuwe events. Kiest iemand later
dezelfde game uit IGDB, dan krijgt het bestaande record de officiële titel en
wordt het weer kiesbaar. Ontbrekende games beheren is een admintaak (US15).

Zoeken en kiezen zijn beperkt tot records die IGDB als hoofdspel classificeert,
zonder bovenliggend spel of editie, met een betrokken studio of uitgever en een
releasedatum. DLC, seizoenen, pakketten, bundels, ports, remasters en speciale
edities (Deluxe, Gold, Ultimate enzovoort) verschijnen daardoor niet. Ook
geannuleerde, geruchte en offline games vallen af, net als herkenbare pakket- en
seizoentitels die IGDB verkeerd als hoofdspel markeert. Een remake heeft in IGDB
een bovenliggend spel; je kiest dan het origineel, onder dezelfde naam.

IGDB sorteert fanprojecten en spin-offs soms boven het spel zelf. De backend haalt
daarom vijftig kandidaten op en zet eerst de exacte titel, dan titels die met de
zoekterm beginnen, dan titels met alle zoekwoorden en daarna de rest (bijvoorbeeld
via een alternatieve naam als "GTA"), telkens op aantal beoordelingen. Een titel
die niemand beoordeelde of verwacht, verschijnt alleen bij een exacte naammatch.
Dezelfde controle geldt voor een rechtstreeks ingestuurde IGDB-ID.

Na het aanscherpen van de catalogus kan `php artisan games:retire-non-main`
bestaande IGDB-keuzes opnieuw beoordelen. Het commando laat ongeschikte records
in de database staan voor bestaande events, maar verbergt ze voor nieuwe keuzes
en filters. Het is veilig opnieuw uit te voeren en pauzeert tussen IGDB-verzoeken.

Vul `IGDB_CLIENT_ID` en `IGDB_CLIENT_SECRET` in de lokale of productieomgeving in.
Gebruik een Twitch-app van het type **Confidential**. Na wijzigen van een
gecachete productieconfiguratie moet die configuratie opnieuw worden opgebouwd.
Zonder credentials blijven de lokaal bekende games kiesbaar; zoeken in de catalogus
meldt dan dat het tijdelijk niet beschikbaar is.

De OAuth-token verloopt volgens Twitch en wordt maximaal tot 60 seconden vóór
de opgegeven vervaltijd hergebruikt. Zoekresultaten blijven 30 minuten in de cache,
gekozen gamegegevens 6 uur. De backend beperkt IGDB-verzoeken tot vier per seconde
en toont bij een storing geen ruwe providerrespons. Een onbekende ID wordt niet
als lokale game opgeslagen. Covers gebruiken de IGDB afbeeldingsdienst; zonder
beschikbare cover toont de interface de eerste letters van de titel. Organisatoren kiezen
per event een of meer platformfamilies (bijvoorbeeld PC, PlayStation, Xbox, Switch
en mobiel). Die staan los van de beschikbare IGDB-platformen van een game en zijn
later via **Spellen en platformen aanpassen** op de eventpagina te wijzigen.
Het publieke eventoverzicht kan op meerdere platformen tegelijk filteren.

Een nieuwe installatie krijgt geen voorbeeldgames of demo-events: `make fresh yes=1`
seedt alleen categorieën, de platformen en de twee accounts uit de README. Games
komen er pas bij wanneer iemand ze voor een event uit de catalogus kiest.

`games:sync-igdb` draait dagelijks om 03:30 via Laravel Scheduler, mits op de
server `php artisan schedule:run` elke minuut wordt aangeroepen. Alleen lokale
IGDB-games die aan een toekomstig event zijn gekoppeld worden bekeken, in batches
van 50, maximaal 200 per run. Een game wordt hoogstens eens per zeven dagen
ververst. Het commando kan met `--limit=50` handmatig worden uitgevoerd. Het
ververst cover-URL, releasejaar en de volledige lijst met beschikbare
providerplatformen. Lokale titels en handmatig toegevoegde games worden niet
overschreven. De scheduler importeert geen nieuwe catalogusrecords.

De [IGDB API-documentatie](https://api-docs.igdb.com/#getting-started) vermeldt
gratis niet-commercieel gebruik en een commercieel partnerschap voor producten
met inkomsten. Vóór een betaalde versie van Samenspel zijn de actuele Twitch/
IGDB-voorwaarden en passende toestemming of een partnerschap nodig. De
documentatie noemt ook zichtbare IGDB-vermelding voor commerciële partners.
