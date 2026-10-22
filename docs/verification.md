# Prüfung Iteration 29

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils **465 Tests / 2227 Assertions**: 189 Unit-/Architekturtests mit 868 Assertions und 276 Integrationstests mit 1359 Assertions. Syntax aller 231 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Neue Migration platform.media:0002_reference_indexes. Keine zusätzliche Laufzeitkomponente. Composer-Manifest gültig mit unveränderten bekannten Hinweisen zur Projektlizenz und bewusst fixierten Bibliotheksversionen.

Sechs neue Integrationsfälle prüfen 105 sichtbare Dateien auf zwei Rebuildseiten, die Grenze von 100 Einzelaufträgen pro Bestätigung, einzelne Audits mit Batchbezug, Workerfortschritt 50/50/1 und erneute Verarbeitung ohne zusätzliche Cachezeile, aktuelle Rechte/Revisions-/Bereichsprüfung und atomaren Abbruch einer gemischten ungültigen Seite, einminütige Vormerkbegrenzung, HTML-Escaping sowie vollständig vor der Mutation geprüfte Formulare, CSRF und doppelte Bestätigung. Ein Fall widerruft die Verarbeitungsberechtigung im Pluginvalidator und provoziert anschließend einen Auditfehler. Ein weiterer lässt gezielt das zweite Kindereignis fehlschlagen und weist den vollständigen Rollback des ersten Kindes, aller Vormerkungen und Audits nach. Die Löschprüfung erhält Ableitungen bei Papierkorb, gemeinsamer Kopie und historischer Referenz und entfernt sie erst nach der letzten endgültigen Quelllöschung über denselben Worker. Wiederholung bleibt idempotent.

Zwei DTO-Fälle prüfen kopierte PHP-Referenzen, exakt gebundenen Space-Kontext/Hash, ungültige Eintragskennungen und Revisionstypen, leere Pläne und mehr als 100 Einträge. Die bestehenden Medien-/Datei-/Identitäts-/Workerfälle bleiben erfolgreich. Ein erster UI-Lauf fand einen fehlenden Seitentitel für media-rebuild; die feste Titel-/Navigationszuordnung wurde ergänzt und die vollständige Matrix danach erneut erfolgreich ausgeführt.

Der echte HTTP-/HTTPS-Prüfer ergänzt die Rebuildseite, Auslesen des HTML-escaped Plans, CSRF-Verweigerung, fehlerhaften Cursor ohne Änderung, erfolgreiche Bestätigung, Ablehnung desselben Formulars und tatsächliche Zustellung über CLI beziehungsweise HTTPS-Trigger. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich. Auch die gesamte vorherige OIDC-/DAV-/Datei-/Medienprüfung bleibt erfolgreich. Keine visuelle Browser- oder native Geräteabnahme daraus ableiten.

Die vorige GitHub-Matrix für Commit b1934ae9639bfaf18cee5b7c0a074a34d82ad23f ist vollständig erfolgreich und ihre vier Joblogs wurden gelesen, einschließlich PHP 8.4.27/8.5.12 und Medienprüfung über HTTP/HTTPS. Siehe verification-iteration-28.md. Neue CI folgt nach Veröffentlichung dieser Phase.

Die Übersicht offene-entscheidungen.md wurde mit aktuellem Konzept, Umsetzung und erteilten Freigaben abgeglichen. Historische offene Angaben werden nicht als erneut erforderliche Zustimmung behandelt. Die laufende Entwicklung kann mit bisherigen Vorgaben fortgesetzt werden. Keine Installation oder Migration auf dem Betreiberhost, keine allgemeine Produktivfreigabe. Automatischer unbeaufsichtigter Vollimport, allgemeine Cacheverwaltung und alte Providersignaturen sowie übrige Konzept-/Betriebs-/Geräteabnahmen bleiben offen. Erwartete PDOException-Logzeilen stammen aus absichtlichen bestehenden DAV-Rollbackfixtures.

### locked-matrix-results.txt

```text
PHP 8.4, lint 231 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 189 ( 33%)
............................................................... 126 / 189 ( 66%)
............................................................... 189 / 189 (100%)


Time: 00:03.929, Memory: 26.00 MB

OK (189 tests, 868 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 276 ( 22%)
............................................................... 126 / 276 ( 45%)
............................................................... 189 / 276 ( 68%)
............................................................... 252 / 276 ( 91%)
........................                                        276 / 276 (100%)

Time: 00:56.066, Memory: 38.00 MB

OK (276 tests, 1359 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 231 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 189 ( 33%)
............................................................... 126 / 189 ( 66%)
............................................................... 189 / 189 (100%)


Time: 00:03.835, Memory: 26.00 MB

OK (189 tests, 868 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 276 ( 22%)
............................................................... 126 / 276 ( 45%)
............................................................... 189 / 276 ( 68%)
............................................................... 252 / 276 ( 91%)
........................                                        276 / 276 (100%)

Time: 00:53.155, Memory: 38.00 MB

OK (276 tests, 1359 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 231 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 189 ( 33%)
............................................................... 126 / 189 ( 66%)
............................................................... 189 / 189 (100%)


Time: 00:03.744, Memory: 26.00 MB

OK (189 tests, 868 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 276 ( 22%)
............................................................... 126 / 276 ( 45%)
............................................................... 189 / 276 ( 68%)
............................................................... 252 / 276 ( 91%)
........................                                        276 / 276 (100%)

Time: 00:49.359, Memory: 38.00 MB

OK (276 tests, 1359 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 231 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 189 ( 33%)
............................................................... 126 / 189 ( 66%)
............................................................... 189 / 189 (100%)


Time: 00:04.404, Memory: 26.00 MB

OK (189 tests, 868 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 276 ( 22%)
............................................................... 126 / 276 ( 45%)
............................................................... 189 / 276 ( 68%)
............................................................... 252 / 276 ( 91%)
........................                                        276 / 276 (100%)

Time: 00:47.781, Memory: 38.00 MB

OK (276 tests, 1359 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
