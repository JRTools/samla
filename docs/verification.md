# Prüfprotokoll: Iteration 12

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 122 Tests, 415 Assertions | 142 Tests, 617 Assertions | 264 Tests, 1032 Assertions |
| 8.5.4 | MySQL 8.0.46 | 122 Tests, 415 Assertions | 142 Tests, 617 Assertions | 264 Tests, 1032 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 122 Tests, 415 Assertions | 142 Tests, 617 Assertions | 264 Tests, 1032 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 122 Tests, 415 Assertions | 142 Tests, 617 Assertions | 264 Tests, 1032 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Syntax aller 121 eigenen PHP-Dateien unter beiden Versionen geprüft. Migrations-CLI unter allen vier Kombinationen erfolgreich: Schema is current. Keine neue Migration. Der freigegebene OIDC-Client und seine 23 transitiven Pakete sind im Anwendungs-Lockfile festgeschrieben. Composer validate erfolgreich mit Hinweisen auf die offene Projektlizenz und die bewusst exakt fixierte OIDC-Version.

## Neue Nachweise

- Reale RS256-Signaturen im Samla-Adapter, korrekter Basic-/PKCE-Request und keine Secrets im Requestbody.
- Ablehnung fehlenden/falschen Nonce, falscher Signatur, Ablauf, ungeprüfter Tokenformate, fehlenden ID-Tokens, azp-/Audiencefehlern und falschen at_hash.
- Zwingende Typ-/Grenzprüfung der Identität und Gruppenliste; unkonfigurierte Claims erzeugen keine externen Gruppen.
- Exakter HTTP-Endpunkt/Methodenvertrag, keine Redirects, begrenzte Antwortdaten, feste Fehler ohne Rohantwort/Secret.
- HTTPS- und kanonische Originprüfung ohne Vertrauen in Forwarded-Header.
- Neue Konten ohne Rollen, stabile Zuordnung über Issuer/Subject, Namensaktualisierung, atomare Audit-/Outboxverarbeitung und keine Rohclaims in Ereignissen.
- Erhalt manueller Mitgliedschaften beim verifizierten externen Abgleich; keine Umdeutung oder stille Löschung externer Quellen durch den manuellen Modus.
- Lokale Sperren, abgelaufene Bestätigung und Plugin-Veto verhindern die Übernahme ohne partielle Daten oder Audit.

## Echter HTTP-Durchstich mit TLS-Testprovider

Der im Repository enthaltene tests/Tools/oidc-web-smoke.py ist mit einer dedizierten media_platform_test_*-Datenbank ausgeführt worden. Er startet einen eigenen kontrollierten HTTPS-Provider mit temporärer CA, echten RSA-signierten ID-Tokens und JWKS. Die CA wird nur für diese PHP-Testprozesse konfiguriert; TLS-Prüfung bleibt aktiv. Alle Zugangsdaten, Subjects und Token sind synthetisch.

Unter PHP 8.4.5 und 8.5.4 mit MySQL wurden tatsächliche Anwendungsrequests geprüft: Loginseite/CSRF, Authorization-Weiterleitung, Codeaustausch mit Basic und S256-Verifier, JWKS-Verifikation, Sitzungs-ID-Wechsel, eigenes Konto ohne automatische Verwaltung, kontrollierte/idempotente Erstadministration, geschützte Verzeichnisse und Gruppenformular, Callback-Replay, ungültiger State ohne Tokenrequest, lokaler Logout nur per POST/CSRF, Sitzungsablauf sowie falscher Nonce und falsche Signatur ohne Sitzung. Lokales Sperren wird in einer bestehenden Sitzung und bei erneutem Login wirksam.

Zusätzlich ist derselbe Ablauf unter PHP 8.4.5 in production mit einem lokalen TLS-Gateway ausgeführt. Der Testserver setzt den HTTPS-Status vertrauenswürdig in der Serverkonfiguration; er wird nicht aus einem Clientheader übernommen. Nachgewiesen sind __Host-samla, Secure, HttpOnly, SameSite=Lax, Path=/ und das Zurücksetzen eines abweichenden PHP-cookie_domain-Defaults. Dies ist keine tatsächlich gestartete PHP-FPM-/Plesk-Instanz und keine echte Browseroberflächenprüfung.

## Reproduktion

Für Unit-/Datenbanktests die normalen Composer-Befehle verwenden. Der optionale HTTP-Prüfer benötigt Python mit cryptography, PHP-Befehlsarrays in SAMLA_TEST_PHP_COMMANDS sowie die expliziten TEST_DB_*-Werte. Beispiel für die PHP-Auswahl:

```sh
export SAMLA_TEST_PHP_COMMANDS='{"8.4":["/opt/plesk/php/8.4/bin/php"],"8.5":["/opt/plesk/php/8.5/bin/php"]}'
python3 tests/Tools/oidc-web-smoke.py
```

Ausschließlich eine eigene isolierte Testdatenbank verwenden: Das Skript löscht deren Anwendungs-Testtabellen und verändert Testkonten. Es prüft das Präfix media_platform_test_ und verlangt eine vorhandene Datenbank. Port 8080 muss auf dem Testrechner frei sein. Temporäre CA, Schlüssel, Sessions, Provider und Testserver werden durch das Skript angelegt und beendet. Python-/Browserwerkzeuge sind keine Anwendungsabhängigkeiten.

## Grenzen

Kein Login auf dem entfernten Betreiberhost und kein vollständig bestätigter Durchstich am echten ZITADEL. Keine direkte PHP-FPM-/Plesk-/Apache- oder nginx-Abnahme, keine neue visuelle Browser-/Accessibility-/Lastabnahme. Die bereitgestellte Apache-Rewritekonfiguration und die Betriebsanleitung müssen auf dem Host aktiviert beziehungsweise geprüft werden. Kein zentraler/Backchannel-Logout, keine Refresh-Sitzungen, kein allgemeiner Notfallzugang und keine Datei-/WebDAV-Funktionen. Die Konfiguration enthält keine echten Zugangsdaten.

Vorherige Nachweise: verification-iteration-11.md. Der vollständige protokollarische Login mit dem kontrollierten Provider ist bestanden; eine pauschale Produktivfreigabe der gesamten Plattform wird nicht behauptet.

## MySQL-Matrix

```text
PHP 8.4, lint 121 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:09.834, Memory: 24.00 MB

OK (122 tests, 415 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:27.608, Memory: 28.00 MB

OK (142 tests, 617 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 121 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:06.383, Memory: 24.00 MB

OK (122 tests, 415 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:22.027, Memory: 28.00 MB

OK (142 tests, 617 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Matrix

```text
PHP 8.4, lint 121 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:05.557, Memory: 24.00 MB

OK (122 tests, 415 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:18.350, Memory: 28.00 MB

OK (142 tests, 617 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 121 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:08.131, Memory: 24.00 MB

OK (122 tests, 415 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 142 ( 44%)
............................................................... 126 / 142 ( 88%)
................                                                142 / 142 (100%)

Time: 00:15.978, Memory: 28.00 MB

OK (142 tests, 617 assertions)


8.5 / migrate CLI: Schema is current.
```
