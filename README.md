# Samla

Samla ist eine schlanke, selbst betriebene Datei- und Medienplattform für eine Organisation. Eine erste responsive Verwaltungsoberfläche ist lokal nutzbar. Geplant sind WebDAV, produktive OIDC-Anmeldung, Dateiversionierung und eine Medienbibliothek mit Metadaten und nachvollziehbaren KI-Kennzeichnungen. Gruppenbasierte Rechte und die geschützten Verwaltungsdienste bilden bereits das Backend.

Fachliche Funktionen erhalten öffentliche Erweiterungsverträge. Das mitgelieferte RBAC-Pflichtplugin verwendet dieselbe Plugin-API wie zusätzliche Erweiterungen. Zuverlässige Dateioperationen, nachvollziehbare Rechte und automatisierte Tests haben Vorrang.

Repository: https://github.com/JRTools/samla

## Entwicklungsstand: Iteration 11 (.env und OIDC-Bibliotheksprüfung)

Vorhanden sind:

- Gemeinsame `.env`-Konfiguration für Web und CLI mit Prozessvorrang und sicherer Vorlage `example.env`.
- Getestete OIDC-Protokollbasis: Laufzeitkonfiguration, Discovery-Prüfung, Authorization Code mit PKCE S256, State/Nonce und einmalige Callback-Verarbeitung. Noch keine angeschlossene Webanmeldung oder Tokenprüfung.
- Erste responsive PHP-Weboberfläche mit Übersicht, Benutzer-/Gruppenverzeichnissen, Statusänderungen, Gruppenanlage/Umbenennung und manuellen Mitgliedschaften.
- Eigene Leseberechtigungen, begrenzte Verzeichnisseiten, CSRF-Schutz, HTML-Escaping, Sicherheitsheader und ausschließlich lokaler Entwicklungszugang.
- Experimentelle Plugin-API, atomare Registrierung und zentrale Autorisierung.
- RBAC mit expliziten Berechtigungen und globalen beziehungsweise bereichsbezogenen Rollenzuweisungen.
- Gruppenmapping mit mehreren Quellen und getrennten manuellen Mitgliedschaften.
- Datei- und umgebungsbasierte Konfiguration ohne zusätzliche Konfigurationsbibliothek.
- PDO-Anbindung, versionierte Migrationen mit Sperre, Prüfsummen und sichtbarem Fehlerzustand.
- Persistente Benutzer, Gruppen, bestätigte Gruppenstände, Mitgliedschaften, Rollen und Zuweisungen.
- Erster geschützter Verwaltungsdienst für fachliche Rollenzuweisungen innerhalb eines Speicherbereichs, mit ausdrücklichen Delegationsregeln und Berechtigungsobergrenzen.
- Geschützter Benutzerstatusdienst zum Sperren und Entsperren, mit Schutz aktuell nutzbarer Zugänge zur Statusverwaltung und zur bereits eingerichteten Mitgliedschaftsverwaltung.
- Geschützte manuelle Gruppenmitgliedschaften, mit Delegationsprüfung für jede Rolle in jedem betroffenen Speicherbereich und getrennt erhaltenen externen Quellen.
- Geschütztes Anlegen und Umbenennen interner Gruppen mit festen Kennungen, geprüftem Namen und Konfliktschutz.
- Geschützte Mappingvergabe und -entzug mit Delegationsprüfung auch für zukünftige Mitglieder und sofortiger Aktualisierung bereits gespeicherter Gruppenstände.
- Plugin-Validierung vor Änderungen, transaktionales Audit und persistierte Abschlussereignisse in einer Outbox.
- Unit-, Architektur- und echte MySQL-/MariaDB-Integrationstests.

Die erste Weboberfläche ist ausschließlich mit ausdrücklich aktiviertem lokalem Entwicklungszugang nutzbar. Ein produktiver OIDC-Login, WebDAV-Endpunkt und Dateioperationen fehlen weiterhin. In Produktion erlaubt der Entwicklungsadapter keinen Zugriff.

Globale beziehungsweise administrative Rollenzuweisungen, Rollenbearbeitung, Gruppenlöschung und atomare Mehrfach-Mappingänderungen bleiben offen. Mapping- und Rollenpflege haben noch keine Formulare. Der Schutz aktuell nutzbarer Administrationszugänge umfasst die beschriebenen vier Verwaltungsfähigkeiten; ein vollständiger Notfallzugang ist noch nicht umgesetzt. Die Outbox speichert Ereignisse, stellt sie aber noch nicht zu.

