# Samla: offene Betreiberentscheidungen

Stand: 22. Oktober 2026. Grundlage sind das Konzept, der aktuelle Code, der fortgeschriebene Umsetzungsplan und die im Projektverlauf erteilten Freigaben. Historische offene Punkte gelten nicht erneut als offen, wenn sie inzwischen entschieden wurden.

Die laufende Umsetzung kann mit den bisherigen Vorgaben fortgesetzt werden. Die folgende Liste ist keine pauschale Freigabeanforderung und muss nicht auf einmal beantwortet werden. Konkrete neue Komponenten werden vor ihrer Einführung mit Paket, Umfang und Betriebsbedarf vorgelegt. Technische Implementierungsdetails, die innerhalb der vereinbarten Architektur liegen, bleiben Entwicklungsaufgabe.

## Fachliche und technische Entscheidungen für kommende Funktionen

| ID | Entscheidung | Was konkret festzulegen ist | Wann nötig |
| --- | --- | --- | --- |
| E01 | Lizenz für Samla | Unter welcher Projektlizenz soll der eigene Code veröffentlicht werden? Die Lizenzen eingebundener Pakete sind davon getrennt. | Vor einer ausdrücklich lizenzierten Veröffentlichung |
| E02 | Zugriffsart und Referenzclients | Entschieden: Die App muss Dateien offline bearbeitbar halten und nach Wiederverbindung automatisch synchronisieren; siehe [Offline-Synchronisation](offline-sync.md). Vor U5 ist zu entscheiden, ob eine eigene installierbare Samla-Web-App oder vorhandene WebDAV-Apps die Offlinebearbeitung und Warteschlange übernehmen sollen. Referenzclients und konkrete Konfliktführung sind danach festzulegen. Je iOS, Android, Windows und macOS konkrete App und Version für die Abnahme festlegen. Die Plattformauswahl selbst ist erledigt. | Vor verbindlicher Gerätekompatibilitätszusage |
| E03 | Zentrale Gruppen und Entzugsgeschwindigkeit | Entschieden: Gruppen und Berechtigungen werden in Samla gepflegt; IdP-Gruppen können auf Samla-Gruppen gemappt werden. Für tatsächliche Mappings fehlen noch: tatsächlicher signierter Claim, stabile Kennungen, Mappings und maximal akzeptierte Verzögerung bei Änderungen oder Entzug. Ein produktiv gesetzter Konfigurationswert ersetzt keine dokumentierte Betriebsrichtlinie. | Vor verbindlicher zentraler Gruppensteuerung |
| E04 | Abmelde- und Sitzungsrichtlinie | Lokale Samla-Abmeldung beibehalten oder auch die zentrale IdP-Sitzung beenden? Gewünschter zentraler Sitzungswiderruf und Richtlinie für Gerätezugänge. Aktuell sind lokale Abmeldung, Kontosperre und einzeln widerrufbare App-Passwörter vorhanden. | Vor Ausbau von zentralem Logout und Sitzungsverwaltung |
| E05 | Startrollen und Delegation | Welche vorhandenen Gruppen sollen administrieren, Dateien verwalten, Medien redigieren, nur lesen oder Rechte vergeben dürfen? Welche Grenzen gelten je Speicherbereich? Kein automatischer Inhaltszugriff für globale Administratoren ist bereits Architekturvorgabe. | Für tatsächliche Rollenkonfiguration und deren Verwaltungsoberfläche |
| E06 | Persönliche Speicherbereiche | Entschieden: persönliche und gemeinsame Bereiche. Entschieden: automatische Bereitstellung bei der ersten Anmeldung. Standardquote noch bestätigen (vorläufiger konfigurierbarer Startwert 10 GiB); Zugriffe und Datenübergabe/-löschung bei Austritt oder Kontolöschung noch festlegen. | Vor automatischer Bereitstellung persönlicher Bereiche |
| E07 | Notfalladministration | Wer darf einen kontrollierten Notfallzugang auslösen, mit welchem Nachweis und welcher Protokollierung? Soll dafür ausschließlich Server-/CLI-Zugang vorgesehen sein? Erstadministration ist bereits eingerichtet; diese wird nicht erneut angefragt. | Vor Abschluss der Administrations- und Wiederherstellungsabläufe |
| E08 | Speicher- und Übertragungsgrenzen | Bestätigt: aktuell voraussichtlich maximal 100 GB Gesamtbestand, ungefähr 1000 Dateien und fünf aktive Nutzer. 100 GB ist eine Planungsgröße, kein automatisch gesetztes Limit. Bestätigt: im Regelfall bis etwa 100 MB pro Datei, Videos können größer sein. Eine verbindliche Einzeldateiobergrenze ist damit nicht festgelegt. Noch offen: Quoten je Bereich, maximale Uploads und benötigte Bild-/Exportgrößen. Die aktuelle Uploadvoreinstellung von 16 MiB ist keine bestätigte fachliche Obergrenze. | Vor belastbarer Größen- und Lastabnahme |
| E09 | Medienquellen und weitere Extraktoren | Welche über die freigegebene EXIF-/GD-Basis hinausgehenden Formate sind verbindlich erforderlich? Ausgewählte JPEG-IPTC-/XMP-/PNG-Text- und KI-Felder sind mit vorhandenen PHP-Schnittstellen umgesetzt. Weitere Parser oder Programme erhalten bei Bedarf jeweils eine konkrete eigene Komponentenvorlage. HEIC/RAW/PSD und TIFF-Vorschauen sind bisher nicht zugesagt. | Sobald eine zusätzliche Komponente tatsächlich benötigt wird |
| E10 | C2PA und Vertrauen | Soll ein separat ausgewählter Validator installiert werden? Wenn ja: akzeptierte Vertrauensanker und ob externe Manifestabrufe überhaupt erlaubt sein sollen. Das reine Vorhandensein eines Manifests bleibt ohne geeigneten Validator ausdrücklich ungeprüft. | Vor kryptografischer Herkunftsvalidierung |
| E11 | Sensible Metadaten und Exportrechte | Welche Gruppen dürfen GPS, Gerätekennungen, Prompts oder Promptautoren sehen, durchsuchen und exportieren? Welche Angaben dürfen einen Bereich verlassen? Aktuell werden GPS und Seriennummern nicht als allgemeine Felder übernommen; ein erlaubter Originaldownload enthält weiterhin dessen eingebettete Daten. | Vor Freischaltung entsprechender Felder und Exportprofile |
| E12 | Redaktionelle Freigabe und Nutzungserlaubnis | Wer darf Medien freigeben und Nutzungsrechte/Begründungen pflegen? Soll der redaktionelle Status nur Kataloginformation bleiben oder bestimmte Downloads/Exporte verbindlich sperren? Eine Sperre müsste ausdrücklich serverseitig umgesetzt werden. | Vor verbindlichem Freigabeworkflow |
| E13 | Priorität widersprüchlicher Metadaten | Welche Quelle soll bei widersprüchlichen eingebetteten Angaben als bevorzugter Katalogwert dienen? Konflikte und Herkunft bleiben sichtbar. Manuelle Angaben werden bei erneuter Extraktion grundsätzlich nicht still überschrieben; darüber muss nicht erneut entschieden werden. | Vor automatischer Übernahme konkurrierender Quellen |
| E14 | Portabler Export | Benötigter Umfang: aktuelle Originale, Historie, Sammlungen und zulässige Metadaten; gewünschte Paketierung und Größenlimit. Native ext-zip/ZipArchive einschließlich libzip ist gemäß [Vorlage](zip-library-decision.md) ausdrücklich freigegeben; offen bleiben fachlicher Umfang und Größenbedarf. Ein Export ersetzt kein Wiederherstellungsbackup. | Vor verbindlicher Exportumfangs- und Größenabnahme |
| E15 | Weitere Viewer-/Editor-Komponenten | Nur bei Bedarf: gerenderte Markdown-Vorschau, isolierte/bereinigte SVG-Anzeige, Office-Integration oder weitere Bild-/Videokonvertierung. Zusätzliche Bibliotheken oder Programme werden einzeln vorgelegt. Der vorhandene sichere Texteditor benötigt keine neue Entscheidung. | Vor Einführung einer solchen Zusatzkomponente |
| E16 | Plugin-Vertrauen und Verteilung | Nur eigene/manuell geprüfte Plugins oder auch fremde Pakete? Wer darf installieren/aktivieren, aus welchen Quellen und mit welchem Update-/Freigabeverfahren? Kompatibilitätsprüfungen sind Teil der Implementierung; die Vertrauenspolitik ist Betreiberentscheidung. | Vor Plugininstallation und allgemeiner Pluginverwaltung |

