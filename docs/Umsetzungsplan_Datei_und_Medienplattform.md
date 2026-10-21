# Umsetzungsplan: Samla

Version: 1.7
Stand: 21. Oktober 2026
Grundlage: Konzept_Datei_und_Medienplattform.md, Version 1.3
Status: Umsetzung begonnen, keine pauschale zusätzliche Komponentenfreigabe

Aktueller Stand: Iteration 6, siehe Abschnitt 16. Repository: https://github.com/JRTools/samla

## 1. Ziel und Vorgehen

Das Konzept bildet die Grundlage für die Umsetzung. Die größten Risiken liegen in der konsistenten Dateispeicherung, dem Rechteentzug und den Plugin-Verträgen. Diese Punkte werden zuerst durch einen kleinen, vollständig getesteten Durchstich abgesichert.

Das technische Fundament der Versionierung wird gegenüber der bisherigen Roadmap vorgezogen: Unveränderliche Inhaltsstände und konfliktgeschützte Änderungen gehören bereits in den ersten Dateispeicher. Historienansicht und Editor folgen später. Eine nachträglich ergänzte Versionierung würde Uploads, WebDAV, Quoten und Fehlerbehandlung erneut verändern.

Die Entwicklung erfolgt in vertikalen Arbeitspaketen. Jedes Paket liefert einen vollständigen Ablauf einschließlich benötigter Oberfläche, Autorisierung, Erweiterungspunkten, Tests und Dokumentation. Der Plan setzt keine zusätzlichen Komponenten als freigegeben voraus.

## 2. Planungsgrundlage

Entsprechend den Annahmen des Konzepts wird mit einer einzelnen Installation, lokalem Dateispeicher außerhalb des Webverzeichnisses und einer gemeinsamen Anwendung für Browser und WebDAV geplant. Diese Annahmen müssen im ersten Arbeitspaket bestätigt werden.

PHP 8.4 ist die vorgesehene Mindestversion; PHP 8.5 wird zusätzlich geprüft. MariaDB 11.8 oder MySQL 8.0 sind als verfügbare Datenbanken bestätigt. Beide werden als Zielplattformen eingeplant; eine erfolgreiche Kompatibilitätsprüfung steht noch aus. Composer, JavaScript, GD und PHPUnit bilden die weitere bestätigte Grundlage. Weitere Bibliotheken, Erweiterungen und Betriebsanforderungen werden vor ihrem Einsatz abgestimmt.

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