## Voraussetzungen

- PHP 8.4 oder 8.5, Composer, PDO und pdo_mysql sowie ctype.
- MySQL 8.0 oder MariaDB 11.8. Die konkrete Prüfabdeckung steht im Prüfprotokoll.
- PHPUnit 13 als Entwicklungsabhängigkeit und dessen erforderliche PHP-Erweiterungen.
- PHP-Sessionfunktion und ein beschreibbares, nicht öffentliches Sessionverzeichnis für die lokale Oberfläche.
- GD für die späteren Medienfunktionen.

Ein zusätzlicher Framework-, Node.js-, Redis- oder Suchdienst ist derzeit nicht eingebaut. Projektlizenz und zusätzliche Integrationsbibliotheken sind noch offen.

## Installation und Konfiguration

```sh
composer install
cp example.env .env
chmod 600 .env
```

Die `.env` im Projektverzeichnis wird automatisch von Weboberfläche, Migrationen, Entwicklungs-Seed und OIDC-Vorabprüfung geladen. Trage dort die Datenbank- und später die OIDC-Einstellungen ein. Bereits gesetzte Prozess-/Webservervariablen haben Vorrang, auch wenn sie leer sind. `.env` und `.env.*` werden nicht committed. Der PHP-Webserver muss `public` als Document Root verwenden; `.env` liegt außerhalb dieses Verzeichnisses. Dateieigentümer und Leserechte müssen zum PHP-Benutzer passen. Details und unterstützte Syntax: [Konfiguration](docs/configuration.md).

`APP_ENV` unterscheidet `production`, `development` und `test`; der Standard ist `production`. Mit prozessseitigem `APP_ENV=test` ignorieren automatisierte Tests die Deployment-Datei vollständig und verwenden ausschließlich explizite `TEST_DB_*`-Prozesswerte. Eine leere Gruppenfrische-Einstellung lässt Identitätszugriff gesperrt, bis die Richtlinie ausdrücklich festgelegt ist.

```sh
composer diagnose
```

Die Diagnose prüft lokale Fähigkeiten und ersetzt keine vollständige Betriebsabnahme.


## Oberfläche lokal ausprobieren

Eine separate Datenbank `samla_dev_local` bereitstellen und die DB-Verbindungsvariablen als Prozessumgebung setzen. Dann:

```sh
export APP_ENV=development
export APP_MAINTENANCE=0
export DB_NAME=samla_dev_local
export APP_GROUP_MAX_AGE_SECONDS=3600
export APP_WEB_DEV_ACCESS=1
export APP_DEV_ACTOR_ID=dev.admin
php bin/dev-seed.php
php -S 127.0.0.1:8080 -t public public/router.php
```

Anschließend im Browser http://127.0.0.1:8080/ öffnen. DB_ENGINE, DB_HOST, DB_PORT, DB_USER und DB_PASSWORD müssen zur eigenen Entwicklungsdatenbank passen. Die 3600 Sekunden sind nur ein lokales Beispiel. Der Seed legt explizite Demorechte an und erneuert synthetische Entwicklungsgruppendaten; er ist keine OIDC-Verifikation.

Der Zugang funktioniert nur mit PHPs lokal gebundenem Entwicklungsserver, Loopback-Client, lokalem Hostheader und einer Datenbank `samla_dev_*`. Kein FPM-/Produktionsbetrieb, Reverseproxy oder öffentlicher Tunnel. Es gibt noch keinen produktiven Login. Vollständige Einrichtung, Funktionsumfang und Sicherheitsgrenzen: [docs/web.md](docs/web.md).

## Migrationen

Zunächst eine leere Datenbank und ein darauf begrenztes Datenbankkonto bereitstellen. Vor Migrationen Schreibzugriffe einschließlich WebDAV durch das Deployment anhalten. `APP_MAINTENANCE=1` ist eine zusätzliche CLI-Vorbedingung und ersetzt das Anhalten laufender Prozesse nicht.

```sh
APP_MAINTENANCE=1 composer migrate
```

Migrationen werden vom vertrauenswürdigen Einstiegspunkt explizit zusammengestellt. Kern und RBAC verwalten eigene Tabellen. Ein Fehler kann bereits ausgeführte DDL hinterlassen. Der Migrationszustand blockiert dann weitere Läufe bis zur kontrollierten Prüfung oder Wiederherstellung. Es gibt keine automatische Rückmigration.

