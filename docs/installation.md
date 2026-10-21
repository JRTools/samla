# Samla mit OIDC installieren

Dieser Stand bietet OIDC-Anmeldung, eigenes Konto und berechtigungsgeschützte Benutzer-/Gruppenverwaltung. Dateioperationen, Medienbibliothek und WebDAV sind noch nicht enthalten. Der Login ist mit einem kontrollierten TLS-Testprovider geprüft; die konkrete Installation mit ZITADEL muss nach Einrichtung ebenfalls abgenommen werden.

## 1. Code und PHP-Abhängigkeiten

PHP 8.4 oder 8.5 mit PDO/pdo_mysql, ctype, JSON, OpenSSL, iconv, mbstring und Sessionfunktion verwenden. Für die HTTPS-Streams müssen allow_url_fopen sowie ein funktionierender CA-Vertrauensspeicher verfügbar sein. GD ist für die späteren Medienfunktionen vorgesehen.

Im Projektverzeichnis aktualisieren:

```sh
git pull --ff-only
composer install --no-dev --no-plugins --no-scripts
```

Bei Plesk ohne globales `php` den tatsächlichen PHP-Interpreter und die Composer-PHAR verwenden. Der unter `command -v composer` gefundene Plesk-Starter kann ein Shellskript sein; ihn nicht mit PHP ausführen:

```sh
/opt/plesk/php/8.4/bin/php /opt/psa/var/modules/composer/composer.phar install --no-dev --no-plugins --no-scripts
```

Die Befehle mit dem geeigneten Subscription-/PHP-Benutzer ausführen. Composer-Plugins bleiben deaktiviert. Ein zusätzlicher Node-, Redis- oder anderer Serverdienst ist nicht nötig.

## 2. Webserver

Document Root in Plesk auf das Unterverzeichnis `public` setzen. Die `.env`, vendor, CLI-Dateien und das übrige Repository dürfen nicht öffentlich ausgeliefert werden. HTTPS und ein beschreibbares privates PHP-Sessionverzeichnis sind erforderlich.

Für Apache liegt ein Frontcontroller-Rewrite in public/.htaccess bei. Es benötigt mod_rewrite und erlaubte Rewrite-Direktiven. Bei reinem nginx die bestehende Standortkonfiguration so einstellen, dass Anwendungsrouten einschließlich `/auth/login`, `/auth/callback`, `/auth/logout` und `/account` an index.php gehen. In Plesk nicht blind einen zweiten `location /`-Block ergänzen.

PHP muss für HTTPS-Anfragen die vertrauenswürdige Servervariable `HTTPS=on` beziehungsweise `HTTPS=1` erhalten. Samla vertraut keinem vom Client gesetzten X-Forwarded-Proto. Falls ein vorgeschalteter Proxy TLS beendet, diese Zuordnung im vertrauenswürdigen Webserver konfigurieren. Host und Port müssen zum konfigurierten Callback-Origin passen.

## 3. Private Konfiguration

Bei einer neuen Installation example.env nach .env kopieren. Eine vorhandene .env beim Update erhalten:

```sh
cp -n example.env .env
chmod 600 .env
```

Dateibesitz und Leserechte müssen zum PHP-Benutzer passen. Datenbankzugang und folgende OIDC-Werte eintragen. Platzhalter ersetzen; keine echten Secrets in example.env oder Git speichern:

```dotenv
APP_ENV=production
APP_MAINTENANCE=0
APP_WEB_DEV_ACCESS=0
DB_ENGINE=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=samla
DB_USER=samla
DB_PASSWORD='DEIN_DATENBANKPASSWORT'
OIDC_ENABLED=1
OIDC_ISSUER=https://id.jrtools.org
OIDC_CLIENT_ID=DEINE_AKTUELLE_CLIENT_ID
OIDC_CLIENT_SECRET='DEIN_AKTUELLES_CLIENT_SECRET'
OIDC_TOKEN_AUTH_METHOD=client_secret_basic
OIDC_REDIRECT_URI=https://samla.example/auth/callback
OIDC_ZITADEL_PROJECT_ID=
OIDC_GROUPS_CLAIM=
APP_GROUP_MAX_AGE_SECONDS=3600
```

