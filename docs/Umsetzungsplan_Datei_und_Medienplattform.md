# Umsetzungsplan: Samla

Version: 1.52
Stand: 23. Oktober 2026
Grundlage: Konzept_Datei_und_Medienplattform.md, Version 1.3
Status: Umsetzung begonnen, keine pauschale zusätzliche Komponentenfreigabe

Aktueller Stand: Iteration 40, Katalogsuche mit Beschreibungen und Tags, siehe Abschnitt 52. Repository: https://github.com/JRTools/samla

Aktuelle vollständige Betreiberentscheidungen: [offene-entscheidungen.md](offene-entscheidungen.md). Bereits entschiedene Punkte sind darin ausdrücklich als erledigt gekennzeichnet.

## 1. Ziel und Vorgehen

Das Konzept bildet die Grundlage für die Umsetzung. Die größten Risiken liegen in der konsistenten Dateispeicherung, dem Rechteentzug und den Plugin-Verträgen. Diese Punkte werden zuerst durch einen kleinen, vollständig getesteten Durchstich abgesichert.

Das technische Fundament der Versionierung wird gegenüber der bisherigen Roadmap vorgezogen: Unveränderliche Inhaltsstände und konfliktgeschützte Änderungen gehören bereits in den ersten Dateispeicher. Historienansicht und Editor folgen später. Eine nachträglich ergänzte Versionierung würde Uploads, WebDAV, Quoten und Fehlerbehandlung erneut verändern.

Die Entwicklung erfolgt in vertikalen Arbeitspaketen. Jedes Paket liefert einen vollständigen Ablauf einschließlich benötigter Oberfläche, Autorisierung, Erweiterungspunkten, Tests und Dokumentation. Der Plan setzt keine zusätzlichen Komponenten als freigegeben voraus.

## 2. Planungsgrundlage

Entsprechend den Annahmen des Konzepts wird mit einer einzelnen Installation, lokalem Dateispeicher außerhalb des Webverzeichnisses und einer gemeinsamen Anwendung für Browser und WebDAV geplant. Diese Annahmen müssen im ersten Arbeitspaket bestätigt werden.

PHP 8.4 ist die vorgesehene Mindestversion; PHP 8.5 wird zusätzlich geprüft. MariaDB 11.8 oder MySQL 8.0 sind als verfügbare Datenbanken bestätigt. Beide Zielplattformen sind inzwischen mit echten Datenbanken geprüft; die aktuelle Matrix steht im Prüfprotokoll und im jeweils neuesten Iterationsabschnitt. Composer, JavaScript, GD und PHPUnit bilden die weitere bestätigte Grundlage. Weitere Bibliotheken, Erweiterungen und Betriebsanforderungen werden vor ihrem Einsatz abgestimmt.

## 3. Arbeitspakete und Reihenfolge

| Paket | Umsetzung und Ergebnis | Voraussetzung und Abnahme |
|---|---|---|
| **AP 0: Technische Entscheidungen** | Hosting prüfen, OIDC-Vertrag klären, Referenzclients und Betriebsgrenzen festlegen. Komponentenentscheidung für Routing, Templates, OIDC, WebDAV und Hintergrundaufgaben vorbereiten. | Dokumentierte Entscheidungen und Freigabe benötigter Komponenten. Keine offene Annahme verhindert den Durchstich. |
| **AP 1: Projektfundament** | Composer-Struktur, Konfiguration, Fehlerbehandlung, Migrationen, PHPUnit, isolierte Testdatenbank und temporären Testdateispeicher einrichten. Plugin-Manifest, Registrierung und öffentliche Verträge beginnen. | Installation und Tests sind reproduzierbar. Ein inkompatibles Pflichtplugin verhindert geschützte Zugriffe. |
| **AP 2: Durchstich mit RBAC** | OIDC-Anmeldung, validiertes Gruppenmapping, RBAC-Pflichtplugin und ein geschützter Speicherbereich. Eine kleine Datei über Browser und WebDAV bereitstellen. App-Passwort erstellen und widerrufen. | Erlaubter und verweigerter Zugriff funktionieren mit echtem Testprovider und Referenzclient. Ein Referenzplugin erweitert die Anwendung ohne Kernänderung. |
| **AP 3: Identität und Verwaltung** | Benutzerstatus, lokale Gruppen, Mapping, Rollen, Zuweisungen, Delegation, effektive Rechte und Sitzungswiderruf. Erstadministration und Wiederherstellung eines Administrationszugangs. | Rechteherkunft ist erklärbar. Lokale Sperren greifen über beide Zugangswege. Mittelbare Rechteausweitung über Gruppen und Mapping ist verhindert. |
| **AP 4: Zuverlässiger Dateispeicher** | Logischer Dateibaum, Upload, Download, Ordner, Umbenennen, Kopieren, Verschieben, Papierkorb und Quoten. Unveränderliche Inhaltsversionen, Schreibkonflikte, Outbox und Konsistenzprüfung integrieren. | Browser und WebDAV verwenden dieselben Dienste. Abbrüche, Parallelzugriffe und Datenbankfehler beschädigen keine bestehenden Dateien. |
| **AP 5: Medienbibliothek** | Raster, Vorschauen, Viewer, Tags, Suche und Feldregistrierung. Metadatenprovider und KI-Kennzeichnungen schrittweise ergänzen. | Dateiversion, Quelle und Auslesestatus bleiben zuordenbar. Sensible Felder und Ableitungen sind geschützt. Nicht unterstützte Formate bleiben herunterladbar. |
| **AP 6: Redaktion und Bearbeitung** | Medienrechte, Sammlungen, Referenzen, Versionshistorie, Wiederherstellung und Texteditor. | Sammlungen verraten keine fremden Inhalte. Referenzen werden bei Löschung berücksichtigt. Editor und WebDAV überschreiben keine konkurrierenden Änderungen still. |
| **AP 7: Export und Betriebsfreigabe** | Portablen Export, Installationsprüfung, Aufbewahrung, Update, Backup, Restore und Betriebsdokumentation abschließen. Clienttests und Lastprüfungen durchführen. | Reproduzierbare Installation, erfolgreiches Update und geprüfter Restore einschließlich Plugin-Daten. Erst danach Produktivfreigabe. |

Nach AP 4 steht eine erste nutzbare Dateiplattform für den Testbetrieb zur Verfügung. Die Medienfunktionen bauen anschließend auf einem bereits abgesicherten Speicher auf.

## 4. Architektur konkretisieren

| Schicht | Aufgabe |
|---|---|
| Öffentliche Verträge | Versionierte Plugin-Schnittstellen, typisierte Kontexte, Berechtigungskennungen und Ereignisse |
| Fachlogik | Regeln für Dateien, Quoten, Mitgliedschaften, Versionen und Metadaten |
| Anwendungsdienste | Vollständige Abläufe mit Autorisierung, Validierung, Transaktionen und Abschlussereignissen |
| Infrastruktur | Datenbank, Dateispeicher, OIDC, Sitzungen und Aufgabenverarbeitung |
| Zugangsadapter | HTTP-Oberfläche und WebDAV |
| Mitgelieferte Plugins | RBAC, Extraktoren, Medienrechte, Sammlungen und Exportbeiträge |

Drei Regeln werden durch Architekturtests abgesichert:

1. HTTP und WebDAV führen keine eigenen fachlichen Dateioperationen aus.
2. Der Kern importiert keine internen RBAC-Klassen und liest keine RBAC-Tabellen.
3. Mitgelieferte Plugins verwenden dieselben öffentlichen Verträge wie zusätzliche Plugins.

Die Plugin-API wächst mit den tatsächlichen Anwendungsfällen. Zu Beginn werden belastbare Verträge für Autorisierung, Validierung, Ereignisse, Provider und UI-Beiträge benötigt. Der gesamte spätere Hook-Katalog wird nicht vorab vollständig implementiert. Jeder neu umgesetzte fachliche Anwendungsfall erhält jedoch unmittelbar seine dokumentierten Erweiterungspunkte und Vertragstests.

## 5. Dateispeicherung und Fehlerbehandlung

Für AP 4 entsteht ein dokumentiertes Zustandsmodell. Der vorgesehene Ablauf lautet:

1. Upload in eine temporäre Datei streamen und dabei Größe sowie Prüfsumme ermitteln.
2. Rechte, Zielzustand und Quote prüfen. Verbindungsabbrüche dürfen keine dauerhafte Quotenreservierung hinterlassen.
3. Den unveränderlichen Inhalt innerhalb desselben Dateisystems atomar bereitstellen.
4. Dateiversion, aktuellen Dateiverweis, Quotenänderung und Outbox-Ereignis gemeinsam in der Datenbank festschreiben.
5. Nicht referenzierte Inhalte und abgebrochene Vorgänge über eine wiederholbare Bereinigung behandeln.

Unmittelbar vor dem Festschreiben werden veränderliche Voraussetzungen unter geeigneten Sperren erneut geprüft. Dazu gehören Rechte, Quote und erwarteter Dateistand.

Ein Fehler nach Bereitstellung des Inhalts darf höchstens einen bereinigbaren, nicht referenzierten Inhaltsstand erzeugen. Er darf keinen Datenbankverweis auf eine fehlende Datei hinterlassen. Diese Fehlerfenster werden durch Integrationstests abgesichert.

Die Outbox wird von Beginn an berücksichtigt. Eine zuverlässige asynchrone Verarbeitung wird erst zugesagt, wenn der Verarbeitungsmechanismus abgestimmt und umgesetzt ist. Abschlussereignisse werden mindestens einmal zugestellt; Handler müssen anhand der Ereigniskennung idempotent arbeiten.

## 6. Medienfunktionen schrittweise liefern

| Schritt | Inhalt |
|---|---|
| **5a: Medienansicht** | Raster, Pagination, geschützte GD-Vorschauen, Orientierung, Bildansicht und technische Basisdaten |
| **5b: Katalog** | Beschreibungen, Tags, Filter, Suche und registrierbare Metadatenfelder |
| **5c: Extraktion** | Freigegebene EXIF-, IPTC-, XMP- und formatspezifische Provider mit dokumentierter Formatmatrix |
| **5d: Herkunft** | KI-Deklarationen, widersprüchliche Angaben, sensible Felder und getrennte Nachweiszustände |

C2PA-Validierung erhält ein eigenes optionales Arbeitspaket nach Komponentenfreigabe. Sie blockiert die erste Medienbibliothek nicht. Ohne geeigneten Validator bleibt ein vorhandener Nachweis ausdrücklich ungeprüft.

Ausgelesene Angaben bleiben an die Dateiversion gebunden. Manuelle Ergänzungen werden getrennt behandelt und durch erneute Extraktion nicht still überschrieben. Fehlende KI-Kennzeichnungen erlauben keine Einstufung als sicher ohne KI entstanden.

## 7. Tests und Qualitätskriterien

Die im Konzept genannten 85 Prozent Line Coverage sind ein ergänzender Indikator. Entscheidend sind nachgewiesene Eigenschaften:

| Bereich | Verbindlicher Nachweis |
|---|---|
| Autorisierung | Jeder geschützte Anwendungsfall besitzt positive und negative Tests, einschließlich direkter URLs und WebDAV. |
| Erweiterbarkeit | Referenzplugin registriert Validator, Provider, Metadatenfeld und UI-Beitrag ohne Änderung am Kern. |
| Konsistenz | Gezielte Fehler an den Übergängen zwischen temporärem Inhalt, Dateibereitstellung und Datenbankabschluss. |
| Parallelität | Konkurrierende Uploads, Quotenverbrauch, Überschreiben und Rollenänderungen werden getestet. |
| Rechteentzug | Sperren, Widerruf und veraltete Gruppenstände gelten auch für App-Zugänge. |
| Metadaten | Kontrollierte Testdateien prüfen Herkunft, Versionswechsel, Parserfehler und Schutz manueller Ergänzungen. |
| Betrieb | Migration, Update und Restore werden mit repräsentativen Daten geprüft. |

Die PHPUnit-Matrix läuft unter PHP 8.4 und 8.5. Die Datenbankmatrix umfasst MariaDB 11.8 und MySQL 8.0. Migrationen und Integrationstests müssen gegen beide echten Datenbanksysteme erfolgreich laufen, bevor die jeweilige Unterstützung als nachgewiesen gilt. SQLite ersetzt diese Kompatibilitätsprüfung nicht.

Jedes Arbeitspaket umfasst relevante Unit-, Integrations-, HTTP- und Plugin-Vertragstests. WebDAV-Tests werden mit den jeweiligen Operationen ergänzt. Browserautomatisierung und Coverage-Messung setzen die Freigabe geeigneter Werkzeuge voraus. Bis dahin werden UI-Prüfungen dokumentiert durchgeführt.

Eine Funktion ist erst abgeschlossen, wenn ihre Akzeptanzkriterien erfüllt, relevante Tests erfolgreich, alle Zugangswege autorisiert, Fehlerfälle behandelt und Erweiterungsverträge sowie Dokumentation aktualisiert sind. Schwerwiegende Sicherheits- oder Datenintegritätsfehler verhindern die Freigabe.

## 8. Erste konkrete Lieferung

AP 0 bis AP 2 bilden die erste gemeinsame Lieferung. Ihr Ergebnis ist klein, aber architektonisch aussagekräftig:

- Anmeldung über den vorhandenen OIDC-Provider.
- Mapping einer externen Gruppe auf eine interne Gruppe.
- Rollenzuweisung für genau einen Speicherbereich.
- Upload und Download einer kleinen Datei im Browser.
- Zugriff auf denselben Bestand über einen WebDAV-Referenzclient.
- Nachweis von Zugriffssperre und App-Passwort-Widerruf.
- RBAC und Referenzplugin ausschließlich über öffentliche Verträge.
- Automatisierte Tests für diese Abläufe.

Dafür werden zunächst benötigt:

1. Hostingdaten einschließlich Webserver, PHP-Erweiterungen und Limits sowie isolierte Testzugänge für MariaDB 11.8 und MySQL 8.0.
2. OIDC-Discovery, Testzugang und ein anonymisiertes Claim-Beispiel.
3. Ein gewünschter WebDAV-Referenzclient.
4. Die Entscheidung zu PHP-CLI und Cron beziehungsweise einer Alternative für Hintergrundaufgaben.
5. Eine verbindliche maximal erlaubte Verzögerung zentraler Gruppenänderungen.

Der letzte Punkt beeinflusst insbesondere die Gültigkeit von Sitzungen und App-Zugängen. OIDC allein stellt keine fortlaufende Gruppensynchronisierung bereit.

## 9. Meilensteine und Aufwandsschätzung

| Meilenstein | Zeitpunkt | Nachgewiesenes Ergebnis |
|---|---|---|
| **M1: Architektur bestätigt** | Nach AP 2 | OIDC, Gruppenmapping, RBAC, Browser, WebDAV und öffentliche Erweiterungsverträge funktionieren zusammen. |
| **M2: Dateispeicher im Testbetrieb** | Nach AP 4 | Gemeinsamer Datenbestand, zuverlässige Dateioperationen, Versionsfundament, Quoten und Fehlerbehandlung. |
| **M3: Medien- und Redaktionsumfang vollständig** | Nach AP 6 | Medienbibliothek, freigegebene Extraktoren, Herkunftsangaben, Medienrechte, Sammlungen, Historie und Editor. |
| **M4: Betriebsfreigabe** | Nach AP 7 | Installation, Update, Export, Clientkompatibilität und Wiederherstellung sind geprüft. |

Belastbare Aufwandsschätzungen und Kalendertermine werden nach M1 erstellt. Dann sind Providerintegration, Hostinggrenzen und WebDAV-Verhalten praktisch geprüft. Verfügbare Entwicklungskapazität und bestätigter Funktionsumfang bilden die weitere Schätzgrundlage.


## 10. Umsetzungsstand: Iteration 1

Stand: 21. Oktober 2026. Die Umsetzung wurde begonnen. Kein Arbeitspaket wird aufgrund eines Teilgerüsts als vollständig abgenommen markiert.

| Paket | Status | Tatsächlicher Stand |
|---|---|---|
| AP 0 | In Bearbeitung | Arbeitsumgebung geprüft. Datenbankversionen MariaDB 11.8 und MySQL 8.0 sind bestätigt. Weitere Hostingdetails, OIDC-Vertrag, Clientauswahl und Betriebsgrenzen bleiben offen. Zusätzliche Laufzeitbibliotheken wurden nicht eingeführt. |
| AP 1 | Teilweise umgesetzt | Composer-Projektstruktur, PSR-4-Namensräume, PHPUnit-Konfiguration, öffentliche Plugin-Verträge, atomare Registrierung, API-Kompatibilitätsprüfung und lokale Fähigkeitsdiagnose vorhanden. Konfigurationssystem, Migrationen, Datenbankintegration und Ziel-Testmatrix fehlen noch. |
| AP 2 | Teilweise umgesetzt | Zentraler Autorisierungsdienst und RBAC-Pflichtplugin als testbare Fachlogik vorhanden. Referenzplugin belegt Registrierung von Berechtigungen und zusätzlichen Einschränkungen. OIDC, HTTP, WebDAV, App-Passwörter und Dateitransfer fehlen noch. |
| AP 3 | Fachlogik begonnen | Strenges Gruppenclaim-Mapping mit mehreren Quellen und getrennten manuellen Mitgliedschaften als reine Logik umgesetzt. Keine persistente Verwaltung oder Oberfläche vorhanden. |
| AP 4 bis AP 7 | Offen | Keine Dateioperationen, Medienverarbeitung oder Betriebsfreigabe implementiert. |

### 10.1 Implementierte Bausteine

- Composer-Projekt mit getrennten Namensräumen für Kern, öffentliche Plugin-API und RBAC-Plugin.
- Unveränderliche Autorisierungsanfragen und Ergebnisse mit expliziten Geltungsbereichen.
- Berechtigungsregistrierung mit Eigentümerprüfung und Ablehnung doppelter Kennungen.
- Expliziter Plugin-Aufbau mit Prüfung eindeutiger IDs und passender API-Major-Version.
- Geschlossene Registrierung nach erfolgreichem Aufbau, auch für zurückbehaltene Registrierungsobjekte.
- Obligatorischer RBAC-Provider: fehlender Provider, unbekannte Aktion, unpassender Scope, nicht anwendbare Entscheidung und Ausnahmen führen zur Ablehnung.
- Weitere Plugins können den Zugriff zusätzlich einschränken. Eine RBAC-Ablehnung bleibt verbindlich.
- Deterministische Reihenfolge zusätzlicher Einschränkungen: Priorität, Plugin-ID, Registrierungsreihenfolge.
- RBAC-Rollenvereinigung innerhalb des passenden Speicherbereichs. Globale Rollen verleihen keine impliziten Bereichsrechte.
- Keine positiven Rechte-Caches in dieser Iteration. Aktueller Identitätsstatus und Mitgliedschaften werden bei jeder Entscheidung abgefragt.
- Gruppenmapping mit exaktem Vergleich, 1:n-Zuordnungen, mehreren Quellen, nachvollziehbarer Herkunft und getrennten manuellen Mitgliedschaften.
- Begrenztes Claim-Schema: höchstens 1.000 Gruppenkennungen und höchstens 256 Bytes je Kennung. Dies sind vorläufige Implementierungsgrenzen und vor Providerintegration zu bestätigen.
- Architekturtests gegen direkte Abhängigkeiten des Kerns von RBAC-Interna und gegen interne Abhängigkeiten des Referenzplugins.
- README, Dokumentation der vorläufigen Plugin-Verträge, Entscheidungsübersicht und Prüfprotokoll.

### 10.2 Prüfergebnisse und Grenzen

| Prüfung | Ergebnis |
|---|---|
| PHP-Syntax | Alle 32 PHP-Dateien erfolgreich geprüft. |
| Automatisierte Vorabtests | 37 Tests, 68 Assertions, erfolgreich. |
| Tatsächliche lokale Testlaufzeit | PHP 8.3.6 mit PHPUnit 9.6.17, isoliert für die Vorabprüfung bereitgestellt. |
| Geplante Entwicklungsabhängigkeit | PHPUnit 13 gemäß composer.json; Installation und Lockdatei stehen noch aus. |
| Zielmatrix PHP 8.4 und 8.5 | Noch nicht ausgeführt. PHP 8.3 ist keine zugesagte Produktversion. |
| Datenbank-, HTTP-, OIDC- und WebDAV-Integration | Noch nicht implementiert und nicht geprüft. |
| Coverage | Nicht gemessen; kein Coverage-Treiber freigegeben oder eingerichtet. |

In der ursprünglichen Arbeitsumgebung standen PHP und Composer nicht zur Verfügung. Für die Vorabprüfung wurden PHP 8.3 und PHPUnit 9.6 aus verfügbaren Distributionspaketen lokal extrahiert. Diese Prüfumgebung wird nicht mit dem Projekt ausgeliefert und begründet keine zusätzliche Produktionsanforderung. Die Zielmatrix und der Composer-Installationslauf bleiben ausdrückliche Abnahmepunkte.

Die PHPUnit-13-Konfiguration wurde noch nicht mit PHPUnit 13 ausgeführt. Die Vorabtests verwenden dieselben Testklassen und den Test-Bootstrap ohne diese Konfiguration. Die erste Plugin-API ist experimentell (Major 0). Manifestdatei und Referenzplugin ersetzen noch keine vollständige Plugin-Verwaltung. Ereignisse, Dateivalidatoren, UI-Beiträge und Exportbeiträge folgen mit den entsprechenden Anwendungsfällen.

### 10.3 Nächste Umsetzungsschritte

1. Tests unter PHP 8.4 und 8.5 mit PHPUnit 13 ausführen, Composer-Abhängigkeiten auflösen und Lockdatei erzeugen.
2. Konfiguration und Trennung zwischen Entwicklung, Test und Produktion ergänzen.
3. Migrationen, getrennte Testdatenbanken und persistente Identity- und RBAC-Repositories für MariaDB 11.8 und MySQL 8.0 umsetzen. Die Schemaentwicklung darf keine ungeprüften herstellerspezifischen Funktionen voraussetzen.
4. Hosting- und Providerdaten aufnehmen, Gruppenfrische festlegen und konkrete Integrationsbibliotheken zur Freigabe vorlegen.
5. OIDC, App-Passwörter und den ersten geschützten HTTP- und WebDAV-Durchstich ergänzen.
6. Erweiterungsverträge mit Validatoren, Ereignissen, Provider-Auswahl und UI-Beiträgen an den tatsächlich umgesetzten Anwendungsfällen erweitern.

Für den realen Integrationsdurchstich werden weiterhin OIDC-Discovery-URL, Testregistrierung, anonymisierter groups-Claim, Hostingdaten, ein Referenzclient und die zulässige Verzögerung zentraler Gruppenänderungen benötigt. Ohne diese Angaben werden keine Provider- oder Hostingdetails erfunden.

## 11. Fortschreibung des Plans

Nach jeder Implementierungsiteration werden Status je Arbeitspaket, tatsächlich gelieferte Funktionen, ausgeführte Prüfungen, Einschränkungen und nächste Schritte aktualisiert. Abnahmen erfordern die im jeweiligen Paket beschriebenen Nachweise. Ein erfolgreicher Unit-Test ersetzt keine Integrations- oder Betriebsabnahme.

| Version | Änderung |
|---|---|
| 1.0 | Ursprünglicher Umsetzungsplan mit Arbeitspaketen, Architektur, Teststrategie und Meilensteinen. |
| 1.1 | Erste Implementierungsiteration dokumentiert: Plugin- und RBAC-Grundgerüst, Gruppenmapping, 37 erfolgreiche Vorabtests und offene Zielmatrix. |

| 1.2 | Verfügbare Datenbankversionen bestätigt: MariaDB 11.8 und MySQL 8.0. Beide in die Testmatrix aufgenommen; tatsächliche Datenbankprüfungen bleiben offen. |

| 1.3 | Produktname Samla, Konfiguration, Migrationen und persistente Identity-/RBAC-Adapter. Zielmatrix mit PHP 8.4 und 8.5 sowie MySQL 8.0 erfolgreich. README, Prüfprotokoll und Commit-Regel ergänzt. |

## 12. Umsetzungsstand: Iteration 2 (Samla)

### 12.1 Gelieferter Stand

- Produktname Samla; Paketname jrtools/samla und PHP-Namensräume Samla.
- Umgebungsbasierte Konfiguration für Entwicklung, Tests und Produktion ohne neue Laufzeitbibliothek.
- Testmodus ausschließlich mit TEST_DB_*-Variablen, Namenspräfix und Schutz gegen eine identisch konfigurierte Produktivdatenbank. Getrennte Datenbankkonten bleiben erforderlich.
- PDO-Verbindungen mit nativen vorbereiteten Abfragen, striktem SQL-Modus, UTC, utf8mb4 und Prüfung der erwarteten Serverfamilie.
- Öffentlicher SchemaMigration-Vertrag und explizite Kern-/Plugin-Migrationsbeiträge.
- Migrationssperre je Datenbank, versioniertes Ledger mit Prüfsummen und Zuständen running, applied und failed. Keine automatische Wiederholung unvollständiger Migrationen.
- Persistente Benutzer, Gruppen, OIDC-Identitätsschlüssel, externe Gruppenstände, Mappingquellen und getrennte manuelle Mitgliedschaften.
- Persistente Rollen, explizite Rollenberechtigungen und Geltungsbereichszuweisungen im RBAC-Plugin. Kein Zugriff des Plugins auf Identity-Tabellen.
- Transaktionen und Zeilensperren für Benutzer- und Rollenmutationen; unveränderte bestehende Rechte nach getesteten Datenbankfehlern.
- Ohne vereinbarte Gruppenfrische-Richtlinie bleibt der persistente Identitätszugriff gesperrt. Der im Test verwendete Wert ist keine Produktiventscheidung.
- Composer-Abhängigkeiten installiert und durch composer.lock festgeschrieben; PHPUnit 13.4.3.
- Aktualisierte README mit Projektbeschreibung, tatsächlichem Funktionsumfang, Einrichtung, Migrationen und Testbefehlen; ergänzte Datenbank- und Plugin-Dokumentation.

### 12.2 Aktuelle Abnahme und Grenzen

