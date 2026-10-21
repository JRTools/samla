# Umsetzungsplan: Samla

Version: 1.16
Stand: 22. Oktober 2026
Grundlage: Konzept_Datei_und_Medienplattform.md, Version 1.3
Status: Umsetzung begonnen, keine pauschale zusätzliche Komponentenfreigabe

Aktueller Stand: Iteration 15, siehe Abschnitt 25. Repository: https://github.com/JRTools/samla

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