`samla.example` durch die tatsächliche Samla-Domain ersetzen. Der Frischewert 3600 ist ein Beispiel und muss bewusst gewählt werden. Eine leere Richtlinie erlaubt die Anmeldung am eigenen Konto, aber keine geschützten Verwaltungszugriffe oder Erstadministration. Bereits gesetzte Prozesswerte haben weiterhin Vorrang vor .env, auch wenn sie leer sind.

ZITADEL: Anwendung als vertraulichen Code-Client mit Auth-Methode **Basic**, PKCE S256 und RS256-ID-Tokens konfigurieren. Die vollständige HTTPS-Redirect-URI exakt registrieren. Client-ID und Projekt-Audience sind unterschiedliche Werte. Falls die Projekt-Audience benötigt wird, ihre numerische Kennung in OIDC_ZITADEL_PROJECT_ID setzen; Samla ergänzt den ZITADEL-Scope automatisch.

Ein leeres OIDC_GROUPS_CLAIM bedeutet lokale/manuelle Gruppen. Dieser Modus ist nur möglich, solange für den Issuer keine externen Gruppenmappings und für den Benutzer keine externen Mitgliedschaftsquellen vorhanden sind. Andernfalls den tatsächlichen, signierten ID-Token-Claim konfigurieren. Er muss eine Liste von Gruppenkennungen liefern; fehlende oder anders strukturierte Claims werden abgelehnt. Ein ZITADEL-Rollenobjekt wird nicht automatisch als Gruppenliste interpretiert.

## 4. Schema vorbereiten und Provider prüfen

Eine eigene leere Datenbank und ein darauf beschränktes Konto bereitstellen. Webzugriffe während Migrationen und Einrichtung anhalten. Dann:

```sh
APP_MAINTENANCE=1 php bin/migrate.php
php bin/oidc-check.php
```

Unter Plesk bei Bedarf `php` jeweils durch `/opt/plesk/php/8.4/bin/php` ersetzen. Die Vorabprüfung sendet keine Zugangsdaten und ersetzt keinen echten Login. APP_MAINTENANCE=1 als CLI-Variable allein hält keinen separat laufenden Webprozess an.

## 5. Ersten Administrator einrichten

Webzugang wieder freigeben und Samla öffnen. Mit dem vorgesehenen Organisationskonto anmelden. Samla zeigt zunächst „Mein Zugang“ mit einer internen `oidc.…`-Kennung. Der erste Login erhält ausdrücklich keine automatischen Verwaltungsrechte.

Diese Kennung kopieren, den Webzugang für die Einrichtung erneut in Wartung nehmen und auf dem Server ausführen:

```sh
APP_MAINTENANCE=1 php bin/admin-bootstrap.php DEINE_SAMLA_KENNUNG
```

Der Befehl akzeptiert nur einen vorhandenen, aktiven, frisch bestätigten Benutzer des konfigurierten Issuers und eine explizite Gruppenfrischerichtlinie. Er richtet die fest begrenzte initiale Verwaltungsrolle ein. Eine bereits anderweitig belegte Bootstrapgruppe oder abweichende Rolle wird abgelehnt. Wiederholung für denselben Benutzer ist möglich; der Befehl dient nicht der laufenden Vergabe zusätzlicher Administratoren.

Wartungsmodus wieder deaktivieren und die Kontoseite neu laden. Benutzer- und Gruppenverwaltung erscheinen entsprechend der jetzt wirksamen Rechte. Eigene Kontodaten bleiben auch ohne Verwaltungsrolle zugänglich; Verzeichnisse prüfen die zentralen Rechte bei jedem Request erneut.

## 6. Kontrolle und Fehlerbilder

