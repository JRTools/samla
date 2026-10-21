# Erste Weboberfläche (Iteration 9)

## Umfang

Samla hat eine erste responsive, serverseitig gerenderte Oberfläche ohne zusätzliche Laufzeitbibliotheken. Sie zeigt eine Übersicht und berechtigungsgeschützte Benutzer- und Gruppenverzeichnisse. Benutzer können gesperrt/entsperrt, Gruppen angelegt/umbenannt und manuelle Mitgliedschaften hinzugefügt/entfernt werden. Für Änderungen werden die vorhandenen Anwendungsdienste verwendet. Rollen- und Mappingpflege haben noch keine Formulare; Dateioperationen und Medienbibliothek sind nicht Teil dieser Oberfläche.

## OIDC-Zugang ab Iteration 12

Der echte OIDC-Weblogin ist angeschlossen und kann unter HTTPS/PHP-FPM eingerichtet werden. Login, eigener Zugang, lokale Abmeldung und kontrollierte Erstadministration sind in [installation.md](installation.md) und [oidc-provider.md](oidc-provider.md) dokumentiert. Der folgende lokale Adapter ist eine separate Alternative für Entwicklung; er wird nicht als Produktionszugang freigeschaltet. Ohne OIDC-Konfiguration oder zulässigen Entwicklungsadapter bleibt der Einstieg bei 503.

## Lokal starten

Eine eigene leere Datenbank mit Namen `samla_dev_local` oder einem anderen `samla_dev_*`-Namen sowie ein darauf begrenztes Konto bereitstellen. `example.env` im Projektverzeichnis nach `.env` kopieren und dort DB_HOST, DB_PORT, DB_USER und DB_PASSWORD eintragen. Die Datei wird automatisch geladen; Prozesswerte haben Vorrang. Siehe [configuration.md](configuration.md). Niemals Zugangsdaten committen.

Die folgenden Werte ebenfalls in `.env` eintragen oder wie gezeigt als Prozessvariablen setzen:

```sh
export APP_ENV=development
export APP_MAINTENANCE=0
export DB_ENGINE=mariadb
export DB_NAME=samla_dev_local
export APP_GROUP_MAX_AGE_SECONDS=3600
export APP_WEB_DEV_ACCESS=1
export APP_DEV_ACTOR_ID=dev.admin
```

`DB_ENGINE=mysql` ist die Alternative bei MySQL 8.0. Der Frischewert ist ausschließlich ein Beispiel für die lokale Entwicklung und keine Entscheidung zur produktiven Gruppenrichtlinie.

Mit PHP 8.4 oder 8.5, Composer-Abhängigkeiten, PDO/pdo_mysql und der PHP-Sessionfunktion:

```sh
composer install
php bin/dev-seed.php
php -S 127.0.0.1:8080 -t public public/router.php
```

Anschließend `http://127.0.0.1:8080/` im Browser öffnen. Die PHP-Konfiguration benötigt ein beschreibbares Sessionverzeichnis. Bei einer eigenen temporären PHP-Konfiguration kann dieses mit `-d session.save_path=/absoluter/privater/pfad` beim Start gesetzt werden. Das Verzeichnis muss zuvor vorhanden sein und darf nicht im Webverzeichnis liegen.

Der Seed arbeitet ausschließlich unter APP_ENV=development und mit einer Datenbank des ausdrücklich eingeschränkten Namenspräfixes. Er legt Demoidentitäten `dev.admin` und `dev.member`, die Gruppen Administration/Öffentlichkeitsarbeit und eine explizite Entwicklungsrolle an. Er authentifiziert keine OIDC-Identität. Wiederholtes Ausführen erneuert synthetische lokale Gruppensnapshots; bestehende lokale Benutzersperren und umbenannte Gruppen werden nicht automatisch zurückgesetzt. Ein abweichender, bereits vorhandener reservierter Rollen- oder Identitätsstand wird nicht überschrieben. Der Seed ist ein Einrichtungswerkzeug, kein öffentlicher Endpunkt. Für Migration/Seed den lokalen Webserver vorher anhalten.