| Prüfung | Ergebnis |
|---|---|
| PHP 8.4.5, PHPUnit 13.4.3, MySQL 8.0.46 | 47 Unit-/Architekturtests und 14 Integrationstests, insgesamt 61 Tests mit 147 Assertions erfolgreich. |
| PHP 8.5.4, PHPUnit 13.4.3, MySQL 8.0.46 | Dieselbe Matrix erfolgreich, ohne Warnungen oder Deprecations. |
| PHP-Syntax | Alle 46 eigenen PHP-Dateien unter beiden PHP-Unterversionen erfolgreich geprüft. |
| Migrations-CLI mit Composer-Autoloader | Unter beiden PHP-Unterversionen erfolgreich gegen das aktuelle Testschema ausgeführt. |
| Composer | Abhängigkeiten installiert, Lockdatei erzeugt; Validierung erfolgreich mit Hinweis auf offene Projektlizenz. |
| MariaDB 11.8 | Implementierungsziel bestätigt, praktischer Test noch offen. |
| HTTP, OIDC und WebDAV | Weiterhin nicht implementiert. |

AP 1 ist um Konfiguration, Migrationen und Datenbank-Testgrundlage erweitert. AP 2 und AP 3 enthalten nun persistente Fachadapter, sind aber ohne vollständigen OIDC-/Datei-Durchstich beziehungsweise autorisierte Verwaltungsdienste nicht abgeschlossen. Die Repository-Schreibmethoden sind private Infrastruktur, keine fertige Benutzer- oder Rollenverwaltung. Delegation, Audit, Gruppenmapping-Administration und Ressourcenexistenz müssen vor ihrer öffentlichen Nutzung ergänzt werden.

Der CLI-Schalter APP_MAINTENANCE ist eine Vorbedingung für Migrationen und noch keine zentrale Wartungssteuerung aller Webprozesse. Das Deployment muss Schreibzugriffe vor Migrationen tatsächlich anhalten. Fehlgeschlagene DDL erfordert kontrollierte Prüfung oder Restore; eine automatische Rückmigration wird nicht zugesagt.

### 12.3 Repository und verbindlicher Arbeitsablauf

Maßgebliches Repository: https://github.com/JRTools/samla. Der Nutzer hat Initialisierung und Veröffentlichung des bestehenden Codes sowie Commits nach jeder Implementierungsphase ausdrücklich beauftragt. README, Umsetzungsplan und relevante Prüfergebnisse werden jeweils mit dem Code aktualisiert und gemeinsam committed. Diese Regel ist zusätzlich in AGENTS.md hinterlegt.

Der GitHub-Zugriff auf das zuvor leere Repository wurde am 21. Oktober 2026 erfolgreich geprüft; main ist als Standardbranch bestätigt und Schreibrechte sind vorhanden. Der Initialisierungs- und Implementierungsstand wird in dieser Phase veröffentlicht. Die tatsächlichen Commitkennungen werden nach dem Schreiben aus dem Repository berichtet.

### 12.4 Nächste Schritte

1. MariaDB-11.8-Integration mit denselben Migrationen und Testklassen nachweisen.
2. Autorisierte Verwaltungsdienste mit Delegationsschutz, Audit und konsistenter Behandlung von Gruppen- und Mappingänderungen ergänzen.
3. OIDC-Providervertrag, Claim-Beispiel und Gruppenfrische-Richtlinie klären; konkrete OIDC-/WebDAV-Bibliotheken zur Freigabe vorlegen.
4. App-Passwörter und ersten geschützten HTTP-/WebDAV-Dateidurchstich umsetzen.
5. Erweiterungsverträge für die neuen Anwendungsfälle unmittelbar ergänzen und testen.

OIDC-Discovery, Testregistrierung, anonymisierter groups-Claim, Hostingdetails, Referenzclient und zulässige Verzögerung zentraler Gruppenänderungen bleiben erforderlich. Projektlizenz und weitere Komponentenentscheidungen sind weiterhin offen.


## 13. Iteration 3: MariaDB-Kompatibilität bestätigt

Am 21. Oktober 2026 wurde die bestehende Matrix zusätzlich gegen MariaDB 11.8.6 ausgeführt. Unter PHP 8.4.5 und PHP 8.5.4 mit PHPUnit 13.4.3 sind jeweils 61 Tests mit 147 Assertions erfolgreich. Alle bisherigen Migrationen, die Migrations-CLI und die Fehler-/Rollback-Prüfungen funktionieren unverändert. Es wurden keine zusätzlichen Laufzeitkomponenten in Samla eingeführt; der Datenbankserver dient nur der isolierten Prüfung.

Die dokumentierten Zielkombinationen umfassen damit beide PHP-Unterversionen mit MySQL 8.0.46 sowie MariaDB 11.8.6. Dies ersetzt keine umfassende Last-, Nebenläufigkeits- oder Betriebsabnahme.

Die vom Nutzer bereitgestellte Discovery-URL https://id.jrtools.org/.well-known/openid-configuration wurde als Integrationsgrundlage dokumentiert. Testregistrierung, Callback-URL, Gruppenclaim und Gruppenfrische-Richtlinie bleiben offen. README, Prüfprotokoll und Entscheidungen wurden aktualisiert. Nächster Implementierungsumfang ist die geschützte, explizit delegierte Rollenzuweisung innerhalb eines Speicherbereichs mit Audit und Erweiterungsvalidierung.

Version 1.4: MariaDB-Nachweis und bekannte OIDC-Discovery ergänzt.


## 14. Iteration 4: Geschützte fachliche Rollenzuweisung

RoleAssignmentService implementiert die ersten öffentlichen Verwaltungsanwendungsfälle: fachliche Rollen innerhalb eines existierenden Speicherbereichs vergeben und entziehen. Jeder Aufruf prüft die vollständige Kern-Autorisierung und eine ausdrückliche Delegationsregel für Akteursgruppe, Rolle und Bereich. Eine gespeicherte Berechtigungsobergrenze verhindert indirekte Ausweitung der Delegation durch Rollenänderungen. Globale und administrative Rollenzuweisungen sind außerhalb dieses ersten Vertrags gesperrt.

Die öffentliche Plugin-API erhält AuthorizationChecker, ResourceDirectory, MutationContext, ChangeValidator und MutationRunner. Validatoren können Änderungen vorab ablehnen und werden in dokumentierter Reihenfolge ausgeführt. Vorbedingungen werden nach Validierung erneut geprüft. Das Referenzplugin verwendet den neuen Vertrag bereits mit einer wirksamen Beispielregel.

Ein gemeinsamer transaktionaler Änderungsablauf serialisiert diese Verwaltungszugriffe und speichert Rollenzuweisung, Audit und bei tatsächlicher Änderung das Abschlussereignis zusammen. Audit-/Outbox-Fehler rollen den gesamten Vorgang zurück. Eine unveränderte Wiederholung ist nachvollziehbar protokolliert und erzeugt kein weiteres Änderungsereignis. Die Outbox persistiert Ereignisse, liefert sie jedoch noch nicht aus.

Neue, zusätzliche Migrationen ergänzen Audit/Outbox und Administrationssperre, einen anfänglichen Speicherbereichskatalog und RBAC-eigene Delegationsdaten. Bereits veröffentlichte Migrationen bleiben unverändert. Kern und RBAC verwenden weiterhin öffentliche Verträge und getrennte Tabellenverantwortung.

### 14.1 Prüfung

In allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 sind jeweils 81 Tests mit 236 Assertions erfolgreich (49 Unit-/Architekturtests und 32 Integrationstests). Die Syntax aller 60 eigenen PHP-Dateien sowie die Migrations-CLI sind ebenfalls geprüft. Die zusätzlichen Tests umfassen Ablehnungen, Delegationsgrenzen, Plugin-Veto, erneute Rechteprüfung, fehlende und konkurrierend gehaltene Administrationssperre sowie Audit-/Outbox-Fehler und Rollback.

### 14.2 Noch offen

Die vollständige Verwaltungsphase ist noch nicht abgeschlossen: globale Delegation, Rollenbearbeitung, Benutzerstatus- und Gruppenverwaltung, Mappingänderungen sowie umfassender Schutz des letzten Administrators und Notfallzugang folgen. Direkte Repository- beziehungsweise SQL-Schreibzugriffe sind interne Einrichtungswerkzeuge; sie sind keine unterstützten parallelen Verwaltungswege. Zukünftige öffentliche Schreibdienste einschließlich OIDC-Synchronisierung müssen in die gemeinsame Sperr-/Transaktionsstrategie eingebunden werden.

Weiterhin offen sind eine Oberfläche, die OIDC-Anmeldung, App-Passwörter, WebDAV und Dateioperationen, Ereigniszustellung sowie die Betriebsabnahme. Der anfängliche Speicherbereichskatalog ist noch kein fertiger Dateispeicher. Bekannte Discovery-Daten sind in oidc-provider.md dokumentiert; Clientregistrierung, Callback-URL, Gruppenclaim und Gruppenfrische bleiben erforderlich. README, API-Handbuch, Datenbankhinweise, Prüfprotokoll und Plan wurden mit dem Code fortgeschrieben.

Version 1.5: Erste geschützte fachliche Rollenzuweisung mit Delegation, Plugin-Validierung und atomarem Audit/Outbox; erfolgreiche Vierfachmatrix dokumentiert.


## 15. Iteration 5: Geschützter Benutzerstatus

UserStatusService ergänzt Sperren und Entsperren mit dem ausdrücklichen globalen Recht `identity.users.status.manage`. IdentityPlugin registriert die Berechtigung; der Dienst nutzt die vollständige zentrale Autorisierung, bestehende Plugin-Validatoren und die gemeinsame Administrationssperre. Benutzerstatus, Autorisierungsversion, Audit und Abschlussereignis werden atomar gespeichert. Unveränderte Wiederholungen erhöhen die Version nicht und erzeugen kein zusätzliches Änderungsereignis. Gruppendaten und Mitgliedschaften bleiben erhalten; Entsperren erneuert keine veralteten Claims.

Nach der Änderung muss mindestens ein aktuell aktiver Benutzer mit frischen Gruppendaten und wirksamem globalem Statusverwaltungsrecht verbleiben. Dabei gelten auch zusätzliche Plugin-Einschränkungen. Eine unzulässige Selbstsperre wird samt Version und Protokolleinträgen zurückgerollt. Der Kern greift weiterhin ausschließlich über öffentliche Autorisierungsverträge auf Berechtigungsentscheidungen zu und liest keine RBAC-Tabellen.

### 15.1 Prüfung

Die Vierfachmatrix aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 ist mit echten Datenbanken erfolgreich: jeweils 97 Tests mit 298 Assertions (49 Unit-/Architekturtests, 48 Integrationstests). Alle 63 eigenen PHP-Dateien und die Migrations-CLI sind ebenfalls geprüft. Die Ergebnisse stehen in verification.md. Neue Integrationsfälle prüfen Berechtigungsfehler, gesperrte und veraltete Akteure, letzten nutzbaren Statusadministrator, zulässige Selbstsperre mit verbleibendem Zugang, Plugin-Veto und erneute Rechteprüfung, Outbox-Rollback, idempotente Wiederholung, unbekanntes Ziel sowie unveränderte Gruppenfrische und Mitgliedschaften.

### 15.2 Nächste Umsetzung

1. Manuelle Gruppenmitgliedschaften mit explizitem Delegationsschutz für sämtliche wirksamen Rollen der Zielgruppe ergänzen. Änderungen dürfen kein indirektes Vergeben administrativer Rechte ermöglichen.
2. Nachbedingungen für Gruppen-, Mapping- und spätere globale Rollenänderungen auf den verbleibenden Administrationszugang ausweiten. Ein getesteter Notfallzugang bleibt ein eigenes Arbeitspaket.
3. OIDC-Registrierung, Callback, Beispielclaim und Gruppenfrische-Richtlinie klären; zusätzliche Integrationsbibliotheken konkret zur Entscheidung vorlegen.
4. Geschützte HTTP-Zugänge, App-Passwörter und Dateidurchstich mit Browser/WebDAV sowie Outbox-Zustellung anschließen.

Der Schutz in dieser Iteration gilt für Statusänderungen und das Recht zur Statusverwaltung. Er garantiert weder einen zukünftigen Provider-Login noch unbegrenzte Gruppenfrische oder die gesamte Administrationsfähigkeit. Es gibt weiterhin keine produktive Oberfläche, OIDC-Anmeldung oder Dateioperationen. README, Verwaltungs- und API-Vertrag, Prüfprotokoll und Plan werden zusammen mit dem Code veröffentlicht.

Version 1.6: Geschützter Benutzerstatus, atomare Ereignisse und Schutz des letzten aktuell nutzbaren Statusverwaltungszugangs.


## 16. Iteration 6: Geschützte manuelle Gruppenmitgliedschaften

GroupMembershipService implementiert Hinzufügen und Entfernen manueller Mitgliedschaften. Jeder Aufruf benötigt das ausdrückliche globale Mitgliedschaftsverwaltungsrecht und eine positive zusätzliche GroupMembershipPolicy. Das RBAC-Plugin prüft dabei jede Rolle der Zielgruppe in jedem zugewiesenen Speicherbereich, das vollständige bereichsbezogene Verwaltungsrecht sowie die ausdrückliche Delegation. Vergaben müssen die bestehenden Berechtigungsobergrenzen einhalten; Rollenwachstum erweitert die Delegation nicht. Globale Rollengruppen und Gruppen mit administrativen RBAC-Rollen sind in diesem ersten Vertrag vollständig von Änderungen ausgeschlossen.

Die experimentelle Plugin-API 0.3 ergänzt den verpflichtenden öffentlichen Policy-Vertrag. Der Kern liest weiterhin keine RBAC-Tabellen und importiert keine RBAC-Interna. Vorbedingungen werden nach Plugin-Validierung erneut geprüft, sodass zwischenzeitlich zusätzliche Rollen der Zielgruppe die Änderung verhindern können.

Manuelle und externe Quellen bleiben getrennt. Der Entzug einer manuellen Quelle löscht keine extern bestätigte Mitgliedschaft; bestehende Providerquellen können den effektiven Zugang daher erhalten. Die Änderung bestätigt keine veralteten Claims und entsperrt keinen Benutzer. Version, Mitgliedschaft, Audit und persistiertes Abschlussereignis werden atomar geändert. Unveränderte Wiederholungen erhalten ein Audit ohne Versionsanstieg oder weiteres Änderungsereignis.

Die gemeinsame Nachbedingung verlangt nach Mitgliedschaftsänderungen jeweils mindestens einen aktuell nutzbaren Zugang zur Statusverwaltung und zur Mitgliedschaftsverwaltung. Sie zählt nur vollständige zentrale Permits einschließlich Plugin-Einschränkungen. Der Benutzerstatusdienst erhält ebenfalls den Schutz des letzten zuvor nutzbaren Mitgliedschaftsadministrators. Ohne bereits eingerichtete Mitgliedschaftsverwaltung bleibt der frühere Statusdienst verwendbar. Ein vollständiger Notfallzugang und die zukünftige Provider-Verfügbarkeit sind damit noch nicht umgesetzt.

### 16.1 Prüfung

Auf allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 sind jeweils 123 Tests mit 436 Assertions erfolgreich (49 Unit-/Architekturtests, 74 Integrationstests). Die Syntax aller 68 eigenen PHP-Dateien sowie die Migrations-CLI sind ebenfalls geprüft. 26 zusätzliche Integrationstests belegen Delegationsgrenzen über mehrere Bereiche und Rollen, administrative Gruppen, Quellenprovenienz, Versionsverhalten, Plugin-Veto und Nachprüfung, Schutz beider Administrationsfähigkeiten sowie vollständiges Rollback bei Audit-/Outbox-Fehlern. Bereits veröffentlichte Migrationen und Laufzeitabhängigkeiten bleiben unverändert.

### 16.2 Nächste Umsetzung und Grenzen

1. Geschützte Gruppenstammdaten und anschließend Mappingänderungen ergänzen, mit vollständiger Betrachtung ihrer Auswirkung auf Mitgliedschaften und verbleibende Administrationsfähigkeit.
2. Administrative Rollenbearbeitung und globale Delegation mit ausdrücklich definiertem Notfallzugang vervollständigen.
3. OIDC-Clientregistrierung, Samla-Callback, tatsächlichen Gruppenclaim und Gruppenfrische festlegen; Auswahl zusätzlicher Integrationsbibliotheken zur Entscheidung vorlegen.
4. Geschützte HTTP-Zugänge, App-Passwörter, ersten Dateidurchstich und WebDAV anschließen; Outbox-Zustellung ergänzen.

Die Funktionen liegen als getestete Anwendungsdienste vor. Gruppenstammdaten, Mappings, OIDC-Synchronisierung, Oberfläche und produktive Dateioperationen sind weiterhin offen. Private Repository- und SQL-Writes dürfen nicht als parallele öffentliche Verwaltungswege verwendet werden. Alle späteren Dienste müssen dieselbe Sperrstrategie und ihre eigenen Nachbedingungen einhalten. README, Verwaltungs- und API-Dokumentation, Prüfprotokoll und dieser Plan werden mit der Implementierung veröffentlicht.

Version 1.7: Manuelle Gruppenmitgliedschaften mit vollständiger fachlicher Delegationsprüfung und Schutz der aktuell nutzbaren Status- und Mitgliedschaftsverwaltung; erfolgreiche Vierfachmatrix.


## 17. Iteration 7: Geschützte Gruppenstammdaten

GroupDetailsService implementiert Anlegen und Umbenennen interner Gruppen mit dem neuen globalen Recht `identity.groups.details.manage`. Neue Kennungen werden vom Dienst zufällig erzeugt; eine alte Kennung kann über diesen Anwendungsfall nicht wiederverwendet werden. Anzeigenamen dürfen mehrfach vorkommen. Anlegen vergibt keine Mitgliedschaften oder Rollen. Umbenennen erhält Kennung, Rollen, Mappings, manuelle und externe Mitgliedschaftsquellen sowie die Identitätsversionen und Bestätigungszeitpunkte.

Eine Umbenennung verlangt den erwarteten bisherigen Namen. Der Writer vergleicht diesen unter Zeilensperre bytegenau; ein abweichender Zustand führt zu MutationConflict und wird nicht überschrieben. Ein exakter No-op erhält nur Audit ohne weiteres Änderungsereignis. Ein Namensvergleich ersetzt keine allgemeine Revision und erkennt eine zwischenzeitliche Änderung mit Rückkehr zum ursprünglichen Namen nicht.

Die experimentelle Plugin-API 0.4 ergänzt GroupDetailsChange sowie MutationConflict und einen strikt zugeordneten Namensvorschlag im MutationContext. Plugins können gültige UTF-8-Namen mit bis zu 255 Codepoints und ohne Steuerzeichen beziehungsweise umgebenden Leerraum vor dem Write prüfen und ablehnen. Der Vorschlag ist unveränderlich. Audit und Outbox enthalten weiterhin Kennungen, keine automatisch kopierten Namensdaten.

Der gemeinsame AdministrationAccessGuard erfasst vor den Validatoren alle aktuell nutzbaren Fähigkeiten aus Status-, Mitgliedschafts- und Gruppenstammdatenverwaltung und verlangt sie nach der Änderung weiterhin. Die Prüfung verwendet vollständige Permits einschließlich Gruppenfrische und Plugin-Einschränkungen. Sie gilt jetzt auch für Status- und Mitgliedschaftsdienst, sodass diese den letzten eingerichteten Stammdatenadministrator ebenfalls nicht entfernen dürfen. Noch nicht eingerichtete Fähigkeiten blockieren die Stammdatenverwaltung nicht; die zusätzliche Statusverwaltungsnachbedingung des Mitgliedschaftsdienstes bleibt bestehen.

### 17.1 Prüfung

Auf allen vier Kombinationen aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 sind jeweils 164 Tests mit 547 Assertions erfolgreich (69 Unit-/Architekturtests und 95 Integrationstests). Die Syntax aller 73 eigenen PHP-Dateien sowie die Migrations-CLI sind ebenfalls geprüft. 20 zusätzliche Unitfälle prüfen Unicode-Grenzen, ungültige Eingaben, Unveränderlichkeit und korrekt zugeordnete Erweiterungskontexte. 21 zusätzliche Integrationsfälle prüfen den vollständigen Stammdatendienst sowie den erweiterten Schutz in Status- und Mitgliedschaftsverwaltung. Migrationen und Laufzeitabhängigkeiten bleiben unverändert.

### 17.2 Nächste Umsetzung und Grenzen

1. Mappingänderungen als eigenen geschützten Anwendungsfall entwerfen: Auswirkungen auf bereits bestätigte Gruppenstände, Berechtigungsdelegation, Quellenprovenienz und verbleibende Administrationsfähigkeit konsistent behandeln.
2. Gruppenlöschung mit expliziter Prüfung aller modulübergreifenden Referenzen und Regeln zur Kennungswiederverwendung ergänzen. Einfaches Kaskadenlöschen ist kein öffentlicher Anwendungsfall.
3. Rollenbearbeitung, globale Delegation und Notfallzugang vervollständigen. Die bisherigen Schutzprüfungen sind Momentaufnahmen und ersetzen keine Betriebsregeln für Provider-Ausfall oder Gruppenablauf.
4. OIDC-Registrierung, Callback, Gruppenclaim und Frische-Richtlinie klären; Integrationsbibliotheken zur Entscheidung vorlegen und anschließend HTTP-Zugänge, App-Passwörter, Dateidurchstich und WebDAV anschließen.

Die Funktionen sind als getestete Anwendungsdienste vorhanden. Eine produktive Oberfläche, OIDC-Anmeldung und Dateioperationen fehlen weiterhin. Die Outbox speichert Ereignisse und stellt sie noch nicht zu. Eine wiederholte Gruppenanlage erzeugt jeweils eine neue Gruppe; automatische HTTP-Retries benötigen später einen zusätzlichen Idempotenzvertrag. README, API- und Verwaltungsdokumentation, Prüfprotokoll und Plan werden gemeinsam mit dem Code veröffentlicht.

Version 1.8: Geschützte Gruppenanlage und konfliktgeprüfte Umbenennung, typisierte Namensvalidierung für Plugins und erweiterter Schutz verbleibender Verwaltungsfähigkeiten; erfolgreiche Vierfachmatrix.


## 18. Iteration 8: Geschützte Mappingkanten und sofortiger Quellenabgleich

GroupMappingService ergänzt Vergabe und Entzug einzelner externer Mappingkanten mit dem globalen Recht `identity.groups.mappings.manage` und einer verpflichtenden GroupMappingPolicy. Das RBAC-Plugin prüft sämtliche Zielrollen in sämtlichen Bereichen einschließlich Delegationsobergrenzen. Die Prüfung gilt auch vor der ersten passenden Anmeldung und ohne aktuelle Mitglieder. Globale und administrative RBAC-Gruppen bleiben von diesem ersten Vertrag ausgeschlossen.

Mappingkante und bestehende abgeleitete Mitgliedschaftsquellen werden unter der gemeinsamen Administrationssperre atomar abgeglichen. Maßgeblich sind gespeicherte verifizierte Gruppensichten des exakt passenden Issuers. Andere Quellen und manuelle Mitgliedschaften bleiben erhalten. Versionen erhöhen sich nur bei tatsächlich veränderten Quellenzeilen. Lokale Sperren und Bestätigungszeitpunkte bleiben unverändert. Der interne Snapshotwriter verwendet ebenfalls die Administrationssperre; ein öffentlicher OIDC-Synchronisierungsanwendungsfall ist damit noch nicht implementiert.

Die experimentelle Plugin-API 0.5 ergänzt GroupMappingChange, GroupMappingPolicy und den strikt zugeordneten Mappingvorschlag im MutationContext. Validatoren können die vollständige Kante prüfen. Audit und persistierte Abschlussereignisse verwenden Zielgruppe und einen längengerahmten SHA-256-Fingerabdruck, ohne Roh-Issuer oder externe Kennung automatisch zu kopieren. Ein bereits vorhandenes Mapping ist bei korrekter Projektion unverändert; eine abweichende Quellenprojektion kann derselbe Aufruf korrigieren und meldet dies als Änderung.

Der Schutz verbleibender aktuell nutzbarer Verwaltungsfähigkeiten umfasst jetzt Status, Mitgliedschaften, Gruppenstammdaten und Mappings. Diese Liste wird vor Validatoren erfasst und nach dem Write erneut geprüft. Die bestehenden Status-, Mitgliedschafts- und Stammdatendienste nutzen denselben Schutz. Alle Quellen, Versionen und Protokolleinträge committen gemeinsam oder werden zurückgerollt.

### 18.1 Prüfung

Die Vierfachmatrix aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 ist erfolgreich: jeweils 206 Tests mit 714 Assertions (84 Unit-/Architekturtests und 122 Integrationstests). Die Syntax aller 79 eigenen PHP-Dateien sowie die Migrations-CLI sind ebenfalls geprüft. 15 zusätzliche Unitfälle und 27 zusätzliche Integrationsfälle belegen Delegation auch ohne aktuelle Mitglieder, sofortigen Abgleich mehrerer Benutzer, exakte Issuertrennung, binäre externe Kennungen, Quellenprovenienz, Versionen, Driftkorrektur, Plugin-Veto und Nachprüfung, Administrationsschutz und Rollback sowie die gemeinsame Snapshot-/Administrationssperre. Migrationen und Laufzeitabhängigkeiten bleiben unverändert.

### 18.2 Empfohlene nächste Priorität: Erste Weboberfläche

Es gibt weiterhin kein Frontend. Die bisherigen Anwendungsdienste sind getestet, aber noch nicht über einen produktiv nutzbaren Browserzugang bedienbar. Nach der Nachfrage nach dem Frontend wird empfohlen, den nächsten Durchstich auf eine erste kleine Oberfläche mit Navigation und Benutzer-/Gruppenverwaltung auszurichten, statt zunächst weitere Verwaltungsfunktionen ausschließlich im Backend auszubauen.

Dafür zuerst den HTTP-/Template- und OIDC-Integrationsweg konkret festlegen. Zusätzliche Bibliotheken sind weiterhin vor Einsatz abzustimmen. Für einen echten Login fehlen insbesondere Clientregistrierung, Samla-Callback, tatsächlicher Gruppenclaim und die Gruppenfrische-Richtlinie. Discovery allein ersetzt diese Angaben nicht. Die Oberfläche muss anschließend ausschließlich die geschützten Dienste verwenden, Akteure aus der authentifizierten Sitzung übernehmen und Formulare einschließlich CSRF-Schutz sowie erlaubte und verweigerte Zugriffe mit Tests abdecken. Ein vorläufiger lokaler Entwicklungszugang wäre ausdrücklich von einem produktiven OIDC-Login zu unterscheiden.

