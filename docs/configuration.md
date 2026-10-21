# Konfiguration über .env

Samla liest eine `.env` im Projektverzeichnis, unabhängig vom aktuellen Arbeitsverzeichnis. Die Vorlage heißt `example.env` und ist versioniert. Die echte `.env` und Dateien mit dem Präfix `.env.` sind von Git ausgeschlossen.

```sh
cp example.env .env
chmod 600 .env
```

Eigentümer und Leserechte müssen zum CLI-/PHP-FPM-Benutzer passen. Die Datei liegt außerhalb des Webroots: Der Document Root ist ausschließlich `public`. Eine falsche Webserverkonfiguration mit dem gesamten Projekt als Webroot ist nicht durch den PHP-Loader abgesichert. Keine Zugangsdaten in `example.env`, Logs, Screenshots oder Git übernehmen.

## Vorrang und Nutzung

Bereits gesetzte Prozess-/Webservervariablen haben Vorrang vor Dateiwerten. Auch ein explizit leerer Prozesswert bleibt leer. Eine fehlende `.env` ist erlaubt; dann wird ausschließlich die Prozessumgebung verwendet. Eine vorhandene fehlerhafte, nicht lesbare oder über 64 KiB große Datei wird abgelehnt. Der Loader verändert weder die Prozessumgebung noch PHP-Superglobals und speichert keine Werte zwischen Aufrufen.

AppConfig, OidcConfig und der Webeinstieg verwenden denselben Loader. Damit gelten die Dateieinstellungen für Migrationen, den lokalen Entwicklungs-Seed, die OIDC-Vorabprüfung und die Weboberfläche. `fromEnvironment` bleibt der explizite, dateiunabhängige Einstieg für Tests und vertrauenswürdige Zusammensetzung. Requestheader, Formulare und Queryparameter werden niemals als Konfiguration übernommen.

Wenn `APP_ENV=test` bereits in der Prozessumgebung gesetzt ist, wird die Deployment-Datei nicht gelesen. Automatisierte Datenbanktests brauchen explizite `TEST_DB_*`-Prozessvariablen und weiterhin eine isolierte Datenbank `media_platform_test_*`. Es gibt keinen Rückfall auf `DB_*` aus einer Produktivkonfiguration. Die Einträge in example.env dokumentieren diese Testvariablen, aktivieren sie für automatisierte Tests aber nicht.

## Unterstützte Syntax

```dotenv
# Kommentar
APP_ENV=production
DB_ENGINE=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=samla
DB_USER=samla
DB_PASSWORD='Beispiel mit Leerzeichen und # Zeichen'
OIDC_ENABLED=0
```

Unterstützt werden Leerzeilen, Kommentarzeilen, `NAME=WERT`, optionale Leerzeichen um `=`, leere Werte und optional `export NAME=WERT`. Namen bestehen aus Buchstaben, Ziffern und Unterstrichen und beginnen nicht mit einer Ziffer. UTF-8-BOM und CRLF werden akzeptiert.

Einfache Anführungszeichen erhalten den Inhalt wörtlich, einschließlich Backslashes und Dollarzeichen. Doppelte Anführungszeichen unterstützen die Escapes `\n`, `\r`, `\t`, `\\`, `\"` und `\$`. Nicht angeführte Werte enthalten keine Leerzeichen; ein nach Leerraum beginnendes `#` startet einen Kommentar. Werte mit Leerzeichen oder `#` am besten immer in einfache Anführungszeichen setzen. Die späteren Fachkonfigurationen prüfen weiterhin ihre eigenen Wertgrenzen.

Variablenersetzung, `${NAME}`, Shellausführung, PHP-Ausführung, mehrzeilige Literale, Fortsetzungszeilen und Includes werden nicht unterstützt. Dollar-/Befehlstext bleibt wörtlicher Inhalt. Doppelte Schlüssel, unbekannte Escapes und nicht geschlossene Anführungszeichen werden abgelehnt. Fehlermeldungen geben keine Zeileninhalte oder Secretwerte aus.

## Betrieb und aktueller Funktionsumfang

Die sichere Vorlage verwendet `APP_ENV=production`, deaktivierten Entwicklungszugang und deaktiviertes OIDC. Für die lokale UI-Erprobung die in [web.md](web.md) genannten Entwicklungswerte in `.env` setzen oder explizit als Prozessvariablen übersteuern. Alle vorhandenen Loopback-/SAPI-/Host-/Datenbankgrenzen bleiben wirksam.

Die `.env` aktiviert keine bislang fehlenden Funktionen. Der produktive OIDC-Login ist weiterhin nicht angeschlossen. `OIDC_ENABLED=1` erlaubt gegenwärtig nur die OIDC-Konfiguration und Vorabprüfung. Für PHP-FPM unter Plesk ist daher trotz Dateikonfiguration weiterhin kein produktiver Webzugang verfügbar.
