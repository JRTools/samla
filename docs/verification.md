# Prüfung Iteration 27

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils 442 Tests und 1876 Assertions (179 Unit-/Architekturtests mit 613 Assertions, 263 Integrationstests mit 1263 Assertions). Syntax aller 206 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Neue Migration platform.administration:0003_delivery_revision; keine zusätzliche Runtimekomponente.

Sieben neue Integrationsfälle prüfen die ausdrücklichen globalen Rechte und lokale Kontosperre, reine Diagnose ohne Payloads, erneutes Vormerken eines failed-Eintrags mit Audit/Abschlussereignis, stale/ABA-Revisionskonflikte, Nur-Lesen und deaktivierten Empfänger, Leserechteentzug im Pluginvalidator mit Rollback, gezielten Auditfehler mit unveränderter Revision, tatsächliche Webzusammensetzung samt Navigation/Methoden/Filter/CSRF/doppeltem Formular sowie vollständige 105-Zustellungen-Pagination und HTML-Escaping ohne Inhaltslecks. Zwei DTO-Tests prüfen exakten globalen Retrykontext, Ziele/Hash, Referenzen und positive Revisionen.

Die ersten beiden UI-Fixturefälle wurden korrekt mit 403 verweigert, weil die tatsächliche Bootstrapzusammensetzung zunächst ohne konfigurierte Gruppenfrische aufgerufen wurde. Die Tests übergeben jetzt explizit dieselbe 3600-Sekunden-Konfiguration wie ihre Identitätsfixtures. Produktionsautorisierung wurde dafür nicht gelockert. Anschließend wurde die vollständige Vierfachmatrix erneut erfolgreich ausgeführt.

Der echte HTTPS-Prüfer prüft zusätzliche Berechtigungen über bin/worker-access.php: fehlende Rechte, Verweigerung ohne Wartungsmodus, read-Vergabe, Entzug und manage-Vergabe. Er prüft die Verwaltungsseite, ausdrücklich erzeugte Testfehler, CSRF-Verweigerung, erfolgreiches Retry, Ablehnung desselben alten Formulars und anschließende Zustellung über denselben Maschinen-Webtrigger. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS einschließlich aller bisherigen Funktionen erfolgreich. Das ersetzt keine visuelle oder native Geräteabnahme.

Die erwarteten PDOException-Zeilen gehören zu absichtlichen bisherigen DAV-Fehlerfixtures. MySQL und HTTP verwenden frische isolierte Fixture-Datenbanken; der gesamte abschließende MySQL- und HTTP-Lauf verwendet dieselbe laufende Instanz. Vorige erfolgreiche GitHub-Matrix und gelesene Joblogs: verification-iteration-26.md. Neue CI startet nach Veröffentlichung. Migration/Hosteinrichtung, tatsächlicher Cron, echte Geräte-, Last-/Crash-/Restore-Abnahme bleiben offen.

Unabhängig davon wurde ext-exif ausschließlich außerhalb des Repositories evaluiert. Unter PHP 8.4.5/8.5.4 wurden Orientierung 6 aus einem künstlichen JPEG-Stream sowie eine ungültige Bildstruktur und unveränderte Originalprüfsummen geprüft. Das ist keine produktive EXIF-Integration oder Parserabnahme; die konkrete Komponentenfreigabe bleibt offen. Siehe exif-library-decision.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 206 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 179 ( 35%)
............................................................... 126 / 179 ( 70%)
.....................................................           179 / 179 (100%)

Time: 00:03.508, Memory: 26.00 MB

OK (179 tests, 613 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 263 ( 23%)
............................................................... 126 / 263 ( 47%)
............................................................... 189 / 263 ( 71%)
............................................................... 252 / 263 ( 95%)
...........                                                     263 / 263 (100%)

Time: 00:51.860, Memory: 38.00 MB

OK (263 tests, 1263 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 206 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 179 ( 35%)
............................................................... 126 / 179 ( 70%)
.....................................................           179 / 179 (100%)

Time: 00:03.820, Memory: 26.00 MB

OK (179 tests, 613 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 263 ( 23%)
............................................................... 126 / 263 ( 47%)
............................................................... 189 / 263 ( 71%)
............................................................... 252 / 263 ( 95%)
...........                                                     263 / 263 (100%)

Time: 00:51.753, Memory: 38.00 MB

OK (263 tests, 1263 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 206 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 179 ( 35%)
............................................................... 126 / 179 ( 70%)
.....................................................           179 / 179 (100%)

Time: 00:03.448, Memory: 26.00 MB

OK (179 tests, 613 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 263 ( 23%)
............................................................... 126 / 263 ( 47%)
............................................................... 189 / 263 ( 71%)
............................................................... 252 / 263 ( 95%)
...........                                                     263 / 263 (100%)

Time: 00:47.075, Memory: 38.00 MB

OK (263 tests, 1263 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 206 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 179 ( 35%)
............................................................... 126 / 179 ( 70%)
.....................................................           179 / 179 (100%)

Time: 00:03.459, Memory: 26.00 MB

OK (179 tests, 613 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 263 ( 23%)
............................................................... 126 / 263 ( 47%)
............................................................... 189 / 263 ( 71%)
............................................................... 252 / 263 ( 95%)
...........                                                     263 / 263 (100%)

Time: 00:46.352, Memory: 38.00 MB

OK (263 tests, 1263 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
