# Prüfprotokoll: Iteration 2

Datum: 21. Oktober 2026. Projekt: Samla.

## Aktueller Nachweis

| PHP | PHPUnit | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|---|
| 8.4.5 | 13.4.3 aus composer.lock | MySQL 8.0.46 | 47 Tests, 111 Assertions | 14 Tests, 36 Assertions | 61 Tests, 147 Assertions |
| 8.5.4 | 13.4.3 aus composer.lock | MySQL 8.0.46 | 47 Tests, 111 Assertions | 14 Tests, 36 Assertions | 61 Tests, 147 Assertions |

Alle aufgeführten Suiten sind erfolgreich, ohne gemeldete Deprecations, PHPUnit-Deprecations oder Warnungen. Die Konfiguration lässt diese Probleme den Lauf fehlschlagen. Die PHP-Syntaxprüfung aller 46 eigenen PHP-Dateien war unter beiden PHP-Unterversionen erfolgreich.

Die Migrations-CLI wurde mit dem Composer-Autoloader unter beiden PHP-Unterversionen ausgeführt; bei aktuellem Schema meldet sie `Schema is current.` Die 14 Integrationstests führen zuvor echte Migrationen aus und prüfen auch Wiederholung, Prüfsummendrift, Fehlerzustand und die Verbindungssperre. Eine gezielte Datenbankfehlerprobe bestätigt den Rollback einer Rollenänderung nach bereits erfolgter Löschung alter Berechtigungen.

Composer-Auflösung und anschließende Installation der 27 Entwicklungsabhängigkeiten sind abgeschlossen. composer.lock fixiert den geprüften Abhängigkeitsstand. `composer validate --no-check-publish` ist erfolgreich; Composer weist auf die noch nicht ausgewählte Projektlizenz hin.

## Prüfumgebung

Die PHP-Laufzeiten und MySQL wurden nur für diese Prüfung separat aus Distributionspaketen bereitgestellt. Es wurden keine Produktivsysteme oder produktiven Daten verwendet. MySQL lauschte ausschließlich lokal. Die PHP-8.5-Laufzeit verwendete lokal bereitgestellte Systembibliotheken, ohne Systembibliotheken zu ersetzen. Temporäre Laufzeiten, Datenbankdateien und Tool-Hilfen gehören nicht zum Repository.

Die aktuellen Tests verwendeten den tatsächlichen Composer-Autoloader der installierten PHPUnit-Version. Projektklassen werden durch tests/bootstrap.php geladen. Die Projektkonfigurationen phpunit.xml und phpunit.integration.xml wurden unverändert verwendet.

## Reproduzierbare Befehle

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Für Integration und Migration müssen die in config/environment.example beschriebenen Datenbankvariablen gesetzt sein. Die Testdatenbank ist vorab separat anzulegen.

## Noch nicht nachgewiesen

- MariaDB 11.8 und weitere konkrete PHP-Patchversionen.
- Coverage, vollständige Nebenläufigkeitsprüfung sowie Update und Restore.
- HTTP, OIDC, App-Passwörter, WebDAV, Dateioperationen und Browserbedienung, da diese Funktionen noch nicht implementiert sind.
- Vollständige Betriebsabnahme; die Fähigkeitsdiagnose wurde nicht als solche gewertet.

Die früheren Vorabtests unter PHP 8.3.6 und PHPUnit 9.6.17 (Iteration 1: 37 Tests; früher Stand von Iteration 2: 59 Tests) sind historische Zwischenergebnisse. PHP 8.3 wird weiterhin nicht als Produktziel zugesagt.

## Ausgabe der aktuellen Zielmatrix

```text
PHP 8.4, lint 46 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

...............................................                   47 / 47 (100%)

Time: 00:00.022, Memory: 20.00 MB

OK (47 tests, 111 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

..............                                                    14 / 14 (100%)

Time: 00:00.723, Memory: 20.00 MB

OK (14 tests, 36 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 46 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

...............................................                   47 / 47 (100%)

Time: 00:00.031, Memory: 20.00 MB

OK (47 tests, 111 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

..............                                                    14 / 14 (100%)

Time: 00:00.821, Memory: 20.00 MB

OK (14 tests, 36 assertions)


8.5 / migrate CLI: Schema is current.
```
