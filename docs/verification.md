# Prüfung Iteration 17

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 340 Tests, 1344 Assertions (147 Unit-/Architekturtests, 193 Integrationstests). Syntax aller 159 eigenen PHP-Dateien und Migrations-CLI erfolgreich, keine PHPUnit-Warnungen/Deprecations.

Neue Fälle prüfen Sperre des eigenen Browserzugriffs ohne Nachweis, richtige/fremde Tokenbesitzer, lokale Sperre trotz Token, rekursive/Depth-0-Ordnerumfänge, betroffene Vorfahren/Ziele, überlappende Sperren, Bereichsgrenzen, stabile Zuordnung nach Rename, Verlängerung/Freigabe/Ablauf, Papierkorbsichtbarkeit, Wurzelsperren, Veto/Rollback und ausgeschlossenes Token/Ownerlabel im Audit. Ein Validator bringt absichtlich innerhalb der laufenden Transaktion eine neue Sperre ein: der zweite Guard verhindert die Änderung und rollt die Sperre mit zurück.

Die tatsächliche HTTP-/HTTPS-Prüfung unter PHP 8.4/8.5 sowie PHP 8.4-HTTPS-Produktionskonfiguration erzeugt eine persistente Sperre über den geprüften Kerndienst und bestätigt 423 für Browser-Papierkorb und Multipart-Ersetzung, weiter möglichen Download sowie normale weitere Änderungen nach Freigabe. Der Helper verweigert Datenbanken außerhalb media_platform_test_*. Alle bisherigen OIDC-/App-Passwort-/Datei-/Versionierungs-/Quoten-/Konsistenzabläufe bestehen. Der 1000-Dateien-Katalog und fünf gleichzeitig gestartete Uploadprozesse bleiben Teil aller vier Integrationsläufe.

Kein öffentlicher DAV-Endpunkt und keine native Clientkompatibilität sind in dieser Phase behauptet. Die Protokollintegration folgt auf Basis derselben Ressourcensperren. Neue Funktionen sind nicht auf dem Betreiberhost deployed. Vollständiger Crash-/Last-/Restore-, visueller und nativer Clientnachweis bleiben offen. Vorangegangene Prüfung: verification-iteration-16.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 159 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 147 ( 42%)
............................................................... 126 / 147 ( 85%)
.....................                                           147 / 147 (100%)

Time: 00:04.757, Memory: 24.00 MB

[30;42mOK (147 tests, 489 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 193 ( 32%)
............................................................... 126 / 193 ( 65%)
............................................................... 189 / 193 ( 97%)
....                                                            193 / 193 (100%)

Time: 00:21.925, Memory: 30.00 MB

[30;42mOK (193 tests, 855 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 159 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 147 ( 42%)
............................................................... 126 / 147 ( 85%)
.....................                                           147 / 147 (100%)

Time: 00:04.546, Memory: 24.00 MB

[30;42mOK (147 tests, 489 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 193 ( 32%)
............................................................... 126 / 193 ( 65%)
............................................................... 189 / 193 ( 97%)
....                                                            193 / 193 (100%)

Time: 00:30.433, Memory: 30.00 MB

[30;42mOK (193 tests, 855 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 159 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 147 ( 42%)
............................................................... 126 / 147 ( 85%)
.....................                                           147 / 147 (100%)

Time: 00:05.246, Memory: 24.00 MB

[30;42mOK (147 tests, 489 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 193 ( 32%)
............................................................... 126 / 193 ( 65%)
............................................................... 189 / 193 ( 97%)
....                                                            193 / 193 (100%)

Time: 00:16.336, Memory: 30.00 MB

[30;42mOK (193 tests, 855 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 159 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 147 ( 42%)
............................................................... 126 / 147 ( 85%)
.....................                                           147 / 147 (100%)

Time: 00:05.296, Memory: 24.00 MB

[30;42mOK (147 tests, 489 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 193 ( 32%)
............................................................... 126 / 193 ( 65%)
............................................................... 189 / 193 ( 97%)
....                                                            193 / 193 (100%)

Time: 00:20.369, Memory: 30.00 MB

[30;42mOK (193 tests, 855 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK

```