Weitere offene Pakete bleiben Gruppenlöschung mit modulübergreifenden Referenzprüfungen, atomare Mehrfach-Mappingänderungen, Rollenbearbeitung/globales Delegieren, Notfallzugang, Outbox-Zustellung, App-Passwörter und der erste Dateidurchstich mit WebDAV. Eine einzelne Mappingänderung wird synchron für alle passenden Benutzer in einer Transaktion verarbeitet; Lastabnahme oder aufgeteilte Hintergrundverarbeitung sind nicht abgeschlossen.

README, API- und Verwaltungsdokumentation, Prüfprotokoll und dieser Plan werden mit der Implementierung veröffentlicht. Die nächste Priorisierung der Oberfläche ist eine Empfehlung; sie stellt keine Freigabe neuer Laufzeitkomponenten oder einen bereits implementierten Login dar.

Version 1.9: Geschützte Mappingkanten mit sofortigem Abgleich gespeicherter externer Quellen und Schutz aller vier beschriebenen Verwaltungsfähigkeiten; erfolgreiche Vierfachmatrix und empfohlener nächster UI-Durchstich.


## 19. Iteration 9: Erste responsive Verwaltungsoberfläche

Samla hat jetzt eine erste serverseitig gerenderte Weboberfläche mit Übersicht, Navigation sowie Benutzer- und Gruppenverzeichnis. Vorhandene Anwendungsdienste sind über echte Formulare bedienbar: Benutzer sperren/entsperren, Gruppen anlegen/umbenennen und manuelle Mitgliedschaften hinzufügen/entfernen. Gruppenpflege behält Konfliktprüfung, Delegationsregeln, Quellenprovenienz und Administrationsschutz bei. Mapping- und Rollenformulare sowie Dateioperationen fehlen noch.

Die Umsetzung verwendet vorhandenes PHP, feste PHP-Templates und CSS ohne zusätzliche Laufzeitbibliotheken, JavaScript-Build oder separaten Frontendserver. Ein vertrauenswürdiger Einstiegspunkt komponiert Kern und RBAC über die vorhandenen Verträge. Neue ausdrückliche globale Rechte `identity.users.read` und `identity.groups.read` schützen Verzeichnisse; Verwaltungsrechte implizieren sie nicht. Jede Verzeichnisabfrage verwendet vollständige Autorisierung einschließlich Plugin-Einschränkungen und liefert höchstens 50 Einträge je Cursorseite. Issuer, Subject und externe Claims werden nicht ausgegeben.

### 19.1 Lokaler Entwicklungszugang

Der produktive OIDC-Login ist weiterhin offen. Für die erste Erprobung gibt es ausschließlich einen ausdrücklich aktivierten lokalen Adapter: APP_ENV=development, Opt-in, serverseitig festgelegter Akteur, Datenbankpräfix samla_dev_*, PHPs eingebauter Server mit Loopback-Bindung, direkter Loopback-Client und zulässiger lokaler Host. Produktion, Testmodus, FPM, fremde Hosts und Remoteclients erlauben diesen Zugang nicht. Forwarded-Header aktivieren ihn nicht. Der Adapter ist weder für einen öffentlichen Tunnel noch einen Reverseproxy oder einen gemeinsam genutzten Rechner gedacht.

Ein begrenztes CLI-Einrichtungswerkzeug erzeugt synthetische Demoidentitäten und explizite Demorechte in der separaten Entwicklungsdatenbank. Die Gruppenfrische wird dort anhand eines ausdrücklich gewählten lokalen Beispiels eingestellt. Das ist keine produktive Gruppenrichtlinie und keine OIDC-Verifikation. Wiederholte Einrichtung entsperrt bestehende Identitäten nicht automatisch. Die vollständige Startanleitung und Grenzen stehen in web.md und README.

### 19.2 HTTP- und Formularschutz

PHP-Sitzungen verwenden Strict Mode, ausschließlich Cookies, HttpOnly und SameSite=Strict. Wechsel des konfigurierten Akteurs erneuert Session-ID und CSRF-Token. Schreibformulare prüfen den sitzungsgebundenen zufälligen Token vor dem Dienstaufruf. Akteure stammen nie aus Formular-, URL- oder Actor-Headerfeldern. In lokaler HTTP-Entwicklung ist das Cookie nicht Secure; produktive HTTPS-Sitzungen benötigen einen eigenen OIDC-Vertrag.

Dynamische HTML-Werte werden escaped. Sicherheitsheader beschränken Inhalte und Formulare auf den vorgesehenen Origin, verhindern Einbettung und setzen no-store/nosniff/no-referrer. Templates und Routen sind fest ausgewählt. Erfolgreiche POSTs leiten mit 303 auf einen internen Pfad weiter und erhalten eine einmalige Sessionmeldung. Ungültige Daten, fehlende Berechtigung, Konflikte und falsche Methoden werden sichtbar behandelt. Laufzeitfehler zeigen keine SQL-Daten oder Stacktraces. Ohne eingerichteten Zugang wird keine Verwaltungsinformation freigegeben.

### 19.3 Prüfung

Die Vierfachmatrix aus PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6 ist erfolgreich: jeweils 226 Tests mit 804 Assertions (90 Unit-/Architekturtests und 136 Integrationstests). Die Syntax aller 96 eigenen PHP-Dateien und die Migrations-CLI sind geprüft. 6 zusätzliche Unitfälle und 14 zusätzliche Integrationstests prüfen Adaptergrenzen, explizite Leserechte, HTML-Escaping, CSRF, Formulare, Rechteentzug, Plugin-Einschränkungen, Namenskonflikt, Cursorseiten und Quellenzählung.

Zusätzliche echte HTTP-Prüfungen unter beiden PHP-Versionen mit MySQL bestätigen Cookie-/Sessionverhalten, CSRF, Post/Redirect/Get, Formularänderungen, geschützte administrative Gruppen, Hostprüfung, erlaubte Assets und 503 in Produktionskonfiguration. Chrome-Prüfungen mit Desktop- und Smartphone-Viewport bestätigen Navigation sowie Gruppenanlage, Umbenennung und Mitgliedschaftsformulare ohne Seitenüberlauf. Die optionalen reproduzierbaren HTTP-/Browserprüfer sind Teil des Repositorys; Browser und Testlaufzeiten sind keine Anwendungsabhängigkeiten. Weitere Details stehen im Prüfprotokoll.

### 19.4 Nächste Schritte

1. Erste Oberfläche lokal anhand der Startanleitung erproben und gewünschte Bedienung abstimmen. Eine produktive Veröffentlichung oder ein echter Login wird durch diese Iteration nicht behauptet.
2. OIDC-Integrationsbibliothek konkret auswählen und vor Einsatz abstimmen. Clientregistrierung, Samla-URL/Callback, Client-ID, benötigte Claims und Gruppenfrische klären. Secrets ausschließlich in die Laufzeitkonfiguration übernehmen.
3. Authentifizierten OIDC-/Sessionablauf einschließlich sicherer Cookies, Logout und verifizierter Gruppensynchronisierung anschließen und als vollständigen Browserdurchstich testen.
4. Danach Mapping-/Rollenoberfläche und Dateidurchstich ergänzen. Gruppenlöschung, globale Delegation, Notfallzugang, Outbox-Zustellung, App-Passwörter und WebDAV bleiben weitere offene Pakete.

Es gibt weiterhin keine öffentliche UI-Beitrags-API für Pluginseiten oder Navigation; geschützte Fachabläufe und Leseberechtigungen nutzen bereits die bestehenden Erweiterungsverträge. Browserprüfungen ersetzen keine vollständige Barrierefreiheits-, Last-, Restore- oder produktive OIDC-Sicherheitsabnahme. README, HTTP-Handbuch, API-Hinweise, Entscheidungen, Prüfprotokoll und Plan werden mit dem Code veröffentlicht.

Version 1.10: Erste responsive PHP-Verwaltungsoberfläche mit geschützten Lese-/Schreibabläufen, ausdrücklich lokalem Entwicklungszugang, HTTP-/Browsernachweisen und fortgeschriebener OIDC-Roadmap.


## 20. Iteration 10: OIDC-Protokollbasis aus bestätigter Testregistrierung

Die neue Clientregistrierung wird am Authorization-Endpunkt akzeptiert; die Login-Seite ist erreichbar. Authorization Code, PKCE S256 und Basic sind als Integrationsweg bestätigt. Der lokale Callback lautet http://127.0.0.1:8080/auth/callback. Ein kompletter Login einschließlich Token-Austausch ist weiterhin nicht nachgewiesen. Nach dem Absenden des geschützten Loginformulars verhinderte eine Browser-Sicherheitsrichtlinie die weitere Beobachtung. Die Projekt-Audience ist von der für das ID-Token erwarteten Client-ID getrennt. Zugangsdaten werden nicht im Repository oder in diesem Plan gespeichert.

### 20.1 Implementiert

- Separate, standardmäßig deaktivierte OIDC-Prozesskonfiguration mit strikter URL-/Callbackprüfung, Basic-Vorgabe und optionalem ZITADEL-Projekt-Audience-Scope. Produktion verlangt HTTPS; lokale HTTP-Callbacks sind nur unter development/test möglich.
- Discovery-Metadatenprüfung mit exaktem Issuer, angekündigtem Code/S256/Basic und auf den konfigurierten HTTPS-Origin begrenzten Endpunkten.
- Interner Login-Ablauf mit unabhängigen zufälligen State-/Nonce-/Verifierwerten, S256-Challenge und serverseitig zu speichernder, fünf Minuten gültiger Transaktion. Jede neue Anmeldung ersetzt den bisherigen Versuch.
- Einmalige Callback-Verarbeitung, Ablauf-/Konfigurationsbindung und Ablehnung unpassender States, Issuer, Codes und IdP-Fehler. Das Ergebnis bleibt ein unbestätigter Code-Grant und verleiht keine Identität oder Rechte.
- Vorbereitete serverseitige Token-Requestdaten für Basic und PKCE, ohne Secret im Requestbody. Debugausgaben geben keine Zugangsdaten oder Login-Transaktionswerte aus.
- CLI-Vorabprüfung über Composer, ohne Datenbank oder Übertragung von Client-/Benutzerzugangsdaten. HTTPS-Zertifikatsprüfung, begrenzte Antwortgröße und keine Redirectfolge.

Keine zusätzliche Laufzeitbibliothek, Migration oder öffentliche Plugin-API wird eingeführt. Die Klassen sind interne Protokollvorbereitung; eine Fachintegration darf weiterhin nur verifizierte Identitäten und die vorhandenen Autorisierungsverträge verwenden.

### 20.2 Prüfung

Vierfachmatrix erfolgreich: PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6 jeweils 241 Tests, 916 Assertions (105 Unit-/Architekturtests, 136 Integrationstests). 103 eigene PHP-Dateien sind syntaktisch geprüft; die Migrations-CLI meldet überall aktuelles Schema. 15 zusätzliche Unitfälle prüfen Konfigurationsgrenzen, Discovery-Vertrauen, PKCE, State, Nonce, Callback-Ablauf, Replay, Konfigurationswechsel, Basic-Kodierung und redigierte Debugausgaben.

Zusätzliche lokale HTTPS-Prüfungen unter beiden PHP-Versionen bestätigen gültige Discovery, Ablehnung nicht vertrauenswürdiger TLS-Zertifikate, keine Redirectfolge, Größen-/JSON-/Issuer-/Originprüfungen, standardmäßiges Opt-in und fehlende Übertragung von Clientzugangsdaten. Das frisch abgerufene Live-Discovery-Dokument erfüllt den neuen Metadatenvertrag. Die direkte PHP-CLI-Verbindung zum Live-Provider scheitert in dieser Testumgebung an der DNS-Auflösung; dies wird nicht als erfolgreicher Online-CLI-Test ausgegeben. Composer-Validierung ist erfolgreich, mit dem bereits offenen Lizenzhinweis.

### 20.3 Offen und nächste Schritte

Die neue Protokollbasis ist noch nicht an den Webeinstieg angeschlossen. OIDC_ENABLED aktiviert keinen Weblogin; /auth/login und /auth/callback sind noch keine aktiven OIDC-Routen. Der lokale Entwicklungszugang bleibt unverändert. Es gibt keine Tokenverifikation, produktive Sitzung oder Gruppensynchronisierung und keine Produktivfreigabe.

Als Nächstes konkrete OIDC-Bibliothek auswählen und vor Einführung abstimmen, Tokenadapter samt Signatur-/Issuer-/Client-Audience-/Nonce-/Zeitprüfung implementieren, serverseitigen Transaktionsverbrauch vor Netzwerk-I/O absichern und Webrouten mit HTTPS-/SameSite-/Logoutvertrag anschließen. Tatsächliche Gruppenclaims, Frischerichtlinie und Erstadministration bleiben festzulegen. Danach vollständigen Login mit dem echten Testprovider und erlaubtem/verweigertem Zugriff abnehmen. Dateidurchstich, WebDAV und die weiteren offenen Pakete bleiben unverändert vorgesehen.

README, OIDC-Handbuch, Entscheidungen, Webhinweise, Prüfprotokoll und dieser Plan werden mit der Phase veröffentlicht.

Version 1.11: Bestätigte OIDC-Testregistrierung in eine getestete Konfigurations-, Discovery-, State-/Nonce-/PKCE- und Callback-Protokollbasis umgesetzt; vollständige Anmeldung bleibt ausdrücklich offen.


## 21. Iteration 11: .env-Konfiguration und geprüfte OIDC-Bibliotheksentscheidung

Auf Wunsch des Betreibers wird die Konfiguration nun aus einer .env im Projektverzeichnis gelesen. Die versionierte Vorlage heißt example.env und ersetzt config/environment.example. Sie verwendet standardmäßig production sowie deaktivierten Entwicklungs- und OIDC-Zugang. Die echte Datei und .env.* sind von Git ausgeschlossen und liegen außerhalb des Webroots public. Dateibesitz und Leserechte müssen zur PHP-Laufzeit passen.

### 21.1 Gemeinsame Dateikonfiguration

Ein begrenzter nativer EnvironmentLoader liest literale Zuweisungen ohne neue Bibliothek. Webeinstieg, AppConfig und OidcConfig nutzen dieselbe Quelle; Migrationen, Entwicklungs-Seed und OIDC-Vorabprüfung übernehmen sie dadurch ebenfalls. Prozesswerte haben Vorrang, auch wenn sie explizit leer sind. Der Loader verändert keine Prozessumgebung oder Superglobals und speichert keine Konfiguration über Aufrufe hinweg. Requestdaten werden nicht übernommen.

Unterstützt werden Kommentare, leere Werte, optionale export-Präfixe, einfache/doppelte Anführungszeichen, definierte Escapes, BOM und CRLF. Doppelte Schlüssel, unbekannte Escapes, nicht geschlossene Anführungszeichen, Steuerzeichen und Dateien über 64 KiB führen zu generischen Fehlern ohne Wertausgabe. Es gibt keine Variablenersetzung, Ausführung von PHP/Shellcode, Includes oder mehrzeilige Literale. Die Syntax und Startanleitung stehen in configuration.md und README.

Automatisierte Tests mit prozessseitigem APP_ENV=test ignorieren die Deployment-Datei vollständig und verwenden weiterhin ausschließlich explizite TEST_DB_*-Werte und isolierte Testdatenbanken. Die unveränderten Grenzen des lokalen Entwicklungszugangs bleiben wirksam. Dateikonfiguration aktiviert keinen produktiven Weblogin.

### 21.2 OIDC-Bibliothek isoliert erprobt

Als konkrete Integrationsbibliothek wird facile-it/php-openid-client 1.0.0 vorgeschlagen. Die isolierte Auflösung außerhalb der Anwendung umfasst das direkte Paket und 23 transitive Pakete. Sie benötigt keinen zusätzlichen Serverdienst. Anwendungs-composer.json, composer.lock und vendor wurden nicht um diese Pakete erweitert. Composer-Plugins und Scripts blieben bei der Evaluation deaktiviert; ein GMP- oder cURL-Modul ist für den geprüften RS256-/NativeHttpClient-Weg nicht nötig. Die tatsächliche Plattformauflösung verlangt JSON, OpenSSL, iconv und mbstring; dies ist vor Einführung auf dem Zielhost zu prüfen.

Das reproduzierbare optionale tests/Tools/oidc-library-probe.php prüft unter PHP 8.4.5 und 8.5.4 mit synthetischen Daten eine gültige Signatur, 16 abgelehnte Tokenfehler, korrekte Basic-/PKCE-Tokenrequests, kontrolliertes Schlüsselnachladen, OAuth-Fehler und die explizite HTTP-Clientzusammenstellung. Dabei wurden zusätzliche Adapterpflichten nachgewiesen: ID-Token und Nonce zwingend verlangen, azp prüfen und vorhandenes at_hash an das Access-Token binden. Die Entscheidungsvorlage oidc-library-decision.md dokumentiert die genaue Paketauflösung, Grenzen und Reproduktion. Ein echter Tokenaustausch wurde nicht durchgeführt.

Die konkrete Aufnahme der Bibliothek steht wegen der bestehenden Regel „Keine zusätzlichen Laufzeitkomponenten ohne Abstimmung einführen“ noch zur Entscheidung. Diese Evaluation ist keine Freigabe oder bereits eingebaute OIDC-Anmeldung.

### 21.3 Prüfung

Vierfachmatrix erfolgreich: PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6 jeweils 253 Tests, 953 Assertions (117 Unit-/Architekturtests und 136 Integrationstests). Syntax aller 106 eigenen PHP-Dateien geprüft; Migrations-CLI meldet überall aktuelles Schema. Zwölf neue Unitfälle prüfen Dateisyntax, Literalbehandlung, Vorrang leerer Prozesswerte, Fehler ohne Secretreflexion, Größen-/Dateitypgrenzen, Testisolation und gemeinsame Konfiguration.

Zusätzliche echte HTTP-Tests unter beiden PHP-Versionen mit MySQL starten Seed und Web ausschließlich aus einer temporären synthetischen .env. Session-/CSRF-/Formularprüfungen bestehen; weder .env noch example.env werden ausgeliefert. Ein prozessseitiges production überschreibt development aus der Datei und sperrt den Entwicklungszugang. OidcConfig übernimmt die Dateieinstellungen ebenfalls. Die temporäre Datei wurde anschließend entfernt. Der Bibliotheksprüfer und die Composer-Plattformprüfung sind unter beiden PHP-Versionen erfolgreich; die Evaluation meldet keine Warnungen oder Deprecations.

### 21.4 Nächste Schritte

Nach Abstimmung der konkreten OIDC-Bibliothek folgen Adapter, abgesicherter Bibliothekstransport, zusätzliche Claimprüfung, Login-/Callback-/Logoutrouten und Sitzungsintegration. Gruppenclaim-Vertrag, Gruppenfrische und Erstadministration bleiben offene Betriebseinstellungen. Der echte Login-Durchstich und eine Produktivfreigabe sind weiterhin nicht abgeschlossen. Der Datei-/Medienumfang und WebDAV bleiben unverändert offen.

README, Konfigurationshandbuch, Entscheidungsvorlage, OIDC-/Betriebshinweise, Prüfprotokoll und dieser Plan werden gemeinsam veröffentlicht.

Version 1.12: Gemeinsame .env-Konfiguration mit example.env, nachgewiesene Datei-/Prozessgrenzen und isoliert geprüfte konkrete OIDC-Bibliotheksvorlage; vollständige Anmeldung bleibt offen.


## 22. Iteration 12: Vollständiger OIDC-Weblogin und Erstadministration

Die Betreiberanweisung zur vollständigen Umsetzung gibt die zuvor vorgeschlagene OIDC-Bibliothek einschließlich ihrer transitiven Abhängigkeiten frei. facile-it/php-openid-client 1.0.0 und 23 transitive Pakete sind in den Anwendungsabhängigkeiten aufgenommen und im Lockfile festgeschrieben. Composer-Plugins bleiben deaktiviert. Der vorhandene .env-Vertrag bleibt bestehen; example.env dokumentiert jetzt die produktive HTTPS-Konfiguration und den optionalen Gruppenclaim.

### 22.1 Webablauf und Tokenprüfung

Loginseite, POST-Anmeldebeginn, GET-Callback, eigene Kontoseite und POST-Abmeldung sind an den Webeinstieg angeschlossen. Ohne OIDC-Konfiguration oder zulässigen Entwicklungsadapter bleibt die bestehende 503-Sperre aktiv; korrekt eingerichtetes OIDC erlaubt jetzt den HTTPS-/PHP-FPM-Zugang. Apache-Frontcontroller-Rewrite und eine ausführliche Plesk-Anleitung sind beigefügt.

Die Anmeldung verwendet Code, S256, Basic und ausdrücklich RS256. Discovery und Endpunkte werden gegen den konfigurierten Issuer/Origin geprüft. Der HTTP-Adapter erlaubt nur fest validierte Endpunkte und Methoden, prüft TLS, folgt keinen Redirects und begrenzt Laufzeit und Antwortgröße einschließlich des gestreamten Inhalts. Signatur- und Tokenprüfungen werden durch die Bibliothek ausgeführt. Samla verlangt ergänzend ein ID-Token, exakten Nonce, passende Client-Audience/azp, begrenzte Identitätsdaten und gültige Zeittypen; ein vorhandenes at_hash wird mit dem Access-Token durch die JOSE-Prüfung gebunden.

Der Callback verbraucht die unter Sessionlock gespeicherte Transaktion vor Netzwerk-I/O. Ein zusätzlicher Austauschnachweis verhindert das Wiederherstellen einer zwischenzeitlich abgemeldeten oder neu begonnenen Sitzung durch eine verspätete Antwort. Wiederholte/falsche Callbacks erzeugen keine Identität und melden einen bereits angemeldeten Benutzer nicht automatisch ab. HTTPS-Sitzungen verwenden ein hostgebundenes __Host-samla-Cookie mit Secure/HttpOnly/Path=/ und SameSite=Lax, keine Domainvererbung sowie strikten Cookiebetrieb. Session-ID und CSRF werden erneuert. Die Sitzung gilt höchstens eine Stunde und nie über den ID-Token-Ablauf hinaus. Keine Tokens werden in der Anwendungssitzung gespeichert.

### 22.2 Identität, Gruppen und Rechte

Verifizierte Identitäten werden über Issuer/Subject zugeordnet. Neue Benutzer erhalten keine Rollen. Vorhandene lokale Sperren bleiben wirksam. Die eigene Kontoseite ist ohne Verwaltungsrolle möglich und zeigt die interne Kennung für die ausdrückliche Erstadministration. Fremde Verzeichnisse und Änderungen bleiben vollständig durch den vorhandenen Autorisierungsdienst geschützt; Rechte werden nicht in der Sitzung eingefroren.

Bei leerem OIDC_GROUPS_CLAIM gelten ausschließlich lokale/manuelle Gruppen. Dieser Modus verweigert die Anmeldung bei vorhandenen externen Mappings oder Mitgliedschaftsquellen. Ein konfigurierter signierter Claim muss eine begrenzte Gruppenliste liefern; fehlende oder falsch strukturierte Claims werden abgelehnt. Der Abgleich erhält manuelle Quellen und lokale Sperren. Rollenobjekte werden nicht automatisch umgedeutet. Die Gruppenfrischerichtlinie bleibt eine explizite Einstellung; ohne sie gibt es keine Verwaltungsfreigabe.

Provisionierung, Name, Gruppenstand, Audit und Outbox werden unter dem gemeinsamen Administrationslock atomar verarbeitet. Der öffentliche MutationRunner-/ChangeValidator-Vertrag erhält identity.login mit interner Benutzerkennung und kann die Übernahme verhindern. Rohclaims, Tokens und Secrets werden nicht protokolliert. Für die transaktionale Zusammensetzung sind interne Writer für bestätigte Gruppen und initiale Rollen ergänzt; sie sind keine ungeschützten öffentlichen Fachendpunkte.

Die initiale Verwaltungsrolle wird nur per CLI bei Wartung, vorhandener frischer aktiver OIDC-Identität und ausdrücklicher Frischerichtlinie eingerichtet. Abweichende reservierte Rollen, fremde Belegung der Bootstrapgruppe und zusätzliche unerwartete Gruppenzuweisungen werden abgelehnt. Wiederholung für denselben Benutzer ist möglich. Kein erster-Login-wird-Admin und keine allgemeine laufende Administrationsvergabe über diesen Befehl.

### 22.3 Prüfung

Vierfachmatrix erfolgreich: PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6 jeweils 264 Tests, 1032 Assertions (122 Unit-/Architekturtests und 142 Integrationstests). 121 eigene PHP-Dateien sind syntaktisch geprüft; die Migrations-CLI meldet überall aktuelles Schema. Zusätzliche Fälle prüfen echte RSA-Token im Adapter, fehlenden Nonce, azp/mehrere Audiences, falsches at_hash, Transportgrenzen, Origin/HTTPS, manuelle/externe Gruppenmodi, stabile Provisionierung, fehlende automatische Rollen, lokale Sperren, Plugin-Veto und Rollback.

Ein reproduzierbarer optionaler HTTP-Prüfer startet einen kontrollierten TLS-Provider mit echten signierten ID-Tokens und JWKS. Unter beiden PHP-Versionen mit MySQL sind vollständiger Code-/Basic-/PKCE-Austausch, Sitzungserneuerung/-ablauf, Replay-Abweisung, initiale Administration, echte geschützte Formulare, lokaler Logout, falscher Nonce, falsche Signatur und unmittelbare lokale Sperre geprüft. Zusätzlich besteht unter PHP 8.4.5 die HTTPS-Produktionskonfiguration mit TLS-Gateway, hostgebundenem Secure-Cookie und vertrauenswürdig gesetztem HTTPS-Serverstatus. Das ist kein tatsächlich ausgeführter PHP-FPM-/Plesk-Prozess. Protokolle und Reproduktion stehen im Prüfprotokoll und unter tests/Tools.

### 22.4 Installation und verbleibende Grenzen

