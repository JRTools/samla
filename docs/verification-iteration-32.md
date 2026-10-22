# Prüfung Iteration 32

JRTools-Gestaltung: gemeinsame PHP-Templates und lokale CSS-Datei, keine neue Runtime und keine Migration. Die vorhandene Inhaltsprüfung der Startseite wurde an die aktualisierte Überschrift angepasst. Der erste Integrationslauf fand noch den alten erwarteten Text; nach Aktualisierung dieser Erwartung wurden beide vollständigen Datenbankläufe erneut ausgeführt.

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich, jeweils 477 Tests / 2489 Assertions (197 Unit-/Architekturtests, 1106 Assertions; 280 Integrationstests, 1383 Assertions). Syntax aller 233 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Tatsächlicher synthetischer HTTP-/HTTPS-Durchlauf erfolgreich: PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS. Erwartete PDOException-Zeilen sind absichtliche DAV-Rollbackfixtures.

Die JRTools-Referenz wurde im Browser betrachtet und ihre öffentliche CSS-Datei gelesen. Die lokale Samla-Vorschau war im Cloud-Browser nicht erreichbar; dessen Sperre für Datei-URLs wurde respektiert. Keine visuelle Browserabnahme der geänderten Samla-Seiten, native Geräteprüfung, vollständige Barrierefreiheits- oder Offlineabnahme behauptet. Designumfang und Kontrastvarianten: design.md.

Keine zusätzliche CSS-Testbibliothek installiert. Ein versuchter optionaler lokaler CSS-Parser war nicht vorhanden; daraus wird kein erfolgreicher Parserlauf abgeleitet. git diff --check erfolgreich. ZIP ist inzwischen freigegeben, bleibt aber noch nicht implementiert; frühere anderslautende Freigabehinweise im historischen Prüfprotokoll sind überholt. GitHub-CI folgt auf die Veröffentlichung.

### locked-matrix-results.txt

```text
PHP 8.4, lint 233 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 197 ( 31%)
............................................................... 126 / 197 ( 63%)
............................................................... 189 / 197 ( 95%)
........                                                        197 / 197 (100%)

Time: 00:04.050, Memory: 26.00 MB

[30;42mOK (197 tests, 1106 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:56.959, Memory: 38.00 MB

[30;42mOK (280 tests, 1383 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 233 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 197 ( 31%)
............................................................... 126 / 197 ( 63%)
............................................................... 189 / 197 ( 95%)
........                                                        197 / 197 (100%)

Time: 00:04.002, Memory: 26.00 MB

[30;42mOK (197 tests, 1106 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:56.454, Memory: 38.00 MB

[30;42mOK (280 tests, 1383 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 233 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 197 ( 31%)
............................................................... 126 / 197 ( 63%)
............................................................... 189 / 197 ( 95%)
........                                                        197 / 197 (100%)

Time: 00:03.751, Memory: 26.00 MB

[30;42mOK (197 tests, 1106 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:50.586, Memory: 38.00 MB

[30;42mOK (280 tests, 1383 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 233 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 197 ( 31%)
............................................................... 126 / 197 ( 63%)
............................................................... 189 / 197 ( 95%)
........                                                        197 / 197 (100%)

Time: 00:03.781, Memory: 26.00 MB

[30;42mOK (197 tests, 1106 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:50.581, Memory: 38.00 MB

[30;42mOK (280 tests, 1383 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK

```


## Nachträgliche GitHub-CI der Designphase

Commit c38786275bcd7f3670ddbacd734e93a19d9d78b2, [Lauf 39751780253](https://github.com/JRTools/samla/actions/runs/39751780253): alle vier Jobs erfolgreich; alle vier Joblogs gelesen. PHP 8.4.27/8.5.12, je 197 Unit-/Architekturtests und 280 Integrationstests, HTTP und HTTPS erfolgreich.

### PHP 8.4 / mariadb

```text
2026-10-22T08:58:57.1640458Z Runtime:       PHP 8.4.27
2026-10-22T08:58:57.4324026Z \u001b[30;42mOK (197 tests, 1106 assertions)\u001b[0m
2026-10-22T08:58:57.5965307Z Runtime:       PHP 8.4.27
2026-10-22T08:59:40.5683449Z \u001b[30;42mOK (280 tests, 1383 assertions)\u001b[0m
2026-10-22T08:59:53.1514380Z 8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
2026-10-22T09:00:02.7693576Z 8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```

### PHP 8.4 / mysql

```text
2026-10-22T08:59:05.0792352Z Runtime:       PHP 8.4.27
2026-10-22T08:59:05.3764280Z \u001b[30;42mOK (197 tests, 1106 assertions)\u001b[0m
2026-10-22T08:59:05.5937319Z Runtime:       PHP 8.4.27
2026-10-22T09:00:05.2157225Z \u001b[30;42mOK (280 tests, 1383 assertions)\u001b[0m
2026-10-22T09:00:21.3693785Z 8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
2026-10-22T09:00:33.9438434Z 8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```

### PHP 8.5 / mariadb

```text
2026-10-22T08:59:06.6552791Z Runtime:       PHP 8.5.12
2026-10-22T08:59:07.3199565Z \u001b[30;42mOK (197 tests, 1106 assertions)\u001b[0m
2026-10-22T08:59:07.5260978Z Runtime:       PHP 8.5.12
2026-10-22T09:00:10.4163674Z \u001b[30;42mOK (280 tests, 1383 assertions)\u001b[0m
2026-10-22T09:00:30.1847100Z 8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
2026-10-22T09:00:45.1729037Z 8.5 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```

### PHP 8.5 / mysql

```text
2026-10-22T08:59:07.3364615Z Runtime:       PHP 8.5.12
2026-10-22T08:59:07.7101775Z \u001b[30;42mOK (197 tests, 1106 assertions)\u001b[0m
2026-10-22T08:59:07.9786153Z Runtime:       PHP 8.5.12
2026-10-22T09:00:17.2032839Z \u001b[30;42mOK (280 tests, 1383 assertions)\u001b[0m
2026-10-22T09:00:35.4969709Z 8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
2026-10-22T09:00:50.1590465Z 8.5 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
