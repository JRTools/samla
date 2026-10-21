# Prüfprotokoll: Iteration 9

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 90 Tests, 205 Assertions | 136 Tests, 599 Assertions | 226 Tests, 804 Assertions |
| 8.5.4 | MySQL 8.0.46 | 90 Tests, 205 Assertions | 136 Tests, 599 Assertions | 226 Tests, 804 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 90 Tests, 205 Assertions | 136 Tests, 599 Assertions | 226 Tests, 804 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 90 Tests, 205 Assertions | 136 Tests, 599 Assertions | 226 Tests, 804 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Die Syntax aller 96 eigenen PHP-Dateien ist unter beiden PHP-Unterversionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Keine neuen Migrationen oder Anwendungsbibliotheken. Die PHP-Sessionfunktion wird für die Oberfläche verwendet und von der Diagnose berücksichtigt.

## Neue Unit- und Integrationsnachweise

6 zusätzliche Unitfälle und 14 zusätzliche Integrationsfälle prüfen:

- Entwicklungsadapter nur mit explizitem Opt-in, separatem Datenbankpräfix, Loopback-Bindung/-Client, lokalem Host und gültigem serverseitigem Akteur.
- Produktions-/Testmodus, FPM, Remoteclient, breite Bindung, falscher Host und Forwarded-Header aktivieren den Adapter nicht.
- Array-/Überlängenfelder, ungültige Cursor und freie Templatepfade werden abgelehnt; dynamische HTML-Werte werden escaped.
- Übersicht, Benutzer- und Gruppenseiten mit tatsächlichen Verzeichnisdaten und eigenen globalen Leseberechtigungen.
- Verwaltungsrechte implizieren keine Leserechte. Reine Leser erhalten keine Formulare und können gefälschte Schreibaufrufe nicht verwenden.
- Plugin-Einschränkungen sperren Verzeichniszugriffe; unbekannte, lokal gesperrte und veraltete Akteure erhalten keine Daten.
- CSRF-Fehler verhindern Änderungen und Audit. Erfolgreiche Formulare verwenden 303, feste interne Redirectziele und Sessionmeldungen.
- Akteurangaben aus Formularen überschreiben den vertrauenswürdig übergebenen Akteur nicht.
- Statusformulare bewahren den letzten Administrator. Mitgliedschaftsformulare verwenden Delegation und schützen administrative Gruppen.
- Gruppenanlage/-umbenennung verwenden die vorhandenen Dienste; veraltete Namen führen zu 409.
- Begrenzte, überlappungsfreie Cursorseiten und keine Issuer-/Subjectausgabe.
- Mitgliederzahlen zählen unterschiedliche Personen statt manuelle/externe Quellen doppelt.
- Ungültige Operationen, falsche HTTP-Methode und unbekannte Route erzeugen keine Änderung.

## Echte HTTP- und Browserprüfungen

Zusätzlich zu PHPUnit wurde der eingebaute PHP-Webserver mit einer getrennten samla_dev_*-Datenbank verwendet. Unter PHP 8.4.5 und 8.5.4 mit MySQL 8.0.46 sind die echten HTTP-Prüfungen erfolgreich: Sessioncookie mit HttpOnly/SameSite=Strict, CSRF, Anlage und Umbenennung über POST/Redirect/GET, Namenskonflikt, Sperren/Entsperren, Mitgliedschaftsformulare, Schutz administrativer Gruppen, falscher Host, falsche Methode, unbekannte Route, Schutz nicht öffentlicher Dateien und CSS-Auslieferung. Derselbe Entwicklungsadapter liefert mit APP_ENV=production jeweils 503.

Ein Headless-Chrome-Browser 156.0.8078.47 mit Playwright prüfte Übersicht, Benutzer und Gruppen unter PHP 8.4.5/MySQL bei 1440×1080 und 390×844 Pixeln. Gruppenanlage, Umbenennung, Mitgliedschaftsformular und Navigation wurden über tatsächliche Bedienelemente ausgeführt. Kein horizontaler Seitenüberlauf und keine pageerror-Ereignisse. Die Desktop- und Mobilansichten wurden zusätzlich anhand interner Screenshots visuell geprüft. Der im Repository enthaltene optionale browser-smoke.cjs lief ebenfalls erfolgreich. Die Browserprüfung wurde nicht zusätzlich gegen MariaDB wiederholt; deren PHP-/Controllerintegration ist in der Vierfachmatrix enthalten.

Die Testbrowser und ihre Downloads sind temporäre Prüfwerkzeuge und keine Runtime-/Node-Abhängigkeiten von Samla. Produktions-OIDC und echtes HTTPS-Sitzungsverhalten sind dadurch nicht abgenommen.

## Reproduktion und Grenzen

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Für echte HTTP-/Browserprüfungen eine ausschließlich dafür vorgesehene lokale Entwicklungsinstanz nach web.md bereitstellen. Diese Prüfer legen Gruppen an und verändern Demoidentitäten:

```sh
python3 tests/http-smoke.py http://127.0.0.1:8080
node tests/browser-smoke.cjs http://127.0.0.1:8080
```

Der zweite Prüfer braucht separat bereitgestelltes Playwright und einen Browser. Die Anwendung selbst benötigt beides nicht. Die Konfiguration und Testsicherheitsgrenzen stehen in database.md und web.md.

Keine produktive Authentifizierung, kein öffentlicher Betrieb, keine OIDC-/WebDAV-Integration, keine Datei-/Mapping-/Rollenoberfläche. Keine vollständige Accessibility-/Browsermatrix, Coverage-Messung, Last-/Nebenläufigkeitsabnahme oder Restore-Prüfung. Der Entwicklungsadapter bleibt lokal und explizit beschränkt. Outbox-Ereignisse sind persistiert, noch nicht zugestellt.

Historische Ergebnisse: verification-iteration-8.md und die vorherigen Iterationsprotokolle. Laufzeiten, Browserdateien, Screenshots und Datenbanken liegen außerhalb des Repositorys.

## MySQL-Ausgabe

```text
PHP 8.4, lint 96 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 90 ( 72%)
.........................                                         90 / 90 (100%)

Time: 00:00.021, Memory: 20.00 MB

OK (90 tests, 205 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:08.124, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 96 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 90 ( 72%)
.........................                                         90 / 90 (100%)

Time: 00:00.014, Memory: 20.00 MB

OK (90 tests, 205 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:07.054, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 96 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 90 ( 72%)
.........................                                         90 / 90 (100%)

Time: 00:00.018, Memory: 20.00 MB

OK (90 tests, 205 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:05.113, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 96 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 90 ( 72%)
.........................                                         90 / 90 (100%)

Time: 00:00.015, Memory: 20.00 MB

OK (90 tests, 205 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:04.748, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.5 / migrate CLI: Schema is current.
```

## HTTP-Ausgabe

```text
8.4 {"http_smoke": "passed", "checks": "session cookie, CSRF, CRUD forms, conflict, protected admin, membership delegation, host gate, routes, headers"}
8.4 production dev-access gate: 503
8.5 {"http_smoke": "passed", "checks": "session cookie, CSRF, CRUD forms, conflict, protected admin, membership delegation, host gate, routes, headers"}
8.5 production dev-access gate: 503
```
