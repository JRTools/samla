# OIDC-Anmeldung (Iteration 12)

Der Weblogin ist angeschlossen. Einrichtung einschließlich Plesk, privater .env, Redirect-URI, Datenbankschema und Erstadministration: [installation.md](installation.md).

## Provider und Client

Discovery: https://id.jrtools.org/.well-known/openid-configuration. Issuer: https://id.jrtools.org. Bekannt sind Authorization `/oauth/v2/authorize`, Token `/oauth/v2/token`, Schlüssel `/oauth/v2/keys`, Userinfo `/oidc/v1/userinfo` und Logout `/oidc/v1/end_session`.

Samla lädt die tatsächlichen Endpunkte aus validierter Discovery. Es verwendet Authorization Code, PKCE S256, client_secret_basic und ausdrücklich RS256-ID-Tokens. Der Client muss dazu passen. Andere Signaturverfahren, Implicit Flow, automatische Registrierung und Refresh-Token-Sitzungen sind nicht freigeschaltet. Client-ID und Secret bleiben in der privaten .env beziehungsweise Prozessumgebung. Die numerische ZITADEL-Projekt-Audience ist optionaler zusätzlicher Scope und ersetzt niemals die erwartete ID-Token-Client-Audience.

facile-it/php-openid-client 1.0.0 und 23 transitive Pakete sind nach Betreiberfreigabe als Laufzeitabhängigkeiten eingebunden und in composer.lock festgeschrieben. Composer-Plugins bleiben deaktiviert. Die vorherige Evaluation und ergänzende Samla-Pflichten stehen in oidc-library-decision.md.

## Vertrauensgrenzen

Issuer, Callback und Clientkonfiguration stammen nur aus vertrauenswürdiger Datei-/Prozesskonfiguration. Die Discovery muss den Issuer exakt bestätigen und Code/S256/Basic unterstützen. Authorization-, Token- und JWKS-Endpunkte verwenden HTTPS und denselben Origin wie der konfigurierte Issuer. Kein Requestparameter oder Tokenheader kann einen anderen Abrufendpunkt bestimmen. Der eigene Origin wird aus dem fest konfigurierten Callback abgeleitet, mit Host/Port und vertrauenswürdigem HTTPS-Serverstatus verglichen. Forwarded-Header aktivieren keinen Zugang.

Der Bibliothekstransport verwendet eine explizite URL-/Methodenliste, Zertifikats-/Hostprüfung, keine HTTP-Redirects, zehn Sekunden Netzwerk- und 15 Sekunden Gesamtlaufzeit sowie maximal 256 KiB Antwortdaten. Keine fremden Schlüssel-URLs aus JWT-Headern. JWKS-Nachladen für eine neue Schlüsselkennung erfolgt nur über den validierten Endpunkt. Es gibt keinen positiven Berechtigungs-Cache und keinen Token-/Schlüssel-Cache über PHP-Requests hinweg.

Loginversuche verwenden unabhängige zufällige State-, Nonce- und Verifierwerte mit jeweils 256 Bit Entropie. Die serverseitige Transaktion gilt fünf Minuten und ist an Issuer, Client, Callback, Scopes und Gruppenclaim-Konfiguration gebunden. Der Authorization-Request enthält ausschließlich den S256-Challenge. Jeder neue Versuch ersetzt den vorigen. Callback-State, Code, Zeitpunkt und gegebenenfalls angekündigter Antwort-Issuer werden vor dem Tokenaustausch geprüft. Auch ein ungültiger Versuch wird verbraucht.

Der Webeinstieg speichert den Verbrauch unter Sessionlock, bevor Discovery-/Token-/Schlüssel-I/O beginnt. Danach muss die ursprüngliche Austauschkennung noch in derselben Sitzung bestehen. Ein zwischenzeitlicher Logout oder neuer Login verhindert, dass eine verspätete Antwort eine Sitzung wiederherstellt. Ungültige beziehungsweise wiederholte Callbackaufrufe melden einen Fehler und melden einen bereits angemeldeten Benutzer nicht automatisch ab.

Die Bibliothek prüft Signatur, ausdrücklich erlaubtes Verfahren, Issuer, Client-Audience und Zeitclaims. Samla verlangt zusätzlich ID-Token und Nonce, überprüft den exakten Nonce, die Typen von iat/exp/nbf, Subject-/Namensgrenzen, einen vorhandenen azp und bei mehreren Audiences einen passenden azp. Ein vorhandenes at_hash wird mit dem Access-Token erneut durch die JOSE-Prüfung gebunden. Nicht bestätigte Antworten erzeugen keine Identität, Gruppenstände oder Sitzung.

## Identitätsübernahme und Rechte

