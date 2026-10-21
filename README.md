# Samla

Samla ist eine schlanke, selbst betriebene Datei- und Medienplattform für eine Organisation. Eine responsive Verwaltungsoberfläche mit OIDC-Anmeldung ist implementiert. Geplant sind WebDAV, Dateiversionierung und eine Medienbibliothek mit Metadaten und nachvollziehbaren KI-Kennzeichnungen. Gruppenbasierte Rechte und die geschützten Verwaltungsdienste bilden bereits das Backend.

Fachliche Funktionen erhalten öffentliche Erweiterungsverträge. Das mitgelieferte RBAC-Pflichtplugin verwendet dieselbe Plugin-API wie zusätzliche Erweiterungen. Zuverlässige Dateioperationen, nachvollziehbare Rechte und automatisierte Tests haben Vorrang.

Repository: https://github.com/JRTools/samla

## Entwicklungsstand: Iteration 15 (App-Passwörter und WebDAV-Abhängigkeit)

Vorhanden sind:

- Private lokale Dateiablage mit Bereichsliste, Ordnern, gestreamten Uploads bis 16 MiB, geschützten Attachment-Downloads, Umbenennen, Verschieben, Einzeldateikopien und Papierkorb/Wiederherstellung. Explizite Bereichsrechte, Quoten, Namenskonflikte, Audit/Outbox, initiale Bereichsanlage und lesender Konsistenzcheck. Einrichtung: [storage.md](docs/storage.md).

- Eigene App-Passwörter nach OIDC-Anmeldung: einmalige Anzeige, sichere Hashes, Ablauf und Einzelwiderruf, aktuelle Identitätsprüfung, begrenzte Authentifizierungsversuche und Audit/Outbox. [Dokumentation](docs/app-passwords.md).
- Gemeinsame `.env`-Konfiguration für Web und CLI mit Prozessvorrang und sicherer Vorlage `example.env`.
- OIDC-Weblogin mit Authorization Code/PKCE S256, Basic, RS256-Tokenprüfung, kurzlebiger einmaliger Callbacktransaktion, sicheren HTTPS-Sitzungen und lokaler Abmeldung. Neues Konto ohne automatische Rollen; kontrollierte initiale Administratoreinrichtung per CLI.
- Erste responsive PHP-Weboberfläche mit Übersicht, Benutzer-/Gruppenverzeichnissen, Statusänderungen, Gruppenanlage/Umbenennung und manuellen Mitgliedschaften.
- Eigene Leseberechtigungen, begrenzte Verzeichnisseiten, CSRF-Schutz, HTML-Escaping, Sicherheitsheader sowie OIDC-Zugang oder ausdrücklich lokaler Entwicklungszugang.
- Experimentelle Plugin-API, atomare Registrierung und zentrale Autorisierung.
- RBAC mit expliziten Berechtigungen und globalen beziehungsweise bereichsbezogenen Rollenzuweisungen.
- Gruppenmapping mit mehreren Quellen und getrennten manuellen Mitgliedschaften.
- Datei- und umgebungsbasierte Konfiguration ohne zusätzliche Konfigurationsbibliothek.
- PDO-Anbindung, versionierte Migrationen mit Sperre, Prüfsummen und sichtbarem Fehlerzustand.
- Persistente Benutzer, Gruppen, bestätigte Gruppenstände, Mitgliedschaften, Rollen und Zuweisungen.
- Erster geschützter Verwaltungsdienst für fachliche Rollenzuweisungen innerhalb eines Speicherbereichs, mit ausdrücklichen Delegationsregeln und Berechtigungsobergrenzen.
- Geschützter Benutzerstatusdienst zum Sperren und Entsperren, mit Schutz aktuell nutzbarer Zugänge zur Statusverwaltung und zur bereits eingerichteten Mitgliedschaftsverwaltung.
- Geschützte manuelle Gruppenmitgliedschaften, mit Delegationsprüfung für jede Rolle in jedem betroffenen Speicherbereich und getrennt erhaltenen externen Quellen.
- Geschütztes Anlegen und Umbenennen interner Gruppen mit festen Kennungen, geprüftem Namen und Konfliktschutz.
- Geschützte Mappingvergabe und -entzug mit Delegationsprüfung auch für zukünftige Mitglieder und sofortiger Aktualisierung bereits gespeicherter Gruppenstände.
- Plugin-Validierung vor Änderungen, transaktionales Audit und persistierte Abschlussereignisse in einer Outbox.
- Unit-, Architektur- und echte MySQL-/MariaDB-Integrationstests.

