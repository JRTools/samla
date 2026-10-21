# Konkrete WebDAV-Entscheidung

Status: isoliert geprüft und am 22. Oktober 2026 vom Betreiber ausdrücklich freigegeben. Die Aufnahme ist in Iteration 15 erfolgt; Protokoll- und Lock-Integration folgen.

## Vorschlag

`sabre/dav` exakt 4.7.1 über Composer einführen und auf die vorhandenen Samla-Fachdienste adaptieren. Kein zusätzlicher Serverdienst, Container oder alternativer Dateispeicher. Die Bibliothek übernimmt WebDAV-Protokoll, XML, bedingte Requests und LOCK/UNLOCK. Samla behält Authentifizierung, Gruppenfrische, RBAC, Quoten, MutationRunner, Audit und Outbox. Die produktive Integration benötigt ausdrücklich getestete gemeinsame Locks für Browser- und WebDAV-Schreibzugriffe, keine getrennte unkoordinierte Schreibwelt.

Quellen: [Paket und konkrete Anforderungen](https://packagist.org/packages/sabre/dav), [Herstellerinstallation](https://sabre.io/dav/install/), [Sperren und PDO-Backend](https://sabre.io/dav/locks/), [Authentifizierungsadapter](https://sabre.io/dav/authentication/).

## Isolierte Paketauflösung

Außerhalb der Anwendung wurde mit Composer, deaktivierten Plugins/Scripts und PHP 8.4.5 aufgelöst:

| Paket | Version |
|---|---|
| sabre/dav | 4.7.1 |
| sabre/event | 5.1.9 |
| sabre/http | 5.1.13 |
| sabre/uri | 2.3.4 |
| sabre/vobject | 4.6.1 |
| sabre/xml | 2.2.11 |
| psr/log | 3.0.2 |

psr/log ist bereits in Samlas Lockfile vorhanden. Der Vorschlag bedeutet daher eine direkte zusätzliche Abhängigkeit und fünf neue transitive Pakete; die genaue gemeinsame Anwendungsauflösung muss nach Freigabe festgeschrieben werden. Die isolierte Auflösung meldete keine bekannten Sicherheitsadvisories. Das ist keine dauerhafte Sicherheitsgarantie. BSD-3-Clause-Lizenz; keine Projektlizenzentscheidung für Samla wird dadurch getroffen.

DOM, SimpleXML, XMLReader, XMLWriter und libxml werden benötigt, zusätzlich zu bereits benötigten Standarderweiterungen. Das muss im CLI- und PHP-FPM-Profil des Zielhosts vorhanden sein; Composer prüft die Plattform beim Installieren. Keine Annahme einer pauschalen PHP-FPM-Modulfreigabe. PDO-MySQL/MariaDB ist für die geplante dauerhafte Sperrpersistenz bereits vorhanden.

## Konkreter Nachweis

Der optionale reproduzierbare `tests/Tools/webdav-library-probe.php` verwendet ausschließlich einen über SAMLA_DAV_PROBE_AUTOLOAD angegebenen isolierten Autoloader. Unter PHP 8.4.5 und PHP 8.5.4 erfolgreich, Warnungen/Deprecations werden als Exceptions behandelt:

- PROPFIND mit Depth 1, Multi-Status und virtuellem Dateieintrag.
- GET und OPTIONS mit angekündigter DAV-Locking-Unterstützung.
- Eigener Basic-Adapter mit synthetischen Zugangsdaten; falsche Daten werden abgewiesen.
- Exklusives LOCK, verweigertes PUT ohne Token, erfolgreiches PUT mit Token und UNLOCK.

Jede Anfrage bekommt wie unter PHP-FPM einen neuen Server und Auth-Plugin. Der Test verwendet einen virtuellen Speicher und einen ausschließlich im Test gehaltenen Lock-Backend. Er beweist noch keine dauerhafte PDO-Sperre, keine Windows-/macOS-Clientkompatibilität und keine Samla-Autorisierung. Der Anwendungs-composer.json, composer.lock, vendor und die Webrouten wurden nicht um SabreDAV erweitert.

## Nach Freigabe

App-Passwörter werden ausschließlich nach OIDC-Anmeldung erzeugt, nur einmal angezeigt, sicher gehasht, einzeln widerrufbar und begrenzt gültig. Ein IdP-Passwort wird weder gespeichert noch für WebDAV weitergereicht. Jede DAV-Anfrage prüft aktiven Benutzer, gültige Gruppenfrische und konkrete Bereichsrechte. Die bestehende Nicht-Cache-Regel bei Rechteentzug bleibt erhalten. WebDAV-Browsergrenzen, Pfaddekodierung, XML-Größen/Entities, Destination, If-/ETag-/Lock-Token, Quoten und Konflikte benötigen eigene Integrationstests. Anschließend Referenzclients unter Windows, macOS und mobilen Geräten prüfen.

Die Projektregel in AGENTS.md lautet: „Keine zusätzlichen Laufzeitkomponenten ohne Abstimmung einführen.“ Die bisherige Freigabe betraf konkret die OIDC-Bibliothek. Daher braucht diese zusätzliche Bibliothek eine gesonderte Freigabe, bevor sie in Samla eingebaut wird.

## Betreiberfreigabe

Die Anweisung „nimm die abhängigkeit mit auf“ gibt die konkrete SabreDAV-Abhängigkeit einschließlich benötigter transitiver Pakete frei. Die zusätzliche gemeinsame Auflösung mit Samlas bestehendem Lockfile erfordert sechs neue Pakete, keine Updates oder Entfernungen bestehender Pakete, und meldet keine bekannten Advisories. Die bestehende Anwendung ist zum Abschluss von Iteration 14 noch unverändert; die folgende Integrationsphase setzt die Freigabe um.

## Umsetzung Iteration 15

Die Anwendung nimmt sabre/dav 4.7.1, sabre/event 5.1.9, sabre/http 5.1.13, sabre/uri 2.3.4, sabre/vobject 4.6.1 und sabre/xml 2.2.11 auf. Keine bestehenden Pakete aktualisiert oder entfernt. cURL, DOM, SimpleXML, XMLReader und XMLWriter sind ausdrücklich im Plattformvertrag genannt. Der Protokollprüfer besteht auch mit dem tatsächlichen Anwendungs-Autoloader. App-Passwortverwaltung und deren persistente Migration sind implementiert. Der WebDAV-Endpunkt und dauerhafte gemeinsame Sperren sind damit noch nicht angeschlossen.
