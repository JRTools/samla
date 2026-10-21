# Prüfprotokoll: Iteration 7

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 69 Tests, 151 Assertions | 95 Tests, 396 Assertions | 164 Tests, 547 Assertions |
| 8.5.4 | MySQL 8.0.46 | 69 Tests, 151 Assertions | 95 Tests, 396 Assertions | 164 Tests, 547 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 69 Tests, 151 Assertions | 95 Tests, 396 Assertions | 164 Tests, 547 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 69 Tests, 151 Assertions | 95 Tests, 396 Assertions | 164 Tests, 547 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Die Syntax aller 73 eigenen PHP-Dateien ist unter beiden PHP-Unterversionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Keine neuen Migrationen oder Laufzeitabhängigkeiten.

## Neue Nachweise

20 zusätzliche Unitfälle und 21 zusätzliche Integrationsfälle prüfen:

- Gültige Unicode-Namen bis einschließlich 255 vierbytefähiger Codepoints, ohne automatische Normalisierung.
- Ablehnung leerer/zu langer Namen, umgebenden Leerraums, Steuerzeichen und ungültigen UTF-8; auch erwartete Namen werden validiert.
- Unveränderlichkeit des Namensvorschlags und strikte Zuordnung zu Gruppenoperation, Ziel und Scope im MutationContext.
- Anlage mit generierter interner Kennung ohne implizite Mitgliedschaft oder Rollenvergabe; doppelte Namen behalten getrennte Kennungen.
- Umbenennen erhält Kennung, manuelle und externe Quellen, Gruppenmapping, Rollenzuweisungen, Identitätsversionen und effektiven Dateizugriff.
- Veraltete beziehungsweise byteweise abweichende Erwartungsnamen führen zu Konflikt, ohne neueren Zustand zu überschreiben.
- Unveränderte Namen erhalten Audit ohne zusätzliches Abschlussereignis.
- Eigenständiges Verwaltungsrecht, aktuelle und ungesperrte Identität sind notwendig.
- Plugin-Validatoren erhalten Namen und können vorab ablehnen. Autorisierung wird anschließend erneut geprüft.
- Audit und Outbox enthalten die Namen nicht automatisch.
- Umbenennen darf über Plugin-Einschränkungen keinen letzten zuvor nutzbaren Administrationszugang entfernen.
- Status- und Mitgliedschaftsänderungen erhalten auch den letzten bereits nutzbaren Gruppenstammdatenadministrator.
- Ungültige Eingaben und unbekannte Ziele hinterlassen keinen erfolgreichen Änderungseintrag.
- Auditfehler bei Anlage sowie Outboxfehler bei Umbenennung rollen Gruppenänderung und Protokolleinträge vollständig zurück.
- Interner Writer benötigt eine Transaktion und kann eine belegte Gruppenkennung nicht neu anlegen.

Alle bisherigen Tests einschließlich Delegation, Quellenprovenienz, Sperrkonkurrenz und Statusverwaltung laufen weiterhin erfolgreich.

## Grenzen und Reproduktion

Namenskonflikte beruhen auf dem erwarteten Namen und sind kein allgemeines Revisionsmodell. Eine zwischenzeitliche Änderung mit Rückkehr zum vorherigen Namen wird dadurch nicht erkannt. Gruppenanlage ist nicht idempotent. Löschen, Mappingänderungen und OIDC-Synchronisierung sind noch keine öffentlichen Anwendungsfälle. Die geprüften Nachbedingungen erhalten aktuell nutzbare Zugänge zu drei beschriebenen Verwaltungsfähigkeiten; sie garantieren keinen zukünftigen Provider-Login oder unbegrenzte Gruppenfrische und ersetzen keinen Notfallzugang.

Keine Oberfläche, HTTP-/OIDC-/WebDAV-Integration, Coverage-Messung, umfassende Last-/Nebenläufigkeitsabnahme oder Restore-Prüfung. Outbox-Ereignisse sind persistiert, noch nicht zugestellt.

Reproduktion nach Einrichtung der Prozessumgebung und einer isolierten Testdatenbank:

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Konfiguration: database.md. Vorherige Ergebnisse: verification-iteration-6.md, verification-iteration-5.md, verification-iteration-4.md und verification-iterations-2-3.md. Temporäre Testlaufzeiten und Datenbankdateien gehören nicht zum Repository.

## MySQL-Ausgabe

```text
PHP 8.4, lint 73 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 69 ( 94%)
....                                                              69 / 69 (100%)

Time: 00:00.019, Memory: 20.00 MB

OK (69 tests, 151 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 95 ( 68%)
..............................                                    95 / 95 (100%)

Time: 00:17.700, Memory: 24.00 MB

OK (95 tests, 396 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 73 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 69 ( 94%)
....                                                              69 / 69 (100%)

Time: 00:00.025, Memory: 20.00 MB

OK (69 tests, 151 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 95 ( 68%)
..............................                                    95 / 95 (100%)

Time: 00:15.117, Memory: 24.00 MB

OK (95 tests, 396 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 73 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 69 ( 94%)
....                                                              69 / 69 (100%)

Time: 00:00.021, Memory: 20.00 MB

OK (69 tests, 151 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 95 ( 68%)
..............................                                    95 / 95 (100%)

Time: 00:11.222, Memory: 24.00 MB

OK (95 tests, 396 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 73 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 69 ( 94%)
....                                                              69 / 69 (100%)

Time: 00:00.044, Memory: 20.00 MB

OK (69 tests, 151 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 95 ( 68%)
..............................                                    95 / 95 (100%)

Time: 00:08.011, Memory: 24.00 MB

OK (95 tests, 396 assertions)


8.5 / migrate CLI: Schema is current.
```