Die Weboberfläche kann jetzt mit konfiguriertem OIDC unter HTTPS/PHP-FPM betrieben werden. Alternativ bleibt der begrenzte lokale Entwicklungsadapter verfügbar. WebDAV und erweiterte Dateioperationen bleiben offen. Der Betreiber bestätigt erfolgreichen ZITADEL-Login und initiale Administration auf seinem Plesk-Host; es gibt keine pauschale Produktivfreigabe für die gesamte Datei-/Medienplattform.

Abgesehen von der begrenzten Erstadministration bleiben globale beziehungsweise administrative Rollenzuweisungen, Rollenbearbeitung, Gruppenlöschung und atomare Mehrfach-Mappingänderungen bleiben offen. Mapping- und Rollenpflege haben noch keine Formulare. Der Schutz aktuell nutzbarer Administrationszugänge umfasst die beschriebenen vier Verwaltungsfähigkeiten; ein vollständiger Notfallzugang ist noch nicht umgesetzt. Die Outbox speichert Ereignisse, stellt sie aber noch nicht zu.

## Voraussetzungen

- PHP 8.4 oder 8.5, Composer, PDO/pdo_mysql, ctype, JSON, OpenSSL, iconv, mbstring sowie cURL, DOM, SimpleXML, XMLReader und XMLWriter/libxml.
- MySQL 8.0 oder MariaDB 11.8. Die konkrete Prüfabdeckung steht im Prüfprotokoll.
- PHPUnit 13 als Entwicklungsabhängigkeit und dessen erforderliche PHP-Erweiterungen.
- PHP-Sessionfunktion und ein beschreibbares, nicht öffentliches Sessionverzeichnis. HTTPS-Streams, allow_url_fopen und gültiger CA-Vertrauensspeicher für OIDC.
- GD für die späteren Medienfunktionen.

Ein zusätzlicher Framework-, Node.js-, Redis- oder Suchdienst ist derzeit nicht eingebaut. Der freigegebene OIDC-Client und SabreDAV 4.7.1 samt ihren transitiven Paketen sind in composer.lock festgeschrieben; Composer-Plugins bleiben gesperrt. Die Projektlizenz bleibt offen; die WebDAV-Protokollintegration wird fortgesetzt.

## Installation und Konfiguration

```sh
composer install
cp -n example.env .env
chmod 600 .env
```

Die `.env` im Projektverzeichnis wird automatisch von Weboberfläche, Migrationen, Entwicklungs-Seed und OIDC-Vorabprüfung geladen. Trage dort die Datenbank- und OIDC-Einstellungen ein. Die vollständige [Installationsanleitung](docs/installation.md) beschreibt auch Plesk, Routing und die Erstadministration. Bereits gesetzte Prozess-/Webservervariablen haben Vorrang, auch wenn sie leer sind. `.env` und `.env.*` werden nicht committed. Der PHP-Webserver muss `public` als Document Root verwenden; `.env` liegt außerhalb dieses Verzeichnisses. Dateieigentümer und Leserechte müssen zum PHP-Benutzer passen. Details und unterstützte Syntax: [Konfiguration](docs/configuration.md).

`APP_ENV` unterscheidet `production`, `development` und `test`; der Standard ist `production`. Mit prozessseitigem `APP_ENV=test` ignorieren automatisierte Tests die Deployment-Datei vollständig und verwenden ausschließlich explizite `TEST_DB_*`-Prozesswerte. Eine leere Gruppenfrische-Einstellung lässt Identitätszugriff gesperrt, bis die Richtlinie ausdrücklich festgelegt ist.

```sh
composer diagnose
```

Die Diagnose prüft lokale Fähigkeiten und ersetzt keine vollständige Betriebsabnahme.


