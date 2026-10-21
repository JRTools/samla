# Prüfprotokoll: Iteration 5

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 49 Tests, 122 Assertions | 48 Tests, 176 Assertions | 97 Tests, 298 Assertions |
| 8.5.4 | MySQL 8.0.46 | 49 Tests, 122 Assertions | 48 Tests, 176 Assertions | 97 Tests, 298 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 49 Tests, 122 Assertions | 48 Tests, 176 Assertions | 97 Tests, 298 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 49 Tests, 122 Assertions | 48 Tests, 176 Assertions | 97 Tests, 298 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Die Syntax aller 63 eigenen PHP-Dateien ist unter beiden PHP-Unterversionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Es wurden keine neuen Migrationen oder Laufzeitabhängigkeiten benötigt.

## Neue Nachweise

16 neue Integrationstests prüfen den Benutzerstatusdienst:

- Sperren und Entsperren wirken sofort auf aktuelle Autorisierungsentscheidungen und erhöhen die Version bei Änderungen.
- Unveränderte Wiederholung erhält Audit ohne Versionsänderung oder zusätzliches Abschlussereignis.
- Entsperren erhält manuelle Mitgliedschaften und erneuert keine veralteten Gruppendaten.
- Fehlendes Verwaltungsrecht sowie gesperrte und veraltete Akteure verhindern Änderungen.
- Der letzte aktuell nutzbare Statusadministrator kann sich nicht sperren; mit einem weiteren nutzbaren Statusadministrator ist dies möglich.
- Gesperrte, veraltete oder durch ein Plugin eingeschränkte Alternativkonten genügen nicht als verbleibender Zugang.
- Validator-Veto und erneute Autorisierung nach Validatoren verhindern unzulässige Änderungen.
- Outbox-Fehler rollen Status, Autorisierungsversion und Audit zurück.
- Unbekannte Zielbenutzer hinterlassen weder erfolgreiche Audit-Einträge noch offene Transaktionen.
- Der interne Writer verlangt eine aktive Transaktion.

Die vorhandenen Nachweise für Delegation, Auditfehler und konkurrierend gehaltene Administrationssperre laufen unverändert mit. Die zusätzlichen Architektur-Assertions prüfen die neu hinzugefügten Klassen auf Modulgrenzen.

## Grenzen und Reproduktion

Der neue Schutz gilt für Statusänderungen und das globale Statusverwaltungsrecht. Die Prüfung ist eine Momentaufnahme; Provider-Verfügbarkeit, zukünftiger Gruppenablauf, künftige Rollen-/Gruppenänderungen und ein vollständiger Notfallzugang sind nicht abgedeckt. Kein HTTP-/OIDC-/WebDAV-Test, keine Coverage-Messung, umfassende Last-/Konkurrenzabnahme oder Restore-Prüfung. Outbox-Ereignisse werden gespeichert, noch nicht zugestellt.

Nach Einrichtung der Prozessumgebung und isolierten Testdatenbank:

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Konfiguration und Testdatenbankgrenzen stehen in database.md. Historische Protokolle: verification-iteration-4.md sowie verification-iterations-2-3.md. Temporäre Testlaufzeiten und Datenbankdateien gehören nicht zum Repository.

## MySQL-Ausgabe

```text
PHP 8.4, lint 63 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.019, Memory: 20.00 MB

OK (49 tests, 122 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................                  48 / 48 (100%)

Time: 00:05.298, Memory: 22.00 MB

OK (48 tests, 176 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 63 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.021, Memory: 20.00 MB

OK (49 tests, 122 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................                  48 / 48 (100%)

Time: 00:07.987, Memory: 22.00 MB

OK (48 tests, 176 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 63 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.014, Memory: 20.00 MB

OK (49 tests, 122 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................                  48 / 48 (100%)

Time: 00:03.314, Memory: 22.00 MB

OK (48 tests, 176 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 63 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.023, Memory: 20.00 MB

OK (49 tests, 122 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................                  48 / 48 (100%)

Time: 00:05.454, Memory: 22.00 MB

OK (48 tests, 176 assertions)


8.5 / migrate CLI: Schema is current.
```
