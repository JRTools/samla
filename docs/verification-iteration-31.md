# Prüfung Iteration 31

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils **477 Tests / 2489 Assertions**: 197 Unit-/Architekturtests mit 1106 Assertions und 280 Integrationstests mit 1383 Assertions. Syntax aller 233 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Komponente. Composer-Manifest gültig mit unveränderten bekannten Hinweisen zur noch offenen Lizenz und zu bewusst fixierten Versionen.

Ein neuer Unitfall prüft nicht zuordenbare benannte Hauptsubjekte, rdf:nodeID/rdf:ID, XML-Basisumleitungen, verschachtelte andere Ressourcen, komplexe Property-/Listenwerte statt erlaubter primitiver Skalare, weiterhin unterstütztes bares RDF und eine gültige aktuelle Deklaration neben einem anders bezeichneten Nebenobjekt. Ein zusätzlicher Integrationsfall weist nach, dass ein fremdes RDF-Subjekt in der tatsächlich gerenderten Medienansicht weder eine aktuelle KI-Einstufung noch dessen konkrete Herkunfts-URI erzeugt. Der Status bleibt teilweise untersucht und die aktuelle Herkunft unbekannt. Alle bisherigen Namespace-, Parser-, Rechte-, Prompt-, Original- und Previewfälle bleiben erfolgreich.

Die zusätzlichen Varianten wurden schrittweise ergänzt; nach der letzten Quelländerung wurde die vollständige Vierfachmatrix erneut erfolgreich ausgeführt. Der tatsächliche HTTP-/HTTPS-Durchlauf mit gültigen Hauptsubjekten, strukturierter KI-Angabe und ausdrücklicher zusätzlicher Promptberechtigung bleibt ebenfalls erfolgreich: PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS. Dies ersetzt keine visuelle Browser- oder native Geräteabnahme.

Eine zusätzliche isolierte PHP-8.4-Probe kombinierte ein synthetisches JPEG mit einem XMP-APP1-Segment vor dem EXIF-Segment und Orientierung 6. Die Vorschau war vorhanden und mit 90 × 60 Pixeln korrekt gedreht; Orientierung 6 und Kamerahersteller wurden gelesen, die eingebettete Metadatenverarbeitung meldete ready und die Originalprüfsumme blieb gleich. Der native EXIF-/Rasterpfad meldete dabei konservativ partial. Dieses Ergebnis wird nicht als vollständige JPEG-/EXIF-Metadatenvalidierung ausgegeben; ausgewählte Abdeckung und abgefangene native Hinweise bleiben ausdrücklich getrennt.

EmbeddedMetadataProvider meldet Verarbeitungs-Version 1.1. Die geänderte Signatur verhindert das ungeprüfte Anzeigen früherer Ableitungen; Bestände können mit dem vorhandenen bereichsweisen Neuaufbau erneut verarbeitet werden. C2PA bleibt nicht untersucht oder validiert. Komplexe/nicht zuordenbare XMP-Subjekte sind eine ausgewiesene Abdeckungsgrenze. Das ist keine kryptografische Dateibindung oder allgemeine RDF-/Formatvalidierung.

Die vorige GitHub-Matrix für Commit c8f769c1023f011ec584370704564256ad3237a2 ist vollständig erfolgreich und alle vier Joblogs wurden gelesen, einschließlich PHP 8.4.27/8.5.12 und HTTP/HTTPS. Siehe verification-iteration-30.md. Neue CI folgt nach Veröffentlichung dieser Phase. Die konkrete ZIP-Komponentenentscheidung ist vorbereitet und noch nicht freigegeben; keine ZIP-Implementierung oder Betreiberinstallation wird behauptet. Weitere Konzept-, Betriebs-, Last-/Restore- und Geräteabnahmen bleiben offen. Erwartete PDOException-Zeilen gehören zu absichtlichen bestehenden DAV-Rollbackfixtures.

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

Time: 00:03.943, Memory: 26.00 MB

OK (197 tests, 1106 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 01:00.073, Memory: 38.00 MB

OK (280 tests, 1383 assertions)
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

Time: 00:03.619, Memory: 26.00 MB

OK (197 tests, 1106 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:56.081, Memory: 38.00 MB

OK (280 tests, 1383 assertions)
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

Time: 00:04.056, Memory: 26.00 MB

OK (197 tests, 1106 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:49.814, Memory: 38.00 MB

OK (280 tests, 1383 assertions)
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

Time: 00:03.854, Memory: 26.00 MB

OK (197 tests, 1106 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 280 ( 22%)
............................................................... 126 / 280 ( 45%)
............................................................... 189 / 280 ( 67%)
............................................................... 252 / 280 ( 90%)
............................                                    280 / 280 (100%)

Time: 00:50.130, Memory: 38.00 MB

OK (280 tests, 1383 assertions)
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
