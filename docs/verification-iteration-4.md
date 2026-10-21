# Prüfprotokoll: Iteration 4

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 49 Tests, 118 Assertions | 32 Tests, 118 Assertions | 81 Tests, 236 Assertions |
| 8.5.4 | MySQL 8.0.46 | 49 Tests, 118 Assertions | 32 Tests, 118 Assertions | 81 Tests, 236 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 49 Tests, 118 Assertions | 32 Tests, 118 Assertions | 81 Tests, 236 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 49 Tests, 118 Assertions | 32 Tests, 118 Assertions | 81 Tests, 236 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Alle 60 eigenen PHP-Dateien sind unter beiden PHP-Unterversionen syntaktisch geprüft. Die Migrations-CLI mit Composer-Autoloader meldet unter allen vier Kombinationen ein aktuelles Schema. Die zusätzlichen Migrationen liefen auf bereits vorhandenen Schemas der vorigen Iterationen.

## Neue Nachweise

- Verwaltungsrecht und passende Delegationsregel sind beide notwendig.
- Fremder Bereich, unbekannte Ressourcen, gesperrter Akteur und veralteter Gruppenstand werden abgelehnt.
- Rollenwachstum erweitert keine Delegationsobergrenze; ein berechtigter Entzug bleibt möglich.
- Globale und administrative Rollenzuweisungen sind außerhalb dieses ersten Dienstes gesperrt.
- Plugin-Veto und erneute Prüfung nach Validatoren verhindern eine unzulässige Änderung.
- Validatorreihenfolge ist deterministisch; Kontextdaten sind unveränderlich.
- Zuweisung, Audit und Abschlussereignis werden bei Audit- oder Outbox-Fehlern gemeinsam zurückgerollt.
- Unveränderte Wiederholungen erzeugen keine zusätzlichen Änderungsereignisse.
- Eine zweite Datenbankverbindung mit gehaltener Administrationssperre verhindert den Schreibzugriff bis zum geprüften Lock-Timeout.
- Das separate Referenzplugin ergänzt einen tatsächlich wirksamen Validator über die öffentliche API.

## Grenzen

Keine Coverage-Messung, vollständige Last-/Nebenläufigkeitsabnahme, Restore-Prüfung oder HTTP-/OIDC-/WebDAV-Integration. Der geprüfte Sperrfall ersetzt keine umfassende Konkurrenzprüfung aller zukünftigen Verwaltungszugänge. Outbox-Empfänger und Zustellung sind noch nicht implementiert. Testlaufzeiten und Datenbankdateien liegen außerhalb des Repositorys.

Reproduktion nach Einrichtung der Prozessumgebung und einer isolierten Testdatenbank:

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Historische Ergebnisse stehen in verification-iterations-2-3.md. Konfiguration und Testdatenbankgrenzen sind in database.md beschrieben.

## MySQL-Ausgabe

```text
PHP 8.4, lint 60 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.021, Memory: 20.00 MB

OK (49 tests, 118 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................                                  32 / 32 (100%)

Time: 00:04.035, Memory: 22.00 MB

OK (32 tests, 118 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 60 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.013, Memory: 20.00 MB

OK (49 tests, 118 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................                                  32 / 32 (100%)

Time: 00:03.167, Memory: 22.00 MB

OK (32 tests, 118 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 60 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.027, Memory: 20.00 MB

OK (49 tests, 118 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

................................                                  32 / 32 (100%)

Time: 00:02.752, Memory: 22.00 MB

OK (32 tests, 118 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 60 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

.................................................                 49 / 49 (100%)

Time: 00:00.022, Memory: 20.00 MB

OK (49 tests, 118 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

................................                                  32 / 32 (100%)

Time: 00:02.129, Memory: 22.00 MB

OK (32 tests, 118 assertions)


8.5 / migrate CLI: Schema is current.
```
