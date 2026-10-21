# OIDC-Integration: Protokollbasis (Iteration 10)

Am 21. Oktober 2026 anhand des vom Nutzer bereitgestellten Discovery-Dokuments geprüft:

- Discovery: https://id.jrtools.org/.well-known/openid-configuration
- Issuer: https://id.jrtools.org
- Authorization Code Flow und PKCE S256 werden angekündigt.
- Authorization-Endpunkt: https://id.jrtools.org/oauth/v2/authorize
- Token-Endpunkt: https://id.jrtools.org/oauth/v2/token
- Schlüssel: https://id.jrtools.org/oauth/v2/keys
- Userinfo: https://id.jrtools.org/oidc/v1/userinfo
- Logout: https://id.jrtools.org/oidc/v1/end_session

Dies ist ein dokumentierter Discovery-Stand. Die neue Protokollbasis prüft die Discovery-Metadaten, lädt jedoch noch keine Schlüssel und prüft keine Tokens. Konkrete Clientdaten werden nicht im Repository gespeichert.

`groups` wird im Discovery-Dokument nicht als Claim angekündigt. Die tatsächliche Bereitstellung und Struktur muss an der Samla-Registrierung geprüft werden. Nicht angekündigt bedeutet nicht zwingend nicht unterstützt.

## Bestätigte Testregistrierung

Die Testregistrierung und ihre Client-Zugangsdaten wurden vom Betreiber bereitgestellt. Der Authorization-Endpunkt akzeptiert die neue Client-ID und leitet mit HTTP 302 zur Login-Seite weiter. Die Testanmeldung wurde im geschützten Browserformular abgesendet. Das Ergebnis nach der Anmeldung konnte wegen einer Browser-Sicherheitsrichtlinie nicht beobachtet werden. Token-Austausch, Basic-Authentifizierung am Token-Endpunkt und tatsächliche Claims sind damit noch nicht erfolgreich nachgewiesen.

| Einstellung | Wert |
|---|---|
| Issuer | `https://id.jrtools.org` |
| Flow | Authorization Code |
| PKCE | S256, obligatorisch |
| Token-Authentifizierung | `client_secret_basic`, in ZITADEL Basic |
| Lokaler Callback | `http://127.0.0.1:8080/auth/callback` |
| Lokale Origin | `http://127.0.0.1:8080` |
| Basisscopes | `openid profile email` |

Eine erlaubte Origin ersetzt keine Redirect-URI. Client-ID und Secret gehören in die private `.env` oder Prozessumgebung, nicht in Konstanten oder Beispieldaten im Repository. Die vom Betreiber angegebene Audience bezeichnet das ZITADEL-Projekt. Sie kann über den projektspezifischen Audience-Scope angefordert werden. Bei der späteren ID-Tokenprüfung bleibt die Client-ID die erwartete Client-Audience; die Projektkennung ersetzt sie nicht.

## Datei-/Prozesskonfiguration und Vorabprüfung

| Variable | Bedeutung |
|---|---|
| `OIDC_ENABLED` | `0` standardmäßig; `1` erlaubt die Protokollkonfiguration und CLI-Prüfung |
| `OIDC_ISSUER` | Vertrauenswürdig konfigurierter HTTPS-Issuer, niemals aus Requestdaten |
| `OIDC_CLIENT_ID` | Aktuelle Client-ID aus der IdP-Anwendungsregistrierung |
| `OIDC_CLIENT_SECRET` | Secret ausschließlich aus der Laufzeitumgebung |
| `OIDC_TOKEN_AUTH_METHOD` | Nur `client_secret_basic` |
| `OIDC_REDIRECT_URI` | Feste vollständige URL mit Pfad `/auth/callback` |
| `OIDC_ZITADEL_PROJECT_ID` | Optionale numerische Projekt-Audience für den zusätzlichen ZITADEL-Scope |

Produktion verlangt HTTPS auch für den Callback. Nur in `development` oder `test` ist HTTP auf exakt localhost, 127.0.0.1 oder IPv6-Loopback erlaubt. Zugangsdaten, Query und Fragment sind in den konfigurierten URLs ausgeschlossen. Ein abschließender Slash am Callback wird nicht stillschweigend korrigiert.

Mit eingetragener `.env` oder gesetzten Prozessvariablen `composer oidc:check` ausführen. Syntax und Vorrang stehen in [configuration.md](configuration.md). Die Vorabprüfung braucht keine Datenbank. Sie benötigt PHP-HTTPS-Streams, `allow_url_fopen` und einen CA-Vertrauensspeicher. Sie lädt höchstens 64 KiB Discovery-Daten, verwendet zehn Sekunden Netzwerk-Timeout und folgt keinen HTTP-Redirects. Sie sendet keine Basic-Zugangsdaten, Tokens oder Benutzerpasswörter. Fehlerausgaben enthalten keine Rohantworten oder Konfiguration.

