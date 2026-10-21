# Prüfung Iteration 13

PHP 8.4.5 und 8.5.4 mit MySQL 8.0.46 und MariaDB 11.8.6: jeweils 290 Tests, 1095 Assertions (136 Unit-/Architekturtests, 154 Integrationstests). Syntax aller 132 eigenen PHP-Dateien sowie wiederholte Migration geprüft. Keine PHPUnit-Warnungen oder Deprecations.

Echter lokaler HTTP-Durchstich unter PHP 8.4/8.5 mit MySQL und zusätzlich PHP 8.4 unter HTTPS-Produktionskonfiguration: OIDC, Cookie-/Sitzungsgrenzen, Erstadministration, RBAC-Formulare, explizite Bereichsanlage, Ordner, echte Multipart-Uploads, CSRF-/Namenskonflikte, gestreamter Attachment-Download, verwaiste Objekte im Konsistenzcheck, Logout und Sperren erfolgreich. Reproduktion: tests/Tools/oidc-web-smoke.py mit SAMLA_TEST_PHP_COMMANDS und isolierten TEST_DB_*-Werten; benötigt Python cryptography und vorhandene isolierte Testdatenbank. Der Prüfer leert deren Anwendungstabellen.

Neue Fälle testen private Pfade und Namen, Größenlimit, binäre Inhalte und leere Dateien, Bereichsisolation, getrennte globale Rechte, Quoten, Plugin-Veto, Guard nach Validator, Rollback, sofortige Sperren und fehlende Originalinhalte. Ein zuerst falsch sortierter erwarteter Auditvergleich wurde korrigiert; die anschließende vollständige Vierfachmatrix ist erfolgreich.

Der Betreiber bestätigt echten ZITADEL-Login und Administratorbootstrap auf Plesk. Neue Dateioperationen wurden nicht auf diesem entfernten Host deployed oder geprüft. Neue Seiten wurden durch HTTP- und Escapingtests geprüft; eine vollständige visuelle Browser-/Barrierefreiheitsabnahme, konkurrierende Prozesslast, Crash-/Powerlossversuche und ein kompletter Backup-Restore sind offen. Native Client-WebDAV und Medienfunktionen sind noch nicht vorhanden.

Die vorangegangene Prüfung ist in verification-iteration-12.md archiviert.

### locked-matrix-results.txt

```text
PHP 8.4, lint 132 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:05.290, Memory: 24.00 MB

[30;42mOK (136 tests, 441 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 154 ( 40%)
............................................................... 126 / 154 ( 81%)
............................                                    154 / 154 (100%)

Time: 00:18.189, Memory: 28.00 MB

[30;42mOK (154 tests, 654 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 132 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:05.237, Memory: 24.00 MB

[30;42mOK (136 tests, 441 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 154 ( 40%)
............................................................... 126 / 154 ( 81%)
............................                                    154 / 154 (100%)

Time: 00:14.274, Memory: 28.00 MB

[30;42mOK (154 tests, 654 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 132 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:06.800, Memory: 24.00 MB

[30;42mOK (136 tests, 441 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 154 ( 40%)
............................................................... 126 / 154 ( 81%)
............................                                    154 / 154 (100%)

Time: 00:12.468, Memory: 28.00 MB

[30;42mOK (154 tests, 654 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 132 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:05.249, Memory: 24.00 MB

[30;42mOK (136 tests, 441 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 154 ( 40%)
............................................................... 126 / 154 ( 81%)
............................                                    154 / 154 (100%)

Time: 00:08.839, Memory: 28.00 MB

[30;42mOK (154 tests, 654 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.5 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.4 production HTTPS: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK

```

## Plesk-Korrektur 13.1

Ein zusätzlicher Unterprozesstest verwendet reales open_basedir und einen Error-Handler, der jede PHP-Warnung als Exception behandelt. Er bestätigt erlaubte Speicherwurzeln ohne Stat-Aufrufe außerhalb der Hostinggrenze, gesperrte interne Symlinks und vorab abgewiesene fremde Speicherpfade. Gezielte LocalContentStoreTest-Suite unter PHP 8.4.5 und 8.5.4: jeweils 15 Tests, 22 Assertions. Die zuvor aufgeführte Vierfachmatrix stammt aus Phase 13; für diese Pfadkorrektur wurde der gezielte Regressionstest ausgeführt.
