# Entscheidungen und offene Angaben

## Umgesetzt

- Modularer Monolith mit separater öffentlicher Plugin-API und eigenem RBAC-Namensraum.
- Weiterhin keine zusätzlichen Laufzeitbibliotheken. Die erste Verwaltungsoberfläche verwendet einen kleinen expliziten PHP-HTTP-Adapter, feste PHP-Templates und statisches CSS. Eine Framework-/Templatebibliothek ist nicht eingeführt.
- Lokaler Entwicklungszugang ausschließlich über explizite Flags, Loopback und PHPs eingebauten Server mit separatem Datenbankpräfix. Dies ist kein produktives Authentifizierungsverfahren.
- Produktname Samla, Paket jrtools/samla und öffentliche Namensräume Samla.
- Composer-PSR-4, PHPUnit-Konfiguration und Lockdatei; installierte Testabhängigkeit PHPUnit 13.4.3.
- Zielmatrix PHP 8.4.5 und 8.5.4 gegen MySQL 8.0.46 erfolgreich geprüft.
- Umgebungsbasierte Konfiguration, PDO-Adapter und eigene Migrationen ohne zusätzliche Laufzeitbibliotheken.
- Erster öffentlicher Verwaltungsdienst nur für fachliche Rollenzuweisungen in Speicherbereichen. Globale und administrative Zuweisungen bleiben außerhalb dieses Vertrags.
- Exakte Delegationsregeln mit Berechtigungsobergrenzen, Plugin-Vorabvalidierung, transaktionales Audit und persistierte Abschlussereignisse.
- Keine positiven Berechtigungs-Caches.
- API-Major 0, ausdrücklich experimentell.
- Keine impliziten Dateiaktionen durch globale Verwaltungsrollen.

## Bestätigte Datenbankziele

Am 21. Oktober 2026 bestätigt: MariaDB 11.8 oder MySQL 8.0 stehen zur Verfügung. Beide werden als Zielplattformen eingeplant. Die tatsächlich eingesetzte Produktivdatenbank bleibt konfigurierbar. Die vorhandenen Migrationen und Integrationstests wurden gegen MySQL 8.0.46 und MariaDB 11.8.6 unter beiden PHP-Unterversionen geprüft.

## Vor Integration erforderlich

- Hosting: Webserver, PHP-Unterversionen und Erweiterungen, Dateisystem, Uploadgrenzen sowie isolierte Datenbank-Testzugänge.
- OIDC: Testregistrierung, lokaler Callback, Code/PKCE S256 und Basic sind bestätigt. Algorithmusauswahl, tatsächliche Gruppenclaims, Tokenprüfung und Integrationserprobung von Logout bleiben offen; siehe oidc-provider.md.
- Maximal zulässiges Alter zentral bestätigter Gruppen und Verhalten bei Ablauf.
- WebDAV-Referenzclient und Grenzen für Datei- und Datenmengen.
- Freigabe konkreter OIDC- und WebDAV-Bibliotheken. Routing und Templates der ersten lokalen Oberfläche funktionieren mit vorhandenem PHP ohne neue Bibliothek; eine spätere Frameworkentscheidung bleibt gesondert abzustimmen.
- PHP-CLI/Cron oder abgestimmte Alternative für Aufgaben, Outbox und Bereinigung.
- Startrollen, Delegation, persönliche Speicherbereiche, administrativer Notfallzugang.
- Projektlizenz, CI, vollständige Browser-/Accessibilitymatrix und Coverage-Treiber. Erste Chrome-Browserprüfungen sind durchgeführt; siehe verification.md.

## Quellen zur Testabhängigkeit

- https://phpunit.de/supported-versions.html
- https://phpunit.de/announcements/phpunit-13.html

Geprüft am 21. Oktober 2026: PHPUnit 13 ist für PHP 8.4 und neuer vorgesehen. Die aktuelle Testmatrix ist in verification.md dokumentiert.

## OIDC-Protokollbasis ohne zusätzliche Laufzeitbibliothek

Iteration 10 setzt die bestätigten Providerangaben in Konfiguration, Metadatenprüfung und den internen State-/Nonce-/PKCE-Ablauf um. Eine CLI-Vorabprüfung sendet keine Zugangsdaten. Sie verwendet vorhandene PHP-HTTPS-Streams und benötigt erlaubtes allow_url_fopen sowie einen CA-Vertrauensspeicher. Zusätzliche Pakete oder ein eigener Token-Signaturprüfer werden nicht eingeführt. Die Webanmeldung bleibt bis zu Bibliotheksadapter, Sitzungsintegration und erfolgreichem Durchstich gesperrt.