## Oberfläche lokal ausprobieren

Eine separate Datenbank `samla_dev_local` bereitstellen und die DB-Verbindungsvariablen als Prozessumgebung setzen. Dann:

```sh
export APP_ENV=development
export APP_MAINTENANCE=0
export DB_NAME=samla_dev_local
export APP_GROUP_MAX_AGE_SECONDS=3600
export APP_WEB_DEV_ACCESS=1
export APP_DEV_ACTOR_ID=dev.admin
php bin/dev-seed.php
php -S 127.0.0.1:8080 -t public public/router.php
```

Anschließend im Browser http://127.0.0.1:8080/ öffnen. DB_ENGINE, DB_HOST, DB_PORT, DB_USER und DB_PASSWORD müssen zur eigenen Entwicklungsdatenbank passen. Die 3600 Sekunden sind nur ein lokales Beispiel. Der Seed legt explizite Demorechte an und erneuert synthetische Entwicklungsgruppendaten; er ist keine OIDC-Verifikation.

Der Zugang funktioniert nur mit PHPs lokal gebundenem Entwicklungsserver, Loopback-Client, lokalem Hostheader und einer Datenbank `samla_dev_*`. Kein FPM-/Produktionsbetrieb, Reverseproxy oder öffentlicher Tunnel. Für diesen lokalen Adapter ist kein IdP nötig. Vollständige Einrichtung, Funktionsumfang und Sicherheitsgrenzen: [docs/web.md](docs/web.md).

## Migrationen

Zunächst eine leere Datenbank und ein darauf begrenztes Datenbankkonto bereitstellen. Vor Migrationen Schreibzugriffe einschließlich WebDAV durch das Deployment anhalten. `APP_MAINTENANCE=1` ist eine zusätzliche CLI-Vorbedingung und ersetzt das Anhalten laufender Prozesse nicht.

```sh
APP_MAINTENANCE=1 composer migrate
```

Migrationen werden vom vertrauenswürdigen Einstiegspunkt explizit zusammengestellt. Kern und RBAC verwalten eigene Tabellen. Ein Fehler kann bereits ausgeführte DDL hinterlassen. Der Migrationszustand blockiert dann weitere Läufe bis zur kontrollierten Prüfung oder Wiederherstellung. Es gibt keine automatische Rückmigration.

## Tests

```sh
composer test
```

Integrationstests benötigen eine eigene Datenbank mit dem Präfix `media_platform_test_` und ein nur dafür berechtigtes Konto. Sie verändern Testdaten und dürfen niemals mit einem Produktivkonto ausgeführt werden.

```sh
APP_ENV=test composer test:integration
```

Alle `TEST_DB_*`-Werte müssen als Umgebungsvariablen gesetzt sein. Fehlende Testkonfiguration führt zum Fehler, nicht zum stillen Überspringen der Datenbanktests. Die Testdatenbank wird vorab angelegt; die Tests legen keine beliebigen Datenbanken an und löschen keine Datenbank.

Nachgewiesen: in allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 jeweils 312 Tests mit 1202 Assertions, ausgeführt mit PHPUnit 13.4.3. Die Composer-Abhängigkeiten sind installiert und über composer.lock festgeschrieben. Aktuelle Ergebnisse: [Prüfprotokoll](docs/verification.md).

## OIDC einrichten

`OIDC_ENABLED=1`, Issuer, Client-ID, Secret und die tatsächliche HTTPS-Callback-URI in .env eintragen. ZITADEL verwendet **Basic**, Authorization Code mit PKCE S256 und RS256-ID-Tokens. Die Redirect-URI muss exakt registriert sein. Der erste Login zeigt das eigene Konto und dessen interne Kennung; er vergibt keine Administratorrechte.

```sh
APP_MAINTENANCE=1 php bin/migrate.php
php bin/oidc-check.php
```

Nach dem ersten Login den Webzugang für die Einrichtung in Wartung nehmen und den vorgesehenen Benutzer ausdrücklich zur initialen Administration machen:

```sh
APP_MAINTENANCE=1 php bin/admin-bootstrap.php DEINE_SAMLA_KENNUNG
```

