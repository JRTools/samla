# Prüfung Iteration 15

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 312 Tests und 1202 Assertions (142 Unit-/Architekturtests, 170 Integrationstests). Syntax aller 148 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Keine PHPUnit-Warnungen/Deprecations.

Neue Integrationstests prüfen zufällige Credential-Kennung/Geheimnis, echten PASSWORD_DEFAULT-Hash, Ausschluss von Geheimnissen aus Listen/Audit/Outbox, unabhängige Konten, unmittelbaren Widerruf und dessen Idempotenz, lokale Sperren/Gruppenfrische, Ablauf auch nach frischer Anmeldung, Fehlversuchbegrenzung und unbekannte Konten ohne Bucket, Plugin-Veto/Rollback sowie Methoden/CSRF/Escaping/einmalige Webanzeige.

Erneuter echter HTTP-Durchstich unter PHP 8.4 und 8.5 sowie zusätzlicher HTTPS-Produktionskonfiguration unter PHP 8.4 erfolgreich. Er umfasst zusätzlich eigene App-Passwortanlage vor Rollenvergabe, einmalige Geheimnisanzeige, geheimefreie spätere Liste, Widerruf, Storage-Init-CLI und tatsächlichen Upload bei vorübergehend fehlender privater Speicherwurzel (verständliche 503-Antwort); anschließend normaler Upload und alle bestehenden Speicher-/OIDC-/RBAC-Prüfungen erfolgreich.

Die tatsächlich installierte SabreDAV-4.7.1-Auflösung ergänzt sechs Pakete, ändert oder entfernt keine bestehenden Paketversionen. Keine bekannten Advisories bei der Auflösung. Composer check-platform-reqs --no-dev besteht unter PHP 8.4.5 und 8.5.4 mit den erforderlichen Erweiterungen einschließlich cURL und XML. Für PHP 8.5 wurde das passende cURL-Testmodul aus dem zum Runtime-Snapshot gehörigen Paket mit SHA-256-Prüfung ergänzt, ohne Systeminstallation. Der optionale DAV-Protokollprüfer besteht mit dem echten Anwendungs-Autoloader unter beiden PHP-Versionen. Composer validate bestätigt eine gültige Struktur und meldet bekannte Warnungen zur fehlenden Projektlizenz und bewusst exakten Pins; --strict ist deshalb kein warnungsfreier Erfolg.

Kein öffentlicher DAV-Endpunkt, keine dauerhaften gemeinsamen DAV-/Browserlocks oder native Clientkompatibilität sind in dieser Phase behauptet. Der Betreiber bestätigt die erfolgreiche Korrektur des fehlenden Speicherverzeichnisses; neue App-Passwortfunktionen wurden hier nicht auf seinem Host deployed. Vollständige Last-/Crash-/Restore-, visuelle und native Clientabnahme bleiben offen. Vorangegangene Prüfung: verification-iteration-14.md.

### locked-matrix-results.txt

```text
PHP 8.4, lint 148 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:10.088, Memory: 24.00 MB

[30;42mOK (142 tests, 466 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:21.476, Memory: 28.00 MB

[30;42mOK (170 tests, 736 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 148 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:06.611, Memory: 24.00 MB

[30;42mOK (142 tests, 466 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:22.749, Memory: 28.00 MB

[30;42mOK (170 tests, 736 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### mariadb-matrix-results.txt

```text
PHP 8.4, lint 148 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:10.357, Memory: 24.00 MB

[30;42mOK (142 tests, 466 assertions)[0m


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:15.952, Memory: 28.00 MB

[30;42mOK (170 tests, 736 assertions)[0m


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 148 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:04.887, Memory: 24.00 MB

[30;42mOK (142 tests, 466 assertions)[0m


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: /workspace/scratch/2dc1270c696b/implementation/phpunit.integration.xml

...............................................................  63 / 170 ( 37%)
............................................................... 126 / 170 ( 74%)
............................................                    170 / 170 (100%)

Time: 00:15.745, Memory: 28.00 MB

[30;42mOK (170 tests, 736 assertions)[0m


8.5 / migrate CLI: Schema is current.

```

### oidc-web-results.txt

```text
8.4 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.5 development HTTP: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK
8.4 production HTTPS: OIDC, sessions, bootstrap, RBAC, space CLI, folders, multipart upload, duplicate/CSRF rejection, streamed attachment, orphan check, logout and blocking: OK

```
