# OIDC-Integration: vorbereitete Providerdaten

Am 21. Oktober 2026 anhand des vom Nutzer bereitgestellten Discovery-Dokuments geprüft:

- Discovery: https://id.jrtools.org/.well-known/openid-configuration
- Issuer: https://id.jrtools.org
- Authorization Code Flow und PKCE S256 werden angekündigt.
- Authorization-Endpunkt: https://id.jrtools.org/oauth/v2/authorize
- Token-Endpunkt: https://id.jrtools.org/oauth/v2/token
- Schlüssel: https://id.jrtools.org/oauth/v2/keys
- Userinfo: https://id.jrtools.org/oidc/v1/userinfo
- Logout: https://id.jrtools.org/oidc/v1/end_session

Dies ist ein dokumentierter Discovery-Stand und noch keine implementierte oder erfolgreich getestete Anmeldung. Endpunkte und Schlüssel werden bei der späteren Integration aus validierter Discovery geladen. Konkrete Clientdaten werden nicht im Repository gespeichert.

`groups` wird im Discovery-Dokument nicht als Claim angekündigt. Die tatsächliche Bereitstellung und Struktur muss an der Samla-Registrierung geprüft werden. Nicht angekündigt bedeutet nicht zwingend nicht unterstützt.

Es fehlen Clientregistrierung, Callback-URL, ein anonymisiertes Gruppenclaim-Beispiel, die verbindliche Gruppenfrische-Richtlinie und die Freigabe der Integrationsbibliothek. Die spätere Tokenprüfung darf nur ausdrücklich ausgewählte Signaturverfahren zulassen.