Der Login ist im Code vollständig angeschlossen. Auf dem Betreiberhost sind weiterhin Codeupdate, Composer-Installation, private .env, Schema, HTTPS-Routing und IdP-Registrierung erforderlich. Diese Installation und der echte ZITADEL-Login wurden hier nicht auf dem entfernten Server ausgeführt. installation.md enthält die konkreten Schritte einschließlich Plesk-PHP/Composer-PHAR und Erstadministration.

Lokaler Logout beendet nur die Samla-Sitzung. Zentraler IdP-/Backchannel-Logout, automatische Refresh-Sitzungen, allgemeiner Sitzungswiderruf und Notfalladministration bleiben offen. Datei-/Medienoperationen, WebDAV und die weiteren Produktpakete sind weiterhin nicht implementiert; der abgeschlossene Login ist keine pauschale Produktivfreigabe der ganzen Plattform. Eine neue vollständige visuelle Browser-/Barrierefreiheitsabnahme gehört nicht zu den durchgeführten HTTP-Prüfungen.

README, Installations-/Konfigurations-/OIDC-Handbuch, Erweiterungshinweise, Entscheidungen, Prüfprotokoll und dieser Plan werden mit der Phase veröffentlicht.

Version 1.13: Freigegebener OIDC-Client, angeschlossener verifizierter HTTPS-Weblogin, sichere lokale Sitzungen und Abmeldung, transaktionale Identitätsübernahme, ausdrückliche Erstadministration und reproduzierbarer HTTP-Durchstich.


## 23. Iteration 13: Erste private Dateiablage

Der Betreiber bestätigt den erfolgreichen echten ZITADEL-Login und die Erstadministration auf seinem Plesk-Host. Ursache des letzten Loginfehlers waren fehlende Composer-Abhängigkeiten. Der OIDC-Vorabcheck verlangt nun die zentralen Bibliotheksklassen; der Webeinstieg protokolliert bei Fehlern zusätzlich Quelldatei und Zeile ohne Exception-Nachricht oder Secrets.

### 23.1 Umfang und Rechte

Die Dateiansicht unter /files bietet berechtigte Speicherbereiche, Ordnernavigation, Ordneranlage, neue Uploads und gestreamte Downloads. Listen sind seitenweise begrenzt, Namen HTML-escaped, Schreibaktionen POST/CSRF-geschützt. PHP prüft echte HTTP-Uploads. Dateien werden ausschließlich als application/octet-stream und Attachment mit nosniff ausgeliefert, auch wenn ihr Name PHP oder HTML enthält. Die maximale Inhaltsgröße ist zunächst 16 MiB; niedrigere Webserver-/PHP-Limits bleiben wirksam.

files.list, files.read und files.create verlangen jeweils eine explizite Rollenzuweisung im angefragten Bereich. Globale Administratorrechte geben keinen impliziten Zugriff. Ein CLI-Installationsbefehl legt unter Wartung mit frischem Administrator einen neuen Bereich mit ausdrücklich ausgewählter vorhandener Gruppe und Quote an. Die erzeugte Rolle gilt nur für diesen neuen Bereich. Es werden keine vorhandenen Rollen erweitert. Bereichsanlage über die Oberfläche und persönliche Bereiche bleiben offen.

### 23.2 Persistenz und Erweiterungen

Eine zusätzliche versionierte Migration ergänzt Bereichsquoten und logische Einträge. Physische Inhalte liegen unter zufälligen Kennungen außerhalb public. Der private Speicherpfad ist über APP_STORAGE_ROOT konfigurierbar; Standard ist var/storage. Symlinks, relative und nicht kanonische Pfade sowie public werden abgewiesen. Uploads werden begrenzt gestreamt, mit SHA-256 versehen, auf 0600 gesetzt, synchronisiert und im selben Dateisystem atomar umbenannt.

Erst danach veröffentlicht eine SQL-Transaktion die Metadaten. Der vorhandene gemeinsame Administrationslock serialisiert Quotenprüfung, Namenskonflikte und geschützte Änderungen. Identität, Rechte und gültiger Zielordner werden vor und nach ChangeValidator geprüft. MutationRunner, Audit und Outbox gelten für storage.folder.create, storage.file.upload und den Offline-Installer storage.space.create. Rohinhalte und Namen werden nicht in Audit/Outbox kopiert. Ein typisierter Inhalts-/Namensvorschlag für Upload-Validatoren folgt separat. Bestehende API 0.5 unverändert.

Abstürze, Veto, Quotenfehler oder Namenskonflikte nach physischer Veröffentlichung können sichere verwaiste Objekte hinterlassen. Bei unklarem Commit-Ergebnis wird keine möglicherweise referenzierte Datei gelöscht. Der neue rein lesende Wartungsprüfer vergleicht Referenzen, Größe und SHA-256 und meldet fehlende/beschädigte, temporäre und verwaiste Objekte. Speicherverbrauch durch verwaiste Objekte, automatische Bereinigung und Überlastschutz bleiben konkrete Aufgaben vor breiter Betriebsfreigabe. Backup-/Restore-Anleitung beschreibt zusammengehörige Datenbank-/Dateisystemstände unter gestoppten Schreibzugriffen; ein vollständiger Recovery-Nachweis steht aus.

### 23.3 Prüfung

Vierfachmatrix: PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6 jeweils 290 Tests und 1095 Assertions (136 Unit-/Architekturtests, 154 Integrationstests). Syntax von 132 eigenen PHP-Dateien geprüft; Migrations-CLI meldet aktuelles Schema. Neue Tests prüfen Namen und Speicherpfade, Uploadgrößen, verschachtelte binäre Roundtrips, Bereichsisolation, fehlende automatische Admin-Dateirechte, Quoten, leere Dateien, Konflikte ohne Überschreiben, Plugin-Veto, erneute Rechteprüfung, Rollback, Sperren, Escaping, CSRF und Attachment-Streaming.

Der vorhandene HTTP-Durchstich wird um explizite Bereichsanlage, echte Multipart-Uploads, Ordner, CSRF-/Konfliktabweisung, Attachment-Download und Befunde des Konsistenzprüfers erweitert. Abschließendes Ergebnis steht im aktuellen Prüfprotokoll. Kein neues Deployment auf dem Betreiberhost wird durch einen Repository-Commit behauptet.

### 23.4 Fortlaufende Umsetzung

Die Betreiberanweisung erlaubt die fortlaufende Abarbeitung des gesamten Konzepts ohne erneutes Go nach jeder Phase. Jede abgeschlossene Phase aktualisiert README, diesen Plan und Prüfergebnisse und wird veröffentlicht. Echte offene Entscheidungen oder fehlende benötigte Zugänge werden gezielt abgefragt. Die bestehende Regel zu zusätzlichen Laufzeitkomponenten bleibt bestehen. Nächste Arbeiten: kontrollierte Objektbereinigung, typisierte Uploadvorschläge, weitere Dateioperationen und Versionen/Papierkorb, Rollen-/Mappingoberfläche, Medienfunktionen und WebDAV einschließlich zu entscheidender Bibliotheksintegration.

Version 1.14: Erste lokale Dateiablage mit expliziten Bereichsrechten, Quoten, sicheren Upload-/Downloadwegen und Wartungswerkzeugen; bestätigter Betreiberlogin und kontinuierliche weitere Umsetzung.


### 23.5 Betreiberprüfung und Plesk-Korrektur 13.1

Der Betreiber bestätigt die zunächst fehlende Speichermigration als Ursache der PDOException und führt sie nachträglich aus. Die Pfadprüfung wird an Plesk open_basedir angepasst: Die serverseitig erlaubte Wurzel bildet die Vertrauensgrenze; Eltern außerhalb dieser Freigabe werden nicht mit is_link untersucht. Innerhalb der Freigabe bleiben Symlinks gesperrt, vollständig außerhalb liegende Speicherpfade werden vor Dateisystemzugriff abgelehnt. open_basedir muss nicht erweitert oder deaktiviert werden.

Ein isolierter PHP-Unterprozess mit echtem open_basedir und als Exceptions behandelten Warnungen prüft zulässige Pfade ohne verbotene Elternzugriffe, Symlinkabweisung und Abweisung fremder Speicherwurzeln. Die gezielte Speichertestsuite besteht unter PHP 8.4.5 und 8.5.4 mit je 15 Tests und 22 Assertions. Diese Korrektur wird separat vor der noch laufenden Lebenszyklusphase veröffentlicht.


## 24. Iteration 14: Datei-Lebenszyklus, kontrollierte Bereinigung und WebDAV-Vorbereitung

### 24.1 Weitere Dateioperationen

Die Oberfläche bietet pro Eintrag Umbenennen, Verschieben innerhalb eines Bereichs, Kopieren einzelner Dateien und Papierkorb. Wiederherstellen ist über einen bereichsbezogenen Papierkorb möglich. Zielordner werden anhand verständlicher Pfadbezeichnungen ausgewählt; die Liste ist auf 100 Ordner begrenzt. Jede Aktion verlangt POST/CSRF und die eigenen atomaren Bereichsrechte. Verschieben benötigt update/create/delete, Kopieren read/create, Papierkorb delete und Wiederherstellen restore/create. Ein bestehender Beitragender wird nicht automatisch zum Dateiverwalter. Ein ausdrücklicher Offline-Installer vergibt read/contribute/manage an eine vorhandene Gruppe in einem vorhandenen Bereich, mit frischem Administrator und Wartung. Wiederholung einer identischen Zuweisung ist idempotent; vorhandene Rollen werden nicht verändert.

Name und Elternkennung werden als optimistische Erwartungen geprüft. Konflikte überschreiben keinen aktuellen Eintrag. Zyklische Verschiebungen werden abgewiesen; Ordnertiefe ist auf 128 begrenzt, der beim Verschieben geprüfte Teilbaum auf 1000 Ordner. Gelöschte Ordner sperren auch direkte Links auf Nachfahren. Eine Wiederherstellung setzt einen erreichbaren Elternordner voraus. Papierkorbinhalte zählen weiter zur Quote und reservieren bisherige Namen. Physische Inhalte von Dateikopien werden unveränderlich gemeinsam referenziert, logisch jedoch erneut gezählt.

### 24.2 Erweiterungsvertrag und Wartung

API 0.6 ergänzt StorageChange als unveränderlichen Änderungsvorschlag mit logischem Namen/Elternkennung und optional gepaarten Inhaltsgrößen/SHA-256. MutationContext verlangt diesen Vorschlag für die definierten Speicheroperationen und verbietet ihn für fremde Operationen. Plugins können Vorschläge prüfen und ablehnen, bevor Metadaten freigegeben werden. Es gibt weiterhin keinen Rohinhalt-/Virenscanner-Vertrag. Audit und Outbox enthalten nur Kennungen.

Die kontrollierte Bereinigung zeigt standardmäßig nur Kandidaten. Erst --apply entfernt maximal 1000 mindestens 24 Stunden alte nicht referenzierte Objekte/Stagingdateien unter dem Administrationslock mit erneuter Referenzprüfung vor und nach Validatoren. Unbekannte Namen, Symlinks, Hardlinks, junge und referenzierte Objekte bleiben erhalten. Schreiber müssen für diesen Offline-Wartungsschritt gestoppt sein. Eine physische Löschung kann nach SQL-Fehler nicht zurückgerollt werden; sie betrifft daher ausschließlich nicht referenzierte Objekte. Anleitung, Grenzen und neuer Migrationsschritt stehen in storage.md.

### 24.3 Prüfung

Vierfachmatrix: PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6 jeweils 304 Tests, 1153 Assertions (142 Unit-/Architekturtests, 162 Integrationstests). 140 eigene PHP-Dateien im Matrixlauf syntaktisch geprüft; der danach ergänzte optionale WebDAV-Prüfer ist zusätzlich unter beiden PHP-Versionen ausgeführt. Migrations-CLI überall erfolgreich.

Neue Fälle prüfen verschachtelte Dateien, Umbenennen/Verschieben/Kopieren, Quoten von Kopien, versteckte Papierkorb-Nachfahren, Wiederherstellung, Zyklus-/Elternabweisung, Konflikte und veraltete Formulare, fehlende Verwaltungsrechte, typisierte Plugin-Vetos, sichere Bereinigung und Lebenszyklusformulare. Der echte HTTP-/HTTPS-Durchstich bestätigt zusätzlich explizite Profilfreigabe, Detailformulare, Umbenennen, veralteten Request mit 409, Dateikopie, Papierkorb und Wiederherstellung sowie reinen GC-Probelauf. Die Plesk-Korrektur wurde vom Betreiber als passend bestätigt. Neue Datei-Lebenszyklusfunktionen wurden hier nicht auf seinem Host deployed.

### 24.4 Konkrete nächste Komponentenentscheidung

SabreDAV 4.7.1 ist als konkrete WebDAV-Bibliothek außerhalb der Anwendung isoliert geprüft. Die Auflösung umfasst das direkte Paket, fünf neue transitive Pakete und das bereits vorhandene psr/log. Keine Anwendungspakete oder Routen wurden verändert. Der optionale reproduzierbare Prüfer bestätigt unter PHP 8.4.5/8.5.4 PROPFIND/GET/OPTIONS, eigenen Basic-Adapter, Abweisung falscher Daten, exklusives LOCK, verweigertes PUT ohne Token, erfolgreiches PUT mit Token und UNLOCK. Warnungen/Deprecations werden als Exceptions behandelt.

Die konkrete Entscheidung einschließlich XML-Erweiterungen, Paketversionen, Plattformgrenzen, PDO-Lock-/App-Passwortintegration und Quellen steht in webdav-library-decision.md. Gemäß der bestehenden AGENTS-Regel zu zusätzlichen Laufzeitkomponenten ist vor der Aufnahme die ausdrückliche Freigabe dieser Bibliothek erforderlich. Die frühere Freigabe bezog sich auf OIDC. Das ist eine echte Komponentenentscheidung und kein erneutes Go für die allgemeine Fortsetzung.

### 24.5 Nachverfolgung des vollständigen Konzepts

| Konzeptbereich | Stand und verbleibende Arbeit |
|---|---|
| Dateien und Speicherbereiche | Erste Bereiche, Quoten, Ordner, neue Uploads/Downloads, Umbenennen/Verschieben, Einzeldateikopien und Papierkorb vorhanden. Versionen, endgültige Löschung, Massenoperationen, rekursive/bereichsübergreifende Operationen, persönliche Bereiche und vollständige Bereichsverwaltung offen. |
| Medienbibliothek | Suche, Filter, Tags, Thumbnail-/Previewerzeugung, Original-/Ableitungsverwaltung und Index-Neuaufbau offen. |
| Viewer und Editor | Berechtigter Attachment-Download vorhanden; Bild-/PDF-/Textviewer und Texteditor samt Konfliktprüfung offen. |
| Metadaten und KI-Herkunft | EXIF/IPTC/XMP, Herkunft und Prioritäten, sensible GPS-/Gerätedaten, KI-Status/Nachweisqualität und Extraktorgrenzen offen. |
| Erweiterbare Felder und Medienrechte | Typisierte Felddefinitionen, Wertevalidierung und gezielte Leserechte noch offen. |
| Sammlungen und Referenzen | Berechtigte Sammlungen, Verweise und deren Pflege offen. |
| Portabilität und Export | Originaldateien downloadbar. Struktur-/Metadatenexport und vollständiger Wiederimport offen. |
| Plugin-Architektur | Öffentliche API, RBAC-Pflichtplugin, Restrictions, Validatoren und MutationRunner vorhanden. Weitere fachliche Provider-/UI-Beiträge, Zustellung der Outbox, Plugin-Lebenszyklus und umfassende Entwicklerbeispiele offen. |
| Identität und OIDC | Echte Anmeldung und initiale Administration bestätigt; globale Rollenerholung/Notfallzugang, allgemeine Sessionwiderrufe und IdP-Logout offen. |
| WebDAV und App-Passwörter | Konkrete Bibliothek isoliert geprüft, Freigabe ausstehend. App-Passwörter, Routing, dauerhafte gemeinsame Locks, Protokolladapter und native Referenzclienttests offen. |
| Gruppen und RBAC | Kernpolicies und erste Benutzer-/Gruppenoberfläche vorhanden. Rollen-/Mappingformulare, globale kontrollierte Rollenzuweisung, Gruppenlöschung und umfassende Delegationspflege offen. |
| Betrieb und Sicherheit | Migrationen, private Pfade, CSRF/Sitzungen, erste Konsistenz-/GC-Werkzeuge vorhanden. Vollständiger Recovery-Nachweis, Überlastschutz/Rate-Limits, Aufbewahrung, Monitoring, Update-/Releaseprozesse und Referenzclientabnahme offen. |
| Tests und Abnahme | Vierfachmatrix und echte lokale HTTP-/HTTPS-Durchstiche vorhanden. Native WebDAV-Clients, breitere visuelle/Barrierefreiheitstests, Last-/Crash-/Parallelitätsabnahmen sowie CI-Automatisierung und vollständige Konzept-Abnahme noch offen. |

Version 1.15: Datei-Lebenszyklus mit expliziten Rechten, typisierter Speicherproposal, kontrollierte Offline-Bereinigung und reviewbare konkrete WebDAV-Bibliotheksentscheidung. Der gesamte Konzeptumfang ist weiterhin nicht fertiggestellt.


### 24.6 Freigabe für Iteration 15

Am 22. Oktober 2026 gibt der Betreiber die konkret geprüfte SabreDAV-Abhängigkeit ausdrücklich frei. Eine zusätzliche isolierte gemeinsame Auflösung mit Samlas vorhandenen Paketen ergänzt sechs Pakete ohne bestehende Updates/Entfernungen und ohne gemeldete Advisories. Die Freigabe ist erteilt; ein Anhalten zur Bibliotheksentscheidung entfällt. Nach Veröffentlichung der abgeschlossenen Iteration 14 folgt die Integration einschließlich App-Passwörtern und gemeinsamem Lock-Vertrag.


## 25. Iteration 15: Freigegebene Bibliothek, eigene App-Passwörter und Speichereinrichtung

### 25.1 Abhängigkeiten

Die ausdrückliche Betreiberfreigabe ist umgesetzt. SabreDAV 4.7.1 und fünf neue transitive Pakete sind in composer.json/composer.lock aufgenommen und installiert. Bestehende Paketversionen wurden nicht verändert. Composer-Plugins/Scripts bleiben deaktiviert. cURL, DOM, SimpleXML, XMLReader und XMLWriter/libxml sind explizite Plattformanforderungen. Die Plattformprüfung besteht unter PHP 8.4.5 und 8.5.4; der Protokollprüfer besteht mit dem tatsächlichen Anwendungsautoloader unter beiden Versionen. Composer meldet keine bekannten Advisories. validate bestätigt die Struktur, mit bekannten Warnungen zur noch offenen Projektlizenz und bewusst exakten OIDC-/DAV-Pins. dependencies.md führt die tatsächlichen Laufzeitpakete und deklarierten Lizenzen auf.

### 25.2 Eigene Gerätezugänge

Der OIDC-Kontobereich bietet App-Passwortanlage, begrenzte Auflistung und Einzelwiderruf. Die eigene aktive frische Identität ist die Berechtigung für diesen Selbstservice; der Akteur stammt ausschließlich aus der Sitzung. Fremde Credentials können weder aufgelistet noch widerrufen werden. Es werden keine Dateirollen erzeugt. Die lokale Entwicklungsanmeldung erhält diese Funktion nicht.

Jeder Zugang hat zufällige unabhängige Credential-Kennung und 256-Bit-Geheimnis, sicheren PASSWORD_DEFAULT-Hash, Bezeichnung sowie Erzeugungs-/Ablauf-/Widerrufszeitpunkt. Gültigkeit 1 bis 90 Tage, maximal 20 aktive Zugänge, begrenzte Übersicht mit aktiven Einträgen zuerst. Das vollständige Geheimnis erscheint nur in der unmittelbaren Erstellungsantwort, nicht in Sitzung, Audit, Outbox oder späterer Liste. POST-/CSRF-Schutz, no-store und bestehende Sicherheitsheader gelten.

Die Authentifizierungsfunktion verweigert Ablauf, Widerruf, lokale Sperren und fehlende Gruppenfrische. Sie begrenzt Hashversuche je Benutzer und serverseitiger Clientadresse pro Minutenfenster und räumt alte gehashte Buckets begrenzt auf. Unbekannte/inaktive Konten erzeugen keine Buckets. Ein positives Credential-Ergebnis ersetzt keine spätere Ressourcenautorisierung. Änderungen verwenden MutationRunner/Validatoren, erneute Eigentümer-/Frischeprüfung und atomare Credentials/Audit/Outbox.

### 25.3 Speichereinrichtung nach Betreiberprüfung

Der Betreiber bestätigt das fehlende Speicherverzeichnis als Ursache der Uploadfehlermeldung. StorageUnavailable liefert jetzt eine verständliche 503-Antwort ohne private Hostpfade. storage-init.php unterstützt die ausdrückliche Ersteinrichtung der konfigurierten Wurzel mit 0700, optional genanntem PHP-FPM-Eigentümer und Schreibprobe. Der Elternordner muss vorhanden/durchquerbar sein. Keine automatische rekursive Eigentümeränderung vorhandener Inhalte.

### 25.4 Prüfung

Vierfachmatrix unter PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 312 Tests, 1202 Assertions (142 Unit-/Architekturtests, 170 Integrationstests). Syntax von 148 eigenen PHP-Dateien geprüft, Migrationen erfolgreich. Neue Fälle prüfen unabhängige Hashes, Ausschluss von Geheimnissen aus Persistenzereignissen und Listen, Widerruf/Idempotenz, fremde Konten, Sperren/Frische, Ablauf, Fehlversuchbegrenzung, unbekannte Konten ohne Buckets, Plugin-Veto/Rollback und einmalige Anzeige/CSRF/Escaping.

Die echten HTTP-/HTTPS-Prüfungen ergänzen App-Passwortanlage ohne Verwaltungsrolle, einmalige Geheimnisanzeige, spätere Liste ohne Geheimnis, Einzelwiderruf und CLI-Speichereinrichtung. Der Speicherfehlerfall wird ebenfalls über einen tatsächlichen Multipart-Upload mit vorübergehend fehlender privater Wurzel geprüft. Detailergebnisse stehen im Prüfprotokoll. Kein Deployment neuer Kontofunktionen auf dem Betreiberhost wird behauptet.

### 25.5 Fortsetzung

Der WebDAV-Endpunkt ist noch nicht angeschlossen. Vor Schreibfreigabe folgen Versions-/Konfliktschutz, gemeinsam für Browser und DAV gültige dauerhafte Sperren und der Protokolladapter. Credential-Widerruf muss auch bei konkreten Ressourcen-/Mutationsprüfungen berücksichtigt werden. Native Clientabnahme, Medienumfang, weitere administrative Oberflächen, vollständiger Recovery-/Lastnachweis und alle in 24.5 genannten übrigen Konzeptpunkte bleiben offen. Die fortlaufende Umsetzung ist weiterhin autorisiert; eine neue Bibliotheksfreigabe für SabreDAV ist nicht mehr erforderlich.

Version 1.16: Tatsächlich installierte freigegebene WebDAV-Bibliothek, sichere OIDC-Kontoselbstverwaltung eigener App-Passwörter und verbesserte private Speichereinrichtung.


### 25.6 Benötigte Betriebsziele vor dem WebDAV-Schreibpfad

Konzeptabschnitt 8.2 verlangt vor Implementierung verbindliche Referenzclients und messbare Betriebsgrenzen; wörtlich: „Zahlen werden ohne Nutzungsdaten nicht erfunden.“ Für die nächsten Entscheidungen zu großen gestreamten Uploads, Versionen, gemeinsamem Locking und deren Last-/Clientabnahme fehlen noch Angaben zum erwarteten Bestand (Dateianzahl, Gesamtvolumen, größte einzelne Datei), ungefähr gleichzeitig aktiven Benutzern und konkreten Referenzclients einschließlich OS-Versionen. Grobe Größenordnungen reichen für die erste Planung.

Die bisherige 16-MiB-Uploadgrenze, 100-Einträge-Seiten und begrenzten Ordnerbaumprüfungen sind vorläufige technische Schutzgrenzen der vorhandenen Umsetzung und keine mit dem Betreiber vereinbarten Zielkapazitäten. Ein erfolgreicher Testlauf beweist diese künftigen Betriebsziele nicht. Die Bibliotheksfreigabe liegt bereits vor; die benötigte Rückfrage betrifft ausschließlich diese im Konzept ausdrücklich geforderten Betriebsangaben, kein erneutes allgemeines Go.

Bis zur Antwort sind die vollständig geprüften und veröffentlichten Phasen einschließlich App-Passwörtern gespeichert. Weiterer Konzeptumfang bleibt gemäß Statusmatrix offen. Weitere konkrete Bild-/Export-/Antwortzeitgrenzen sind spätestens vor den entsprechenden Medien-/Exportphasen festzulegen.


## 26. Iteration 16: Betriebsziel, Dateiversionen und konfigurierbare Uploadgrenze

### 26.1 Geklärte Betriebsangaben

Der Betreiber nennt als Größenordnung etwa 1000 Dateien und fünf aktive Benutzer sowie iOS, Android, Windows und macOS als Zielplattformen. Die in 25.6 angefragten Größen-/Clientangaben sind damit für die Fortsetzung ausreichend eingegrenzt; ein weiteres allgemeines Go ist nicht nötig. Konkrete Dateigröße und Gesamtvolumen bleiben offen und werden durch Konfiguration/Quoten nicht als erfundene Betriebsziele festgesetzt. Konkrete native Client-/OS-Versionen und deren Abnahme bleiben vor Auslieferung nachzuweisen.

APP_MAX_UPLOAD_BYTES ersetzt den hart eingebauten Maximalwert. Standard bleibt die bisherige technische Schutzgrenze 16 MiB, technisch konfigurierbar zwischen 1 Byte und 1 TiB. Streaming, tatsächliche Inhaltsbegrenzung und unveränderliche private Objekte bleiben erhalten. Browser-Requests haben 1 MiB zusätzlichen Multipart-Spielraum; PHP- und Webserver-Limits sind separat anzupassen. Die individuelle Bereichsquote ist davon unabhängig.

### 26.2 Versionierung und Konflikte

