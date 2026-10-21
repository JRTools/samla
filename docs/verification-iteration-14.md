# Prüfung Iteration 14

PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 304 Tests, 1153 Assertions (142 Unit-/Architekturtests, 162 Integrationstests). Syntax von 140 eigenen PHP-Dateien im Matrixlauf geprüft, anschließend zusätzlich der optionale WebDAV-Probe unter beiden PHP-Versionen. Migrationen einschließlich Papierkorbspalte erfolgreich; keine PHPUnit-Warnungen/Deprecations.

Neue Tests prüfen Metadatenänderungen und unveränderliche Kopien, Quoten, Bereichsrechte, versteckte Papierkorb-Nachfahren, Wiederherstellung, Zyklen/Eltern, veraltete Erwartungen, Namenskonflikte, typisierte Veto-Vorschläge und deren Strukturgrenzen sowie GC-Probelauf/Anwendung mit Schutz alter referenzierter und junger/fremder Objekte.

Echte HTTP-Anfragen unter PHP 8.4/8.5 und HTTPS-Produktionskonfiguration unter PHP 8.4: vollständiger OIDC-Pfad, Rollenbootstrap, Bereichsanlage, echte Multipart-Uploads, Attachment-Streaming, explizite Profilfreigabe zweimal, Detail-/Zielformulare, Umbenennen, veralteter POST mit 409, Dateikopie, Papierkorb, gesperrter direkter Download, Wiederherstellung und GC-Probelauf erfolgreich. Wiederholbare Werkzeuge stehen unter tests/Tools. HTTP-Prüfer benötigen Python cryptography und ausdrücklich isolierte media_platform_test_*-Datenbanken; sie leeren Anwendungstabellen.

SabreDAV 4.7.1 ausschließlich außerhalb der Anwendung isoliert aufgelöst. Optionaler webdav-library-probe.php unter PHP 8.4.5 und 8.5.4 erfolgreich: PROPFIND/GET/OPTIONS, synthetischer Basic-Adapter und falsche Credentials, LOCK, PUT ohne Token abgewiesen, PUT mit Token und UNLOCK. Jede Warnung/Deprecation löst einen Fehler aus. Ein zunächst wiederverwendeter Auth-Plugin-Testzustand wurde durch einen frischen Server pro Anfrage korrigiert, entsprechend dem geplanten PHP-FPM-Betrieb. Keine Samla-WebDAV-Integration, keine dauerhafte PDO-Sperre und keine nativen Clienttests behauptet. Entscheidungsvorlage enthält Paketauflösung und offene Integration.

Die Plesk-open_basedir-Korrektur und nachgeholte Migration bestätigt der Betreiber. Die neuen Lebenszyklusfunktionen sind nicht auf seinem Host deployed oder abgenommen. Vollständige visuelle Barrierefreiheitsprüfung, breiter Parallelitäts-/Last-/Crashnachweis, Backup-Restore-Abnahme, CI und vollständige Konzeptabnahme bleiben offen. Vorangegangene Prüfung: verification-iteration-13.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 140 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:04.952, Memory: 24.00 MB

[30;42mOK (142 tests, 460 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 162 ( 38%)
............................................................... 126 / 162 ( 77%)
....................................                            162 / 162 (100%)

Time: 00:24.472, Memory: 28.00 MB

[30;42mOK (162 tests, 693 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 140 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:05.034, Memory: 24.00 MB

[30;42mOK (142 tests, 460 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 162 ( 38%)
............................................................... 126 / 162 ( 77%)
....................................                            162 / 162 (100%)

Time: 00:26.188, Memory: 28.00 MB

[30;42mOK (162 tests, 693 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 140 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:05.310, Memory: 24.00 MB

[30;42mOK (142 tests, 460 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 162 ( 38%)
............................................................... 126 / 162 ( 77%)
....................................                            162 / 162 (100%)

Time: 00:15.722, Memory: 28.00 MB

[30;42mOK (162 tests, 693 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 140 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:06.771, Memory: 24.00 MB

[30;42mOK (142 tests, 460 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 162 ( 38%)
............................................................... 126 / 162 ( 77%)
....................................                            162 / 162 (100%)

Time: 00:12.236, Memory: 28.00 MB

[30;42mOK (162 tests, 693 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.5 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.4 production HTTPS: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK

```