Die feste Entwicklungsrolle enthält die beiden Leserechte und die vier ausdrücklichen Identitätsverwaltungsrechte. Sie gibt keine impliziten Datei-/Bereichsrechte. Die administrative Gruppe bleibt gegen Mitgliedschaftsänderungen über den öffentlichen Dienst geschützt. Für erste Mitgliedschaftsversuche eine neue unzugewiesene Gruppe verwenden. Fachliche Gruppen mit Bereichsrollen benötigen zusätzlich die zuvor dokumentierte Delegation.

## Entwicklungszugang und Sitzungen

DevelopmentAccess akzeptiert ausschließlich die gleichzeitige Kombination aus:

- APP_ENV=development und APP_WEB_DEV_ACCESS=1.
- PHP_SAPI=cli-server, explizit auf 127.0.0.1/localhost/IPv6-Loopback gebunden.
- Datenbankname mit Präfix `samla_dev_`.
- Direktem Loopback-Client und zulässigem lokalen Hostheader.
- Gültiger serverseitiger APP_DEV_ACTOR_ID.

Produktions-/Testumgebung, FPM, fremder Host, Remoteclient, breite Bindung und fehlendes Opt-in erlauben keinen Zugriff über diesen Adapter. Forwarded-Header schalten ihn nicht frei. Der Zugang ist nur für einen vertrauenswürdigen lokalen Entwicklungsrechner gedacht und darf nicht über einen Reverseproxy, Tunnel oder gemeinsam genutzten Host angeboten werden. Die Prüfung ist kein Ersatz für eine produktive Authentifizierung.

Der Benutzer wird aus der vertrauenswürdigen Datei-/Prozesskonfiguration übernommen, niemals aus Formularen, URL-Parametern oder Actor-Headern. Dieser Entwicklungsadapter besitzt kein Loginformular, keinen Benutzerwechsler und keinen OIDC-Logout. Ändern des Entwicklungsakteurs erfolgt durch Anhalten und Neukonfigurieren des lokalen Servers. Bei einer anderen serverseitigen Identität werden Session-ID und CSRF-Token erneuert.

Sessions verwenden Strict Mode, ausschließlich Cookies, HttpOnly und SameSite=Strict. Das Cookie ist für diese direkte lokale HTTP-Entwicklung nicht Secure. Für produktive HTTPS-Sitzungen wird ein eigener OIDC-/Sessionvertrag einschließlich Secure-Cookie, Laufzeiten und Logout benötigt. CSRF-Token werden mit random_bytes erzeugt, an die Sitzung gebunden und vor allen Schreibzugriffen mit hash_equals geprüft. Fehlerhafte oder fehlende Formulardaten werden abgelehnt.

## HTTP und Berechtigungen

| Weg | Methode | Verhalten |
|---|---|---|
| / | GET | Übersicht mit berechtigten Navigationseinträgen |
| /users | GET | Benutzername, interne Kennung und lokaler Sperrstatus |
| /groups | GET | Gruppenname, interne Kennung, Anzahl unterschiedlicher Mitglieder und berechtigte Formulare |
| /users/status | POST | UserStatusService, ohne Umgehung des Administrationsschutzes |
| /groups/create | POST | GroupDetailsService.create |
| /groups/rename | POST | GroupDetailsService.rename mit erwartetem bisherigen Namen |
| /memberships/change | POST | GroupMembershipService.grant/revoke |

Die neuen globalen Rechte `identity.users.read` und `identity.groups.read` sind ausdrücklich zu vergeben. Ein Verwaltungsrecht impliziert kein Leserecht. AdministrationQueryService prüft die vollständige zentrale Autorisierung vor jeder Verzeichnisabfrage. AccessRestriction kann eine Verzeichnisfunktion zusätzlich sperren. Die globalen Leserechte erlauben das betreffende Verzeichnis als Ganzes; eine zusätzliche Zeilenfilter-API existiert noch nicht.