Iteration 11 ergänzt die isolierte Bibliothekserprobung und empfiehlt `facile-it/php-openid-client 1.0.0` einschließlich der dokumentierten transitiven Pakete. Der Prüfer weist unter beiden PHP-Versionen die Bibliotheksfunktionen und erforderliche zusätzliche Samla-Prüfungen nach. Die Aufnahme als Laufzeitabhängigkeit ist noch nicht abgestimmt; siehe [Entscheidungsvorlage](oidc-library-decision.md).


## .env auf ausdrücklichen Betreiberwunsch

Iteration 11 ergänzt den nativen, begrenzten EnvironmentLoader und ersetzt die bisherige Vorlage durch example.env im Projektverzeichnis. Die private .env wird automatisch gelesen; Prozesswerte behalten Vorrang. Automatisierte Tests mit prozessseitigem APP_ENV=test ignorieren diese Datei. Die früher dokumentierte ausschließliche Prozesskonfiguration gilt damit als abgelöst. Es wird keine zusätzliche Konfigurationsbibliothek eingeführt. Die Syntax und Zugangsgrenzen stehen in configuration.md.


## OIDC-Freigabe und Webintegration (Iteration 12)

Die Betreiberanweisung zur vollständigen Umsetzung gibt die vorgeschlagene Bibliothek facile-it/php-openid-client 1.0.0 einschließlich ihrer 23 transitiven Pakete frei. Die tatsächliche Auflösung ist im Anwendungs-Lockfile festgeschrieben; Composer-Plugins bleiben deaktiviert. Der Code verwendet ausdrücklich NativeHttpClient, HTTPS-Vertrauen, feste Endpunkte und RS256. Die ergänzenden ID-Token-/Nonce-/azp-/at_hash-Prüfungen sind implementiert. Ältere offene Freigabe-/Integrationspunkte in den historischen Abschnitten sind damit für diesen Umfang erledigt.

OIDC-Weblogin, eigenes Konto, transaktionale Provisionierung mit Plugin-Veto, feste Sitzungslaufzeit und lokale Abmeldung sind angeschlossen. Die erstmalige Verwaltungsrolle erfordert einen Offline-CLI-Schritt bei Wartung, frischem aktivem OIDC-Konto und expliziter Gruppenfrischerichtlinie. Kein erster-Login-wird-Admin. Ohne konfigurierten Gruppenclaim werden nur manuelle Gruppen bestätigt, und vorhandene externe Mappings/Quellen verhindern diesen Modus. Ein konfigurierter signierter Claim muss eine begrenzte Liste liefern. Keine automatische Rollenobjekt-Übersetzung.

Offen bleiben tatsächliche Host-/ZITADEL-Abnahme, zentrale IdP-Abmeldung/Backchannel-Logout, allgemeine Notfalladministration sowie die übrigen Datei-/Medien-/WebDAV-Pakete. Ein erfolgreicher Login ist keine Freigabe der gesamten Plattform.


## Iteration 14: weitere Dateioperationen und WebDAV-Vorbereitung

Dateirechte bleiben vollständig bereichsbezogen. Löschen in den Papierkorb und Wiederherstellen erhalten getrennte atomare Berechtigungen. Keine automatische Erweiterung bestehender Rollen. Ein kontrollierter Offline-Installer vergibt ausdrücklich gewählte Profile; globale Verwaltungsrechte allein bleiben ohne Dateizugriff. Kopien nutzen unveränderliche Objekte gemeinsam, während die logische Quote je Datei zählt. Namen im Papierkorb bleiben bis zur späteren endgültigen Löschung reserviert.

API 0.6 ergänzt einen typisierten StorageChange, ohne Rohinhalt oder Betriebssystempfad in Plugin-Kontext/Audit einzuschleusen. Metadatenänderungen laufen unter dem bestehenden MutationRunner. Physische Garbage-Collection bleibt ein ausdrücklich anzuwendender Offline-Schritt nach Probelauf, mit Altersgrenze und erneuter Referenzprüfung.

SabreDAV 4.7.1 wurde außerhalb der Anwendung isoliert aufgelöst und unter PHP 8.4/8.5 protokollseitig erprobt. Die konkrete zusätzliche Anwendungsabhängigkeit benötigt gemäß AGENTS.md noch Freigabe. Entscheidungsvorlage: webdav-library-decision.md. Kein zusätzlicher Netzwerkdienst wird vorgeschlagen. Versionen, weitere Speicherfunktionen, Medienfunktionen und andere Konzeptpunkte sind damit nicht als abgeschlossen erklärt.

Die anschließende Betreiberanweisung vom 22. Oktober 2026 gibt SabreDAV konkret frei. Iteration 15 darf die zusätzliche Bibliothek samt benötigter transitiver Abhängigkeiten aufnehmen.