Die neue Migration ergänzt Eintragsrevisionen und historische Objektverweise. Die Oberfläche bietet Ersetzen eines Dateiinhalts, begrenzte Historienliste neueste Revision zuerst, geschützten Download früherer Inhalte und Wiederherstellung als neuer aktueller Stand. Jede Ersetzung archiviert den bisherigen Inhalt, jede Wiederherstellung sichert vorher den bisherigen aktuellen Stand. Kennung und Name bleiben bestehen. Metadaten-/Papierkorbänderungen erhöhen ebenfalls die Revision.

Ein veralteter erwarteter Revisionsstand oder veränderte Name-/Elternmetadaten verhindern Überschreiben. Autorisierung, Sichtbarkeit, Eltern und Erwartung werden unter dem bestehenden MutationRunner vor und nach Validatoren geprüft. Archivierung, Referenzwechsel, Audit und Outbox sind eine SQL-Transaktion. Physisch bereits veröffentlichte Objekte bleiben bei unklarem Commit sicher erhalten und können als verwaist gemeldet werden.

files.versions.read ist ein zusätzliches atomisches Bereichsrecht, ohne automatische Erweiterung vorhandener Rollen. Der Offline-Installer ergänzt ausdrücklich das separate history-Profil. Historienanzeige/-download verlangen weiterhin files.read/list; Wiederherstellung zusätzlich files.update. Normales Ersetzen verlangt update/list. Kopieren übernimmt den aktuellen Inhalt und startet ohne kopierte Vorgeschichte. Eine technische Schutzgrenze von 1000 historischen Zuständen pro Datei stoppt neue Ersetzungen, ohne eine automatische Lösch-/Retentionsregel zu erfinden.

### 26.3 Quoten, Konsistenz und Erweiterungen

Die gemeinsame Quotenprojektion zählt aktuelle, historische und Papierkorbinhalte. Wiederherstellung und Dateikopien zählen logisch auch dann erneut, wenn physischer unveränderlicher Inhalt geteilt wird. Der bestehende Administrationslock serialisiert die Quote über alle Schreibwege. Konsistenzprüfung und GC berücksichtigen historische Referenzen; eine alte noch referenzierte Version darf nicht gelöscht werden.

MutationContext/StorageChange unterstützen storage.file.replace und storage.version.restore mit neuen Inhaltseigenschaften und Zielkennungen entry/version. Plugins können Inhalte anhand des vorgeschlagenen Hash-/Größen-/Namensvertrags weiterhin ablehnen. Audit/Outbox speichern keine Freitextnamen oder Rohinhalte. Der API-Stand bleibt 0.6.

### 26.4 Nachweis und Fortsetzung

Vierfachmatrix unter PHP 8.4.5/8.5.4 und MySQL 8.0.46/MariaDB 11.8.6: 324 Tests mit 1277 Assertions (144 Unit-/Architekturtests und 180 Integrationstests), 152 eigene PHP-Dateien syntaktisch geprüft, Migrationen erfolgreich. Neue Fälle prüfen Konfigurationsgrenzen, tatsächliche Streamgrenze, historische Inhalte und Wiederherstellung, Revisionserwartungen, Quoten, getrennte Rechte, fremde Versionen, Papierkorb-Nachfahren, GC-Schutz und Plugin-Rollback.

Ein isolierter 1000-Dateien-Katalog wird über zehn Seiten ohne fehlende oder doppelte Einträge gelesen. Fünf tatsächlich gleichzeitig gestartete PHP-Prozesse werden über eine Startbarriere freigegeben; bei Quote für drei Uploads werden exakt drei gespeichert und zwei abgewiesen. Die Prozesse verwenden denselben berechtigten Testakteur. Das prüft gemeinsame Quotenserialisierung, nicht einen vollständigen Fünfbenutzer-/Crash-/Leistungsnachweis.

Die echte HTTP-/HTTPS-Prüfung ergänzt Multipart-Ersetzen, Abweisung eines veralteten Replacements mit 409, zunächst verweigerte Historie, ausdrückliche history-Freigabe, Historien-Attachmentdownload und Wiederherstellung. Die bestehenden OIDC-, App-Passwort-, Rollen-, Papierkorb-, Konsistenz- und Einrichtungsprüfungen bleiben erhalten. Detaillierte Ergebnisse stehen im Prüfprotokoll; neue Funktionen sind nicht auf dem Betreiberhost deployed.

Nächste Phase: dauerhafte gemeinsame Ressourcensperren für Browser und DAV, danach der konkrete WebDAV-Protokolladapter. Native Clients, erweiterte Dateioperationen, Medien, administrative Restfunktionen und die übrige Konzeptabnahme bleiben offen. Die Fortsetzung ist durchgehend autorisiert.

Version 1.17: Abgestimmter Größen-/Plattformrahmen, versionierte Inhalte mit separatem Historienrecht, quotenkorrekte Referenzen und tatsächlich geprüfte Parallelitätsgrenze.


## 27. Iteration 17: Persistente gemeinsame Ressourcensperren

### 27.1 Kernverhalten

Eine zusätzliche versionierte Migration führt exklusive Sperren nach Bereich und stabiler Eintragskennung ein. Bereichswurzel, Einzeleintrag und rekursiver Teilbaum sind abgebildet. Gültigkeit 1 bis 3600 Sekunden, ausdrückliche Verlängerung und Freigabe durch den berechtigten Eigentümer mit Token. Bereits abgelaufene Sperren werden ignoriert und bei neuer Anlage im Bereich bereinigt. Sperranlage benötigt files.update und ein sichtbares Ziel; globale Administration gibt keine Sonderfreigabe.

Alle bisherigen Dateimutationen prüfen dieselbe Sperrbasis. Inhalte, direkte Elternmitgliedschaft und betroffene Teilbäume werden unterschieden: Depth-0-Ordnersperren blockieren neue/verschobene Mitglieder, aber nicht den Inhalt vorhandener Kinder; rekursive Sperren schützen alle Nachfahren. Änderungen eines Vorfahren beachten gesperrte Kinder. Zielordner werden ebenfalls geprüft. Datei-Lesezugriffe bleiben unter den bestehenden Rechten möglich.

Ein Token ersetzt keine Rechte und gilt nur für die eigene Sperre im tatsächlichen Bereich. Rename/MOVE behalten die Sperre durch stabile Kennung. Browser ohne mitgegebenen DAV-Nachweis antwortet bei Sperrkonflikt mit 423, auch wenn derselbe Benutzer die Sperre über ein anderes Gerät erworben hat. In dieser Phase existiert noch keine zusätzliche Browser-Sperrverwaltung.

### 27.2 Atomizität und Erweiterungen

Sperranlage, -verlängerung und -freigabe verwenden MutationRunner, Guard vor/nach Validatoren, gemeinsamen Administrationslock und Audit/Outbox. Geprüft wird auch die erwartete Sperrkennung, damit ein zwischenzeitlicher Ersatz nicht als alter Zustand verändert wird. Die geschützten Speicherwriter verwenden diese Prüfung obligatorisch; ein fehlender optional injizierter Guard führt zur Standardprüfung, nicht zu einem erlaubenden Bypass.

API 0.7 ergänzt StorageLockChange mit rekursivem Umfang und begrenzter Laufzeit. MutationContext verlangt den Vorschlag genau für storage.lock.acquire/renew/release im Space-Scope mit lock-Ziel. Tokens und freie Clientlabels sind nicht Teil von Vorschlag/Audit/Outbox. Große Dateiübertragungen bleiben außerhalb der kurzen SQL-Transaktion; vor sichtbarer Freigabe werden Rechte und Sperren erneut geprüft.

Technische Schutzgrenzen: 1000 aktive Sperren und 10000 Katalogeinträge pro Bereich, 32 mitgegebene Tokens pro Mutation. Der Zielbestand von ungefähr 1000 Dateien/fünf aktiven Benutzern wird dadurch nicht zu einer ungeprüften Lastzusage erweitert.

### 27.3 Prüfung

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 340 Tests, 1344 Assertions (147 Unit-/Architekturtests und 193 Integrationstests). Syntax von 159 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Neue Fälle prüfen eigene Sperre ohne Nachweis, fremden Tokenbesitzer, lokale Sperre trotz Token, rekursive/Depth-0-Ordnersperren, geschützte Vorfahren/Ziele, stabile Kennung nach Rename, überlappende Anlage, Bereichsgrenzen, Verlängerung/Freigabe/Ablauf, gelöschte Ziele, Bereichswurzel, Plugin-Veto, ausgeschlossene Tokens/Labels in Ereignissen und erneute Prüfung einer durch Validator eingebrachten Sperre vor dem Schreiben.

Echte HTTP-/HTTPS-Anfragen bestätigen zusätzlich 423 für Browser-Papierkorb und Inhaltsersetzung während einer aktiven persistierten Sperre, weiter möglichen Download und Fortsetzung nach Freigabe. Alle bisherigen OIDC-/App-Passwort-/Datei-/Versions-/Quoten-/Konsistenzprüfungen bleiben erfolgreich. Der 1000-Dateien-/Fünfprozessfall bleibt Teil der Matrix. Neue Funktionen sind nicht auf dem Betreiberhost deployed.

### 27.4 Fortsetzung

Die Sperrbasis ist abgeschlossen; der konkrete WebDAV-Protokolladapter wird nun darauf aufgebaut. Eine separate nur von Sabre verwaltete Locktabelle wird ausdrücklich vermieden. Credential-Widerruf, Origin-/Destination-/XML-/If-/ETag-Grenzen und native Clientabnahme müssen im Adapter geprüft werden. Die übrigen Konzeptpunkte bleiben gemäß Statusmatrix offen. Die Fortsetzung ist weiterhin autorisiert.

Version 1.18: Gemeinsame transaktionale Ressourcensperren mit stabiler Zuordnung, differenziertem Ordnerumfang und nachgewiesenem Browser-Schutz.

## 28. Iteration 18: Geschützter WebDAV-Lesezugang

### 28.1 Öffentlicher Adapter

Der öffentliche Einstiegspunkt leitet /dav/ nach derselben kanonischen Origin-/HTTPS- und Wartungsprüfung an einen eigenen Request-lokalen SabreDAV-Adapter weiter. Browser-Sessions und der lokale Entwicklungsakteur sind kein DAV-Authentifizierungsweg. Basic verwendet ausschließlich eigene App-Passwörter; Benutzername ist die bereits angezeigte interne Samla-Kennung. Neue Abhängigkeiten oder Migrationen sind dafür nicht nötig.

OPTIONS, PROPFIND Depth 0/1, GET und HEAD sind freigeschaltet. Alle Schreibmethoden werden vor Seiteneffekten mit 405 verweigert; OPTIONS meldet ausschließlich den aktuellen Funktionsumfang. Die Liste zeigt nur explizit lesbare Bereiche, stabile Bereichskennungen bilden URL-Wurzeln und displayname liefert lesbare Namen. Ordnernamen werden einmal dekodiert und exakt abgefragt. Für Inhaltszugriffe bleibt zusätzlich files.read nötig, ausdrücklich auch vor HEAD und bedingten Antworten.

### 28.2 Sicherheits- und Streaminggrenzen

Ein AuthorizationChecker-Dekorator verbindet die vorhandene vollständige Plugin-/RBAC-Autorisierung mit erneuter Prüfung des konkreten Credentials auf Eigentümer, Ablauf, Widerruf und aktuellen Identitätsstatus. Der Requestkontext speichert nur Kennungen, kein Passwort. Bereits authentifizierte Requests können mit einem zwischenzeitlich widerrufenen Credential keine weitere Ressourcenfreigabe erhalten.

XML ist auf 64 KiB begrenzt; DTDs einschließlich UTF-16-Darstellung und externe Entitäten werden abgewiesen. Ungültige Prozentkodierung, Steuerzeichen, kodierte Trenner, Punktsegmente, Query und Fragment sind keine Dateipfade. Unendliche PROPFIND-Tiefe und Listen oberhalb der technischen Kataloggrenze sind gesperrt. Fehlerantworten enthalten keine Exceptiontexte, Tokens, SQL oder internen Pfade.

Dateien bleiben private Streams mit Attachment und application/octet-stream. Range-Antworten kopieren exakt Content-Length statt bis zum Dateiende. Auch aufrufbare Multipart-Antworten werden erst bei Ausgabe ausgeführt. ETags verwenden Kennung und Revision; der tatsächliche Download prüft den zur Metadatenaufnahme erwarteten Stand erneut. Ein veralteter Knoten liefert keine neuen Bytes unter alten ETag-/Größenangaben. Sabres eigener SAPI-Ausgabepfad ist gepuffert, damit insbesondere 304-Antworten nicht versehentlich vor Samlas Sicherheitsheadern senden. Last-Modified wird noch nicht behauptet.

### 28.3 Verifikation und Fortsetzung

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 366 Tests und 1423 Assertions (164 Unit-/Architekturtests, 202 Integrationstests), Syntax aller 168 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Echte HTTP-/HTTPS-Anfragen über den öffentlichen Einstiegspunkt bestätigen den Leseadapter einschließlich Range/304/Widerruf. Neue Fälle decken Bereichssichtbarkeit, Pfadkodierung, GET/HEAD/ETag/Range, Leserecht vor 304, Header-Spoofing, Credentialwiderruf nach Authentifizierung, Kontosperre, XML-/DTD-/Tiefengrenzen, verweigerte Schreibmethoden, Papierkorb-Nachfahren, veraltete Downloadknoten und kontrollierte Streamausgabe ab. Der HTTP-Prüfer verwendet einen separaten Client ohne Browser-Cookies.

Der nächste Schritt ist der Schreibadapter für dieselben Kerndienste und persistenten Sperren. Vor Freischaltung sind If-/Destination-Validierung, begrenzte PUT-Streams, Lock-Backend und atomare Namens-/Versionskonflikte nachzuweisen. Native Clients auf iOS, Android, Windows und macOS bleiben gesondert abzunehmen; derzeit wird keine konkrete Gerätekompatibilität behauptet. Weitere Konzeptpunkte bleiben gemäß vorheriger Statusmatrix offen. Die durchgehende Fortsetzung bleibt autorisiert.

Version 1.19: Öffentlicher DAV-Lesezugang mit App-Passwörtern, wiederholter Ressourcenprüfung, bedingten/gestreamten Downloads und begrenztem XML-Protokolladapter.

## 29. Iteration 19: WebDAV-Schreibadapter und wieder verwendbare Namen

### 29.1 Gemeinsame Dateidienste

PUT verwendet die vorhandene private Inhaltsveröffentlichung und versionierte Ersetzung mit erneut erwarteter Revision. MKCOL, MOVE innerhalb eines Bereichs, COPY einzelner Dateien und DELETE verwenden denselben FileService wie Browseränderungen. DELETE legt den Eintrag im Papierkorb ab. Bereichswurzeln können nicht umbenannt oder gelöscht werden. Alle Operationen verwenden explizite Bereichsrechte, obligatorische Kern-Sperrprüfung, Quote, MutationRunner mit erneuten Guards nach Validatoren sowie Audit/Outbox. Das vorgeschlagene Fachereignis ist kein besonderer unkontrollierter DAV-Schreibpfad.

Die neue unveränderliche Migration 0006_active_names ersetzt die bisherige Namenseindeutigkeit durch den generierten aktiven Namen. Papierkorbeinträge reservieren ihren bisherigen Namen nicht mehr. Wiederholtes Löschen/Neuanlegen gleicher Namen bleibt möglich, die bisherigen Inhalte bleiben erhalten und zählen zur Quote. Eine Wiederherstellung bei erneut belegtem Namen ist ein atomarer sichtbarer Konflikt ohne Verdrängung. Auch Browseroperationen profitieren davon. Vor Deployment ist der dokumentierte gesicherte Migrationsablauf erforderlich.

### 29.2 Sperren und Protokollgrenzen

Der Sabre-Lock-Backend-Adapter projiziert ausschließlich die gemeinsame storage_locks-Tabelle über stabile Bereichs-/Eintragskennungen. Vorhandene Ressourcen, Depth 0/infinity, höchstens 3600 Sekunden, Verlängerung und Freigabe sind angeschlossen. Ordnernamen/Pfade werden beim Lesen der Sperren aus aktuellen Eintragskennungen rekonstruiert. Same-actor-Token und normale Rechte bleiben obligatorisch. Browser ohne Token erhält weiterhin 423. Ein fremder berechtigter Benutzer kann mit einem entdeckten Token weder schreiben noch freigeben.

If wird vor Übergabe vollständig auf die von Sabre ausgewertete Grammatik und Grenzwerte geprüft. Nur positive gültige opaquelocktoken-Nachweise gehen an den Kerndienst. Getaggte Ressourcen müssen ebenfalls zur eigenen Origin gehören. Destination wird auf kanonische Origin, Port und DAV-Pfad begrenzt; es gibt keinen Abruf fremder Ziele. Ein Schutz vor beforeUnbind verhindert Sabres nicht atomare vorangestellte Ziellöschung auch bei einem erst später sichtbar werdenden Ziel. Name-/Revisions-/Sperrkonflikte werden zusätzlich unter der Kerntransaktion geprüft.

PUT prüft Schreibrecht vor Übernahme des Inhalts und übernimmt tatsächliche Bytes begrenzt in einen diskgestützten temporären Stream. Höchstens 1 MiB bleibt in diesem zusätzlichen Puffer im Arbeitsspeicher. Danach greift die vorhandene private Veröffentlichung mit erneuter Autorisierung und Sperrprüfung vor SQL-Referenzierung. Headergrenzen, tatsächliche Uploadgrenze, Content-Range-Verbot und leere Finder-Ankünfte bei positiver erwarteter Länge werden geprüft. Eine zusätzliche temporäre Kopie bleibt als bewusster, noch nicht auf Durchsatz optimierter Zwischenschritt bestehen.

### 29.3 Noch begrenzte Fälle

Bestehende MOVE/COPY-Ziele werden ohne Löschung mit 409 beziehungsweise Overwrite F mit 412 abgewiesen. Rekursive Ordnerkopien, bereichsübergreifende Operationen und Lock-null-Anlage benötigen weitere atomare Kerndienste und bleiben ohne Teiländerung gesperrt. PROPPATCH ist nicht freigeschaltet. Diese Einschränkungen sind kein vollständiger Nachweis nativer Clientkompatibilität und bleiben Teil der nächsten Dateioperationen. Browser-Sperrverwaltung, endgültige Papierkorbbereinigung, Medien-/Such-/Sammlungsfunktionen und administrative Restfunktionen sind ebenfalls weiter offen.

### 29.4 Verifikation

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 376 Tests und 1484 Assertions (166 Unit-/Architekturtests, 210 Integrationstests), Syntax aller 173 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Echte HTTP-/HTTPS-Anfragen bestätigen MKCOL, PUT, Versionierung/If-Match, LOCK/UNLOCK, COPY/MOVE und DELETE/Neuanlage neben sämtlichen bisherigen Abläufen. Ein Validator widerruft absichtlich das verwendete Credential innerhalb der Transaktion; der letzte Guard muss Dateireferenz, Audit und Widerruf gemeinsam zurückrollen. Die Clientplattformen bleiben iOS, Android, Windows und macOS; konkrete native Abnahme ist weiterhin gesondert nötig. Neue Funktionen werden nicht automatisch auf dem Betreiberhost ausgerollt.

Version 1.20: DAV-Schreibzugriff über gemeinsame Fach-/Sperrdienste, begrenzte Streams, sichere Protokollpfade und wieder verwendbare Papierkorbnamen.

## 30. Iteration 20: Automatische GitHub-Prüfungen

Der neue GitHub-Workflow übernimmt die vier Zielkombinationen PHP 8.4/8.5 mit MySQL 8.0/MariaDB 11.8. Ein Job installiert Composer-Lockfile und Plattformvertrag, prüft eigene Syntax, Unit-/Architektur-/Integrationstests, Migrations-CLI und anschließend den bestehenden synthetischen HTTP-/HTTPS-Prüfer. Dieser deckt OIDC, App-Passwörter, DAV-Lese-/Schreib-/Sperroperationen und bisherige Browser-/Speicherabläufe ab. Die isolierte Python-Testabhängigkeit wird separat fixiert; die Produktionsanwendung erhält keine neue Laufzeitkomponente.

Die Service-Datenbank und Credentials sind ausschließlich für kurzlebige isolierte Tests bestimmt. Kein echtes IdP-Konto und keine Produktionsdaten werden verwendet. Checkout und PHP-Setup sind anhand offizieller Quellrepos auf volle Commitkennungen fixiert. Repository-Token nur contents:read, Checkout ohne persistierte Credentials, normale Pull-Request-Ausführung ohne privilegierten Fremdcodekontext, keine Deployment-/Nachrichtenaktion.

YAML-Struktur, vier Kombinationen, vollständige Actionpins und eingeschränkte Berechtigungen wurden lokal geprüft. Der Anwendungsstand bleibt die erfolgreich geprüfte Iteration 19 mit jeweils 376 Tests und 1484 Assertions. Eine erneute unveränderte Laufzeitmatrix ist für diese Workflow-/Dokumentationsänderung nicht als neuer Nachweis erfunden. Der tatsächliche erste GitHub-Lauf wird nach Veröffentlichung kontrolliert; native Client-/Crash-/Last-/Restore-Nachweise bleiben offen. Danach werden die übrigen Dateioperationen und Konzeptpunkte ohne allgemeine Go-Rückfrage weiter umgesetzt.

Version 1.21: Isolierte vierfache GitHub-Prüfung mit echten HTTP-/HTTPS-Testabläufen und nachvollziehbarer Trennung von lokaler Prüfung und GitHub-Laufstatus.

Erster Lauf 39692714418: GitHub wies die Portabfrage in jobs.matrix.env vor Jobanlage mit einem Kontextfehler ab. Iteration 20.1 bindet den dynamischen Serviceport stattdessen in einem Ausführungsschritt über GITHUB_ENV ein. Die öffentliche Fehlerannotation wurde gelesen; kein erfolgreicher Lauf wird aus der YAML-Prüfung abgeleitet. Die weitere Laufabnahme bleibt Teil dieser Phase.

Folgelauf 39692926549: Beide MariaDB-Jobs sind erfolgreich. Die MySQL-Tests benötigen für ihre absichtlich fehlerauslösenden Triggerfixtures eine Binary-Logging-Testfreigabe (Fehler 1419 im gelesenen Joblog). Iteration 20.2 aktiviert diese ausschließlich im kurzlebigen isolierten MySQL-CI-Container über dessen Test-root. Das Anwendungskonto behält seine datenbankbegrenzten Rechte; produktive Migrationen benötigen diese Fehler-Trigger nicht. Der korrigierte Vierfachlauf wird erneut kontrolliert.

Abgeschlossen: GitHub-Lauf 39693200739 zu cd92c7df05fba354f188a757052ade6649576d88 ist in allen vier Jobs erfolgreich. Status und Joblogs sind gelesen. PHP 8.4.27/8.5.12 mit MySQL 8.0.46/MariaDB 11.8.9: pro Job 376 Tests mit 1484 Assertions und vollständiger HTTP-/HTTPS-Prüfer. Der frühere lokale Nachweis mit PHP 8.4.5/8.5.4 und MariaDB 11.8.6 bleibt getrennt erhalten. Keine native Client- oder Betreiberabnahme behauptet. Die nächste Iteration ergänzt atomisches MOVE/COPY-Überschreiben statt vorangestellter Ziellöschung.

## 31. Iteration 21: Atomares MOVE/COPY-Überschreiben

### 31.1 Ein gemeinsamer Fachvorgang

Der DAV-Adapter dispatcht MOVE/COPY nach den normalen Sabre-Bedingungs-/Sperrprüfungen in einen eigenen Handler, der exakt einen FileService-Vorgang ausführt. Die nicht atomare Bibliotheksfolge Ziellöschung/Transfer wird nicht benutzt. Ein vorhandenes ungesperrtes gleichartiges Ziel wird mit erwarteter Kennung und Revision erfasst. Innerhalb des gemeinsamen Mutationslocks wird sein bisheriger Eintrag in den Papierkorb gelegt und danach die Quelle verschoben beziehungsweise eine Kopie angelegt. Frühere Zielinhalte und Historien bleiben erhalten und zählen weiterhin zur Quote.

Die Guards prüfen Quelle und Ziel, die zusätzlichen update/delete-Rechte, Revisionen, Namen, Orte, Sichtbarkeit, Sperren und Zyklen vor und nach Plugins. targets.replaced ergänzt die stabile bisherige Zielkennung im bestehenden StorageChange-/MutationContext-Vertrag; Audit/Outbox enthalten keine Freitextnamen oder Tokens. Ein Quoten-/Audit-/Outboxfehler rollt beide Seiten zurück. Eine nachträglich aufgetauchte oder geänderte Zieldatei wird nicht ungeprüft verdrängt. Overwrite F liefert 412, Quelle gleich Ziel und Typwechsel sind Konflikte. Das Ersetzen eines Vorfahren der Quelle wird abgewiesen.

### 31.2 Leases und verbleibende Grenzen

Ein vorhandener direkt oder im Teilbaum gesperrter Zieleintrag bleibt mit 423 geschützt, auch wenn der Benutzer dessen Token vorlegt. Das bewahrt die stabile Zuordnung statt einer stillen Neuzuordnung an die verschobene Quelle. Direkte versionierte PUT-Ersetzung bleibt mit gültigem Nachweis möglich; MOVE/COPY kann nach ausdrücklicher Zielfreigabe erfolgen. Übergeordnete Ordnersperren gelten weiterhin mit regulärer Nachweisprüfung.

Ein autorisiertes DELETE mit vollständigen Nachweisen entfernt die betroffenen Leases im selben Papierkorb-/Audit-/Outboxvorgang. Danach entsteht unter dem wieder verwendeten Namen keine alte Sperre. Die begrenzte Teilbaumermittlung berücksichtigt auch aufbewahrte Nachfahren. Alte bereits auf unsichtbaren Papierkorbeinträgen liegende Leases aus Vorversionen werden nicht als ungültiger DAV-Pfad ausgegeben; die Kernprüfung bleibt bis Freigabe/Ablauf konservativ.