Eine explizite Gruppenfrischerichtlinie ist für Verwaltungszugriffe erforderlich. OIDC_GROUPS_CLAIM leer bedeutet manuelle/lokale Gruppen und ist nur ohne externe Mappings/Quellen möglich; für externe Gruppen den tatsächlichen signierten Listenclaim konfigurieren. Die Projekt-Audience ersetzt nicht die Client-ID. Lokales Abmelden beendet die Samla-Sitzung, erhält aber die zentrale IdP-Sitzung. Sitzungen enden spätestens nach einer Stunde oder beim früheren Tokenablauf.

[Vollständige Installation einschließlich Plesk](docs/installation.md) · [OIDC-Vertrag und Sicherheitsgrenzen](docs/oidc-provider.md) · [Bibliotheksentscheidung](docs/oidc-library-decision.md)

## Architektur und Dokumentation

| Verzeichnis | Inhalt |
|---|---|
| `src` | Kern, Konfiguration, Datenbankinfrastruktur und Identitätsadapter |
| `packages/plugin-api` | Öffentliche, vorläufige Plugin-Verträge |
| `plugins/rbac` | RBAC-Plugin mit eigener Persistenz und Migrationen |
| `migrations` | Kernmigrationen |
| `bootstrap` | Vertrauenswürdige Zusammenstellung der Kern- und Plugin-Migrationsbeiträge |
| `examples` | Beispielerweiterung |
| `tests` | Unit-, Architektur- und Integrationstests |
| `docs` | Konzept, Umsetzungsplan, Entscheidungen, Verträge und Betriebshinweise |

- [Umsetzungsplan](docs/Umsetzungsplan_Datei_und_Medienplattform.md)
- [Konzept](docs/Konzept_Datei_und_Medienplattform.md)
- [Plugin-API](docs/plugin-api.md)
- [Datenbankbetrieb](docs/database.md)
- [Verwaltungsdienst und Delegation](docs/administration.md)
- [OIDC-Providerdaten](docs/oidc-provider.md)
- [Entscheidungen und offene Angaben](docs/decisions.md)

Nach jeder Implementierungsphase werden README, Umsetzungsplan und Prüfergebnisse aktualisiert und gemeinsam mit dem Code committed. Offene Prüfungen werden ausdrücklich als offen geführt.

Plesk-Korrektur 13.1: Die private Pfadprüfung berücksichtigt open_basedir und untersucht keine verbotenen übergeordneten Verzeichnisse. Interne Symlinks und nicht erlaubte Speicherwurzeln bleiben gesperrt. Nach einem Codeupdate Speichermigrationen ausdrücklich ausführen.

Iteration 14 ergänzt typisierte StorageChange-Vorschläge für Plugin-Validatoren, explizite Offline-Bereichsprofile read/contribute/manage und eine kontrollierte Objektbereinigung mit Probelauf. Gelöschte Ordner sperren auch direkte Links auf ihren Inhalt. Einrichtung und Grenzen: [Dateiablage](docs/storage.md). WebDAV ist als nächste Integration konkret vorbereitet. SabreDAV ist vom Betreiber freigegeben und seit Iteration 15 installiert. Die eigentliche Protokoll-/Lock-Anbindung folgt: [Entscheidungsvorlage](docs/webdav-library-decision.md).

Iteration 15: App-Passwörter sind über den OIDC-Kontobereich verwaltbar; ein angeschlossener WebDAV-Endpunkt folgt erst mit den gemeinsamen Sperr-/Versionierungsdiensten. Fehlender privater Speicher führt jetzt zu einer verständlichen Antwort; bin/storage-init.php unterstützt die sichere Ersteinrichtung mit ausdrücklichem PHP-FPM-Eigentümer.

Die vorhandenen Upload-/Listen-/Baumgrenzen sind vorläufige technische Schutzgrenzen. Vor der nächsten WebDAV-Schreib-/Lastabnahme werden gemäß Konzept 8.2 Bestandsgröße, maximale Dateigröße, parallele Nutzung und konkrete Referenzclients mit dem Betreiber festgelegt.
