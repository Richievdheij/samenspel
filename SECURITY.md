# Beveiligingsbeleid

## Een kwetsbaarheid melden

Meld een beveiligingsprobleem **niet** in een openbare issue. Gebruik
[Report a vulnerability](https://github.com/Richievdheij/samenspel/security/advisories/new)
op het tabblad **Security**: dan ziet alleen de eigenaar van deze repository de
melding.

Beschrijf wat je gevonden hebt, op welke pagina of route, en hoe het na te doen
is. Je krijgt binnen een week een reactie.

## Welke versie

Alleen de laatste release en de versie die live staat op
<https://samenspel.158-178-149-229.sslip.io> krijgen beveiligingsupdates.

## Hoe Samenspel zelf bewaakt wordt

- `make audit` faalt bij een bekende kwetsbaarheid in een Composer- of
  npm-pakket. CI draait die controle bij elke push naar `main` en `develop`.
- Dependabot stelt elke week updates voor de pakketten en de GitHub Actions
  voor ([.github/dependabot.yml](.github/dependabot.yml)).
- Hoe de applicatie de OWASP Top 10 afvangt, staat in
  [docs/security.md](docs/security.md). Hoe de productieserver beveiligd is, staat
  in [docs/otap.md](docs/otap.md).
