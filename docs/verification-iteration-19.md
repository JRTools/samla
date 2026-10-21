# Prüfung Iteration 19

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 376 Tests, 1484 Assertions (166 Unit-/Architekturtests, 210 Integrationstests). Syntax aller 173 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Migration 0006_active_names ergänzt die Eindeutigkeit aktiver Namen; keine zusätzliche Laufzeitabhängigkeit.

Neue DAV-Fälle prüfen PUT-Anlage, versionierte Ersetzung, veraltetes If-Match, MKCOL/MOVE/Einzeldatei-COPY, Ablehnung von Überschreibzielen ohne Verlust, DELETE/Neuanlage mehrfach gleicher Namen, konfliktbehaftete Wiederherstellung, gemeinsame Browser-/DAV-Sperren, Verlängerung/Freigabe, rekursive Sperren, fehlendes Lock-null-Ziel ohne Teilanlage, fremde Destination und If-URI, vollständig geprüfte If-Syntax, tatsächliche und deklarierte Streamgrenzen sowie leer angekommenen Finder-Inhalt. Ein zweiter berechtigter Benutzer kann einen entdeckten Token weder zum Schreiben noch zum Freigeben verwenden.

Ein Validator widerruft das verwendete App-Passwort absichtlich innerhalb der laufenden Dateitransaktion. Der finale Ressourcen-Guard verhindert die neue Dateireferenz; Widerruf und Audit werden zurückgerollt. Der bisherige Inhalt bleibt lesbar. Die schon vorhandenen 1000-Dateien- und Fünfprozess-Quotentests bleiben erfolgreich.

Echte HTTP-Anfragen über public/index.php unter PHP 8.4/8.5 und HTTPS mit PHP 8.4-Produktionskonfiguration bestätigen ohne Browser-Cookies MKCOL, PUT, LOCK, abgewiesenen PUT ohne Nachweis, Ersetzung mit If/If-Match, UNLOCK, COPY/MOVE, DELETE/Neuanlage sowie die bisherigen Lese-/OIDC-/Sperr-/Historien-/Konsistenzfälle. Native Clients auf iOS/Android/Windows/macOS, vollständige Crash-/Last-/Restore-Prüfung und Betreiberdeployment sind damit nicht nachgewiesen. Bestehende MOVE/COPY-Ziele, Ordner-COPY, bereichsübergreifende Operationen und Lock-null-Anlage bleiben ausdrücklich begrenzt. Vorangegangene Prüfung: verification-iteration-18.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 173 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:06.050, Memory: 24.00 MB

OK (166 tests, 538 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 210 ( 30%)
............................................................... 126 / 210 ( 60%)
............................................................... 189 / 210 ( 90%)
.....................                                           210 / 210 (100%)

Time: 00:55.749, Memory: 32.00 MB

OK (210 tests, 946 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 173 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:04.609, Memory: 24.00 MB

OK (166 tests, 538 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 210 ( 30%)
............................................................... 126 / 210 ( 60%)
............................................................... 189 / 210 ( 90%)
.....................                                           210 / 210 (100%)

Time: 01:02.548, Memory: 32.00 MB

OK (210 tests, 946 assertions)


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 173 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:04.845, Memory: 24.00 MB

OK (166 tests, 538 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 210 ( 30%)
............................................................... 126 / 210 ( 60%)
............................................................... 189 / 210 ( 90%)
.....................                                           210 / 210 (100%)

Time: 00:49.534, Memory: 32.00 MB

OK (210 tests, 946 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 173 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:04.633, Memory: 24.00 MB

OK (166 tests, 538 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 210 ( 30%)
............................................................... 126 / 210 ( 60%)
............................................................... 189 / 210 ( 90%)
.....................                                           210 / 210 (100%)

Time: 00:53.168, Memory: 32.00 MB

OK (210 tests, 946 assertions)


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