Sabres nachgelagerte pfadbasierte afterUnbind-Sperrbereinigung ist im Adapter ausdrücklich deaktiviert. Die feste Kennung und die SQL-Transaktion sind maßgeblich; ähnlich beginnende Namen benachbarter gesperrter Dateien dürfen weder entsperrt werden noch nach erfolgreichem Commit einen falschen Fehler auslösen. Ein eigener Fall prüft dieses Präfixnachbarverhalten.

Lock-null-Anlage, rekursive Ordnerkopien, bereichsübergreifende Vorgänge und der Umgang bestimmter nativer Programme mit gesperrten Überschreibzielen bleiben weiter umzusetzen beziehungsweise abzunehmen. Keine zusätzliche Migration oder Laufzeitabhängigkeit.

### 31.3 Verifikation

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 382 Tests und 1523 Assertions (166 Unit-/Architekturtests, 216 Integrationstests), Syntax aller 174 eigenen PHP-Dateien und Migrations-CLI erfolgreich. HTTP/HTTPS bestätigt COPY/MOVE-Überschreiben samt korrekten Zielbytes und abgewiesenem Overwrite F. Erfolgreiches Überschreiben, aufbewahrte Ziele, Auditfehler mit gemeinsamem Rollback, Quotenfehler ohne Inhaltsverlust, gesperrte Ziele, DELETE-Leasebereinigung, geschützte Präfixnachbarn und konservative unsichtbare Alt-Leases sind geprüft. Die bereits erfolgreiche GitHub-Matrix wird nach Veröffentlichung auch diese neue Implementierung prüfen.

Version 1.22: Atomarer Erhalt bisheriger MOVE/COPY-Ziele, doppelte Zielprüfung und transaktionale Leasebereinigung beim Papierkorb.

GitHub-Nachweis Iteration 21: Lauf 39695089226 zu 022ee2f9a5626b51b06b6cebfbf713ed552ab0a8 erfolgreich in allen vier Jobs. Status und vollständige Joblogs gelesen: PHP 8.4.27/8.5.12, jeweils 166 Unit-/Architekturtests mit 540 Assertions, 216 Integrationstests mit 983 Assertions sowie HTTP/HTTPS. Damit 382 Tests und 1523 Assertions pro Job. Keine native Geräteabnahme daraus abgeleitet.

## 32. Iteration 22: Neue Datei und exklusive Sperre atomar anlegen

### 32.1 Gemeinsamer Änderungsvorschlag

LOCK auf einen noch nicht vorhandenen Dateinamen legt die neue leere Datei und ihre exklusive Sperre in einem einzigen FileService-/MutationRunner-Vorgang an. Der Status ist 201; nachfolgender PUT braucht den erhaltenen Token und archiviert den bisherigen leeren Stand regulär. Es gibt keine vorangestellte, bereits sichtbar gewordene Bibliotheks-Dateianlage. Dateien bleiben außerhalb des öffentlichen Verzeichnisses und werden vor SQL-Referenzierung unveränderlich veröffentlicht.

API 0.8 ergänzt die zusammengesetzte Operation storage.file.create_locked: StorageChange und StorageLockChange sind beide verpflichtend, targets enthält entry und lock. Guards prüfen files.create und files.update, Namen, Eltern, Sichtbarkeit, Quote, Sperr-/Katalogkapazität und Konflikte vor und nach Plugins. Ein Veto oder fehlgeschlagener Lease-INSERT rollt Dateireferenz, Lease, Audit und Outbox gemeinsam zurück. Bereits veröffentlichte unreferenzierte Objekte folgen wie andere unklare/fallierte Veröffentlichungen der kontrollierten Bereinigung; Token und freie Ownerdaten bleiben aus Fachvorschlag/Ereignissen ausgeschlossen.

### 32.2 Korrekte Protokollgrenzen

Der Lock-Adapter validiert die zwingenden lockinfo-/lockscope-/locktype-Strukturen nach der bestehenden begrenzten und DTD-freien XML-Prüfung. Leere Bodys verlängern ausschließlich vorhandene Sperren. Gemeinsame Sperren sind nicht implementiert und werden auch nicht mehr in supportedlock beworben. Lockroot-URIs für Unicode-/Leerzeichen-/Prozentnamen werden genau einmal pro Komponente kodiert, während interne Operationen weiterhin dekodierte Pfade verwenden. Exklusive rekursive Vorfahrensperren dürfen nicht durch eine weitere überlappende Neuanlage dupliziert werden.

Die bereits vorhandene technische Grenze von 10000 aufbewahrten Einträgen pro Bereich wird jetzt vor jeder Datei-/Ordner-/Kopier-/LOCK-Neuanlage geprüft. Das verhindert das bisher mögliche Überschreiten um einen Eintrag und erhält die Nutzbarkeit des gemeinsamen Sperrguards an der Grenze. Bestehende Einträge können weiterhin gelöscht werden. Papierkorbeinträge zählen zur Grenze; endgültige Bereinigung wird separat ausdrücklich berechtigt ergänzt.

### 32.3 Prüfung und Fortsetzung

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 390 Tests und 1567 Assertions (167 Unit-/Architekturtests, 223 Integrationstests), Syntax aller 174 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Neue Fälle prüfen neue leere Datei samt Lease, korrektes Schreiben/Entsperren, ausgeschlossene Tokens im Audit, getrennte Rechte, abgewiesene Shared-Sperre, tatsächliche supportedlock-XML-Struktur, kodierte Lockroots, überlappende Vorfahren, Lease-INSERT-Fehler ohne Teilzustand, Plugin-Veto und die 10000-Eintragsgrenze über alle Anlagewege bei weiter möglichem DELETE. Ein zunächst falsch klassifiziertes unvollständiges Lock-XML wird nun als ungültige Struktur mit 400 abgewiesen; Shared bleibt davon getrennt 501.

Echte HTTP-/HTTPS-Anfragen bestätigen zusätzlich LOCK-Neuanlage (201), PUT mit If-Nachweis (204) und UNLOCK (204). Alle bisherigen OIDC-/DAV-/Browser-/Versions-/Quoten-/Konsistenzprüfungen bleiben erfolgreich. Keine neue Migration oder Laufzeitabhängigkeit. Der folgende Schritt ist die explizit berechtigte endgültige Papierkorbbereinigung, danach die verbleibenden Baum-/Bereichsoperationen. Medien-, administrative Restfunktionen und native Clientabnahme bleiben offen; die Fortsetzung bleibt autorisiert.

Version 1.23: Atomare Datei-/Sperranlage, vollständiger gemeinsamer Plugin-Vorschlag, korrekt beworbene Sperren und eingehaltene Kataloggrenze.

GitHub-Nachweis Iteration 22: Lauf 39696359449 zu dba1a5ea83178981a89756508849fabdd0a31d15 erfolgreich in allen vier Jobs. Status und vollständige Logs gelesen: PHP 8.4.27/8.5.12, jeweils 167 Unit-/Architekturtests mit 545 Assertions, 223 Integrationstests mit 1022 Assertions sowie HTTP/HTTPS. Damit 390 Tests und 1567 Assertions pro Job. Keine native Clientabnahme daraus abgeleitet.

## 33. Iteration 23: Explizite endgültige Papierkorbbereinigung

### 33.1 Rechte und Benutzerentscheidung

Das neue Bereichsrecht files.purge ist von Papierkorbzugriff, normalem Löschen und Wiederherstellen getrennt. Keine bestehende manage-Rolle wird erweitert. Der Offline-Bereichsinstaller unterstützt das ausdrücklich ergänzende purge-Profil. Die Papierkorbübersicht kann mit list und restore oder purge gelesen werden; nur berechtigte Aktionen erscheinen. Im Browser erfordert endgültiges Löschen POST, gültiges CSRF, erwartete Revision und eine eigene ausdrückliche Bestätigung. Der Benutzer bestätigt den gesamten Inhalt einschließlich Nachfahren und früherer Versionen. Aktive Wurzeln sind nicht zulässig; ein ausdrücklich gelöschtes Kind kann auch unter einem ebenfalls gelöschten Elternordner bereinigt werden.

### 33.2 Unveränderlicher Teilbaumvorschlag und Atomizität

API 0.9 ergänzt StoragePurgeChange und die Operation storage.entry.purge. Der Vorschlag enthält höchstens 10000 stabile Kennungen mit erwarteten positiven Revisionen, kanonisch nach Zeichenfolgen sortiert und gegen nachträgliche Änderung über übergebene PHP-Skalarreferenzen kopiert. MutationContext verlangt den Wurzeleintrag in der Map und den bisherigen StorageChange-Wurzelkontext. Vor und nach Validatoren werden Identität, Rechte, Wurzelzustand, gesamter aktueller Teilbaum und betroffene Sperren erneut geprüft. Änderungen eines Kindes invalidieren den gesamten Plan.

Ein SQL-Vorgang entfernt die betroffenen Leases und Katalogeinträge samt kaskadierenden Historien, schreibt ein Audit und vollständige begrenzte Abschlussereignisse. Je Outbox-Chunk höchstens 500 Eintragskennungen, gemeinsame batch_id und einzelne eindeutige payload.id mit chunk_index/chunk_count. Dadurch bleiben Payloads unter der TEXT-Grenze, ohne große Teilbäume ungeprüft abzuschneiden. Ein Fehler auch im zweiten Chunk rollt Löschung, Audit und frühere Chunks gemeinsam zurück. Empfänger müssen nach Ereigniskennung deduplizieren; die Zustellung selbst ist weiterhin gesondert umzusetzen.

### 33.3 Dateiobjekte und Grenzen

Die logische Quote und Katalogplätze werden frei. Private unveränderliche Objekte werden durch diesen Vorgang nicht physisch gelöscht. Die separate bestehende Objektbereinigung beachtet weiterhin verbleibende aktuelle/historische Referenzen, geteilte Inhaltsobjekte und das Objektalter. Die 24-Stunden-Grenze ist keine Wiederherstellungsfrist ab Purge. Im Produkt ist eine endgültig entfernte Katalogreferenz nicht mehr wiederherstellbar. WebDAV DELETE bleibt normaler Papierkorb, eine zeitgesteuerte Retentionsregel wird nicht erfunden.

### 33.4 Prüfung und Fortsetzung

Vierfachmatrix PHP 8.4.5/8.5.4 mit MySQL 8.0.46/MariaDB 11.8.6: jeweils 402 Tests und 1627 Assertions (170 Unit-/Architekturtests, 232 Integrationstests), Syntax aller 177 eigenen PHP-Dateien und Migrations-CLI erfolgreich. Echte HTTP/HTTPS-Anfragen prüfen das separate CLI-Profil, ausdrückliche Bestätigung und endgültige Bereinigung. Geprüft sind explizite Rechte, fremde Bereiche/Benutzer, aktive/veraltete Wurzeln, gelöschte Eltern, Quoten-/Historienbereinigung, Erhalt gemeinsam verwendeter Objekte bei anschließender GC, vollständige Teilbäume über mehrere Outbox-Chunks, Fehler im zweiten Chunk mit vollständigem Rollback, geänderte Nachfahren, erhaltene aktive Leases und getrennte Purge-/Restore-Oberfläche. Opaque hexadezimale Kennungen werden auch in Testvergleichen explizit als Zeichenfolgen sortiert; eine zufällig wissenschaftlich lesbare Kennung darf keine numerische Sortierung auslösen.

Keine neue Migration oder zusätzliche Laufzeitkomponente. Native Clients, vollständige Crash-/Last-/Restore-Abnahme, Medien, verbleibende Baum-/Bereichsoperationen und administrative Restfunktionen bleiben gesondert offen. Die durchgehende Umsetzung bleibt autorisiert.

Version 1.24: Explizit berechtigte endgültige Papierkorbbereinigung mit unveränderlichem Teilbaumplan und vollständig transaktionalen Outbox-Chunks.


## 34. Iteration 24: Geschützte Textansicht und versionierter Browsereditor

### 34.1 Nutzung und Grenzen

Unterstützte Text-, Markdown-, JSON-, YAML- und weitere Quelltextdateien lassen sich bis 64 KiB im Browser öffnen. UTF-8 ist verpflichtend; NUL, verbotene Steuerzeichen, ungültige Kodierung und nicht unterstützte Endungen werden verworfen. Die Anzeige escaped sämtliche Inhalte; HTML, PHP und Markdown werden niemals ausgeführt oder gerendert. Nur Leseberechtigte sehen gespeicherten Inhalt. Ohne update bleibt die Textansicht schreibgeschützt. LF-Zeilenumbrüche werden beim Speichern ausdrücklich vereinheitlicht. Kein neuer Renderer, Editorbaustein, PHP-Modul oder Serverdienst.

### 34.2 Konflikte und Erweiterungsverträge

Speichern verlangt list/read/update und die beim Öffnen gelesene Revision. Die vorhandene FileService-Versionierung prüft read auch vor und nach Validatoren unter dem gemeinsamen SQL-Lock. Historien, Quoten, Sperren, Audit und Outbox bleiben gemeinsam geschützt. Der bestehende StorageChange-/ChangeValidator-/Abschlussereignisvertrag gilt auch für Textänderungen. Eine geänderte Datei erzeugt 409; der eigene Entwurf bleibt escaped im Formular, kann als Attachment heruntergeladen werden und wird niemals automatisch über den fremden Stand geschrieben. Dieselbe Entwurfsbehandlung gilt bei aktiver Sperre oder Quoten-/Versionsgrenze. Der Entwurfsdownload exportiert ausschließlich eigene Formulardaten mit CSRF und liest keine gespeicherten Inhalte.

### 34.3 Prüfung und Fortsetzung

Neue Integrationstests prüfen Quelltext-Escaping, Nur-Lesen, fehlende Rechte und fremde Benutzer, ursprüngliche Historie, erfolgreichen Versionswechsel, Konfliktsicherung und Entwurfsattachment, Format-/Kodierungs-/Größengrenzen, CSRF/Methoden, aktive Sperren, Quote und einen Leserechteentzug im Pluginvalidator vor dem zweiten Guard. Maximale 64-KiB-LF-Texte werden auch nach der CRLF-Übertragung eines Browsers korrekt verarbeitet. Der echte HTTP-/HTTPS-Prüfer ergänzt Formulargrenze, Revision, Quelltextanzeige, Speichern und Entwurfsdownload.

Die endgültigen Ergebnisse stehen in verification.md. Die erfolgreiche vorherige GitHub-Matrix ist in verification-iteration-23.md dokumentiert. Keine neue Migration oder Laufzeitkomponente. Medienbibliothek und Metadaten, öffentliche Viewer-/Editorprovider, verbleibende Datei- und Verwaltungsfunktionen, Zustellung der Outbox sowie Betriebs- und echte Clientabnahme bleiben offen. Weitere Umsetzung ist autorisiert; notwendige Komponentenentscheidungen bleiben ausdrücklich abzustimmen.

Version 1.25: Begrenzter UTF-8-Editor mit gemeinsamer Versionierung, erneutem Leseguard und sicherbarer Konfliktfassung.


## 35. Iteration 25: Sichtbare Namen über einen Speicherbereich suchen

### 35.1 Suchumfang und Bedienung

Die Weboberfläche ergänzt eine bereichsweite Suche über Dateinamen und Ordnernamen mit Datei-/Ordnerfilter, logischem Elternpfad und Cursorseiten bis 100 Treffern. Die Unicode-Groß-/Kleinschreibung wird ignoriert, Sonderzeichen werden wörtlich gesucht. Leere Suche zeigt sichtbare Einträge. Suchtext und Typ bleiben beim Seitenwechsel erhalten. Es werden weder Dateiinhalte noch Papierkorb oder Fremdbereiche durchsucht.

### 35.2 Sichtbarkeit und öffentlicher Vertrag

FileService.search verwendet das bestehende Bereichsrecht files.list und die öffentlichen Autorisierungs-/Einschränkungsverträge. Aktive Identität und Gruppenfrische bleiben verpflichtend. Treffer unter gelöschten, fehlenden, zyklischen oder unzulässigen Eltern sind ausgeschlossen. Download verlangt weiterhin sein eigenes Leserecht. Der Katalogscan bleibt auf 10000 Einträge einschließlich gelöschter Einträge begrenzt und verweigert darüber unvollständige Ergebnisse. Kein externer Suchdienst, keine Schreiboperation, keine Migration oder Laufzeitabhängigkeit. Zusätzliche Suchprovider und Metadaten-/Tagsuche bleiben offen.

### 35.3 Prüfung und Fortsetzung

Neue Integrationsfälle prüfen Unicode, wörtliche Prozent-/Unterstrichzeichen, fehlende Inhaltsindizierung, Dateitypfilter und Ordnerpfad; Papierkorbeltern, beschädigte Elternketten; list ohne read, fremde Benutzer/Bereiche, lokale Sperre und Pluginrestriktion; Escaping und ungültige Eingaben; vollständige duplikatfreie 1000-Einträge-Pagination und Verweigerung ab 10001 Katalogeinträgen. Der echte HTTP-/HTTPS-Prüfer ergänzt Suche über verschachtelte Namen und Filterfehler. Endgültige Matrixergebnisse stehen in verification.md; die erfolgreiche vorige GitHub-Matrix in verification-iteration-24.md.

Weitere Medien-, Datei-, Verwaltungs- und Betriebsfunktionen werden ohne erneutes allgemeines Go fortgesetzt. Neue Runtime-/Betriebskomponenten bleiben vor Einsatz abzustimmen.

Version 1.26: Bereichsweite Namenssuche mit Sichtbarkeitsprüfung, Typfilter und begrenzter Pagination.


## 36. Nächste Etappe: Zustellung der Outbox und Medienaufgaben

Die vorher ausdrücklich offene Entscheidung zur Hintergrundverarbeitung wird vor Einführung einer neuen Betriebsanforderung eingeholt. docs/background-processing-decision.md beschreibt den konkreten Vorschlag: vorhandenes PHP-CLI, geplanter Aufruf pro Minute als Subscription-Benutzer, höchstens 50 Zustellungen, kooperatives 45-Sekunden-Budget, eigener Überlappungsschutz, persistente Empfänger-/Ereigniszustände, begrenzte Leases und idempotente Wiederholungen bis zum sichtbaren Fehlerstatus. Es werden keine automatische GC oder zusätzliche Aufbewahrungsregeln eingeschaltet.

Nach Zustimmung folgen öffentliche Empfängerverträge, Migration, Dispatcher, Diagnose und Referenzempfänger einschließlich echter Parallelitäts-/Fehlerprüfungen gegen beide Datenbanken. Medienhandler binden Ergebnisse anschließend an konkrete Inhaltsversionen. Hosteinrichtung ist eine eigene Betreiberabnahme nach fertiger CLI. Der noch nicht vorhandene Worker wird nicht als implementiert oder als zugestellt ausgewiesen.

Benötigte Entscheidung: Darf und kann auf dem Plesk-Host ein PHP-CLI-Aufruf pro Minute unter dem Subscription-Benutzer als geplante Aufgabe laufen? Das Konzept hält PHP-CLI/Cron ausdrücklich für einen Vorschlag und verlangt bei fehlendem Cron eine abgestimmte Alternative. Die bisherigen Umsetzungen sind veröffentlicht; es ist kein allgemeines Fortsetzungs-Go erforderlich, sondern diese konkrete Betriebsentscheidung.

Version 1.27: Reviewbare Entscheidungsvorlage für die nächste Hintergrundverarbeitungsphase.

UI-Abdeckung zu den veröffentlichten Funktionen und verbleibenden CLI-/Backendlücken ist in docs/ui-status.md dokumentiert. Veröffentlichung und Betreiberdeployment werden getrennt ausgewiesen.


## 37. Iteration 26: Freigegebener Worker mit CLI- und Webzugang

### 37.1 Betreiberentscheidung und Zugänge

Der Betreiber bestätigt PHP-CLI per Cron und verlangt zusätzlich einen Webrequest-Trigger für Hoster ohne CLI-Cron. Beides ist mit demselben OutboxWorker umgesetzt. CLI erlaubt bis 50 Ereignisversuche oder reine Statusabfrage. POST /tasks/run ist standardmäßig aus, verlangt kanonisches HTTPS und ein separates zufälliges Token als Bearer-Header oder POST-Formularwert. In .env liegt nur dessen SHA-256. URL-Parameter und GET lösen keine Arbeit aus. Token kann mit lokalem PHP oder dem ausgelieferten Offline-Web-Crypto-Werkzeug erzeugt werden. Auf dem Host wurde kein Cronjob eingerichtet und kein produktives Token erzeugt.

### 37.2 Öffentlicher Vertrag, Zustellzustände und Fehler

API 0.10 ergänzt OutboxEvent, OutboxSubscription und OutboxHandler sowie atomare besitzgebundene Pluginregistrierung. Migration platform.administration:0002_outbox_delivery führt Ereignissequenz, Empfängerwasserstände, dauerhafte Bedienreihenfolge, Zustellleases und Referenzquittungen ein. Ein separater Datenbanklock verhindert überlappende CLI-/Webläufe; der fachliche globale Lock wird nur für die kurze Discovery gehalten, damit niedrigere uncommittete Sequenzen nicht übersprungen werden. Mindestens-einmal-Zustellung verlangt Idempotenz je Empfänger und Ereignis. Abbrüche vor Quittierung können wiederholt werden. Offene Handlertransaktionen werden verworfen.

Discovery ist je Empfänger auf 100 passende neue Ereignisse begrenzt, Zustellversuche insgesamt auf 50. Nach 45 Sekunden wird kein weiterer Handler begonnen; bereits laufende Aufgaben werden nicht hart abgebrochen. Leases laufen nach 120 Sekunden aus. Fehler erhalten exponentielle Wiederholabstände und nach acht Versuchen einen sichtbaren Fehlerstatus. Deaktivierte Empfänger werden nicht aufgerufen; Typ-/Replayänderungen erfordern eine neue Kennung. Der idempotente Referenzempfänger belegt ausgewählte Storage-Abschlussereignisse ohne Dateien oder Secrets zu kopieren. Medienextraktion, Vorschauen und ein administrativer Retrybildschirm sind damit noch nicht implementiert.

### 37.3 Verifikation, Installation und Fortsetzung

Unitprüfungen decken öffentliche DTO-/Registrierungsgrenzen und Referenzkopien, Tokenauthentifizierung ohne Sitzung, deaktivierten Webtrigger, Methoden-/URL-Verweigerung und verzögerte Worker-/DB-Erzeugung nach Authentifizierung ab. Echte Integrationen prüfen begrenzte historische Zustellung, Wasserstände, Retry bis Fehlerstatus, malformed Events, parallele sowie rekursive Aufrufe, Abbruch zwischen Wirkung und Quittierung, Leaseablauf, achte abgebrochene Zustellung, kleine faire Batches, Vertragsänderung, offene Handlertransaktion und Discovery bei einer noch nicht abgeschlossenen niedrigeren Sequenz. HTTP/HTTPS und CLI werden zusätzlich über die tatsächlichen Einstiegspunkte geprüft. Finale Ergebnisse stehen in verification.md.

Vor Nutzung ist die neue Migration im Wartungsmodus erforderlich. docs/background-worker.md beschreibt Plesk-Aufgabe, Webtrigger, Tokenrotation, Status und Grenzen. Eine visuelle Adminoberfläche für Zustellung/Retry, fachliche Medienhandler, weitere Konzeptfunktionen sowie Host-/Geräte-/Last-/Restore-Abnahme bleiben offen. Die durchgehende Umsetzung ist weiter autorisiert; zusätzliche Komponenten wie C2PA-Validatoren bleiben vor Einsatz abzustimmen.

Version 1.28: Gemeinsamer Outbox-Worker per freigegebenem CLI-Cron und authentifiziertem HTTPS-POST mit dauerhaften Zustellzuständen.


## 38. Iteration 27: Workerzustellungen im Browser verwalten

### 38.1 Ansicht und explizite Rechte

Die Navigation ergänzt Hintergrundaufgaben mit globalem operations.worker.read. Zähler, Zustellstatusfilter, bis 100 Zeilen pro Cursorseite und technische Empfänger-/Ereigniskennungen werden ohne Payloads, Tokens oder Dateiinhalte dargestellt. operations.worker.retry erlaubt zusätzlich das erneute Vormerken endgültiger Fehler aktiver Empfänger. Neue Rechte werden nicht automatisch vergeben. Ein lokaler Wartungshelfer vergibt beziehungsweise entzieht ausdrücklich read/manage-Profile an interne Gruppen und prüft frischen aktiven Administrator und unveränderte reservierte Rollen.

### 38.2 Revisionsschutz, Hooks und Transaktion

Migration platform.administration:0003_delivery_revision ergänzt eine Zustellrevision. Claim, Quittierung, finales Leaseende und manuelles Retry erhöhen sie. Ein altes Formular kann daher auch nach einem weiteren Fehlerzyklus keinen neuen Versuch unbemerkt zurücksetzen. API 0.11 ergänzt OutboxRetryChange im exakten globalen MutationContext. Rechte, failed-Zustand, aktive Empfänger und erwartete Revision werden vor/nach Pluginvalidatoren geprüft. Pending-/Processing-/Delivered-Zustände und deaktivierte Empfänger sind ausgeschlossen. Reset, Audit und Outbox-Abschlussereignis committen gemeinsam. Das Formular benötigt POST und CSRF; es führt keinen Handler unmittelbar aus.

### 38.3 Prüfung und Fortsetzung

Neue Integrationstests decken separate Rechte und lokale Sperre, Nur-Lesen ohne Payloads, deaktivierte Empfänger, korrektes und veraltetes Retry einschließlich ABA-Folge, Rechteentzug im Validator, Auditfehlerrollback, tatsächliche Webzusammensetzung/Navigation, Filter, CSRF, Methode, doppelte Formulare sowie vollständige Pagination und Escaping ab. Öffentliche DTO-Kontexte prüfen genaue Ziele und Scope. Der echte HTTPS-Prüfer verweigert unberechtigte Ansichten und Wartungshelfer ohne Wartung, vergibt/entzieht Profile und prüft das Browser-Retry samt anschließendem gemeinsamen Worker. Ergebnisse stehen in verification.md; die erfolgreiche vorige CI in verification-iteration-26.md.

## 39. Folgende Medienphase: gesonderte EXIF-Entscheidung

