# Prüfung Iteration 21

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 382 Tests, 1523 Assertions (166 Unit-/Architekturtests, 216 Integrationstests). Syntax aller 174 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine zusätzliche Migration oder Laufzeitabhängigkeit.

MOVE/COPY auf vorhandene gleichartige ungesperrte Ziele ist atomar geprüft. Overwrite F verhindert die Ersetzung; bisherige Ziele bleiben im Papierkorb. Ein absichtlich ausgelöster Audit-INSERT-Fehler bestätigt den gemeinsamen Rollback von Quell-/Zielzustand und Outbox. Der gekürzte PDOException-Logeintrag im Matrixprotokoll stammt genau aus diesem erwarteten Fehlerfall; die DAV-Antwort enthält weder SQL noch Exceptiontext. Ein Quotenfehler erhält beide Inhalte ebenfalls. Gesperrte Ziele bleiben trotz passendem Token geschützt; nach ausdrücklichem UNLOCK ist Ersetzen möglich.

Autorisierter DELETE entfernt die eigenen betroffenen Leases in derselben SQL-Transaktion. Neue Dateien gleichen Namens erben keine alte Sperre. Ein gesperrter ähnlich beginnender Nachbarname bleibt gesperrt; die Bibliotheksbereinigung nach Pfadpräfix wird nicht verwendet. Ein aus einer Vorversion simulierter Lease auf einem unsichtbaren Papierkorbnachfahren führt zur konservativen Kernablehnung statt einem defekten Protokollpfad.

Der echte HTTP-/HTTPS-Prüfer bestätigt zusätzlich COPY auf bestehendes Ziel mit Overwrite F (412), tatsächliche Ersetzung (204), MOVE einer temporären Datei auf ein bestehendes Ziel (204), Entfernen des Quellpfads und neue korrekte Zielbytes. Die vollständigen bisherigen OIDC-/DAV-/Browser-/Versions-/Quoten-/Konsistenzfälle bleiben erfolgreich. Ein Startversuch des lokalen HTTP-Harness scheiterte zunächst an einer noch nicht erreichbaren temporären Testdatenbank; der isolierte Wiederholungslauf ist vollständig grün. Der temporäre Harness prüft nun zusätzlich TCP-Erreichbarkeit, nicht nur die Startlogzeile.

Der erfolgreich gelesene GitHub-Lauf der vorangegangenen Iteration bleibt in verification-iteration-20.md dokumentiert. Die neue Implementierung startet nach Veröffentlichung erneut die CI-Matrix. Native Geräteabnahme, vollständige Crash-/Last-/Restore-Prüfung und Betreiberdeployment sind nicht nachgewiesen. Offene Referenzclientliste und konkrete Prüfschritte: client-validation.md. Lock-null-Anlage, rekursive Ordnerkopien und bereichsübergreifende Operationen bleiben offen.

### locked-matrix-results.txt

```text
PHP 8.4, lint 174 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:05.429, Memory: 24.00 MB

OK (166 tests, 540 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 216 ( 29%)
............................................................... 126 / 216 ( 58%)
............................................................... 189 / 216 ( 87%)
...........................                                     216 / 216 (100%)

Time: 01:25.497, Memory: 32.00 MB

OK (216 tests, 983 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 174 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:04.927, Memory: 24.00 MB

OK (166 tests, 540 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 216 ( 29%)
............................................................... 126 / 216 ( 58%)
............................................................... 189 / 216 ( 87%)
...........................                                     216 / 216 (100%)

Time: 01:03.312, Memory: 32.00 MB

OK (216 tests, 983 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26


8.5 / migrate CLI: Schema is current.
```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 174 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:05.086, Memory: 24.00 MB

OK (166 tests, 540 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 216 ( 29%)
............................................................... 126 / 216 ( 58%)
............................................................... 189 / 216 ( 87%)
...........................                                     216 / 216 (100%)

Time: 01:09.335, Memory: 32.00 MB

OK (216 tests, 983 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 174 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 166 ( 37%)
............................................................... 126 / 166 ( 75%)
........................................                        166 / 166 (100%)

Time: 00:06.005, Memory: 24.00 MB

OK (166 tests, 540 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 216 ( 29%)
............................................................... 126 / 216 ( 58%)
............................................................... 189 / 216 ( 87%)
...........................                                     216 / 216 (100%)

Time: 01:02.380, Memory: 32.00 MB

OK (216 tests, 983 assertions)
Samla DAV failed: PDOException in /workspace/scratch/2dc1270c696b/implementation/src/Administration/PdoMutationRunner.php:26


8.5 / migrate CLI: Schema is current.
```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.5 development HTTP: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
8.4 production HTTPS: OIDC, app passwords, cookie-free DAV reads/writes/locks/copy/move/name reuse/revocation, storage setup, RBAC, multipart files, historical versions and restore, quota/conflicts, lifecycle, consistency, logout and blocking: OK
```
