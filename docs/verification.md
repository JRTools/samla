# Prüfung Iteration 34: portabler Exportkern

PHP 8.4.5 und 8.5.4: jeweils 208 Unit-/Architekturtests mit 1202 Assertions erfolgreich, einschließlich acht neuen Exportfällen. Syntax aller eigenen PHP-Dateien und composer validate --no-check-publish erfolgreich. Keine neue Datenbankmigration. Native ext-zip/libzip ist die ausdrücklich freigegebene Komponente; Composer führt ext-zip als optionale Exportfähigkeit auf. CI installiert das Modul für die nativen Tests.

Geprüft sind Originalbytes und SHA-256, versionsgebundenes JSON-Manifest, portable relative Archivnamen, Metadaten ohne automatische Exportfreigabe, zusätzliche sensible Exportrechte, begrenzte Providerregistrierung, Dateianzahl/Größe/Zeit, fehlerhafte Quelle, native Abschlussabbrechung, private Dateien und Stagingbereinigung. Ein 128-MiB-Video wird gestreamt; der Test prüft einen zusätzlichen PHP-Spitzenspeicher unter 8 MiB, die ZIP-Inhaltsprüfsumme und die Archivgröße. Eine harte Betriebssystem-Zeitunterbrechung oder Lastabnahme wird daraus nicht abgeleitet.

Die native Abschlussabbrechung wurde mit einer injizierten monotonen Uhr tatsächlich ausgelöst und hinterließ kein veröffentlichtes oder gestagtes ZIP. Der vorherige Error-Handler wird nach nativen Aufrufen wiederhergestellt. Archivierung speichert Originale ohne Kompression; keine Bild-/Videokonvertierung. Datenbank-/Browser-Exportaufträge, Rechteprüfung des späteren Downloads, Ablaufbereinigung und UI werden im direkt folgenden Arbeitspaket angeschlossen und sind hier noch nicht fertig.

Die zuvor veröffentlichte Bereichs-/Berechtigungsphase ist in allen vier GitHub-Kombinationen einschließlich HTTP/HTTPS erfolgreich; gelesene Logs: verification-iteration-33.md. Die Datenbankintegration wurde für dieses zunächst interne Exportkernpaket lokal nicht erneut ausgeführt; dessen aktuelle GitHub-CI folgt nach Veröffentlichung. Die öffentlichen Providerregistrierungen werden durch Unit-/Architekturtests geprüft. Native Referenzclients, visuelle Abnahme und Offline-Synchronisierung bleiben offen.

Temporäre Testmodule: verifizierte PHP-8.4.5-/8.5.4-ZIP-Pakete und libzip 1.11.3/1.11.4 aus den bereits verwendeten Ubuntu-Snapshot-Metadaten. Nur in die isolierten Testruntimes extrahiert, keine Installation auf dem Betreiberhost. Keine zusätzlichen Programme oder Composer-Pakete.

```text
PHP 8.4: lint 255 files

PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 208 ( 30%)
............................................................... 126 / 208 ( 60%)
............................................................... 189 / 208 ( 90%)
...................                                             208 / 208 (100%)

Time: 00:04.885, Memory: 26.00 MB

[30;42mOK (208 tests, 1202 assertions)[0m


./composer.json is valid, but with a few warnings
See https://getcomposer.org/doc/04-schema.md for details on the schema
# General warnings
- No license specified, it is recommended to do so. For closed-source software you may use "proprietary" as license.
- require.facile-it/php-openid-client : exact version constraints (1.0.0) should be avoided if the package follows semantic versioning
- require.sabre/dav : exact version constraints (4.7.1) should be avoided if the package follows semantic versioning


PHP 8.5: lint 255 files

PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 208 ( 30%)
............................................................... 126 / 208 ( 60%)
............................................................... 189 / 208 ( 90%)
...................                                             208 / 208 (100%)

Time: 00:04.841, Memory: 26.00 MB

[30;42mOK (208 tests, 1202 assertions)[0m


./composer.json is valid, but with a few warnings
See https://getcomposer.org/doc/04-schema.md for details on the schema
# General warnings
- No license specified, it is recommended to do so. For closed-source software you may use "proprietary" as license.
- require.facile-it/php-openid-client : exact version constraints (1.0.0) should be avoided if the package follows semantic versioning
- require.sabre/dav : exact version constraints (4.7.1) should be avoided if the package follows semantic versioning

```
