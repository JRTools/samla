# Prüfprotokoll: Iteration 11

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 117 Tests, 354 Assertions | 136 Tests, 599 Assertions | 253 Tests, 953 Assertions |
| 8.5.4 | MySQL 8.0.46 | 117 Tests, 354 Assertions | 136 Tests, 599 Assertions | 253 Tests, 953 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 117 Tests, 354 Assertions | 136 Tests, 599 Assertions | 253 Tests, 953 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 117 Tests, 354 Assertions | 136 Tests, 599 Assertions | 253 Tests, 953 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Syntax aller 106 eigenen PHP-Dateien unter beiden Versionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Keine neuen Migrationen oder Anwendungsabhängigkeiten.

## Dateikonfiguration

Zwölf zusätzliche Unitfälle prüfen fehlende Datei, Kommentare/BOM/CRLF/export, leere und angeführte Werte, definierte Escapes, wörtliche Dollar-/Shell-/PHP-Ausdrücke ohne Ausführung, Vorrang einschließlich leerer Prozesswerte, keine Veränderung der Prozessumgebung, Fehler ohne Wertausgabe, doppelte Schlüssel, Größen-/Dateitypgrenzen, erneutes Laden, prozessseitige Testisolation und gemeinsame App-/OIDC-/Entwicklungszugangskonfiguration. example.env ist syntaktisch gültig und aktiviert weder Entwicklung noch OIDC; eine leere Gruppenfrischerichtlinie bleibt gesperrt.

Unter PHP 8.4.5 und 8.5.4 wurde zusätzlich ein tatsächlicher PHP-Webserver mit MySQL und einer separaten samla_dev_*-Datenbank betrieben. APP_*, DB_*, TEST_DB_* und OIDC_* wurden aus der Prozessumgebung entfernt. Ausschließlich eine temporäre .env mit synthetischen lokalen Daten stellte die Konfiguration bereit. Seed und Webstart sowie die echten HTTP-Prüfungen bestanden: Sessioncookie, CSRF, Gruppen-/Benutzerformulare, Konflikt, Administrationsschutz, Mitgliedschaftsdelegation, Hostgrenze, Routen und Header. /.env und /example.env liefern keine Datei. Ein explizites prozessseitiges APP_ENV=production übersteuert die Entwicklungseinstellung aus der Datei und ergibt 503. OidcConfig lädt seine Werte ebenfalls aus derselben Datei. Die temporäre .env wurde danach entfernt.

## Isolierte Bibliothekserprobung

facile-it/php-openid-client 1.0.0 wurde ausschließlich außerhalb der Anwendung installiert und mit deaktivierten Composer-Plugins/Scripts ausgewertet. Die vollständige Auflösung und Reproduktion stehen in oidc-library-decision.md. Beide PHP-Versionen bestehen Composer check-platform-reqs der 24-Paket-Auflösung. Der optionale Bibliotheksprüfer lief in beiden Versionen ohne Warnungen/Deprecations, mit ausdrücklich gewähltem NativeHttpClient und ohne cURL-/GMP-Modul.

Nachgewiesen sind gültiges RS256-ID-Token, 16 abgelehnte Tokenfehler, korrekter Basic-/PKCE-Request mit einem PSR-18-Testclient, kontrolliertes Schlüssel-Nachladen und OAuth-Fehler. Gesondert dokumentiert sind die über die Standardverwendung hinaus nötigen Adapterprüfungen für zwingendes ID-Token, Nonce-Präsenz, azp und vorhandenes at_hash. Die Bibliotheksauswahl ist keine Behauptung, dass diese zusätzlichen Anforderungen bereits in Samlas Anmeldung implementiert sind.

```text
{"php":"8.4.5","valid_signature":true,"invalid_tokens_rejected":16,"basic_pkce_request":true,"key_rotation":true,"oauth_error_rejected":true,"adapter_obligations":["require_id_token","require_nonce","validate_azp","validate_at_hash_when_present"],"warnings_or_deprecations":0}
{"php":"8.5.4","valid_signature":true,"invalid_tokens_rejected":16,"basic_pkce_request":true,"key_rotation":true,"oauth_error_rejected":true,"adapter_obligations":["require_id_token","require_nonce","validate_azp","validate_at_hash_when_present"],"warnings_or_deprecations":0}
```

## Grenzen

Die OIDC-Pakete sind noch keine Anwendungsabhängigkeiten. Ihre konkrete Einführung steht gemäß AGENTS.md zur Abstimmung. Kein echter Token-Endpunkt wurde mit Benutzer-/Clientzugangsdaten aufgerufen. Der Prüfclient simuliert nur den Tokenrequest; der Schlüsselwechsel erfolgt über einen kontrollierten Schlüssellieferanten, nicht über entfernte JWKS. Der konkrete Bibliotheks-HTTP-Transport muss später mit realem HTTPS einschließlich Redirect-/Größen-/Zeitgrenzen getestet werden.

Kein produktiver Weblogin, keine Tokenprüfung im Webeinstieg, keine produktive Sitzung und keine verifizierte Gruppensynchronisierung. Die bestehenden Grenzen bleiben trotz .env aktiv. Eine neue visuelle Browserprüfung war für diese Konfigurationsänderung nicht Gegenstand; die tatsächlichen HTTP-Abläufe wurden erneut geprüft. Vorherige OIDC-Protokollnachweise: verification-iteration-10.md. Keine Produktivfreigabe.
