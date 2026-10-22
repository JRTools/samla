# Prüfung Iteration 23

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 402 Tests, 1627 Assertions (170 Unit-/Architekturtests, 232 Integrationstests). Syntax aller 177 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder produktive Laufzeitabhängigkeit.

Neue Fälle prüfen das separate Purge-Recht ohne automatische manage-Erweiterung, fremde Benutzer/Bereiche, aktive/veraltete Wurzeln, gelöschte Kinder unter ebenfalls gelöschten Eltern, gesamte Teilbäume, Historiendeletion und freigegebene Quote. Die anschließende tatsächliche Objektbereinigung entfernt nur den nicht mehr referenzierten alten Versionsinhalt und erhält den von einer Kopie weiter benutzten aktuellen Blob.

Ein 501-Einträge-Teilbaum wird vollständig mit zwei begrenzten Outbox-Chunks und einem Audit bereinigt. Der erzwungene Fehler genau im zweiten Chunk rollt Kataloglöschung, Audit und ersten Chunk zurück. Ein Validator ändert einen Nachfahren; der zweite Guard verwirft den unveränderlichen Gesamtplan und rollt auch die Fixtureänderung zurück. Aktive aufbewahrte Leases blockieren bis zum Ablauf. DTO-Tests prüfen rootgebundenen Operationskontext, Grenzen, positive Revisionen, Zeichenfolgensortierung numerisch aussehender IDs und Kopie statt übergebener PHP-Skalarreferenz.

Browsertests prüfen Purge-Oberfläche mit ausschließlich list/purge ohne Restore, Escaping, POST-Pflicht, CSRF, explizite Bestätigung und erfolgreiche endgültige Löschung. Der echte HTTP-/HTTPS-Prüfer erteilt das separate purge-Profil per Wartungs-CLI, legt eine Testdatei an, verschiebt sie in den Papierkorb, verweigert fehlende Bestätigung und bestätigt die endgültige Löschung. Sämtliche bisherigen OIDC-/DAV-/Versions-/Überschreib-/Sperr-/Quoten-/Konsistenzfälle bleiben erfolgreich.

Ein früher Testvergleich sortierte opaque IDs implizit numerisch; nun wird SORT_STRING verwendet, wie bereits im eigentlichen Purge-Vorschlag. Unterbrochene lokale Testläufe hinterließen Fehlerfixtures; die bekannten Testtrigger werden gezielt bereinigt und die temporären Matrixwrapper erzeugen frische isolierte Datenbanken je Zelle. Die lokale MySQL-Testinstanz wurde nach einem fehlenden Redo-Log aus vollständig reproduzierbaren Fixtures neu initialisiert; der alte Testbestand ist zur Diagnose erhalten. Der endgültige MySQL-Lauf verwendet dieselbe laufende Instanz auch für HTTP statt eines Zwischenneustarts. Daraus wird kein allgemeiner Crash-/Restore-Nachweis abgeleitet. Die erwarteten gekürzten PDOException-Zeilen stammen aus absichtlichen Audit-/Lease-Fehlerfixtures.

Die vorige tatsächliche GitHub-Matrix ist in verification-iteration-22.md dokumentiert. Die neue Implementierung startet nach Veröffentlichung ihren CI-Lauf. Native Clientabnahme, vollständige Crash-/Last-/Restore-Abnahme und Betreiberdeployment bleiben offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 177 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:05.307, Memory: 26.00 MB

OK (170 tests, 556 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 232 ( 27%)
............................................................... 126 / 232 ( 54%)
............................................................... 189 / 232 ( 81%)
...........................................                     232 / 232 (100%)

Time: 01:22.906, Memory: 36.00 MB

OK (232 tests, 1071 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 177 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:06.022, Memory: 26.00 MB

OK (170 tests, 556 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 232 ( 27%)
............................................................... 126 / 232 ( 54%)
............................................................... 189 / 232 ( 81%)
...........................................                     232 / 232 (100%)

Time: 01:21.433, Memory: 36.00 MB

OK (232 tests, 1071 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 177 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:04.877, Memory: 26.00 MB

OK (170 tests, 556 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 232 ( 27%)
............................................................... 126 / 232 ( 54%)
............................................................... 189 / 232 ( 81%)
...........................................                     232 / 232 (100%)

Time: 01:13.811, Memory: 36.00 MB

OK (232 tests, 1071 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 177 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:05.704, Memory: 26.00 MB

OK (170 tests, 556 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 232 ( 27%)
............................................................... 126 / 232 ( 54%)
............................................................... 189 / 232 ( 81%)
...........................................                     232 / 232 (100%)

Time: 01:12.201, Memory: 34.00 MB

OK (232 tests, 1071 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```

## GitHub CI nach Veröffentlichung

[Lauf 39699215348](https://github.com/JRTools/samla/actions/runs/39699215348) für 7482df88592be30c0134e37bd4df70f6cd2fb83a ist erfolgreich. Alle vier Jobprotokolle wurden gelesen: PHP 8.4.27/8.5.12 mit MySQL/MariaDB, jeweils 170 Unit-/Architekturtests (556 Assertions) plus 232 Integrationstests (1071 Assertions), insgesamt 402 Tests/1627 Assertions. HTTP und HTTPS sind in jeder Zelle erfolgreich. Jobs: 119564059673, 119564059718, 119564059735, 119564059766.