## Betriebsrichtlinien vor einer vollständigen Produktivabnahme

| ID | Entscheidung | Was konkret festzulegen ist | Bisheriger Umgang |
| --- | --- | --- | --- |
| B01 | Aufbewahrung | Entschieden: Löschfrist muss konfigurierbar sein. Konkrete Fristen und Geltungsbereiche (Papierkorb, Versionen, Audit) bleiben offen; diese werden nicht pauschal gleichgesetzt. | Keine erfundene automatische Retention; endgültiges Löschen nur ausdrücklich berechtigt |
| B02 | Sicherung und Wiederanlauf | Backupziel, Zeitplan, Aufbewahrung, Verantwortlicher sowie tolerierbarer Datenverlust und Wiederherstellungsdauer (RPO/RTO). Konsistente Sicherung von Datenbank, Originalen und benötigter Konfiguration einschließlich Plugins festlegen. | Restore-Abnahme noch offen; Medienableitungen sind regenerierbar |
| B03 | Messbare Abnahmeziele | Akzeptierte Antwortzeiten, parallele Vorgänge, Exportumfang und Geräte-/Browsermatrix einschließlich gewünschter Barrierefreiheitsprüfung. | Automatisierte Servermatrix vorhanden; keine vollständige native Geräte- oder Lastabnahme behauptet |
| B04 | Betriebsplanung und Updates | Tatsächliches Schedulerintervall, Wartungsfenster, Verantwortliche für Updates/Migrationen sowie Ablauf einer Wiederherstellung nach fehlgeschlagenem Update. | CLI-Cron und authentifizierter HTTPS-Trigger sind freigegeben und implementiert; deren Wahl ist nicht erneut offen |
| B05 | Zusätzliche Testwerkzeuge | Bei Bedarf konkrete Freigabe zusätzlicher Browserautomatisierung oder Coverage-Treiber und deren Einsatzort. | PHPUnit, bestehende GitHub-CI und synthetische HTTP-/HTTPS-Prüfer vorhanden; keine neue Anwendungsruntime daraus ableiten |

