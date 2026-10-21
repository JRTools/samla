# Prüfprotokoll: Iteration 10

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 105 Tests, 317 Assertions | 136 Tests, 599 Assertions | 241 Tests, 916 Assertions |
| 8.5.4 | MySQL 8.0.46 | 105 Tests, 317 Assertions | 136 Tests, 599 Assertions | 241 Tests, 916 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 105 Tests, 317 Assertions | 136 Tests, 599 Assertions | 241 Tests, 916 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 105 Tests, 317 Assertions | 136 Tests, 599 Assertions | 241 Tests, 916 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Syntax aller 103 eigenen PHP-Dateien unter beiden Versionen geprüft. Migrations-CLI unter allen vier Kombinationen erfolgreich: Schema is current. Keine neuen Migrationen oder Laufzeitbibliotheken. Composer validate erfolgreich, mit dem bereits offenen Hinweis auf die fehlende Projektlizenz.

## Neue Protokollnachweise

15 zusätzliche Unitfälle mit synthetischen Zugangsdaten prüfen:

- Standardmäßig deaktivierte Konfiguration, explizites Opt-in und Fehler bei unvollständigen Einstellungen.
- HTTPS in Produktion, begrenzte lokale HTTP-Ausnahme, fester Callbackpfad, keine URL-Zugangsdaten, Querys oder Fragmente.
- Exakten Discovery-Issuer, HTTPS-Endpunkte desselben Origin und angekündigte Unterstützung für Code/S256/Basic.
- Unabhängige zufällige State-/Nonce-/Verifierwerte und S256-Challenge mit unabhängig berechnetem SHA-256-Vektor.
- Kein Secret oder Verifier im Authorization-Request; optionale Projekt-Audience als Scope.
- Einmaligen Callback-Verbrauch einschließlich Fehlern, ersetzte Versuche, Ablaufgrenze und zukünftige Zeitstempel.
- Strikten State-/Issuervergleich, verpflichtenden Antwort-Issuer bei angekündigter Unterstützung und Ablehnung von Array-/Überlängen-/Steuerzeichenwerten.
- Ungültigkeit einer ausstehenden Anmeldung nach Client-, Callback- oder Scopewechsel.
- Token-Requestvorbereitung mit ursprünglichem Callback/Verifier, korrekt percent-kodiertem Basic-Header und ohne Secret im Requestbody.
- Client-ID als erwartete ID-Token-Audience, unabhängig von der Projektkennung.
- Redigierte Debugausgaben und keine Reflexion von IdP-Fehlerbeschreibungen.

AuthorizationCodeGrant bleibt unbestätigtes Protokollmaterial und wird nicht als Identität verwendet. Diese Tests prüfen keine Tokensignatur.

## CLI und Provider

Ein isolierter lokaler HTTPS-Testserver mit eigener temporärer Test-CA wurde unter beiden PHP-Versionen verwendet. Je Version wurden gültige Discovery, HTTP-Redirect, falscher Issuer, fremder Token-Origin, ungültiges JSON, Antwort über 64 KiB, nicht vertrauenswürdiges TLS-Zertifikat, deaktiviertes OIDC und HTTP-Callback unter Produktion geprüft. Gültige Discovery ist erfolgreich; alle Fehlerfälle werden mit Exitcode 1 und generischer Fehlermeldung beendet. Redirects lösen keinen zweiten Request aus. Client-Secrets und Benutzerzugangsdaten werden nicht übertragen; die Testserver-Anfragen enthalten keinen Authorization-Header.

Das aktuelle echte Discovery-Dokument von id.jrtools.org wurde separat über HTTPS abgerufen und erfolgreich durch ProviderMetadata geprüft. Die direkte PHP-Streamverbindung zum Live-Provider scheitert in dieser temporären Testumgebung an der DNS-Auflösung. Die erfolgreiche lokale HTTPS-Prüfung und die Metadatenprüfung sind daher ausdrücklich kein erfolgreicher Online-CLI-Durchstich.

Vor dieser Implementierung wurde der echte Authorization-Endpunkt mit neuer Client-ID, lokalem Callback, Code und PKCE S256 geprüft: HTTP 302 zur Login-Seite. Die Login-Seite wurde im Browser erreicht und die Testanmeldung über das geschützte Formular abgesendet. Nach diesem Schritt verhinderte die Browser-Sicherheitsrichtlinie eine weitere Beobachtung. Vollständiger Login, Basic-Tokenaustausch und Claims sind nicht bestätigt. Es wurden keine Tokens oder Secrets in Prüfprotokolle übernommen.

## Grenzen

Keine angeschlossenen OIDC-Webrouten, kein Tokenadapter, keine Signaturprüfung, keine produktive Sitzung und keine Gruppensynchronisierung. Die Weboberfläche bleibt ausschließlich über den bisherigen lokalen Entwicklungsadapter zugänglich. Die Protokolltests ersetzen keine OIDC-Browserabnahme oder Produktivfreigabe. Die HTTP-/Browserbelege der unveränderten Oberfläche stehen im [vorherigen Prüfprotokoll](verification-iteration-9.md); diese Prüfungen wurden in Iteration 10 nicht wiederholt. Die Bibliotheksauswahl, Gruppenclaims und Gruppenfrischerichtlinie bleiben offen.

## Ausgaben der Datenbankmatrix

### MySQL

```text
PHP 8.4, lint 103 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 105 ( 60%)
..........................................                      105 / 105 (100%)

Time: 00:00.033, Memory: 20.00 MB

OK (105 tests, 317 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:16, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 103 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 105 ( 60%)
..........................................                      105 / 105 (100%)

Time: 00:00.034, Memory: 20.00 MB

OK (105 tests, 317 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:13.723, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.5 / migrate CLI: Schema is current.
```

### MariaDB

```text
PHP 8.4, lint 103 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 105 ( 60%)
..........................................                      105 / 105 (100%)

Time: 00:00.034, Memory: 20.00 MB

OK (105 tests, 317 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:10.314, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 103 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

...............................................................  63 / 105 ( 60%)
..........................................                      105 / 105 (100%)

Time: 00:00.038, Memory: 20.00 MB

OK (105 tests, 317 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 136 ( 46%)
............................................................... 126 / 136 ( 92%)
..........                                                      136 / 136 (100%)

Time: 00:08.182, Memory: 28.00 MB

OK (136 tests, 599 assertions)


8.5 / migrate CLI: Schema is current.
```
