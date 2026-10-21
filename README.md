# Samla

Samla ist eine schlanke, selbst betriebene Datei- und Medienplattform für eine Organisation. Geplant sind eine responsive Weboberfläche, WebDAV, OIDC-Anmeldung, gruppenbasierte Rollen, Versionierung und eine Medienbibliothek mit Metadaten und nachvollziehbaren KI-Kennzeichnungen.

Fachliche Funktionen erhalten öffentliche Erweiterungsverträge. Das mitgelieferte RBAC-Pflichtplugin verwendet dieselbe Plugin-API wie zusätzliche Erweiterungen. Zuverlässige Dateioperationen, nachvollziehbare Rechte und automatisierte Tests haben Vorrang.

Repository: https://github.com/JRTools/samla

## Entwicklungsstand: Iteration 4

Vorhanden sind:

- Experimentelle Plugin-API, atomare Registrierung und zentrale Autorisierung.
- RBAC mit expliziten Berechtigungen und globalen beziehungsweise bereichsbezogenen Rollenzuweisungen.
- Gruppenmapping mit mehreren Quellen und getrennten manuellen Mitgliedschaften.
- Umgebungsbasierte Konfiguration ohne zusätzliche Konfigurationsbibliothek.
- PDO-Anbindung, versionierte Migrationen mit Sperre, Prüfsummen und sichtbarem Fehlerzustand.
- Persistente Benutzer, Gruppen, bestätigte Gruppenstände, Mitgliedschaften, Rollen und Zuweisungen.
- Erster geschützter Verwaltungsdienst für fachliche Rollenzuweisungen innerhalb eines Speicherbereichs, mit ausdrücklichen Delegationsregeln und Berechtigungsobergrenzen.
- Plugin-Validierung vor Änderungen, transaktionales Audit und persistierte Abschlussereignisse in einer Outbox.
- Unit-, Architektur- und echte MySQL-/MariaDB-Integrationstests.

Es gibt noch keine produktiv nutzbare Weboberfläche, keinen OIDC-Login, keinen WebDAV-Endpunkt und keine Dateioperationen. Repository-Schreibmethoden sind interne Adapter. Der erste autorisierte Dienst für fachliche Rollenzuweisungen ist vorhanden. Globale beziehungsweise administrative Rollenzuweisungen, Rollenbearbeitung sowie Benutzer- und Gruppenverwaltung sind noch kein öffentlicher Anwendungsfall. Die Outbox speichert Ereignisse; ein Zustellungsdienst ist noch nicht implementiert.

## Voraussetzungen

- PHP 8.4 oder 8.5, Composer, PDO und pdo_mysql sowie ctype.
- MySQL 8.0 oder MariaDB 11.8. Die konkrete Prüfabdeckung steht im Prüfprotokoll.
- PHPUnit 13 als Entwicklungsabhängigkeit und dessen erforderliche PHP-Erweiterungen.
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
