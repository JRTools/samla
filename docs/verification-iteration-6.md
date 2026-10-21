# Prüfprotokoll: Iteration 6

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 49 Tests, 127 Assertions | 74 Tests, 309 Assertions | 123 Tests, 436 Assertions |
| 8.5.4 | MySQL 8.0.46 | 49 Tests, 127 Assertions | 74 Tests, 309 Assertions | 123 Tests, 436 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 49 Tests, 127 Assertions | 74 Tests, 309 Assertions | 123 Tests, 436 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 49 Tests, 127 Assertions | 74 Tests, 309 Assertions | 123 Tests, 436 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Die Syntax aller 68 eigenen PHP-Dateien ist unter beiden PHP-Unterversionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Keine neuen Migrationen oder Laufzeitabhängigkeiten.

## Neue Nachweise

26 zusätzliche Integrationstests (25 Mitgliedschaftstests und ein Statusverwaltungstest) prüfen:

- Hinzufügen und Entfernen manueller Mitgliedschaften mit wirksamer Rechteänderung, Versionserhöhung, Audit und Abschlussereignissen.
- Unveränderte Wiederholungen ohne Versionsanstieg oder weitere Änderungsereignisse.
- Erhalt mehrerer externer Mitgliedschaftsquellen und weiterhin wirksamer externer Rechte nach manuellem Entzug.
- Kein automatisches Entsperren von Zielbenutzern oder Erneuern veralteter Gruppendaten.
- Globales Mitgliedschaftsrecht, bereichsbezogenes Verwaltungsrecht und Delegation sind jeweils notwendig. Eigene Zielrechte ersetzen keine Delegation.
- Jede Rolle der Zielgruppe und jeder Speicherbereich müssen abgedeckt sein. Weitere Rollen oder Bereiche ohne Delegation verhindern die gesamte Änderung.
- Rollenwachstum überschreitet keine Berechtigungsobergrenze; fachlicher Entzug bleibt bei passender Regel möglich.
- Globale und administrative RBAC-Gruppen sind auch bei vorhandener Regel und bei Selbständerungen ausgeschlossen.
- Gruppen ohne Rollen benötigen weiterhin das globale Mitgliedschaftsrecht.
- Gesperrte/veraltete Akteure, unbekannte Gruppen, fehlende Speicherbereiche und unbekannte Zielbenutzer verhindern unzulässige Änderungen.
- Zusätzliche Plugin-Einschränkungen für den Zielbereich gelten auch bei globalem Mitgliedschaftsrecht.
- Plugin-Veto und erneut geprüfte Zielrollenzuweisungen nach Validatoren sind wirksam.
- Indirekter Verlust des letzten Status- oder Mitgliedschaftsadministrators über Plugin-Einschränkungen führt zum Rollback der Mitgliedschaft und Autorisierungsversion.
- Sperren des letzten zuvor nutzbaren Mitgliedschaftsadministrators wird auch im Statusdienst abgelehnt. Mit weiterem nutzbarem Mitgliedschaftsadministrator ist die Sperre möglich.
- Audit- und Outbox-Fehler rollen Mitgliedschaft, Version und Protokolleinträge vollständig zurück.
- Der interne Membershipwriter verlangt eine aktive Transaktion.

Bestehende Tests einschließlich administrativer Konkurrenzsperre und Statusverwaltung laufen weiter. Die zusätzlichen Architektur-Assertions erfassen die neuen Kern- und RBAC-Klassen.

## Grenzen und Reproduktion

Die Prüfung verbleibender Administrationsfähigkeiten gilt für die beschriebenen Status- und Mitgliedschaftsänderungen und den aktuellen Zustand. Kein Nachweis zukünftiger Provider-Verfügbarkeit, unbegrenzter Gruppenfrische oder eines Notfallzugangs. Globale administrative Gruppen können über diesen Dienst nicht gepflegt werden. Gruppenstammdaten, Mappingverwaltung und OIDC-Synchronisierung fehlen noch. Keine Oberfläche, HTTP-/OIDC-/WebDAV-Integration, Coverage-Messung, umfassende Last-/Nebenläufigkeitsabnahme oder Restore-Prüfung. Outbox-Ereignisse werden gespeichert, noch nicht zugestellt.

Reproduktion nach Einrichtung der Prozessumgebung und isolierten Testdatenbank:

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Konfiguration: database.md. Historische Ergebnisse: verification-iteration-5.md, verification-iteration-4.md und verification-iterations-2-3.md. Temporäre Testlaufzeiten und Datenbankdateien gehören nicht zum Repository.

## MySQL-Ausgabe

```text
PHP 8.4, lint 68 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.014, Memory: 20.00 MB

OK (49 tests, 127 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 74 ( 87%)
.........                                                         74 / 74 (100%)

Time: 00:08.960, Memory: 24.00 MB

OK (74 tests, 309 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 68 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.020, Memory: 20.00 MB

OK (49 tests, 127 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 74 ( 87%)
.........                                                         74 / 74 (100%)

Time: 00:07.662, Memory: 24.00 MB

OK (74 tests, 309 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 68 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.022, Memory: 20.00 MB

OK (49 tests, 127 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 74 ( 87%)
.........                                                         74 / 74 (100%)

Time: 00:05.903, Memory: 24.00 MB

OK (74 tests, 309 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 68 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.032, Memory: 20.00 MB

OK (49 tests, 127 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................................................. 65 / 74 ( 87%)
.........                                                         74 / 74 (100%)

Time: 00:04.912, Memory: 24.00 MB

OK (74 tests, 309 assertions)


8.5 / migrate CLI: Schema is current.
```