Identitäten werden ausschließlich über das verifizierte Paar Issuer/Subject zugeordnet. E-Mail ist kein Kontoschlüssel und kein Administratornachweis. Neue Konten erhalten eine stabile interne Kennung, aber keine Rolle. Vorhandene Sperren bleiben wirksam. Name, bestätigter Gruppenstand, Audit und Outbox committen atomar oder werden zurückgerollt. Die Anmeldung verwendet den gemeinsamen Administrationslock und die öffentlichen MutationRunner-/ChangeValidator-Verträge mit Operation `identity.login`; Ziel ist ausschließlich die interne Benutzerkennung. Claims, Tokens und Secrets werden nicht protokolliert.

Bei leerem OIDC_GROUPS_CLAIM werden ausschließlich lokale Gruppen verwendet. Vorhandene externe Mappings/Quellen verhindern diesen Modus. Ein konfigurierter Claim muss als oberster signierter ID-Token-Claim eine begrenzte Liste von Gruppenkennungen liefern. Fehlende oder falsche Claims führen zum Fehler; manuelle Mitgliedschaften bleiben bei gültigem externem Abgleich erhalten. Rollenobjekte werden nicht implizit umgedeutet.

Die eigene Kontoseite ist nach bestätigter Anmeldung und lokaler Aktivprüfung ohne Verwaltungsrolle verfügbar. Fremde Verzeichnisse und Änderungen verwenden unverändert vollständige Autorisierung, Plugin-Einschränkungen, Gruppenfrische und Delegationsregeln. Rechte werden nicht in der Sitzung eingefroren. Ein leerer Gruppenfrischewert gibt keine Verwaltungsrechte frei. Lokale Sperren greifen auch bei bestehenden Sitzungen.

## Sitzung und Abmelden

HTTPS-Sitzungen verwenden `__Host-samla`, Secure, HttpOnly, Path=/ und SameSite=Lax, damit der externe GET-Callback die begonnenen Sitzungsdaten erreicht. Strikter Sessionmodus und ausschließlich Cookies sind aktiviert. Die ID wird vor dem Loginversuch und nach erfolgreicher Bestätigung erneuert; CSRF wird bei erfolgreicher Anmeldung erneuert. Nur direkte lokale Entwicklung erlaubt den getrennten nicht-Secure-Cookienamen samla_oidc_dev.

Die Sitzung ist auf den früheren Zeitpunkt aus Tokenablauf und einer Stunde begrenzt. Änderungen von Client-/Callback-/Claimkonfiguration invalidieren ihre Bindung. Keine Speicherung von ID-/Access-/Refresh-Tokens in der Anwendungssitzung. Loginbeginn und lokaler Logout verlangen POST und CSRF. GET /auth/login zeigt nur das Formular. Die Login-CSP erlaubt zusätzlich den fest konfigurierten IdP-Origin für die Formularweiterleitung; andere Anwendungsseiten erlauben nur eigene Formulare. Alle Antworten verwenden no-store und no-referrer.

POST /auth/logout löscht die lokale Sitzung und den Cookie. Die zentrale IdP-Sitzung bleibt bestehen. Globale IdP-Abmeldung, Backchannel-Logout und administrativer Widerruf einzelner Sitzungen sind noch nicht implementiert. HTTP-Fehler und Logs enthalten keine rohen Bibliotheksausnahmen, Tokens oder Zugangsdaten.

## Routen

| Route | Methode | Verhalten |
|---|---|---|
| /, /account | GET | Loginformular beziehungsweise eigener bestätigter Zugang |
| /auth/login | GET | Anmeldeformular |
| /auth/login | POST | CSRF, neue Transaktion, Weiterleitung zum IdP |
| /auth/callback | GET | Einmaliger Codeaustausch, Verifikation, Provisionierung, Sitzungswechsel |
| /auth/logout | POST | CSRF und lokale Abmeldung |
| /users, /groups und Änderungsrouten | bestehende Methoden | Geschützte Verwaltungsdienste |

## Prüfung und verbleibende Abnahme

Unit-/Datenbanktests prüfen zusätzliche Token-/Transport-/Origin-/Gruppen-/Provisionierungsfälle. Der optionale HTTP-Prüfer testet echte Requests mit einem kontrollierten TLS-Provider, Basic/PKCE, signierten Tokens und JWKS. Er prüft HTTP-Entwicklung unter beiden PHP-Versionen und HTTPS-Produktionskonfiguration mit TLS-Gateway, Secure-Cookie und vertrauenswürdig gesetztem HTTPS-Serverstatus. Das ist kein tatsächlicher PHP-FPM-/Plesk-Lauf.

Der vollständige Login am echten ZITADEL und die Serverinstallation müssen nach Eintragen der privaten Konfiguration geprüft werden. Keine Behauptung einer bereits erfolgten Installation auf dem Betreiberhost. Dateispeicher, WebDAV und Medienfunktionen bleiben offene Produktpakete; siehe Umsetzungsplan.
