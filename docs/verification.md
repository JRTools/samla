# Prüfung Iteration 28

PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6: alle vier Kombinationen erfolgreich. Jeweils **457 Tests / 2167 Assertions**: 187 Unit-/Architekturtests mit 854 Assertions, 270 Integrationstests mit 1313 Assertions. Syntax aller 226 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Composer-Manifest gültig; bekannte Hinweise zur noch offenen Lizenz und zu bewusst fixierten Abhängigkeiten unverändert. GD und EXIF sind als optionale Medienfähigkeiten in Composer suggest dokumentiert; das bestehende Core-Installationsprofil bleibt ohne Medienmodule nutzbar.

Neue Migration platform.media:0001_assets. Der Betreiber hat ext-exif ausdrücklich freigegeben („Exif erweiterung ist ok“). Tests laden tatsächliches GD 2.3.3 und ext-exif unter beiden PHP-Versionen. Die zusätzlichen passenden Ubuntu-GD-Pakete wurden anhand der Paketmetadaten mit SHA-256 geprüft und ausschließlich in der temporären Testlaufzeit installiert. Keine Runtimebinärdateien im Repository.

Die neuen synthetischen Bildfixtures werden unmittelbar mit GD und einer minimalen TIFF-IFD im Test erzeugt. Alle acht Orientierungen werden anhand sämtlicher sechs Farbzellen geprüft, nicht nur anhand von Breite und Höhe. Geprüft werden unveränderte Originalprüfsummen, Herstellerfeld, PNG-Alpha, GIF, begrenzte Skalierung, fehlendes GD/EXIF, ungültige Orientierung ohne irreführende Vorschau, falsche/defekte Inhalte, Pixel-/Speichergrenzen sowie PNG-Text-/EXIF-Chunks, CRC und angehängte Daten. Providerverträge prüfen Quellhash, unabhängige Streams, Eigentümer, Rechtekatalog, sensible Felder, Grenzen, Priorität und geschlossene Registrierung.

Sieben Integrationsfälle prüfen automatische Outbox-Verarbeitung, Erkennung anhand der Bytes, HTML-Escaping und binäre Antwort, unveränderten Download, aktuellen Versionsbezug und alte Revisionskonflikte, getrennte Vorschau-/Original-/Metadatenrechte, fremde und gesperrte Konten, unsichtbare Eltern, CSRF/Methoden, Revisionsschutz, einminütige Vormerkbegrenzung ohne Inlineparser, wiederholte Verarbeitung und fehlende Provider, zusätzliche aktuelle Rechte auf sensible Felder, Rechteentzug durch Validator mit vollständigem Rollback, Kopien mit gemeinsamem Cache und eine während der Verarbeitung entfernte letzte Quellreferenz ohne verwaiste neue Ableitung.

Ein erster neuer Integrationsfall übergab den Zielordner versehentlich als erwarteten alten Elternordner und wurde korrekt mit MutationConflict verworfen. Die Fixture verwendet jetzt den richtigen getrennten Zielparameter; Produktionskonfliktschutz wurde nicht geändert. Nach den letzten zusätzlichen Fällen wurde die komplette Vierfachmatrix erneut erfolgreich ausgeführt.

Der echte HTTP-Prüfer ergänzt tatsächliche Medienrechtevergabe, DAV-Binärupload eines künstlichen PNG, Vormerkung und CSRF-Verweigerung, denselben CLI-/HTTPS-Worker, Detailanzeige und cookie-authentifizierten PNG-Abruf mit image/png und no-store sowie alte Revisionen. PHP 8.4/8.5 development HTTP und PHP 8.4 production HTTPS erfolgreich. Keine echte Browserdarstellungs- oder native Geräteabnahme. Vorherige erfolgreiche GitHub-Matrix mit gelesenen Logs: verification-iteration-27.md. Neue CI folgt nach Veröffentlichung.

Cachekapazität und native Parserlimits sind technische Grenzen, keine Last-/Fuzzing-/harte Zeitabbruchgarantie. TIFF ohne Vorschau; IPTC/XMP/PNG-Text/KI/C2PA, manuelle Katalogpflege und vollständige Cache-/Rebuildverwaltung bleiben offen. Kein Zugriff auf den Betreiberhost und keine Migration oder Schedulerinstallation dort. Betriebs-, Last-/Crash-/Restore- und native Clientabnahme bleiben gesondert offen. Erwartete PDOException-Logzeilen stammen aus absichtlichen bisherigen DAV-Rollbackfixtures.

### locked-matrix-results.txt

```text
PHP 8.4, lint 226 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 187 ( 33%)
............................................................... 126 / 187 ( 67%)
.............................................................   187 / 187 (100%)

Time: 00:03.808, Memory: 26.00 MB

OK (187 tests, 854 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 270 ( 23%)
............................................................... 126 / 270 ( 46%)
............................................................... 189 / 270 ( 70%)
............................................................... 252 / 270 ( 93%)
..................                                              270 / 270 (100%)

Time: 00:53.629, Memory: 38.00 MB

OK (270 tests, 1313 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 226 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 187 ( 33%)
............................................................... 126 / 187 ( 67%)
.............................................................   187 / 187 (100%)

Time: 00:03.643, Memory: 26.00 MB

OK (187 tests, 854 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 270 ( 23%)
............................................................... 126 / 270 ( 46%)
............................................................... 189 / 270 ( 70%)
............................................................... 252 / 270 ( 93%)
..................                                              270 / 270 (100%)

Time: 00:55.006, Memory: 38.00 MB

OK (270 tests, 1313 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 226 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 187 ( 33%)
............................................................... 126 / 187 ( 67%)
.............................................................   187 / 187 (100%)

Time: 00:03.759, Memory: 26.00 MB

OK (187 tests, 854 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 270 ( 23%)
............................................................... 126 / 270 ( 46%)
............................................................... 189 / 270 ( 70%)
............................................................... 252 / 270 ( 93%)
..................                                              270 / 270 (100%)

Time: 00:47.847, Memory: 38.00 MB

OK (270 tests, 1313 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 226 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 187 ( 33%)
............................................................... 126 / 187 ( 67%)
.............................................................   187 / 187 (100%)

Time: 00:04.034, Memory: 26.00 MB

OK (187 tests, 854 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 270 ( 23%)
............................................................... 126 / 270 ( 46%)
............................................................... 189 / 270 ( 70%)
............................................................... 252 / 270 ( 93%)
..................                                              270 / 270 (100%)

Time: 00:47.704, Memory: 38.00 MB

OK (270 tests, 1313 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