## Tests

```sh
composer test
```

Integrationstests benötigen eine eigene Datenbank mit dem Präfix `media_platform_test_` und ein nur dafür berechtigtes Konto. Sie verändern Testdaten und dürfen niemals mit einem Produktivkonto ausgeführt werden.

```sh
APP_ENV=test composer test:integration
```

Alle `TEST_DB_*`-Werte müssen als Umgebungsvariablen gesetzt sein. Fehlende Testkonfiguration führt zum Fehler, nicht zum stillen Überspringen der Datenbanktests. Die Testdatenbank wird vorab angelegt; die Tests legen keine beliebigen Datenbanken an und löschen keine Datenbank.

Nachgewiesen: in allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 jeweils 253 Tests mit 953 Assertions, ausgeführt mit PHPUnit 13.4.3. Die Composer-Abhängigkeiten sind installiert und über composer.lock festgeschrieben. Aktuelle Ergebnisse: [Prüfprotokoll](docs/verification.md).

## OIDC vorbereiten

Die bestätigte Testregistrierung verwendet Authorization Code mit PKCE S256 und `client_secret_basic` (ZITADEL: Basic). `example.env` beschreibt die `OIDC_*`-Werte. Trage Client-ID und Secret in die private `.env` ein oder setze sie über die Prozessumgebung. Die Vorlage enthält keine realen Zugangsdaten.

```sh
composer oidc:check
```

Mit `OIDC_ENABLED=1` und vollständiger OIDC-Konfiguration prüft dieser CLI-Aufruf das Discovery-Dokument über HTTPS. Er sendet weder Client-Secret noch Benutzerzugangsdaten und meldet keine erfolgreiche Anmeldung. PHP benötigt dafür HTTPS-Streams, erlaubtes `allow_url_fopen` und einen gültigen CA-Vertrauensspeicher. `OIDC_ENABLED` aktiviert aktuell ausschließlich die Protokollvorbereitung, keinen Weblogin. Der vorhandene Webzugang bleibt lokal begrenzt. Ablauf, Konfiguration und nächste Integrationsschritte: [OIDC](docs/oidc-provider.md).

Für die nächste Integration wurde `facile-it/php-openid-client 1.0.0` mit seinen 23 transitiven Paketen isoliert unter PHP 8.4/8.5 geprüft. Signatur-/Fehlerfälle, Basic/PKCE und Schlüsselwechsel sind durch ein reproduzierbares Prüfskript belegt. Die Bibliothek ist noch keine Anwendungsabhängigkeit; ihre Aufnahme benötigt die gemäß AGENTS.md vorgesehene Abstimmung. Grenzen, genaue Versionen und zusätzliche Adapterprüfungen stehen in der [Entscheidungsvorlage](docs/oidc-library-decision.md). Diese Vorbereitung erweitert noch nicht die nutzbaren Webfunktionen.

## Architektur und Dokumentation

| Verzeichnis | Inhalt |
|---|---|
| `src` | Kern, Konfiguration, Datenbankinfrastruktur und Identitätsadapter |
| `packages/plugin-api` | Öffentliche, vorläufige Plugin-Verträge |
| `plugins/rbac` | RBAC-Plugin mit eigener Persistenz und Migrationen |
| `migrations` | Kernmigrationen |
| `bootstrap` | Vertrauenswürdige Zusammenstellung der Kern- und Plugin-Migrationsbeiträge |
| `examples` | Beispielerweiterung |
| `tests` | Unit-, Architektur- und Integrationstests |
| `docs` | Konzept, Umsetzungsplan, Entscheidungen, Verträge und Betriebshinweise |

- [Umsetzungsplan](docs/Umsetzungsplan_Datei_und_Medienplattform.md)
- [Konzept](docs/Konzept_Datei_und_Medienplattform.md)
- [Plugin-API](docs/plugin-api.md)
- [Datenbankbetrieb](docs/database.md)
- [Verwaltungsdienst und Delegation](docs/administration.md)
- [OIDC-Providerdaten](docs/oidc-provider.md)
- [Entscheidungen und offene Angaben](docs/decisions.md)

Nach jeder Implementierungsphase werden README, Umsetzungsplan und Prüfergebnisse aktualisiert und gemeinsam mit dem Code committed. Offene Prüfungen werden ausdrücklich als offen geführt.