`ProviderMetadata` verlangt eine exakte Issuer-Übereinstimmung und angekündigte Unterstützung für Code, S256 und Basic. Authorization-, Token- und JWKS-Endpunkte müssen HTTPS und denselben Origin wie der Issuer verwenden; abweichende Origins benötigen vor einer späteren Unterstützung eine ausdrückliche Vertrauensrichtlinie. Das ist eine bewusste Einschränkung für den vorliegenden Provider und keine Behauptung, dass OIDC immer denselben Origin verlangt. Das Live-Discovery-Dokument erfüllt diese Bedingungen.

## Implementierter Ablaufvertrag

`AuthorizationFlow.begin` erzeugt getrennte zufällige State-, Nonce- und PKCE-Verifierwerte mit jeweils 256 Bit Entropie. Der Authorization-Request enthält ausschließlich den S256-Challenge, niemals den Verifier oder Client-Secret. Eine neue Anmeldung ersetzt den älteren Versuch in derselben Sitzung. Eine `LoginTransaction` ist ausschließlich serverseitig zu speichern.

`AuthorizationFlow.consume` entfernt den gespeicherten Versuch vor weiterer Verarbeitung. Auch Fehler beenden diesen Versuch. Der Callback muss vor Ablauf von fünf Minuten eintreffen, darf nicht aus der Zukunft stammen und muss zu Issuer, Client, Callback und Scopes der begonnenen Konfiguration passen. State wird mit hash_equals verglichen. Arraywerte, fehlende/überlange Codes, Steuerzeichen und IdP-Fehler werden abgelehnt. Ein zurückgelieferter `iss` muss exakt passen; wenn Discovery diesen Parameter ankündigt, ist er obligatorisch.

Das Ergebnis `AuthorizationCodeGrant` ist ausdrücklich keine authentifizierte Identität. Es bereitet nur Token-Requestdaten einschließlich ursprünglichem PKCE-Verifier und Callback sowie den korrekt kodierten Basic-Header vor. Diese Daten dürfen nicht geloggt werden. Debugausgaben der Konfiguration, Transaktion und des Grants sind redigiert. Es gibt keinen selbst implementierten Signaturprüfer, keine Benutzeranlage und keine Gruppensynchronisierung aus ungeprüften Callbackdaten.

Diese Klassen sind interne Protokollinfrastruktur, keine neue öffentliche Plugin-Fachfunktion. Sie vergeben keine Rechte und umgehen keine bestehenden Rechteprüfungen. Die spätere authentifizierte Fachintegration muss weiterhin die öffentlichen Identitäts-/Autorisierungsverträge nutzen.

## Nächste Integration und Grenzen

Die Klassen sind noch nicht an `public/index.php` angeschlossen. `/auth/login` und `/auth/callback` sind weiterhin keine aktiven OIDC-Webrouten. Insbesondere schaltet `OIDC_ENABLED=1` keinen produktiven Zugang frei. Der bestehende lokale Entwicklungsadapter bleibt unverändert.

Für die nächste Phase:

1. Die isoliert auf PHP 8.4/8.5 erprobte Bibliothek `facile-it/php-openid-client 1.0.0` zur Aufnahme abstimmen. Die [Entscheidungsvorlage](oidc-library-decision.md) dokumentiert 24 aufgelöste Pakete, Tests und zusätzliche Adapterpflichten. Eine Freigabe oder ein Einbau in Samla ist noch nicht erfolgt.
2. Adapter für Token-Austausch und strikte Signatur-/Issuer-/Audience-/Nonce-/Zeitprüfung implementieren. Schlüsselrotation und ausdrücklich erlaubte Signaturverfahren testen.
3. Webrouten und serverseitige Sitzungen anschließen. Verbrauchte Transaktionen müssen unter Sessionlock gespeichert werden, bevor ein Token-Request beginnt. Produktionscookies benötigen Secure/HttpOnly und für den externen GET-Callback einen passenden SameSite-Vertrag; Sitzungswechsel, Laufzeiten und Logout sind gesondert zu testen.
4. Nur verifizierte Identitäten provisionieren. Tatsächliche Gruppenclaims, Frischerichtlinie und Erstadministration festlegen; vorhandene Sperren und manuelle Mitgliedschaften erhalten.
5. Vollständigen echten Browserdurchstich samt fehlgeschlagenen Logins, Tokenprüfung und Rechteentzug nachweisen.

Die Vorabprüfung und die Protokolltests sind kein Nachweis einer vollständigen Anmeldung oder Produktivfreigabe.
