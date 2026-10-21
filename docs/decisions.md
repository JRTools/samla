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
