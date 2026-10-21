# Prüfung Iteration 16

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 324 Tests, 1277 Assertions (144 Unit-/Architekturtests, 180 Integrationstests). Syntax aller 152 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine PHPUnit-Warnungen/Deprecations.

Neue Fälle prüfen konfigurierbare Uploadgrenzen und tatsächliches begrenztes Streaming, echte frühere Inhalte, Wiederherstellung als neuer Stand, veraltete Revision vor Lesen des Eingabestreams, quotenkorrekte Versionsspeicherung ohne stilles Entfernen, separate Historienrechte, fremde Versionskennungen, Papierkorbvorfahren, Schutz historischer Objekte vor GC, Plugin-Veto/Rollback und Versionsformulare. Nach der Änderung auf neueste Revision zuerst wurde die vollständige Matrix erneut ausgeführt.

Ein isolierter Katalog mit 1000 Dateien wird über zehn begrenzte Seiten ohne Dubletten/Verluste gelesen. Fünf PHP-Unterprozesse warten an einer Startbarriere und laden gleichzeitig als derselbe berechtigte Testakteur zehn Bytes hoch. Bei Quote von 30 Bytes entstehen genau drei Dateien und drei erfolgreiche Auditereignisse; zwei Uploads werden abgewiesen. Dieser tatsächliche Konkurrenztest ist Teil aller vier Matrixkombinationen. Er belegt Quotenserialisierung in diesem Szenario, keinen umfassenden Mehrbenutzer-/Crash-/Lastnachweis.

Der erneute echte HTTP-/HTTPS-Durchstich unter PHP 8.4/8.5 und zusätzlich PHP 8.4-HTTPS-Produktionskonfiguration prüft Multipart-Ersetzen, 409 für veraltete Revision, zunächst 403 ohne Historienrecht, explizite Offline-history-Freigabe, historische Attachments und Wiederherstellung. Alle bestehenden OIDC-, App-Passwort-, Gruppen-/Rollen-, Datei-/Papierkorb-, Storage-Init-/Konsistenz-/GC-Prüfungen bestehen. Reproduktion über tests/Tools und ausdrücklich isolierte media_platform_test_*-Datenbanken; die HTTP-Werkzeuge leeren Anwendungstabellen.

Betriebsangabe des Betreibers: ungefähr 1000 Dateien, fünf aktive Benutzer, iOS/Android/Windows/macOS. Konkrete native Clients/OS-Versionen, große Datei-/Volumenziele und deren Leistungs-/Abbruchverhalten sind weiterhin nicht abgenommen. Die konfigurierbare technische Grenze bis 1 TiB ist keine Zusage solcher Hostkapazität. Keine neuen Funktionen auf dem Betreiberhost deployed. Gemeinsame DAV-/Browserlocks und öffentlicher DAV-Endpunkt folgen. Vollständiger Restore-/Crash-/Lastnachweis und visuelle/native Abnahme bleiben offen. Vorangegangene Prüfung: verification-iteration-15.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 152 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 144 ( 43%)
............................................................... 126 / 144 ( 87%)
..................                                              144 / 144 (100%)

Time: 00:04.801, Memory: 24.00 MB

[30;42mOK (144 tests, 476 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 180 ( 35%)
............................................................... 126 / 180 ( 70%)
......................................................          180 / 180 (100%)

Time: 00:18.342, Memory: 28.00 MB

[30;42mOK (180 tests, 801 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 152 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 144 ( 43%)
............................................................... 126 / 144 ( 87%)
..................                                              144 / 144 (100%)

Time: 00:04.473, Memory: 24.00 MB

[30;42mOK (144 tests, 476 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 180 ( 35%)
............................................................... 126 / 180 ( 70%)
......................................................          180 / 180 (100%)

Time: 00:19.420, Memory: 28.00 MB

[30;42mOK (180 tests, 801 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 152 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 144 ( 43%)
............................................................... 126 / 144 ( 87%)
..................                                              144 / 144 (100%)

Time: 00:05.170, Memory: 24.00 MB

[30;42mOK (144 tests, 476 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 180 ( 35%)
............................................................... 126 / 180 ( 70%)
......................................................          180 / 180 (100%)

Time: 00:13.764, Memory: 28.00 MB

[30;42mOK (180 tests, 801 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 152 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 144 ( 43%)
............................................................... 126 / 144 ( 87%)
..................                                              144 / 144 (100%)

Time: 00:05.290, Memory: 24.00 MB

[30;42mOK (144 tests, 476 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 180 ( 35%)
............................................................... 126 / 180 ( 70%)
......................................................          180 / 180 (100%)

Time: 00:16.926, Memory: 28.00 MB

[30;42mOK (180 tests, 801 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK

```
