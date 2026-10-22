# Prüfung Iteration 24

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich, jeweils 409 Tests und 1679 Assertions (170 Unit-/Architekturtests mit 562 Assertions, 239 Integrationstests mit 1117 Assertions). Syntax aller 182 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Laufzeitkomponente.

Sieben neue Integrationsfälle decken gespeicherten Quelltext mit ausbrechendem textarea/script, Nur-Lesen, fehlendes read/update und fremde Benutzer, erfolgreiche Historienbildung, Konflikt ohne Überschreiben und Entwurfsattachment, Dateiformate/Kodierung/NUL/Größe, POST/CSRF, aktive Sperre und Quote, Leserechteentzug im Validator sowie leeren Inhalt und die maximale browserseitige CRLF-Übertragung ab.

Der erste Prüflauf fand einen fehlenden Layouttitel und einen verschachtelten Transaktionsaufruf in der Rechteentzugsfixture. Der Layouttitel wurde ergänzt und die Fixture verwendet den expliziten InsideTransaction-Writer. Danach wurden die vollständigen vier Kombinationen erneut erfolgreich geprüft. Die erwarteten PDOException-Zeilen stammen aus bisherigen absichtlichen DAV-Audit-/Lease-Fehlerfixtures.

Der echte HTTP-/HTTPS-Prüfer deckt Editoröffnung, CSRF-Verweigerung, neue Version, stale POST mit erhaltenem Entwurf, Entwurfsattachment, Escaping gespeicherten Markups und den 64-KiB-LF-Grenzfall mit URL-kodiertem CRLF-Body über public/index.php ab. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich. Dies ersetzt keine visuelle oder native Geräteabnahme.

Die letzte erfolgreiche GitHub-Matrix und gelesenen Jobprotokolle sind in verification-iteration-23.md dokumentiert. Nach Veröffentlichung startet die neue CI. Deployment, öffentliche weitere Viewer-/Editorprovider, native Geräte, vollständige Last-/Crash-/Restore-Abnahme bleiben offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 182 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.483, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 239 ( 26%)
............................................................... 126 / 239 ( 52%)
............................................................... 189 / 239 ( 79%)
..................................................              239 / 239 (100%)

Time: 00:47.902, Memory: 36.00 MB

OK (239 tests, 1117 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 182 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:04.127, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 239 ( 26%)
............................................................... 126 / 239 ( 52%)
............................................................... 189 / 239 ( 79%)
..................................................              239 / 239 (100%)

Time: 00:48.984, Memory: 36.00 MB

OK (239 tests, 1117 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 182 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.466, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 239 ( 26%)
............................................................... 126 / 239 ( 52%)
............................................................... 189 / 239 ( 79%)
..................................................              239 / 239 (100%)

Time: 00:44.555, Memory: 36.00 MB

OK (239 tests, 1117 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 182 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:03.432, Memory: 26.00 MB

OK (170 tests, 562 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 239 ( 26%)
............................................................... 126 / 239 ( 52%)
............................................................... 189 / 239 ( 79%)
..................................................              239 / 239 (100%)

Time: 00:43.544, Memory: 36.00 MB

OK (239 tests, 1117 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:110


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