Für korrekt orientierte Handyfoto-Ableitungen und ausgewählte EXIF-Felder wird die native PHP-Erweiterung ext-exif vorgeschlagen. Die konkrete Vorlage docs/exif-library-decision.md beschreibt Umfang, Stream-/Größen-/Datenschutzgrenzen und Fallback ohne Modul. Eine isolierte Evaluation außerhalb der Anwendung hat unter PHP 8.4.5/8.5.4 ein künstliches JPEG mit Orientierung 6 korrekt aus einem Stream gelesen und eine ungültige Datei kategorisiert; Originalprüfsummen blieben unverändert. Die Komponente ist noch nicht in Samla integriert. Gemäß Konzept und AGENTS wird vor der Einbindung die ausdrückliche Freigabe eingeholt. ExifTool und C2PA-Werkzeuge bleiben separate offene Entscheidungen.

Version 1.29: Berechtigte Worker-Verwaltungsansicht mit atomarem, revisionsgebundenem Retry und vorbereiteter EXIF-Komponentenentscheidung.


## 40. Iteration 28: Freigegebene EXIF-Basis und geschützte Medienansichten

Die ausdrückliche Betreiberfreigabe „Exif erweiterung ist ok“ vom 22. Oktober 2026 erfüllt den offenen Komponentenentscheid aus Abschnitt 39. ext-exif wird für ausgewählte JPEG-/TIFF-Felder und alle acht Orientierungen verwendet. ExifTool, C2PA und weitere Parser bleiben gesondert abzustimmen.

Implementiert werden namensraumgebundene öffentliche MediaField-/MediaProvider-Verträge, begrenzte unveränderliche Quellen, typisierte Werte mit Rohwert/Normalisierung/Quelle/Status sowie ein enger PNG-Vorschauvertrag ohne Text-/EXIF-Chunks. Der gemeinsame Outbox-Worker verarbeitet aktuelle Dateiinhalte; Originale bleiben unverändert. Private SQL-Ableitungen sind an Blob, SHA-256, Größe und Provider-/Feldsignatur gebunden. Neue Migration platform.media:0001_assets.

Browsernavigation Medien mit Bereichs-/Ordnerseiten und Detailansicht, revisionsgebundene geschützte Vorschauroute, getrennte Bereichsrechte media.previews.read, media.metadata.read und media.process. Sensible Erweiterungsfelder verlangen eigene Rechte. Bestehende Rollen werden nicht erweitert; das ausdrückliche CLI-Profil media ergänzt die vorhandenen Dateirechte. Analysevormerkung ist CSRF-/Revisions-/Validator-geschützt, auditiert und pro Datei auf eine pro Minute begrenzt. CLI-Cron und HTTP-Trigger verwenden denselben Handler.

GD unterstützt JPEG/PNG/GIF/WebP, TIFF bleibt Metadatenbasis ohne Vorschau. 16 MiB Quellen, 16.777.216 Pixel, Speicherabschätzung, 480-Pixel-Vorschau, 20.000 Cachezeilen und 256 MiB Vorschauinhalt begrenzen diesen ersten Ausbau. Keine automatische Cachelöschung oder Vollrebuild, kein harter Parserzeitabbruch. Dokumentation: media.md.

Prüfung und tatsächlicher Veröffentlichungsstand: verification.md. Offen bleiben weitere Feldtypen, manuelle Katalogpflege, IPTC/XMP/KI/C2PA, Tags/Sammlungen, Export/Mediensuche, vollständiger Rebuild und Cachepflege sowie die weiteren Datei-/Verwaltungs-/Betriebs- und Geräteabnahmen. Die weitere Umsetzung ist autorisiert und erfordert kein erneutes Go je Phase.

Version 1.30: Native EXIF-Erweiterung freigegeben; erster Medienprovider, privater Ableitungscache und berechtigte Medienoberfläche.


## 41. Iteration 29: Medienanalyse für Bestände und Ableitungsbereinigung

Ein Bereich kann mit media.process seitenweise neu analysiert werden. Ein Formular umfasst höchstens 100 sichtbare Dateien einschließlich Unterordnern und bindet deren aktuelle Revisionen. MediaBatchRefreshChange und ein exakt gebundener MutationContext bilden den öffentlichen Vorschlag. Vor und nach Erweiterungsvalidatoren werden Rechte und Einträge erneut geprüft. Kürzlich vorgemerkte Dateien sind ausgenommen; konkurrierende Änderungen führen zum vollständigen Rollback der Seite. Pro Datei entstehen ein nachvollziehbarer Auditdatensatz und eine eigene Workerzustellung mit Batchbezug. Ein Fehler in späteren Kindereignissen rollt auch frühere Vormerkungen, Audit und Zustellungen zurück. Kein Parser läuft im Formularrequest.

Die Browserseite erlaubt Bestätigung mit nächstem Cursor, Überspringen und erneuten Beginn. Für rund 1000 Dateien sind etwa zehn Bestätigungen erforderlich. Neue Dateien unterhalb eines bereits verwendeten Cursors werden beim nächsten Durchlauf erfasst. Der technische Worker analysiert aktuelle sichtbare Inhalte; die bestätigten Revisionen sind Auftragsnachweise und kein späterer historischer Inhaltszugang.

Ein zusätzlicher technischer Outbox-Empfänger bereinigt nach ausdrücklich berechtigtem endgültigem Löschen unreferenzierte Medienableitungen. Aktuelle Dateien, Papierkorb, gemeinsam verwendete Kopien und Historien bleiben Referenzen. Gemeinsamer SQL-Lock, auf die Cachekapazität begrenzte Auswahl und Blobindizes schützen parallele Änderungen. Neue Migration platform.media:0002_reference_indexes, keine zusätzliche Komponente. Physische Original-GC und zeitbasierte Retention bleiben getrennte ausdrücklich festzulegende Betriebsregeln.

Die neue Übersicht offene-entscheidungen.md fasst die noch tatsächlich notwendigen Betreiberentscheidungen zusammen, einschließlich Lizenz, Referenzclients/Offlinebedarf, Gruppenrichtlinie, Rollen/Privatbereichen/Notfall, Größen, zusätzlichen Medienkomponenten, sensiblen Metadaten, Redaktion, Export und Plugin-Vertrauen sowie Aufbewahrung, Backup und Betriebsabnahme. Datenbankziele, Plattformen, bisherige Komponentenfreigaben und insbesondere EXIF werden nicht erneut angefragt.

Prüfung und Veröffentlichungsstand: verification.md. Die vorige veröffentlichte Medienphase ist in allen vier GitHub-Jobs einschließlich HTTP/HTTPS erfolgreich, dokumentiert in verification-iteration-28.md. Weitere Umsetzung bleibt autorisiert. IPTC/XMP/KI/C2PA, vollständige Katalogpflege, Tags/Sammlungen/Export, übrige Datei-/Verwaltungsfunktionen und Betriebs-/Geräteabnahmen folgen; keine pauschale Fertig- oder Produktivfreigabe.

Version 1.31: Berechtigter seitenweiser Medienneuaufbau, atomare Einzelzustellungen und Bereinigung ausschließlich unreferenzierter Ableitungen; aktuelle Betreiberentscheidungen zusammengeführt.


## 42. Iteration 30: Eingebettete Metadaten und deklarierte KI-Angaben

EmbeddedMetadataProvider verwendet die öffentlichen Medienverträge für Standard-XMP/Photoshop-IPTC in JPEG sowie unkomprimierte PNG-Text-/XMP-Felder. Vorhandenes PHP, DOM/libxml und iptcparse genügen; keine neue Komponente oder Migration. Begrenzte Parserpfade, CRC-/Längenprüfung, keine Dekompression, abgewiesene XML-DTD/Entities, deaktivierte externe Auflösung und ein kooperatives Einsekundenbudget ergänzen die bestehenden Quell-/Pixel-/Workergrenzen. Ein laufender nativer Parser wird dadurch nicht hart unterbrochen.

Ausgewählte beschreibende Felder, Herkunfts-URIs, KI-System/-version, Prompt und Promptautor bleiben mit Rohwert, Normalisierung, Quelle und Status getrennt. Alle Werte erscheinen über die registrierten Feldverträge in der bestehenden Medien-Detailansicht. Nur unterstützte strukturierte DigitalSourceType-Angaben führen zu als generativ erstellt beziehungsweise bearbeitet gekennzeichnet. Mehrere unterschiedliche Angaben erzeugen Konflikt; fehlende, unvollständig untersuchte oder nicht unterstützte Daten bleiben unbekannt. Keine Ableitung aus Dateiname, Programmname oder freiem Prompttext und kein KI-Bilddetektor. C2PA bleibt ausdrücklich nicht untersucht oder validiert.

Prompt-/Autorfelder verlangen zusätzlich media.sensitive.read und sind nicht automatisch exportierbar. Bestehende Rollen ändern sich nicht; das ausdrückliche Wartungsprofil media-sensitive wird separat vergeben. Nicht berechtigte Ansichten zeigen weder diese Werte noch ihre konkreten Feldlabels. Originaldownloads behalten eingebettete Angaben, PNG-Vorschauen entfernen sie. Neue Dateiversionen erhalten neue Ableitungen; alte KI-Angaben werden nicht auf neue Quellen übertragen.

Die Verarbeitungssignatur ändert sich mit den registrierten neuen Feldern/Providern. Bestehende Bestände werden über den in Iteration 29 ergänzten seitenweisen Neuaufbau aktualisiert. Unterstützte Quellen, Kodierungs-/Mengen-/Laufzeitgrenzen und Primärquellen stehen in embedded-metadata.md. Allgemeine Katalogpflege, automatische Quellenpriorität, Tags/Sammlungen, weitere Formate, Export und C2PA sowie übrige Konzept- und Betriebsabnahmen bleiben offen.

Prüfung und Veröffentlichung: verification.md. Die vorherige GitHub-Matrix ist vollständig erfolgreich und mit gelesenen Logs in verification-iteration-29.md dokumentiert. Die aktuelle Betreiberentscheidungsübersicht wird fortgeführt; bereits entschiedene Punkte werden nicht erneut angefragt.

Version 1.32: Begrenztes IPTC-/XMP-/PNG-Auslesen, strukturierte KI-Deklaration mit getrennter Nachweisqualität und geschützten Promptfeldern.


### 42.1 Konkrete folgende Komponentenentscheidung für Export

Die Vorlage zip-library-decision.md beschreibt native ext-zip/ZipArchive einschließlich libzip in der tatsächlichen Workerlaufzeit, den vorgesehenen ersten ZIP-/JSON-Aufbau und erforderliche Rechte-/Grenz-/Abbruchtests. Die Bibliothek ist noch nicht in Samla eingeführt. Vor der ZIP-Paketierung ist gemäß Konzept und AGENTS die konkrete Betreiberfreigabe erforderlich; bestehende Komponentenfreigaben werden nicht erweitert ausgelegt. Die übrigen offenen Betreiberentscheidungen bleiben in offene-entscheidungen.md zusammengefasst.


## 43. Iteration 31: XMP-Subjektbindung präzisieren

Die Auslese beschränkt XMP-Eigenschaften auf direkte Felder der unterstützten Hauptbeschreibungen unter xmpmeta, xapmeta oder barem RDF. Nur ein fehlender oder leerer rdf:about-Wert ohne anders gebundene rdf:nodeID/rdf:ID wird als unterstützter Dateibezug behandelt. XML-Basisumleitungen und komplexe statt primitiver Property-/Listenwerte werden als nicht unterstützt behandelt. Ausdrücklich fremde Subjekte und verschachtelte Ressourcen werden nicht als Herkunftsangaben des aktuellen Mediums übernommen. Ein ausschließlich benanntes nicht zuordenbares Subjekt ergibt teilweise untersucht und unbekannte Herkunft. Die Abdeckung bleibt bewusst konservativ und ersetzt keine Herkunftsvalidierung.

Provider-Version 1.1 verändert die Verarbeitungssignatur, damit ältere Ergebnisse nicht ungeprüft angezeigt werden. Bestehende Bestände können mit dem vorhandenen seitenweisen Neuaufbau aktualisiert werden. Keine neue Migration oder Komponente. Zusätzliche Unit-/Integrationsfälle prüfen fremde und verschachtelte Subjekte, bare RDF-Unterstützung und einen gültigen Hauptwert neben einem anders bezeichneten Nebenobjekt.

Prüfung und Veröffentlichungsstand: verification.md. Die vorherige GitHub-Matrix einschließlich XMP-/Promptprüfung ist in allen vier Kombinationen erfolgreich und mit gelesenen Joblogs in verification-iteration-30.md dokumentiert. Für die folgende ZIP-Paketierung liegt weiterhin die konkrete noch nicht freigegebene Komponentenentscheidung zip-library-decision.md vor. Die übrigen offenen Entscheidungen bleiben in offene-entscheidungen.md gebündelt.

Version 1.33: Keine Übernahme fremder beziehungsweise verschachtelter RDF-Herkunftsangaben als aktuelle Dateideklaration; Cachebindung an Provider-Version 1.1.


### 43.1 Betreiberfreigabe für ZIP und nächste fachliche Angaben

Am 22. Oktober 2026 hat der Betreiber native ext-zip/ZipArchive einschließlich der in zip-library-decision.md beschriebenen libzip-Abhängigkeit ausdrücklich freigegeben. Die vorherigen Hinweise auf eine noch offene Komponentenfreigabe sind historisch überholt. Keine fertige Exportfunktion und keine Installation auf dem Betreiberhost werden daraus abgeleitet.

Für die fachliche Auslegung sind als nächste Angaben Gesamtbestand in GB, größte benötigte Einzeldatei und gewünschte Paketgröße sowie Onlinezugriff gegenüber Offline-Synchronisierung hilfreich. Gruppenverwaltung, persönliche Bereiche und Aufbewahrung können bei den jeweils betroffenen Funktionen entschieden werden. Technische Umsetzung innerhalb des freigegebenen Umfangs kann fortgesetzt werden. Die vollständige Betreiberübersicht bleibt offene-entscheidungen.md.

Version 1.34: ZIP-Freigabe festgehalten; keine Änderung am Anwendungscode. Dokumentationsprüfung mit git diff --check; keine erneute Anwendungstestmatrix für diese reine Dokumentationsänderung.


### 43.2 Bestätigte Größen- und Organisationsvorgaben

Der Betreiber plant aktuell voraussichtlich maximal 100 GB Gesamtbestand. Diese Angabe dient der Kapazitätsplanung und setzt keine globale harte Quote. Größte Einzeldatei und maximale Exportpaketgröße sind weiterhin unbekannt; insbesondere werden nicht 100 GB als Upload- oder ZIP-Grenze übernommen.

Persönliche und gemeinsame Speicherbereiche sind gewünscht. Gruppen und Berechtigungen werden in Samla gepflegt; externe IdP-Gruppen dienen optional als Mappingquelle für lokale Samla-Gruppen. Gruppenhoheit und tatsächliche externe Mitgliedschaft bleiben getrennt; spätere IdP-Mappings benötigen konkrete Claims/Kennungen und eine Entzugsrichtlinie.

Offlinebearbeitung mit automatischer Synchronisierung nach Wiederverbindung ist für die bestätigten Plattformen verbindlich. Referenzclients und konkrete Konfliktoberfläche bleiben festzulegen. Vorhandene WebDAV-Unterstützung allein gilt nicht als geprüfte Offline-Synchronisierung. Zusätzliche Clientkomponenten werden nicht aus diesem Wunsch pauschal freigegeben.

Die Löschfrist muss konfigurierbar sein. Konkrete Vorgabewerte sowie getrennte Regeln für Papierkorb, Dateiversionen und Audit werden damit noch nicht festgelegt. Es wird keine automatische Löschung mit erfundenen Fristen aktiviert.

Nächster freigegebener Implementierungsschritt bleibt der geschützte ZIP-Export aktueller Originale samt Manifest über den vorhandenen Worker. Persönliche Bereiche, Offlineabnahme und konfigurierbare Aufbewahrungsabläufe folgen als eigene prüfbare Arbeitspakete. Die Betreiberübersicht und README wurden abgeglichen.

Version 1.35: Betreiberantworten dokumentiert; reine Dokumentationsänderung, mit git diff --check geprüft.


## 44. Iteration 32: JRTools-Gestaltung und konkretisierter Offlinebedarf

Gemeinsames Layout und lokale CSS-Datei wurden an die betrachtete JRTools-Startseite angepasst: dunkle schieferblaue Navigation/Kopfzeile, helle graue Flächen, weiße Karten, grüne Akzente, Systemschriften und kompaktere Rundungen. Kleine Texte und Buttons verwenden kontraststärkere Varianten. Fokus, Formulare und responsive Tabellen wurden abgeglichen. Keine externe Schrift, Theme-Runtime, neue Bibliothek oder Migration. Die Startseite öffnet Dateien und Medien und kündigt diese nicht mehr als zukünftige Funktionen an. Bestehende Rechteprüfungen bleiben wirksam.

Der Betreiber bestätigt Offlinebearbeitung und spätere Synchronisierung. Referenzclients und Konfliktbehandlung bleiben zu klären; WebDAV allein ist kein Nachweis einer fertigen Offline-Synchronisierung. Übliche Einzeldateigröße ist bis etwa 100 MB; größere Videos bleiben möglich. 100 MB ist keine harte Obergrenze. Vorhandene konfigurierbare Uploadgrenze und Medienparsergrenzen werden durch diese Dokumentation nicht still geändert.

Die JRTools-Referenz wurde visuell im Browser geprüft. Die lokale Samla-Vorschau konnte wegen getrennter Loopback-Umgebung und gesperrter Datei-URLs im Cloud-Browser nicht visuell abgenommen werden; kein Umgehen der Richtlinie. Prüfergebnisse und Grenzen siehe verification.md und design.md.

Während dieser Phase wünscht der Betreiber zusätzlich eine Berechtigungsverwaltung im UI. Sie wird als folgende eigene Phase umgesetzt, damit alltägliche Gruppen-/Bereichs- und Workerzuweisungen keine Konsolenarbeit mehr verlangen. Danach bleibt der bereits freigegebene ZIP-Export vorgesehen. Keine erneute Freigabe der bekannten Komponenten nötig.

Version 1.36: JRTools-Gestaltung; Offlinebearbeitung und übliche Dateigröße konkretisiert.


## 45. Iteration 33: Bereiche und feste Berechtigungsprofile im UI

Die vom Betreiber gewünschte Onlineverwaltung ist ergänzt: Bereiche mit Namen und Quote anlegen/bearbeiten, vollständig leere Bereiche ausdrücklich bestätigt löschen und Gruppenrechte global oder pro Bereich zuweisen/entziehen. Die Oberfläche verwendet Dropdowns und Anzeigenamen für Gruppen, Bereiche, Berechtigungsprofile und Benutzer bei manuellen Mitgliedschaften. Keine Freitextkennung zum Auswählen referenzierter Entitäten. Verwaltungslisten bleiben begrenzt; Überschreitungen werden ausdrücklich gemeldet und nicht still abgeschnitten.

Die festen Profile entsprechen den bisherigen Datei-/Medien-/Worker-CLI-Profilen. Bekannte alte CLI-Bereichsanlagezuweisungen mit exakt ursprünglichem Rechteumfang werden zusätzlich verständlich bezeichnet und können entzogen werden. Abweichende reservierte Definitionen werden nicht automatisch umgeschrieben. Andere eigene oder administrative Rollen und allgemeine Delegations-/IdP-Mappingverwaltung bleiben separate offene Oberflächen. Die Profilansicht ist keine vollständige Erklärung aller effektiven Rechte.

Die vorhandene Standardadministration kann die neue Oberfläche ohne zusätzlichen Bootstrap verwenden. Gemeinsame Voraussetzung sind aktuelle globale Verzeichnislese- und Mitgliedschaftsverwaltungsrechte gemäß access-administration.md. Feste normale Profilgruppen lassen sich damit auch bezüglich manueller Mitglieder verwalten; administrative/unbekannte Rollen behalten ihren Schutz. Die Anlage eines Bereichs sowie globale Administration verleihen keine automatischen Dateirechte.

Bereichsquoten berücksichtigen Originale, Versionen und Papierkorb. Senken unter den aktuellen Verbrauch wird verhindert. Gefüllte Bereiche können nicht über den Bereichsknopf gelöscht werden; hierfür sind zunächst die bestehenden expliziten Inhalts-/Purge-Abläufe nötig. Aktive Sperren verhindern die Leerbereichslöschung. Bereichskatalog, RBAC-Zuweisungen/Delegationen, Audit und Outbox werden beim Löschen gemeinsam transaktional behandelt; andere Bereiche und Rollen bleiben bestehen. Die Löschung eines leeren Bereichs ist endgültig und verlangt eine ausdrückliche Bestätigung.

Neue öffentliche Verträge AccessProfile, AccessProfileAdministration, AccessProfileChange, StorageSpaceChange und SpaceAccessCleanup trennen Kern und RBAC-Speicherung. Vorschläge sind unveränderlich und exakt an MutationContext gebunden. Aktuelle Rechte- und Zustandsprüfungen vor/nach Pluginvalidierung, CSRF, POST und beobachtete Zustandsfingerprints schützen die Endpunkte. Der Lifecycle-Cleanup ist ein vertrauenswürdiger Transaktionshook; kein direkter unprotokollierter Webendpunkt. Keine neue Migration, Laufzeitkomponente oder automatische Rechteerweiterung bestehender Rollen.

Prüfung: Unit-/Architekturverträge, Integrationsfälle für Rechteentzug, sensible Felder, Konflikte, manipulierte Formulare, Pluginveto, Quoten inklusive Historie/Papierkorb und atomare Löschung sowie echter OIDC-HTTP-/HTTPS-Durchlauf. Ergebnis und Prüfgrenzen stehen in verification.md. Eine visuelle/native Browserabnahme bleibt offen. Bedienung und genaue Grenzen sind in access-administration.md dokumentiert.

Weitere Konzeptarbeit bleibt offen, insbesondere freie Rollen-/Delegationsverwaltung, IdP-Mapping-UI, automatische persönliche Bereiche, konfigurierbare Retention, geprüfte Offline-Synchronisierung und der bereits freigegebene portable ZIP-Export. Für diese Punkte wird kein Abschluss behauptet und keine neue pauschale Freigabe verlangt.

Version 1.37: Bereichs- und feste Berechtigungsverwaltung, benannte Dropdowns und Schutz leerer Bereichslöschungen.


## 46. Iteration 34: interner portabler Paketkern

Die freigegebene native ZIP-Fähigkeit wird als optionale Composer-Empfehlung und CI-Testmodul aufgenommen. Typisierte öffentliche Exportquellen, Feldwerte und Providerregistrierungen ergänzen die Erweiterungspunkte. Beiträge verlangen ausdrückliche Exportfreigabe und aktuelle Feldrechte; sensible Angaben benötigen zusätzlich ein gesondertes Exportrecht. Eine versionierte Verarbeitungssignatur bindet die folgenden Aufträge an ihre Provider. Bestehende Medienfelder werden nicht automatisch exportierbar.

Der interne ZIP-Speicher erstellt Pakete mit JSON-Manifest und unveränderten Originalen, sicheren relativen Namen, Quellen-/Archivprüfsummen, privaten Rechten und Staging. Quellen werden gestreamt und vor der Archivierung auf Größe/Hash geprüft. Dateianzahl, Gesamtbytes, Manifest und kooperative Laufzeit bleiben begrenzt. Native Abschlussabbrechung, Fehlerbereinigung und ein 128-MiB-Video sind geprüft. Es wird keine harte Betriebssystem-Zeitgrenze oder native Clientabnahme behauptet.

Dieses Paket allein veröffentlicht noch keine Fachfunktion im Web. Es folgt ohne neue Go-Abfrage die persistente Auftragsebene mit aktuellen Rechten, reserviertem Platz, Worker, Status, Ablauf/Bereinigung und geschütztem UI-Download. Technische Voreinstellungen sind konfigurierbar und ersetzen weder 100 GB geplanten Gesamtbestand noch die noch unbestimmte Obergrenze größerer Videos. Primärquellen und Grenzen: portable-export.md. Tests: verification.md.

Version 1.38: geprüfter Paketkern und öffentliche Exportbeiträge; native ext-zip/libzip innerhalb der erteilten Freigabe.

## 47. Iteration 35: Exportaufträge, Worker und Oberfläche

Dateiauswahl, eigene Auftragsliste, Status/Ablauf, geschützter Binärdownload, bestätigtes Entfernen und revisionsgebundener Retry sind im UI verfügbar. Das neue feste Bereichsprofil Originale exportieren enthält explizite Lese-/Exportrechte; sensible Exportbeiträge haben ein gesondertes Zusatzprofil. Kein bestehendes Inhaltsprofil wird still erweitert. ZIP bleibt optional, native ZIP-Fähigkeit ist in FPM und Worker erforderlich.

Die neue Migration platform.exports:0001_jobs speichert Besitzer, exakte aktuelle Quellen, Providerpolicy, begrenzte Platzreservierung und Zustand. Erstellung läuft idempotent über den vorhandenen Outbox-Worker. Besitzer, Identitätsfrische, Quellenrevisionen und tatsächlich exportierte Feldrechte werden bei Erstellung und Download erneut geprüft; Hashprüfung schützt das private Archiv. Ein öffentlich typisierter ExportChange ermöglicht Pluginveto. Terminale Paketergebnisse erhalten eigene transaktionale Ereignisse ohne Dateinamen oder Pfade.

Öffentliche WorkerTask-Beiträge ergänzen technische Aufgaben ohne neue Ereignisse. Eine begrenzte Ablaufbereinigung entfernt nur temporäre Ableitungen und Aufträge unter Sperre, auch bei ansonsten leerer Warteschlange. Konfigurierbare Startgrenzen: 100 Dateien/1 GiB/20 Sekunden/24 Stunden, globale Reservierung 5 GiB; maximal fünf gespeicherte Aufträge je Nutzer. Die Paketlebensdauer ist keine fachliche Papierkorb-/Versionen-/Auditfrist. Originale und ihre eingebetteten Angaben bleiben unverändert; die eingebauten Medienfelder erhalten keine automatische Metadatenexportfreigabe.

Vier lokale PHP-/Datenbankkombinationen mit jeweils 513 Tests/2769 Assertions und erweiterte echte HTTP-/HTTPS-Abläufe sind erfolgreich. GitHub-CI für Paket 34 ist vollständig erfolgreich; Paket 35 folgt nach Veröffentlichung. Einzelheiten: verification.md und portable-export.md. Betreiberdeployment, native Geräte-/Offlineabnahme und visuelle Abnahme bleiben offen.

