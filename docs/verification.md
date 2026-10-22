# Prüfung Iteration 25

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils 415 Tests und 1722 Assertions (170 Unit-/Architekturtests mit 562 Assertions, 245 Integrationstests mit 1160 Assertions). Syntax aller 183 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Laufzeitkomponente.

Sechs neue Integrationsfälle prüfen Unicode-Suche und wörtliche Sonderzeichen ohne Inhaltsindizierung; Typfilter und Elternpfade; gelöschte und defekte Elternketten; list ohne read, fremde Benutzer/Bereiche, lokale Sperre und ausdrückliches Plugin-Deny; Formulareingaben/Escaping; vollständige duplikatfreie Seiten über 1000 Einträge und Verweigerung unvollständiger Ergebnisse bei 10001 Einträgen. Der echte HTTP-/HTTPS-Prüfer findet umbenannte Dateien in Unterordnern, prüft Typfilter und verweigert ungültige Filter. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich. Bestehende OIDC-/DAV-/Editor-/Purgefälle bleiben erfolgreich.

Die erwarteten gekürzten PDOException-Zeilen gehören zu absichtlichen bisherigen Audit-/Lease-Fehlerfixtures. Die letzte erfolgreiche GitHub-Matrix steht in verification-iteration-24.md. Neue CI startet nach Veröffentlichung. Native Clients, visuelle Geräteabnahme, vollständige Last-/Crash-/Restore-Abnahme und Betreiberdeployment bleiben offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 184 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.607, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 245 ( 25%)
............................................................... 126 / 245 ( 51%)
............................................................... 189 / 245 ( 77%)
........................................................        245 / 245 (100%)

Time: 00:50.169, Memory: 36.00 MB

OK (245 tests, 1160 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 184 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.954, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 245 ( 25%)
............................................................... 126 / 245 ( 51%)
............................................................... 189 / 245 ( 77%)
........................................................        245 / 245 (100%)

Time: 00:49.941, Memory: 36.00 MB

OK (245 tests, 1160 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 184 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.987, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 245 ( 25%)
............................................................... 126 / 245 ( 51%)
............................................................... 189 / 245 ( 77%)
........................................................        245 / 245 (100%)

Time: 00:45.587, Memory: 36.00 MB

OK (245 tests, 1160 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 184 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:04.080, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 245 ( 25%)
............................................................... 126 / 245 ( 51%)
............................................................... 189 / 245 ( 77%)
........................................................        245 / 245 (100%)

Time: 00:44.422, Memory: 36.00 MB

OK (245 tests, 1160 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