Weitere Zusatzwünsche wie Schadsoftwareprüfung oder Verschlüsselung im Ruhezustand sind im Konzept als mögliche Erweiterungen genannt, bislang aber nicht zugesagt. Sie blockieren den vereinbarten ersten Umfang nicht und werden nur bei tatsächlichem Bedarf zu eigenen Entscheidungen.

## Bereits entschieden oder bestätigt

| Thema | Festgelegt |
| --- | --- |
| Produkt | Samla; Repository JRTools/samla |
| Sprache und Datenbanken | PHP 8.4/8.5, MariaDB 11.8 oder MySQL 8.0 |
| Grundarchitektur | Modularer Monolith, öffentliche Erweiterungsverträge, obligatorisches RBAC-Plugin; globale Administration gewährt keine impliziten Dateirechte |
| Konfiguration | Gemeinsame private .env und veröffentlichte example.env |
| Hosting und Anmeldung | Bekannte Plesk-/PHP-FPM-Installation unter https://samla.jrtools.org; ZITADEL-Login vom Betreiber als funktionierend bestätigt; Client-Secret-Basic |
| Erstadministration | Vom Betreiber erfolgreich durchgeführt |
| Bibliotheken und Basis | Composer, PHPUnit, GD, OIDC-Client und SabreDAV samt konkret freigegebenen transitiven Paketen |
| EXIF | Native Erweiterung ausdrücklich freigegeben: „Exif erweiterung ist ok“; keine Freigabe von ExifTool oder C2PA daraus ableiten |
| ZIP-Exporte | Native ext-zip/ZipArchive einschließlich libzip ausdrücklich freigegeben; geschützter Browserexport implementiert, tatsächliche Hostingprüfung bleibt Betreiberaktion |
| Hintergrundaufgaben | CLI-Cron und zusätzlich geschützter Webrequest für Hoster ohne CLI-Cron |
| Nutzung und Zielplattformen | Rund 1000 Dateien, fünf aktive Nutzer; iOS, Android, Windows und macOS |
| Speicher und Organisation | Voraussichtlich bis 100 GB Gesamtbestand; persönliche und gemeinsame Speicherbereiche; persönliche Anlage bei erster Anmeldung ausdrücklich gewählt |
| Gruppenhoheit | Lokale Samla-Gruppen mit optionalem Mapping von IdP-Gruppen auf Samla-Gruppen |
| Offline und Löschfrist | Offlinebearbeitung mit automatischer Synchronisierung nach Wiederverbindung verbindlich; Löschfrist konfigurierbar |
| Entwicklung und Veröffentlichung | Selbständig fortsetzen, Plan und README je Phase aktualisieren und regelmäßig auf GitHub veröffentlichen |

Es fehlen keine erneuten Testzugangsdaten für den bereits bestätigten OIDC-Durchstich. Die tatsächliche Installation neuer Codephasen, aktivierte Module der jeweils verwendeten CLI-/FPM-Laufzeit und der Restore-/Gerätetest sind Prüf- beziehungsweise Betreiberaktionen und nicht automatisch neue Produktentscheidungen.

