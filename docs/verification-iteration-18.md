# Prüfung Iteration 18

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 366 Tests, 1423 Assertions (164 Unit-/Architekturtests, 202 Integrationstests). Syntax aller 168 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Laufzeitabhängigkeit.

Der öffentliche DAV-Leseadapter ist auf Bereichssichtbarkeit, exakte Pfadnamen, GET/HEAD/ETag/Range, Leserecht vor bedingten Antworten, Credentialwiderruf auch nach Authentifizierung, lokale Sperre, XML/UTF-16-DTD-/Tiefengrenzen, verweigerte Schreibmethoden, Papierkorb-Nachfahren, veraltete Downloadknoten und begrenzte Streamausgabe geprüft. Ein früherer Test verglich die Großschreibung percentkodierter URI-Bytes unnötig streng; die Prüfung vergleicht nun den dekodierten Dateinamen.

Echte HTTP-Anfragen über public/index.php unter PHP 8.4/8.5 und HTTPS mit PHP 8.4-Produktionskonfiguration prüfen einen separaten Client ohne Browser-Cookies: PROPFIND, Download, Attachmentheader, Range, 304 und Widerruf. Für diese Prüfung wurden im temporären CLI-Testserver die bereits verpflichtenden XML-Erweiterungen geladen. Die vollständigen bisherigen OIDC-, App-Passwort-, Speicher-, Versions-, Sperr-, Quoten- und Konsistenzfälle bleiben erfolgreich. Der 1000-Dateien-Katalog und fünf gleichzeitig gestartete Uploadprozesse bleiben Teil aller vier Integrationsläufe.

WebDAV-Schreibmethoden bleiben ausdrücklich gesperrt. Kein Nachweis konkreter nativer Clients und kein vollständiger Crash-/Last-/Restore-Nachweis. Neue Funktionen sind nicht auf dem Betreiberhost deployed. Vorangegangene Prüfung: verification-iteration-17.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 168 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 164 ( 38%)
............................................................... 126 / 164 ( 76%)
......................................                          164 / 164 (100%)

Time: 00:05.064, Memory: 24.00 MB

OK (164 tests, 524 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 202 ( 31%)
............................................................... 126 / 202 ( 62%)
............................................................... 189 / 202 ( 93%)
.............                                                   202 / 202 (100%)

Time: 00:32.434, Memory: 30.00 MB

OK (202 tests, 899 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 168 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 164 ( 38%)
............................................................... 126 / 164 ( 76%)
......................................                          164 / 164 (100%)

Time: 00:06.015, Memory: 24.00 MB

OK (164 tests, 524 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 202 ( 31%)
............................................................... 126 / 202 ( 62%)
............................................................... 189 / 202 ( 93%)
.............                                                   202 / 202 (100%)

Time: 00:36.875, Memory: 30.00 MB

OK (202 tests, 899 assertions)


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 168 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 164 ( 38%)
............................................................... 126 / 164 ( 76%)
......................................                          164 / 164 (100%)

Time: 00:07.015, Memory: 24.00 MB

OK (164 tests, 524 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 202 ( 31%)
............................................................... 126 / 202 ( 62%)
............................................................... 189 / 202 ( 93%)
.............                                                   202 / 202 (100%)

Time: 00:26.539, Memory: 30.00 MB

OK (202 tests, 899 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 168 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 164 ( 38%)
............................................................... 126 / 164 ( 76%)
......................................                          164 / 164 (100%)

Time: 00:06.373, Memory: 24.00 MB

OK (164 tests, 524 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 202 ( 31%)
............................................................... 126 / 202 ( 62%)
............................................................... 189 / 202 ( 93%)
.............                                                   202 / 202 (100%)

Time: 00:26.403, Memory: 30.00 MB

OK (202 tests, 899 assertions)


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV listing/download/range/conditional read/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV listing/download/range/conditional read/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV listing/download/range/conditional read/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