Nächste unabhängige Arbeiten: persönliche Bereiche, weitere Katalogpflege und Rechteoberflächen. Automatische persönliche Bereitstellung und fachliche Aufbewahrungsfristen benötigen weiterhin die in offene-entscheidungen.md bezeichneten konkreten Vorgaben; sie blockieren unabhängige Implementierung nicht. Weitere Arbeitspakete werden ohne Go-Abfrage umgesetzt und einzeln veröffentlicht.

Version 1.39: kompletter begrenzter Exportdurchstich, technische Workeraufgaben und UI statt rein internem Paketkern.

## 48. Iteration 36: IdP-Gruppenmappings im Browser

Die bisherige abgesicherte Mappingfunktion erhält eine Weboberfläche mit lokalem Gruppennamen-Dropdown, festen konfigurierten Issuer, exakter externer Kennung und bestätigtem Entfernen. Neue Kanten sind ohne konfigurierten signierten OIDC-Gruppenclaim gesperrt, damit der Modus lokaler Gruppen nicht die nächste Anmeldung verliert. Bestehende Kanten lassen sich bereinigen. Eine IdP-API oder neue Laufzeitkomponente wird nicht eingeführt; keine neue Migration.

Der bestehende öffentliche Mapping-/Delegationsvertrag bleibt maßgeblich. Administrative Zielgruppen, Rechteumfang der Gruppe, Snapshotfrische und verbleibende Administrationszugänge bleiben geschützt. Ein optionaler erwarteter Kantenbestand verhindert veraltete Browseränderungen unter dem gemeinsamen Mutationslock. Externe Quellen und manuelle Mitgliedschaften bleiben getrennt; Audit/Outbox enthalten keine Rohclaims. Auswahl und Neuanlage sind begrenzt. Details: group-mappings.md.

Sechs zusätzliche Integrationstests prüfen Namensauswahl/HTML-Escaping, feste Issuerbindung, lokale Claimkonfiguration, Entfernen trotz deaktivierter Claims, aktuelle Rechte/Delegation, CSRF, fehlerhafte Formulare, veralteten Zustand, atomare Kapazitätsgrenze und begrenzte Listen. Der synthetische HTTP-/HTTPS-Test prüft zusätzlich den sicheren lokalen Gruppenmodus. Genaue Matrixergebnisse: verification.md. Paket 35 ist inzwischen in allen vier GitHub-Kombinationen erfolgreich.

Die fachlichen Vorgaben zum tatsächlichen IdP-Claim und akzeptierter Entzugsverzögerung bleiben konkret einzurichten; die UI erfindet dafür keine Werte. Unabhängige Katalog- und Dateifunktionen werden ohne weitere Go-Abfrage fortgesetzt.

Version 1.40: Gruppenmapping als geprüfter Browserablauf mit bestehendem öffentlichen Policyvertrag.

## 49. Iteration 37: manuelle Beschreibungen und Tags

Die Dateidetailseite öffnet einen getrennten manuellen Katalog mit Beschreibung und Tag-Mehrfachauswahl nach Namen. Tagverwalter legen Bereichstags an, benennen sie um und löschen nach Bestätigung ausschließlich unbenutzte Tags. Vorhandene Original-, Versions- und Extraktionsdaten werden nicht umgeschrieben. Neue Bereichsprofile Katalog lesen/bearbeiten/verwalten ergänzen die Berechtigungsoberfläche ohne automatische Rollenerweiterung. Neue Migration platform.catalog:0001_manual erforderlich, keine neue Laufzeitkomponente.

Katalog- und Dateirevision schützen gegen parallele Bearbeitung und veraltete Quellen; fremde Bereichsreferenzen werden abgewiesen. Aktuelle Dateileserechte, sichtbare Eltern, Identitätsfrische und separate Katalogrechte gelten für jeden Zugriff. Beschreibungen sind begrenzter sicher dargestellter Text (16 KiB), Tagwörterbücher maximal 100 Namen mit 80 Zeichen, Dateizuordnungen maximal 50. Öffentlich typisierte Vorschläge binden Validatoren an konkreten Inhalt, Operation und Bereich; auditierte Outboxereignisse enthalten keine Katalogtexte.

Papierkorb/Wiederherstellung behält manuelle Werte; endgültiges Löschen entfernt Dateikatalog und Referenzen atomar. Ein im Papierkorb benutzter Tag ist weiter gegen versehentliche Wörterbuchlöschung geschützt. Umbenennung/Verschieben erhält den Katalog, neue Inhalte behalten manuelle Angaben. Einzeldateikopien übernehmen ausdrücklich zunächst keine manuellen Werte. Neuaufbau ausgelesener Medien überschreibt diese Tabellen nicht. Kein automatischer Katalogexport oder fachlicher Retentionswert wird eingeführt.

Unit- und Integrationsfälle prüfen unveränderliche Vorschläge, genaue Bindung, Autorisierung, CSRF, Referenzen, Revisionen, Kapazität, Pluginveto, datensparsame Ereignisse, Originalintegrität und Papierkorb/Purge. Der synthetische HTTP-/HTTPS-Durchlauf ergänzt echte Profile, benannte Tags, Beschreibung, Konflikt und Rechteentzug. Matrixergebnisse und Grenzen: verification.md; Bedienung: manual-catalog.md. Die GitHub-Matrix von Paket 36 ist vollständig erfolgreich.

Nächster unabhängiger Ausbau: Mehrfachbearbeitung von Tags, Tagsuche und weitere Katalogfelder. Automatische persönliche Bereiche und noch unbestimmte Aufbewahrungs-/Offlinevorgaben bleiben gesonderte Betreiberentscheidungen.

Version 1.41: manueller Dateikatalog als eigener geprüfter Browserdurchstich.

## 50. Iteration 38: atomare Tag-Mehrfachbearbeitung

Die Dateiliste öffnet eine ordnerweise Auswahl bis 100 Dateien mit benannten Checkboxen und einem Tag-/Operations-Dropdown. Hinzufügen/Entfernen erfordert Bestätigung, aktuelle Dateileserechte und catalog.write. Ordner werden nicht rekursiv übernommen. Eine gemeinsame Mutation prüft exakte Datei-/Katalogrevisionen, Quellenzugriff und Tagkapazität für alle ausgewählten Dateien. Ein Konflikt oder Fehler rollt den gesamten Auftrag zurück; Beschreibung und andere Tags bleiben erhalten.

Der neue öffentliche CatalogBatchChange enthält den unveränderlich kopierten, sortierten exakten Auswahl-/Änderungsplan, gebunden an Bereich, Tag, Operation und Signatur. Pluginveto bleibt möglich. Nur tatsächlich geänderte Dateien erhalten eine neue Katalogrevision und individuelle Audit-/Outboxereignisse; unveränderte Zuweisungen bleiben No-ops. Die Gesamtmutation wird zusätzlich protokolliert. Beschreibungen und Tagtexte stehen nicht in diesen Ereignissen. Listen- und Formularverarbeitung bleiben begrenzt; Katalogrevisionen für die angezeigte Ordnerseite werden gesammelt gelesen.

Die HTTP-Prüfung erweitert den realen Ablauf um Tagentzug/-erneute Zuweisung, veraltete Batchformulare und eine tatsächlich 16 KiB große UTF-8-Beschreibung mit größerem URL-kodiertem Request. Dafür wird die Bodygrenze ausschließlich für den begrenzten Katalogspeicherpfad angepasst; Feldgrenzen gelten unverändert. Keine neue Migration oder Laufzeitkomponente über den bereits vorhandenen Katalog hinaus.

Die Tests prüfen zusätzlich mehrteilige Änderungen, erhaltene Beschreibungen/andere Tags, unveränderte Mitglieder, individuelle Ereignisse, Konflikt/Kapazität, Pluginveto und injizierten SQL-Fehler mit vollständigem Rollback. Matrix und Webprüfungen: verification.md. GitHub-CI von Paket 37 ist vollständig erfolgreich. Die persönliche Bereitstellung bei der ersten Anmeldung ist inzwischen ausdrücklich gewählt. Die noch nicht bestätigte Standardquote wird als konfigurierbarer technischer Startwert von 10 GiB vorbereitet; Austritts-/Löschregeln, Aufbewahrung und native Offline-Referenzclients bleiben konkrete Betreiberangaben; sie werden nicht aus den technischen Kataloglimits abgeleitet.

Version 1.42: gemeinsamer Tagauftrag mit exaktem Revisionsplan und Einzelnachvollziehbarkeit.

## 51. Iteration 39: automatische persönliche Bereiche

Die Betreiberentscheidung zur Anlage bei der ersten Anmeldung ist umgesetzt. Nach bestätigter OIDC-Anmeldung entsteht je stabilem internem Konto ein persönlicher Bereich mit lokaler verwalteter Eigentümergruppe und einer festen bereichsbezogenen RBAC-Rolle. Der Kern nutzt PersonalSpaceChange und den öffentlichen PersonalSpaceAccess-Transaktionsvertrag, keine privaten RBAC-Adapter. Eine aktuelle Konto-/Frischeprüfung und die Kontinuität vorhandener Administration bleiben vorgeschaltet. Fehler, Pluginveto und Rollenkollisionen rollen die vollständige Anlage einschließlich Mitgliedschaft/Audit/Outbox zurück.

Neue Migration platform.storage:0009_personal_spaces erforderlich. APP_PERSONAL_SPACE_ENABLED und APP_PERSONAL_SPACE_QUOTA_BYTES steuern ausschließlich neue Anlagen. Der vorläufige konfigurierbare Startwert 10 GiB ist weiterhin nicht als bestätigte fachliche Quote ausgegeben. Wiederholte Anmeldung ändert weder administrativ gepflegte Namen noch Quoten oder Besitz. Reservierte Rollen/-gruppen sind vor normaler Mitgliedschafts-, Mapping-, Profil- und Delegationsbearbeitung geschützt. Persönliche Bereiche und Eigentümer werden im vorhandenen Verwaltungs-UI nach Namen angezeigt; andere Konten und Administratoren erhalten keinen impliziten Dateizugriff. Zusätzliche ausdrücklich protokollierte Gruppenprofile bleiben möglich.

Persönliche Bereiche werden derzeit auch leer nicht durch die allgemeine Bereichslöschung entfernt. Kontosperren gelten sofort für den aktuellen Zugriff, Daten bleiben erhalten; Ausschalten automatischer Anlage löscht keine vorhandenen Bereiche. Austritts-/Eigentumsübergabe-/Löschregeln müssen vor entsprechenden destruktiven Funktionen konkret festgelegt werden. Keine neue Laufzeitkomponente oder automatisch erfundene fachliche Retention.

Unit- und Integrationsprüfungen umfassen stabile Kennungen, genaue Selbstbindung, Konfigurationsgrenzen, Eigentümerrechte ohne Fremd-/Adminzugriff, Erhalt von Name/Quote, gesperrte/veraltete Konten, verwaltete Gruppen, gesperrte persönliche Löschung, öffentliche Hooks, Plugin-/SQL-Fehler, Rollenkollisionen, Administrationskontinuität und gemeinsamen Transaktionslock. Der reale synthetische HTTP-/HTTPS-Ablauf prüft Erst- und Wiederanmeldung sowie die neuen Verwaltungsgrenzen. Einzelheiten: verification.md und personal-spaces.md. GitHub-CI für Paket 38 ist vollständig erfolgreich.

Weitere unabhängige Konzeptteile bleiben Katalogsuche, Sammlungen, freie Felder, zusätzliche Verwaltungsoberflächen und abschließende Betriebs-/Geräteabnahme. Offene Lebenszyklus-, Offline- und Komponentenentscheidungen bleiben in offene-entscheidungen.md sichtbar. Umsetzung und Einzelveröffentlichung werden ohne Go-Abfragen fortgesetzt.

Version 1.43: persönliche Bereitstellung mit Eigentümervertrag, atomarer Rollenanlage und Verwaltungskennzeichnung.

## 52. Iteration 40: Katalogsuche mit Beschreibungen und Tags

Die Dateiliste und der manuelle Katalog öffnen eine geschützte Suchseite über Dateinamen, manuelle Beschreibungen und zugeordnete Tags. Ein Tag-Dropdown zeigt Namen; ein zusätzlich kombinierbarer Dateiendungsfilter wird ausdrücklich nicht als inhaltliche Formatprüfung ausgegeben. Unicode-groß-/kleinschreibungsunabhängige wörtliche Suche, begrenzte Beschreibungsausschnitte, escaped Ordner-/Taglabels und Cursorseiten bis 100 Treffer sind umgesetzt. Es werden keine Gesamttrefferzahlen versteckter Dateien angezeigt.

Original-/Katalogleserechte werden aktuell geprüft, Kandidaten stammen aus sichtbaren Elternketten. Vor Ausgabe wird jeder Treffer nochmals auf aktive Sichtbarkeit/Dateirevision und die aktuellen Bereichsrechte geprüft. Papierkorb und gelöschte/kaputte Eltern bleiben ausgeschlossen. Eingebettete sensible Medienfelder, Originalbytes und andere Pluginfelder werden nicht still in den Suchumfang aufgenommen. Keine neue Migration, externe Suchkomponente oder Inhaltsindexierung.

Der neue öffentliche FileCatalogReader und unveränderliche FileCatalogEntry-Werte stellen den begrenzten sichtbaren Namens-/Revisionskatalog ohne private Serverpfade oder Bytes bereit. FileService implementiert den Vertrag und teilt die bewährte Pfadprüfung mit der bestehenden Namenssuche. Katalogwerte werden blockweise gelesen; die technischen Grenzen bleiben 10000 Katalogeinträge, 100 Tags je Bereich, 50 Tags je Datei und 16 KiB Beschreibung. Details: catalog-search.md.

Ein neuer Unitfall und fünf Integrationstests ergänzen öffentliche Werte, kombinierte/literale Unicode-Suche, Namen/Tags/Beschreibungen, gelöschte Eltern, aktuelle Rechte, fremde Referenzen, Escaping und 101-Treffer-Seitenwechsel. Der vollständige HTTP-/HTTPS-Ablauf prüft tatsächliche Suchseiten, Filter, Unicode bei großer Beschreibung und Rechteentzug; die bestehende Namenssuche bleibt in der Regression. Genaue Ergebnisse: verification.md. GitHub-CI von Paket 39 ist vollständig erfolgreich.

Weitere Konzeptarbeit: Sammlungen/Referenzen, freie Katalogfelder, weitere Verwaltungsoberflächen und abschließende Betriebs-/Geräteabnahme. Offene konkrete Betreiberentscheidungen bleiben dokumentiert und ersetzen keine erneute Go-Abfrage für unabhängige Arbeiten.

Version 1.44: manueller Katalog einschließlich geschützter bereichsweiter Suche.


## 53. Anforderungsklärung: Offlinebearbeitung und automatische Synchronisation

Der Betreiber bestätigt ausdrücklich, dass die App Daten offline bearbeitbar halten und nach Wiederverbindung automatisch synchronisieren soll. Dies ist ein verbindliches Ausbauziel. Die vorhandene WebDAV-Anbindung und der Browsereditor erfüllen es noch nicht vollständig.

[offline-sync.md](offline-sync.md) konkretisiert dauerhafte lokale Änderungen, Speicherung der Ausgangsrevision, bedingte Uploads, Wiederaufnahme nach Neustart und Verbindungsabbruch, Antwortverlust, aktuelle Rechte und sichtbare Konflikte. Vorläufiger Sicherheitsstandard ist der Erhalt beider Bearbeitungen; keine stille Auswahl nach Gerätezeit. Mobile Hintergrundbeschränkungen werden ausdrücklich berücksichtigt. Konkrete Clients und neue Laufzeitkomponenten sind damit nicht pauschal ausgewählt oder freigegeben.

Die Arbeitspakete folgen Serververtragsprüfung, lokaler Clientbasis/Warteschlange, Synchronisation/Konfliktoberfläche und tatsächlicher Geräteabnahme. Diese Dokumentationsfortschreibung enthält keine neue Laufzeitfunktion, Migration oder behauptete Offlineabnahme. Die zuvor unvollständige Arbeit an Sammlungen ist nicht veröffentlicht und wird nicht als Iteration 41 abgeschlossen geführt.

Version 1.45: Offlinebearbeitung und automatische Synchronisation verbindlich konkretisiert; veröffentlichter Funktionsstand bleibt Iteration 40.


## 54. Geplante Überarbeitung von Oberfläche und Bedienung

Der Betreiber bewertet die Oberfläche als funktional, aber stark unintuitiv, und beauftragt eine optische Prüfung sowie Überarbeitungsplanung. Die öffentliche Anmeldeseite wurde im Browser als DOM und Screenshot betrachtet. Die angemeldeten Seiten waren nicht zugänglich; Navigation, Dateiliste, Startseite, Berechtigungs- und Bereichsverwaltung wurden ergänzend anhand der aktuellen Templates und CSS geprüft. Eine vollständige visuelle Abnahme wird daraus nicht abgeleitet.

[ui-overhaul.md](ui-overhaul.md) enthält konkrete Befunde, Zielbild und fünf Arbeitspakete: U1 Navigation/Seitenrahmen, U2 Dateiverwaltung, U3 Bereiche/Berechtigungen, U4 Medien/Katalog/Rückmeldungen und U5 Offline-/Synchronisationsoberfläche. U1 und U2 erhalten Vorrang vor weiteren umfangreichen Formularoberflächen. JRTools-Gestaltung und aktuelle Sicherheitsverträge bleiben Grundlage; kein zusätzliches Frontendframework ist beschlossen.

Die Abnahme umfasst autorisierte synthetische Daten, Desktop und Mobil, reine Leser, lange Namen, leere Zustände, Tastatur und Zoom. Die angemeldeten Ausgangsansichten müssen vor Umsetzung noch tatsächlich im Browser betrachtet werden. Dieses Paket veröffentlicht ausschließlich die Analyse und Planung, keine überarbeitete Oberfläche und keine neue Migration. Die Umsetzung erfolgt weiterhin paketweise mit README, Plan, gezielten Tests und GitHub-Veröffentlichung ohne erneute Go-Abfrage.

Version 1.46: UI-Überarbeitung als priorisierte Arbeitspakete eingeplant; veröffentlichter Funktionsstand bleibt Iteration 40.



## 55. UI-Paket U1: Navigation und Seitenrahmen

Arbeitsplatz und Verwaltung sind getrennt. Ein Kontomenü ersetzt verteilte Kontoaktionen, Unteransichten markieren den zugehörigen Abschnitt. Die mobile Navigation ist per Tastatur und Escape bedienbar; ohne JavaScript bleiben alle Links erreichbar. Login und Startseite sind kompakter; der OIDC-Start zeigt nun die Arbeitsfunktionen, während Mein Zugang separat bleibt. Anzeigenamen ersetzen interne Kennungen in der Kopfzeile. Ein lokales optionales Skript wird über script-src self zugelassen, ohne Inline-Skripte oder fremde Quellen.

Prüfung: PHP 8.4.5: 23 gezielte Unitprüfungen mit 133 Assertions erfolgreich; Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Ein erster zusätzlicher OIDC-Clienttestversuch scheiterte an noch nicht installierten Composer-Testabhängigkeiten, nicht an einer fachlichen Assertion. Zielmatrix und HTTP-Durchlauf folgen über GitHub-CI. Die angemeldeten Ausgangsansichten waren nicht live zugänglich; vollständige authentifizierte und mobile visuelle Abnahme bleibt offen.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.


## 56. UI-Paket U2: Dateien und Aktionen im Mittelpunkt

Eine kompakte Werkzeugleiste ersetzt die dauerhaft vorgeschalteten Upload- und Ordnerformulare. Dateinamen öffnen die Details, der Bereichs- und Ordnerkontext bleibt sichtbar. Die Exportauswahl hat einen Zähler und lässt sich aufheben; ohne JavaScript bleibt das Formular nutzbar. Detailaktionen sind gezielt aufklappbar. Namenssuche bietet bei vorhandenen Katalogrechten einen Wechsel zur Metadatensuche. Auswahl gilt ausdrücklich nur für die aktuelle Seite; Tagbearbeitung bleibt ein eigener Bereichsablauf.

Prüfung: PHP 8.4.5: 29 gezielte Unitprüfungen mit 181 Assertions erfolgreich, jetzt einschließlich installierter fixierter Composer-Abhängigkeiten und OIDC-Clientprüfungen. Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Neue Rendererprüfung deckt Nur-Lese-Navigation, Escaping und ausgeschlossene Upload-/Exportformulare ab. Zielmatrix, echter HTTP-Durchlauf und authentifizierte visuelle Abnahme bleiben noch offen.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.


## 57. UI-Paket U3: Bereiche und Gruppenprofile verständlich verwalten

Die Bereichsliste öffnet gezielt einen Bereich; Neuanlage und Löschung sind getrennte Abläufe. Quoten akzeptieren GB, GiB und kleinere Einheiten, mit exakter ganzzahliger Umrechnung und verlustfreier Darstellung bestehender Bytewerte. Profilkarten verbinden Beschreibung, aktuellen Zuweisungsstatus und die passende bestätigte Aktion. Bereichslinks führen direkt in die zugehörige Rechteverwaltung. Bestehende POST-Verträge bleiben kompatibel; widersprüchliche Größenfelder werden abgewiesen.

Prüfung: PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich, einschließlich Grenzwerten, Dezimalkomma, Überlauf, Bruchteilen von Bytes und exakten Quoten-Roundtrips. PHP- und Python-Syntax erfolgreich. Zusätzlicher Integrationstest und HTTP-Prüfablauf für die neue Größeneingabe vorbereitet; Ausführung der Datenbankmatrix erfolgt in CI und wird getrennt dokumentiert. Authentifizierte visuelle Abnahme noch offen.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.


## 58. UI-Paket U4: Katalog, Medien und hilfreiche Fehlerrückmeldungen

Tags werden als benannte Checkboxen gewählt; Tagverwaltung und erneute Medienanalyse öffnen sich bei Bedarf. Sucheinstiege sind verbunden, leere Medien- und Exportansichten nennen den nächsten Schritt. Fehlermeldungen haben konkrete Überschriften. Katalogentwürfe, Bereichsname/Quote, Ordnernamen und Dateiaktionen bleiben bei unterstützten Eingabe- und Konfliktfehlern im Formular erhalten. Ursprüngliche Revisions- und Konfliktbedingungen bleiben bestehen; ein Konflikt erhält keinen still erneuerten Schreibnachweis. Datei-Uploads müssen aus Browsergründen gegebenenfalls erneut ausgewählt werden. Technische Verarbeitungszustände werden nicht als fertig oder synchronisiert umgedeutet.

Prüfung: PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich; alle eigenen PHP-Dateien, das optionale lokale JavaScript und der aktualisierte HTTP-Prüfablauf sind syntaktisch geprüft. Integrationstests prüfen zusätzlich den erhaltenen Katalogentwurf samt alter Revision und Quoteneingaben. Diese Datenbanktests folgen mit der vollständigen CI-Matrix. Zwei überholte UI-Textannahmen aus den vorherigen CI-Läufen wurden auf die neue Struktur angepasst. Ein lokaler synthetischer HTML-Vorschauserver wurde erstellt, ist aber aus dem Cloud-Browser nicht erreichbar (ERR_HTTP_RESPONSE_CODE_FAILURE). Keine vollständige visuelle Abnahme behauptet.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.


## 59. UI-Paket U4.1: Abschlussprüfung und lokale Skriptauslieferung

Der lokale Router liefert das optionale UI-Skript mit korrektem MIME-Typ aus; der HTTP-Prüfablauf kontrolliert die Auslieferung. Assetversionen verhindern alte Layoutdateien nach dem Update. Schriftgrößen und Touchziele der bestehenden Formulare wurden vereinheitlicht. U1 bis U4 sind implementiert. U5 bleibt ausdrücklich abhängig von der noch offenen Entscheidung zwischen einer eigenen installierbaren Samla-Web-App und vorhandenen WebDAV-Apps; eine lokale Warteschlange oder echte Synchronisation ist im aktuellen Serverfrontend nicht vorhanden.

Prüfung: Vollständiger CI-Lauf 39890742597 für U4 ist in allen vier Kombinationen grün: je 221 Unit-/Architekturtests mit 1318 Assertions sowie 336 Integrationstests mit 1766 Assertions, zusätzlich HTTP und HTTPS. Alle vier Testlogs wurden gelesen; archiviert in verification-ui-u1-u4.md. Nachtrag lokal: 31 gezielte Tests mit 202 Assertions sowie tatsächliche HTTP-Auslieferung des UI-Skripts erfolgreich. Der Folgelauf für diesen Nachtrag wird gesondert geprüft. Visuelle Abnahme bleibt wegen des nicht erreichbaren lokalen Vorschauhosts offen.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.


## 60. UI-Paket U4.2: Isolierte Browserprüfung und visuelle Nachweise

Ein zusätzlicher CI-Job rendert die echten Templates mit ausschließlich synthetischen Daten und ohne Datenbank oder Produktivzugriff. Playwright 1.64.1 wird nur im temporären CI-Testverzeichnis installiert, nicht als Samla-Laufzeitabhängigkeit. Die Prüfung deckt Desktop- und Mobilbreiten, Navigation/Escape, Dateiauswahl, aufklappbare Formulare, Leseransicht, erhaltene Entwürfe und den Betrieb ohne JavaScript ab. 44 Bildschirmaufnahmen werden als kurzlebiges CI-Artefakt bereitgestellt und anschließend optisch geprüft.

Prüfung: Alle zwölf synthetischen PHP-Fixturrouten wurden lokal über HTTP ohne PHP-Warnungen gerendert. PHP- und JavaScript-Syntax erfolgreich. Der vorausgehende Nachtrag 39891300634 ist in allen vier PHP-/Datenbankkombinationen erfolgreich. Die neue Browserprüfung ist vorbereitet und wird erst nach ihrem tatsächlichen Lauf als erfolgreich ausgewiesen; eine Produktions- oder native Geräteabnahme ersetzt sie nicht.

Keine neue Laufzeitkomponente oder Datenbankmigration. Tatsächliche native Geräteabnahme und Betreiberdeployment bleiben getrennte Schritte. Weitere UI-Pakete werden ohne Go-Abfrage fortgesetzt.