- Loginseite, echter ZITADEL-Login und Rückkehr nach `/account` prüfen.
- Danach berechtigten und verweigerten Verzeichniszugriff sowie Abmelden testen.
- Der 503 „Anmeldung … noch nicht eingerichtet“ bedeutet weiterhin OIDC_ENABLED=0 beziehungsweise fehlende Aktivierung im laufenden Konfigurationsstand.
- Eine generische 503-Antwort kann auch Wartungsmodus, Datenbank-, Session- oder Providerfehler bedeuten. PHP-Logs und CLI-Prüfungen verwenden, ohne Secrets zu veröffentlichen.
- 400 beim Origin weist auf eine falsche Samla-Adresse, Callback-Origin oder fehlende vertrauenswürdige HTTPS-Zuordnung hin.
- 401 nach dem Callback bedeutet, dass Transaktion, Token oder Konto nicht bestätigt werden konnten; Anmeldung neu beginnen und Client-/Claimkonfiguration prüfen.
- 403 bei einer Verwaltung bedeutet fehlende wirksame Rechte, eine Sperre, veraltete Gruppen oder eine fehlgeschlagene Sicherheitsprüfung.

Sitzungen enden spätestens nach einer Stunde beziehungsweise dem früheren ID-Token-Ablauf. Es gibt keine automatische Refresh-Token-Sitzung. „Bei Samla abmelden“ beendet die lokale Sitzung; die zentrale IdP-Sitzung bleibt bestehen. Zentraler Logout, Backchannel-Logout und eine allgemeine Notfalladministration sind nicht Teil dieses Standes.


## Dateiablage ab Iteration 13

Nach erfolgreichem Login und Erstadministration folgen die neue Speichermigration, ein privates für PHP beschreibbares Speicherverzeichnis und die ausdrückliche Bereichsanlage. [storage.md](storage.md) beschreibt Plesk-PHP-Limits, Bereichsrechte, Quoten, Konsistenzprüfung und Backup/Restore. Globale Administration allein berechtigt nicht zum Lesen fremder Dateien.

Bei 503 protokolliert der Webeinstieg jetzt Exception-Klasse sowie Quelldatei und Zeile, ohne Exception-Nachricht, Tokens oder Credentials. oidc-check.php prüft zusätzlich zentrale OIDC-Composer-Klassen; ein erfolgreicher Discovery-Aufruf allein beweist weiterhin keinen vollständigen Weblogin.


## Zusätzliche Voraussetzungen ab Iteration 15

Die freigegebene SabreDAV-Bibliothek ist im Lockfile enthalten. Composer install erneut ausführen. cURL, DOM, SimpleXML, XMLReader und XMLWriter einschließlich libxml müssen in CLI und PHP-FPM verfügbar sein; sie sind ausdrücklich im Composer-Plattformvertrag enthalten. Es wird kein zusätzlicher Serverdienst benötigt.

Die App-Passwortmigration ausdrücklich anwenden. Unter „Mein Zugang“ können eigene App-Passwörter erzeugt und einzeln widerrufen werden. Die Oberfläche zeigt neue Geheimnisse einmalig an. Der WebDAV-Endpunkt folgt erst mit dem vollständigen Protokoll-/Lock-Adapter; installierte Bibliotheken allein aktivieren ihn nicht. Weitere Hinweise: app-passwords.md.


## Update auf Iteration 16

Neue Versionsmigration vor Verwendung der Dateiablage im Wartungsfenster ausführen:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/migrate.php
```

Es gibt keine automatische Vergabe des zusätzlichen Historienleserechts. Gruppen bei Bedarf ausdrücklich mit dem history-Profil ergänzen (storage.md). Die vorhandene Uploadgrenze bleibt standardmäßig unverändert; APP_MAX_UPLOAD_BYTES macht sie konfigurierbar. Eine höhere Grenze verlangt auch passende PHP-/Webserverwerte und verfügbare Bereichsquote. Die neuen Versionstabellen gehören in das konsistente Datenbank-/Dateisystembackup.


## Update auf Iteration 17

Die neue Sperrtabelle ist vor Schreibzugriffen zu migrieren. Der vorhandene Wartungsbefehl bin/migrate.php gilt unverändert. Browser und spätere DAV-Mutationen verwenden dieselbe Sperrbasis. Es gibt in dieser Phase noch keinen angeschlossenen WebDAV-Endpunkt.
