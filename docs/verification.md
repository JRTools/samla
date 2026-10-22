# Prüfung Iteration 33

Bereichs- und feste Profilverwaltung mit benannten Dropdowns: PHP 8.4.5/8.5.4 × MySQL 8.0.46/MariaDB 11.8.6 in allen vier Kombinationen erfolgreich. Jeweils **493 Tests / 2620 Assertions**: 200 Unit-/Architekturtests mit 1137 Assertions und 293 Integrationstests mit 1483 Assertions. Syntax aller 247 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine neue Migration oder Abhängigkeit. git diff --check erfolgreich.

Die neuen öffentlichen Vertragsprüfungen behandeln unveränderliche explizite Profilrechte und genaue Bindung von Profil-/Bereichsvorschlägen an Operation, Ziel und Scope. Die Integrationsfälle prüfen benannte/escaped Dropdowns, fehlende Rechte, gesperrte Konten, CSRF, Bestätigung, manipulierte Ziele/JSON/Scopes, veraltete Formulare, additive Profile, sofortigen Entzug, getrennte sensible Rechte, globale Workerprofile ohne Datei-/Identitätsadministration und abweichende reservierte Rollen.

Zusätzlich geprüft: echte Vorschläge an Pluginvalidatoren, Veto und zwischen den Guardprüfungen entzogene Administratorrechte mit vollständigem Rollback; Bereichsanlage ohne implizite Dateirechte; Namen/Quoten mit Zustandsprüfung; Verbrauch einschließlich Historie und Papierkorb; nicht kaskadierende Ablehnung gefüllter Bereichslöschung; aktive Sperren; atomare Bereinigung von Zuweisungen/Delegationen mit Rücknahme bei Lifecyclefehler; Fortbestand anderer Bereiche und Rollen; bestehende CLI-Bereichszuweisungen im benannten Profilmenü sowie weiterhin geschützte administrative/eigene Rollen. Benutzer-Dropdowns mit tatsächlich escaped Anzeigenamen sind im vorhandenen Verwaltungsintegrationstest ergänzt.

Der tatsächliche synthetische OIDC-Durchlauf prüft die neuen HTTP-Endpunkte einschließlich Bereichsanlage, fehlender automatischer Freigabe, namensbasierten Auswahlen, Zuweisen, Entziehen, stale 409, Name/Quote, expliziter Löschbestätigung und erfolgreicher Leerbereichslöschung. PHP 8.4/8.5 development HTTP sowie PHP 8.4 production HTTPS erfolgreich. Bestehende DAV-, Medien-, Versions-, Worker-, Sperren- und Kontoprüfungen bleiben erfolgreich.

Während der Entwicklung wurden zwei Fehler in den neuen Testfixtures korrigiert (Spaltenname trashed_at und fehlendes Statusverwaltungsrecht des synthetischen Administrators). Ein vorübergehender MySQL-Teststart scheiterte an einer fehlenden Redodatei im temporären Fixture; die isolierte Datenbank wurde neu unter /tmp initialisiert und danach erfolgreich geprüft. Keine Betreiber- oder Produktivdatenbank war betroffen. Erwartete PDOException-Zeilen im erfolgreichen Lauf stammen aus bestehenden absichtlichen DAV-Rollbacktests.

Die vorherige GitHub-Matrix der Designphase ist mit gelesenen Joblogs vollständig erfolgreich in verification-iteration-32.md dokumentiert. Die neue GitHub-CI folgt auf die Veröffentlichung dieser Phase. Keine visuelle Browser-/native Geräteabnahme behauptet; deren Einschränkung ist in design.md dokumentiert. Freie Rollenbearbeitung, allgemeine administrative Zuweisungen, Delegations-/IdP-Mapping-UI, automatische persönliche Bereiche, Retention und Offline-Synchronisierung bleiben eigene offene Umfänge. Details: access-administration.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 247 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 200 ( 31%)
............................................................... 126 / 200 ( 63%)
............................................................... 189 / 200 ( 94%)
...........                                                     200 / 200 (100%)

Time: 00:03.719, Memory: 26.00 MB

[30;42mOK (200 tests, 1137 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 293 ( 21%)
............................................................... 126 / 293 ( 43%)
............................................................... 189 / 293 ( 64%)
............................................................... 252 / 293 ( 86%)
.........................................                       293 / 293 (100%)

Time: 00:57.159, Memory: 38.00 MB

[30;42mOK (293 tests, 1483 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 247 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 200 ( 31%)
............................................................... 126 / 200 ( 63%)
............................................................... 189 / 200 ( 94%)
...........                                                     200 / 200 (100%)

Time: 00:04.136, Memory: 26.00 MB

[30;42mOK (200 tests, 1137 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 293 ( 21%)
............................................................... 126 / 293 ( 43%)
............................................................... 189 / 293 ( 64%)
............................................................... 252 / 293 ( 86%)
.........................................                       293 / 293 (100%)

Time: 00:56.980, Memory: 38.00 MB

[30;42mOK (293 tests, 1483 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 247 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 200 ( 31%)
............................................................... 126 / 200 ( 63%)
............................................................... 189 / 200 ( 94%)
...........                                                     200 / 200 (100%)

Time: 00:03.739, Memory: 26.00 MB

[30;42mOK (200 tests, 1137 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 293 ( 21%)
............................................................... 126 / 293 ( 43%)
............................................................... 189 / 293 ( 64%)
............................................................... 252 / 293 ( 86%)
.........................................                       293 / 293 (100%)

Time: 00:50.088, Memory: 36.00 MB

[30;42mOK (293 tests, 1483 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 247 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 200 ( 31%)
............................................................... 126 / 200 ( 63%)
............................................................... 189 / 200 ( 94%)
...........                                                     200 / 200 (100%)

Time: 00:03.959, Memory: 26.00 MB

[30;42mOK (200 tests, 1137 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 293 ( 21%)
............................................................... 126 / 293 ( 43%)
............................................................... 189 / 293 ( 64%)
............................................................... 252 / 293 ( 86%)
.........................................                       293 / 293 (100%)

Time: 00:49.466, Memory: 38.00 MB

[30;42mOK (293 tests, 1483 assertions)[0m
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Storage/FileService.php:139


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, named profile dropdowns and protected grant/revoke, space create/edit/empty-delete UI, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, named profile dropdowns and protected grant/revoke, space create/edit/empty-delete UI, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, named profile dropdowns and protected grant/revoke, space create/edit/empty-delete UI, storage setup, RBAC, multipart files, historical versions and restore, UTF-8 editor/draft recovery/form limits, scoped name search, bounded CLI/HTTPS worker and protected status/retry UI, media/EXIF provider and private binary previews and bounded batch rebuild, structured XMP/AI and separately authorized prompts, quota/conflicts, lifecycle, consistency, logout and blocking: OK

```
