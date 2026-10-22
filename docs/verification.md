# Prüfung Iteration 30

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils **475 Tests / 2466 Assertions**: 196 Unit-/Architekturtests mit 1088 Assertions und 279 Integrationstests mit 1378 Assertions. Syntax aller 233 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Laufzeitkomponente. Vorhandenes PHP, DOM/libxml und iptcparse ergänzen den freigegebenen GD-/EXIF-Pfad. Native Schnittstellen wurden in beiden tatsächlichen PHP-Laufzeiten ausgeführt.

Sieben neue Unitfälle verwenden ausschließlich synthetische GD-/TIFF-/JPEG-/PNG-/XML-/IIM-Fixtures. Sie prüfen strukturierte generativ-erzeugt/bearbeitet/andere/fehlende/widersprüchliche Angaben, kontrollierte URIs, echte XMP-Namespaces statt Präfix-Spoofing, einfache Elemente/Attribute/RDF-Listen, getrennte System-/Versions-/Prompt-/Autorfelder, unveränderte Originalhashes, PNG-Latin-1 und iTXt-UTF-8, explizite IPTC-Kodierung und nicht erratene unbekannte Kodierungen, wiederholte Keywords, private Feldregistrierung ohne implizite Exportfreigabe, DTD/Entities ohne Dateileck, komprimierte nicht entpackte Textblöcke, CRC, große Werte, abgebrochene Container, 29+3 Werte, XML-Knotengrenze, UTF-16-Verweigerung und deterministisch überschrittenes kooperatives Zeitbudget. Fehlende Kennzeichnungen werden nicht in eine Aussage ohne KI umgedeutet.

Drei weitere Integrationsfälle prüfen die echte Medien-Detailansicht mit HTML-Escaping, sichtbarer strukturierter Deklaration und ausgeblendeten konkreten Prompt-/Autorfeldern ohne zusätzliches Recht, explizite zusätzliche Berechtigung, fehlende Original-/Vorschaurechte, Verweigerung von Metadaten bei reinem Vorschaurecht sowie einen Inhaltsversionswechsel mit erneuter Extraktion ohne Übernahme alter KI-Marker. Das Original bleibt bytegleich, während die geschützte Vorschau die eingebetteten Promptdaten nicht übernimmt. Die bestehenden Erweiterungs- und Rechteprüfungen bleiben erfolgreich.

Beim Ergänzen des Zeitbudgets fand ein Unitlauf zwei noch nicht angepasste interne XMP-Aufrufe; die Erkennung verweigerte sie als teilweise verarbeitet. Die Aufrufe wurden korrigiert. Danach wurde die vollständige Vierfachmatrix erneut erfolgreich ausgeführt. Keine Warnungen im abschließenden Unit-/Architekturlauf.

Der tatsächliche HTTP-/HTTPS-Prüfer ergänzt ein über DAV geschriebenes künstliches PNG mit Standard-XMP und strukturiertem DigitalSourceType. CLI-/HTTPS-Worker verarbeiten es; die Detailseite zeigt die Deklaration, aber keinen privaten Prompt. Erst das ausdrücklich per Wartungshelfer vergebene Profil media-sensitive macht diesen in derselben Sitzung sichtbar. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich, einschließlich aller bisherigen OIDC-/DAV-/Datei-/Worker-/Medien-/Rebuildfälle. Keine tatsächliche visuelle Browser- oder native Geräteabnahme daraus ableiten.

Die vorige GitHub-Matrix für Commit 2a57cad600bbddeef7f8dddbfcafaa33b481cac9 ist vollständig erfolgreich, sämtliche vier Joblogs wurden gelesen, PHP 8.4.27/8.5.12 einschließlich HTTP/HTTPS. Siehe verification-iteration-29.md. Neue CI folgt nach Veröffentlichung.

Der zusätzliche Provider hat begrenzte Teilabdeckung und ist kein vollständiger Bild-, Metadaten- oder Herkunftsvalidator. Extended XMP, komprimierte PNG-Texte und weitere Container bleiben nicht beziehungsweise teilweise unterstützt. Das Einsekundenbudget wird kooperativ geprüft und unterbricht keine laufende native Funktion. C2PA wird nicht untersucht/validiert. Kein produktiver Betreiberhostzugriff, keine behauptete Deployment-/Restore-/Last-/Geräteabnahme. Erwartete PDOException-Zeilen stammen weiterhin aus absichtlichen DAV-Rollbackfixtures.

### locked-matrix-results.txt

```text
PHP 8.4, lint 233 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 196 ( 32%)
............................................................... 126 / 196 ( 64%)
............................................................... 189 / 196 ( 96%)
.......                                                         196 / 196 (100%)

Time: 00:04.299, Memory: 26.00 MB

OK (196 tests, 1088 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 279 ( 22%)
............................................................... 126 / 279 ( 45%)
............................................................... 189 / 279 ( 67%)
............................................................... 252 / 279 ( 90%)
...........................                                     279 / 279 (100%)

Time: 00:56.009, Memory: 38.00 MB

OK (279 tests, 1378 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 233 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 196 ( 32%)
............................................................... 126 / 196 ( 64%)
............................................................... 189 / 196 ( 96%)
.......                                                         196 / 196 (100%)

Time: 00:04.176, Memory: 26.00 MB

OK (196 tests, 1088 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 279 ( 22%)
............................................................... 126 / 279 ( 45%)
............................................................... 189 / 279 ( 67%)
............................................................... 252 / 279 ( 90%)
...........................                                     279 / 279 (100%)

Time: 00:57.858, Memory: 38.00 MB

OK (279 tests, 1378 assertions)
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

...............................................................  63 / 196 ( 32%)
............................................................... 126 / 196 ( 64%)
............................................................... 189 / 196 ( 96%)
.......                                                         196 / 196 (100%)

Time: 00:04.428, Memory: 26.00 MB

OK (196 tests, 1088 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 279 ( 22%)
............................................................... 126 / 279 ( 45%)
............................................................... 189 / 279 ( 67%)
............................................................... 252 / 279 ( 90%)
...........................                                     279 / 279 (100%)

Time: 00:49.121, Memory: 38.00 MB

OK (279 tests, 1378 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 233 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 196 ( 32%)
............................................................... 126 / 196 ( 64%)
............................................................... 189 / 196 ( 96%)
.......                                                         196 / 196 (100%)

Time: 00:04.509, Memory: 26.00 MB

OK (196 tests, 1088 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 279 ( 22%)
............................................................... 126 / 279 ( 45%)
............................................................... 189 / 279 ( 67%)
............................................................... 252 / 279 ( 90%)
...........................                                     279 / 279 (100%)

Time: 00:50.535, Memory: 38.00 MB

OK (279 tests, 1378 assertions)
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
