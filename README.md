# Samla

Samla ist eine schlanke, selbst betriebene Datei- und Medienplattform für eine Organisation. Eine erste responsive Verwaltungsoberfläche ist lokal nutzbar. Geplant sind WebDAV, produktive OIDC-Anmeldung, Dateiversionierung und eine Medienbibliothek mit Metadaten und nachvollziehbaren KI-Kennzeichnungen. Gruppenbasierte Rechte und die geschützten Verwaltungsdienste bilden bereits das Backend.

Fachliche Funktionen erhalten öffentliche Erweiterungsverträge. Das mitgelieferte RBAC-Pflichtplugin verwendet dieselbe Plugin-API wie zusätzliche Erweiterungen. Zuverlässige Dateioperationen, nachvollziehbare Rechte und automatisierte Tests haben Vorrang.

Repository: https://github.com/JRTools/samla

## Entwicklungsstand: Iteration 9

Vorhanden sind:

- Erste responsive PHP-Weboberfläche mit Übersicht, Benutzer-/Gruppenverzeichnissen, Statusänderungen, Gruppenanlage/Umbenennung und manuellen Mitgliedschaften.
- Eigene Leseberechtigungen, begrenzte Verzeichnisseiten, CSRF-Schutz, HTML-Escaping, Sicherheitsheader und ausschließlich lokaler Entwicklungszugang.
- Experimentelle Plugin-API, atomare Registrierung und zentrale Autorisierung.
- RBAC mit expliziten Berechtigungen und globalen beziehungsweise bereichsbezogenen Rollenzuweisungen.
- Gruppenmapping mit mehreren Quellen und getrennten manuellen Mitgliedschaften.
- Umgebungsbasierte Konfiguration ohne zusätzliche Konfigurationsbibliothek.
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
```

`config/environment.example` beschreibt die benötigten Prozess- beziehungsweise Webserver-Umgebungsvariablen. Die Datei wird nicht automatisch geladen. Secrets gehören in die Laufzeitkonfiguration und nicht in das Repository.

`APP_ENV` unterscheidet `production`, `development` und `test`; der Standard ist `production`. Der Testmodus verwendet ausschließlich `TEST_DB_*`. Eine leere Gruppenfrische-Einstellung lässt Identitätszugriff gesperrt, bis die Richtlinie ausdrücklich festgelegt ist.

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

Nachgewiesen: in allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 jeweils 81 Tests mit 236 Assertions, ausgeführt mit PHPUnit 13.4.3. Die Composer-Abhängigkeiten sind installiert und über composer.lock festgeschrieben. Aktuelle Ergebnisse: [Prüfprotokoll](docs/verification.md).

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
