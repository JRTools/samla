# Prüfung Iteration 22

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 390 Tests, 1567 Assertions (167 Unit-/Architekturtests, 223 Integrationstests). Syntax aller 174 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Laufzeitabhängigkeit.

Neue Fälle prüfen atomare LOCK-Neuanlage mit leeren Dateibytes und anschließendem versionierten PUT, exakten gemeinsamen Plugin-Vorschlag, fehlende create/update-Rechte, abgewiesene Shared-Sperren, unterstützte exklusive XML-Lockstruktur, kodierte Lockroots, rekursive Vorfahrenskonflikte ohne Teilanlage, erzwungenen Lease-INSERT-Fehler mit gemeinsamem Rollback sowie Veto vor beiden Referenzen. Die gekürzten PDOException-Zeilen im Protokoll stammen aus den absichtlich ausgelösten Audit-/Lease-Fehlerfixtures. Ein unvollständiges Lock-XML wurde zunächst von der Bibliothek als Shared vorbelegt; der Adapter prüft nun die verpflichtende Struktur und meldet 400.

Der 10000-Einträge-Test erreicht die bereits dokumentierte technische Grenze und bestätigt Abweisung weiterer MKCOL/PUT/COPY/LOCK-Anlagen bei weiter möglichem DELETE. Die bisherigen 1000-Dateien-/Fünfprozessfälle bleiben erhalten. Unterstützte Sperrarten werden per DOM/XPath am konkreten XML-Element statt nur an einer Textteilzeichenfolge geprüft.

Echte HTTP-Anfragen unter PHP 8.4/8.5 und HTTPS mit PHP 8.4-Produktionskonfiguration bestätigen LOCK auf neuen Dateinamen (201), PUT mit If-Nachweis (204), UNLOCK (204) und alle bisherigen Lese-/Schreib-/Überschreib-/OIDC-/Browser-/Historien-/Konsistenzfälle. Die zuletzt erfolgreiche GitHub-Matrix der vorangegangenen Iteration ist in verification-iteration-21.md dokumentiert; der neue Commit startet anschließend die CI-Matrix. Native Clients, vollständige Crash-/Last-/Restore-Abnahme und Betreiberdeployment bleiben offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 174 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 167 ( 37%)
............................................................... 126 / 167 ( 75%)
.........................................                       167 / 167 (100%)

Time: 00:04.551, Memory: 24.00 MB

OK (167 tests, 545 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 223 ( 28%)
............................................................... 126 / 223 ( 56%)
............................................................... 189 / 223 ( 84%)
..................................                              223 / 223 (100%)

Time: 01:19.158, Memory: 34.00 MB

OK (223 tests, 1022 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:109


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 174 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 167 ( 37%)
............................................................... 126 / 167 ( 75%)
.........................................                       167 / 167 (100%)

Time: 00:05.318, Memory: 24.00 MB

OK (167 tests, 545 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 223 ( 28%)
............................................................... 126 / 223 ( 56%)
............................................................... 189 / 223 ( 84%)
..................................                              223 / 223 (100%)

Time: 01:16.337, Memory: 34.00 MB

OK (223 tests, 1022 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:109


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 174 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 167 ( 37%)
............................................................... 126 / 167 ( 75%)
.........................................                       167 / 167 (100%)

Time: 00:05.049, Memory: 24.00 MB

OK (167 tests, 545 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 223 ( 28%)
............................................................... 126 / 223 ( 56%)
............................................................... 189 / 223 ( 84%)
..................................                              223 / 223 (100%)

Time: 01:07.935, Memory: 34.00 MB

OK (223 tests, 1022 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:109


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 174 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 167 ( 37%)
............................................................... 126 / 167 ( 75%)
.........................................                       167 / 167 (100%)

Time: 00:04.816, Memory: 24.00 MB

OK (167 tests, 545 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 223 ( 28%)
............................................................... 126 / 223 ( 56%)
............................................................... 189 / 223 ( 84%)
..................................                              223 / 223 (100%)

Time: 01:09.573, Memory: 34.00 MB

OK (223 tests, 1022 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:109


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
