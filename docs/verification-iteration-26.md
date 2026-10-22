# Prüfung Iteration 26

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils 433 Tests und 1821 Assertions (177 Unit-/Architekturtests mit 599 Assertions, 256 Integrationstests mit 1222 Assertions). Syntax aller 197 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Neue Migration platform.administration:0002_outbox_delivery; keine neue Runtimebibliothek oder neuer Serverdienst.

Neue Unitprüfungen decken kopierte JSON-/Typreferenzen, Ereignisgrenzen, Empfängerbesitz, doppelte/geschlossene Registrierung, Tokenhash-Konfiguration, deaktivierten Webtrigger, Methoden-/URL-Verweigerung, fehlende/falsche/Header-vorrangige Tokens und verzögerten Workeraufbau erst nach gültiger Authentifizierung ab. Antworten sind no-store und enthalten keine Tokens.

Elf echte Datenbankfälle prüfen begrenzte Replay-Discovery, maximal 50 Zustellversuche und dauerhafte Quittierungen über 120 Ereignisse; Standard-Wasserstand ohne stilles Replay und Ignorieren unpassender/deaktivierter Empfänger; Backoff und sichtbares Ende nach acht Versuchen; ungültige Ereignisse ohne Handleraufruf; reale zweite PDO-Verbindung und rekursiven Aufruf während des aktiven Handlers mit busy statt Parallelverarbeitung; gezielten SQL-Quittierungsfehler nach bereits ausgeführtem idempotentem Effekt mit anschließendem Lease-Replay ohne Doppelwirkung; achte unterbrochene Zustellung und faire Ein-Ereignis-Batches; Verweigerung geänderter Empfängerverträge; einen noch nicht abgeschlossenen niedrigeren Sequenzschreiber mit gleichzeitiger höherer Sequenz; Rollback einer vom Handler offen gelassenen Transaktion; Grenzen und fehlenden Pflichtprovider.

Der tatsächliche HTTP-/HTTPS-Prüfer ergänzt deaktivierte Route im lokalen HTTP-Modus, POST-Pflicht, fehlendes/falsches Token, URL- und Bodygrenzen, sitzungslosen Beareraufruf ohne Set-Cookie, Formularfallback und JSON-Zähler. Er führt zusätzlich bin/worker.php mit einem Ereignis und --status aus. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich. Bestehende OIDC-/DAV-/Editor-/Such-/Purgefälle bleiben erfolgreich.

Das lokale Offline-HTML-Werkzeug wurde mit Web Crypto in einer isolierten Node-VM ausgeführt: zufälliges Tokenformat, passender SHA-256-Konfigurationswert, unterschiedliche Neugenerierung und connect-src none geprüft. Kein generiertes Token wurde ausgegeben oder gespeichert. Dies ersetzt keine visuelle Browserabnahme.

Ein Zwischenlauf scheiterte erwartungsgemäß an der Migrationsprüfsumme, nachdem die noch unveröffentlichte neue Migration während laufender Tests weiterentwickelt worden war. Danach wurde ein stabiler Endstand vollständig mit frischen Fixture-Datenbanken geprüft. Die wiederverwendete lokale MySQL-Testinstanz fehlte bei einem Neustart eine Redo-Datei; der abschließende Lauf nutzte einen neuen isolierten, aus Fixtures initialisierten Datadir und dieselbe laufende Instanz auch für HTTP/HTTPS. Auch der temporäre HTTP-Wrapper erzeugt jetzt eine frische Testdatenbank. Keine produktive Installation oder Benutzerdaten wurden repariert oder verändert; dies ist kein Crash-/Restore-Nachweis. Die gekürzten PDOException-Zeilen stammen aus absichtlichen bisherigen DAV-Fehlerfixtures.

Vorige erfolgreiche GitHub-Matrix: verification-iteration-25.md. Die neue CI startet nach Veröffentlichung. Hosteinrichtung, reales Token, echte Cronausführung, visuelle/native Geräte-, Last-/Crash-/Restore-Abnahme sowie Medienhandler sind noch offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 197 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 177 ( 35%)
............................................................... 126 / 177 ( 71%)
...................................................             177 / 177 (100%)

Time: 00:03.875, Memory: 26.00 MB

OK (177 tests, 599 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 256 ( 24%)
............................................................... 126 / 256 ( 49%)
............................................................... 189 / 256 ( 73%)
............................................................... 252 / 256 ( 98%)
....                                                            256 / 256 (100%)

Time: 00:51.593, Memory: 38.00 MB

OK (256 tests, 1222 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 197 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 177 ( 35%)
............................................................... 126 / 177 ( 71%)
...................................................             177 / 177 (100%)

Time: 00:03.497, Memory: 26.00 MB

OK (177 tests, 599 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 256 ( 24%)
............................................................... 126 / 256 ( 49%)
............................................................... 189 / 256 ( 73%)
............................................................... 252 / 256 ( 98%)
....                                                            256 / 256 (100%)

Time: 00:51.376, Memory: 38.00 MB

OK (256 tests, 1222 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 197 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 177 ( 35%)
............................................................... 126 / 177 ( 71%)
...................................................             177 / 177 (100%)

Time: 00:03.252, Memory: 26.00 MB

OK (177 tests, 599 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 256 ( 24%)
............................................................... 126 / 256 ( 49%)
............................................................... 189 / 256 ( 73%)
............................................................... 252 / 256 ( 98%)
....                                                            256 / 256 (100%)

Time: 00:47.166, Memory: 38.00 MB

OK (256 tests, 1222 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 197 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 177 ( 35%)
............................................................... 126 / 177 ( 71%)
...................................................             177 / 177 (100%)

Time: 00:03.709, Memory: 26.00 MB

OK (177 tests, 599 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 256 ( 24%)
............................................................... 126 / 256 ( 49%)
............................................................... 189 / 256 ( 73%)
............................................................... 252 / 256 ( 98%)
....                                                            256 / 256 (100%)

Time: 00:46.019, Memory: 38.00 MB

OK (256 tests, 1222 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```

## GitHub CI nach Veröffentlichung

[Lauf 39726876269](https://github.com/JRTools/samla/actions/runs/39726876269) für ab1f28bb660703360678101d4c43071774fa21c2 ist erfolgreich. Alle vier Jobprotokolle gelesen: PHP 8.4.27/8.5.12 mit MySQL/MariaDB, jeweils 177 Unit-/Architekturtests mit 599 Assertions und 256 Integrationstests mit 1222 Assertions, insgesamt 433 Tests/1821 Assertions. HTTP und HTTPS einschließlich CLI-/Webtrigger sind in allen vier Zellen erfolgreich. Jobs: 119652691363, 119652691560, 119652691605, 119652691731.