Verzeichnisse liefern maximal 50 Einträge pro Seite und einen geprüften Schlüsselcursor für weitere Einträge. Es gibt noch keine Suche oder Sortierauswahl. Benutzerseiten geben keine Issuer-/Subjectwerte oder externen Claims aus. Gruppenzahlen zählen eindeutige Benutzer über beide Quellenarten, unabhängig vom lokalen Sperrstatus. Aktiver lokaler Status bedeutet nicht automatisch wirksamen Zugriff: Gruppenfrische und Berechtigungen bleiben notwendig.

Formulare werden nur mit passender globaler Fähigkeit gezeigt. Das ist eine Bedienhilfe; die Dienste prüfen jeden tatsächlichen Schreibaufruf erneut. Eine sichtbare Mitgliedschaftsform garantiert deshalb keine Delegation für jede Zielgruppe. Fehlende Berechtigung beziehungsweise Sicherheitsprüfung führt zu 403, Namenskonflikt zu 409, ungültige Eingabe zu 400, unbekannte Route zu 404 und falsche Methode zu 405. Erfolgreiche POSTs verwenden 303 auf einen festen internen Pfad und eine einmalige Sessionmeldung. Datenbank- oder Laufzeitfehler erzeugen eine generische 503-Seite ohne SQL, Zugangsdaten oder Stacktrace.

Alle dynamischen HTML-Werte werden mit ENT_QUOTES/ENT_SUBSTITUTE escaped. Die Content Security Policy erlaubt keine Scripts, keine fremden Inhalte oder Frames und beschränkt Formulare auf denselben Origin. Zusätzlich sind no-store, nosniff und no-referrer gesetzt. Seitentemplates sind fest ausgewählt; Requestpfade wählen keine beliebigen Include-Dateien. Der Entwicklungsrouter liefert nur die feste CSS-Datei direkt aus. Das Webroot ist ausschließlich `public`, nicht das Projektverzeichnis.

## Erweiterungen und Grenzen

Schreibabläufe nutzen dieselben Plugin-Validatoren, Delegationspolicies, Nachbedingungen, Audit- und Outboxtransaktionen wie das Backend. Lesefunktionen verwenden neue registrierte Berechtigungen und die bestehenden Autorisierungs-/Einschränkungsverträge. Die vertrauenswürdige Zusammensetzung steht in bootstrap/web.php; der Kern importiert keine RBAC-Interna. Eigene Navigation, Pluginseiten und Zeilenfilter sind noch keine öffentliche UI-Erweiterungs-API.

OIDC-Anmeldung und Identitätsübernahme sind ergänzt. WebDAV, Dateiablage sowie Mapping-/Rollenoberfläche fehlen weiterhin; die vollständige Plattform ist noch nicht produktiv abgenommen. Die CSS-Ansichten sind responsiv und benötigen kein JavaScript. CSRF-/Sessionprüfungen des Entwicklungszugangs ersetzen keine spätere OIDC-Sicherheitsabnahme. Ergebnisse der PHP-/Datenbankmatrix und der tatsächlichen HTTP-/Browserprüfungen stehen in verification.md.


## Zusätzliche HTTP- und Browserprüfungen

Die folgenden Skripte verändern die Demoidentitäten und legen Gruppen an. Nur gegen eine eigens isolierte, mit dev-seed eingerichtete lokale Instanz verwenden:

```sh
python3 tests/http-smoke.py http://127.0.0.1:8080
```

Der optionale `tests/browser-smoke.cjs` benötigt separat bereitgestelltes Playwright und einen verfügbaren Testbrowser. Diese Werkzeuge gehören nicht zu den Laufzeitabhängigkeiten von Samla. Das Skript prüft beide Viewportgrößen, Seitenüberlauf und echte Formularbedienung:

```sh
node tests/browser-smoke.cjs http://127.0.0.1:8080
```

Bei extern bereitgestellten Testwerkzeugen muss deren node_modules-Verzeichnis für den Node-Resolver verfügbar sein. SAMLA_BROWSER_EXECUTABLE kann einen vorhandenen Testbrowser auswählen. Browserdownloads und ausführbare Testlaufzeiten werden nicht mit dem Anwendungscode ausgeliefert.
