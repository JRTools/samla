# Überarbeitung von Oberfläche und Bedienung

Stand: 22. Oktober 2026. Auftrag: Die funktionale, aber vom Betreiber als stark unintuitiv beschriebene Oberfläche optisch prüfen und eine Überarbeitung einplanen. JRTools-Farben und Produktname Samla bleiben die Gestaltungsgrundlage.

## Prüfung und Grenzen

Die öffentliche Anmeldeseite von https://samla.jrtools.org/ wurde im Browser als DOM und Screenshot betrachtet. Sichtbar sind eine breite dunkle Seitennavigation mit nur „Übersicht“, eine zusätzliche dunkle Kopfzeile, ein großer Begrüßungsbereich und eine separate Anmeldekarte. Für diese einzelne Aufgabe beansprucht der Rahmen viel Fläche; die Anmeldung ist über mehrere Überschriften und Texte verteilt.

Die angemeldeten Ansichten waren in dieser Browsersitzung nicht zugänglich und wurden nicht als live visuell geprüft ausgegeben. Ergänzend wurden die aktuellen Repositorydateien templates/layout.php, home.php, files.php, permissions.php, space-administration.php und public/assets/samla.css gelesen. Die folgenden Befunde zu diesen Ansichten sind aus dem Quellcode abgeleitet; Wirkung bei realen Daten und Geräten muss noch im Browser geprüft werden. Es wurden keine produktiven Inhalte oder Berechtigungen verändert.

## Konkrete Befunde

| Bereich | Befund | Geplante Verbesserung |
| --- | --- | --- |
| Einstieg | Große Werbefläche vor den Arbeitsfunktionen; Dateien und Medien stehen neben Verwaltungsaufgaben. | Arbeitsorientierter Einstieg mit persönlichem und gemeinsamem Speicher; kurze Orientierung statt großer Werbefläche. |
| Navigation | Dateien, Medien, Exporte, Kontozugang, Abmelden und Administration stehen in einer Navigationsliste. | Klare Gruppen „Arbeitsplatz“ und „Verwaltung“, Kontomenü separat; Dateiverwaltung zuerst. |
| Kontext | Kopfzeile zeigt meist nur einen Seitentitel; Unteransichten gehören visuell nicht durchgehend zum aktiven Navigationspunkt. | Durchgängiger aktiver Abschnitt sowie Brotkrumen mit Bereich und Ordner. |
| Dateiliste | Suche, Ordneranlage, Upload, Papierkorb, Katalogsuche, Tagbearbeitung und Exportformular stehen vor der Inhaltsliste. | Eine kompakte Werkzeugleiste, direkt sichtbare Dateien; Zusatzaktionen im passenden Kontext. |
| Aktionen | Dateiname ist für Dateien kein klarer Einstieg; „Eintrag verwalten“ ist allgemein. Exportauswahl wiederholt Dateinamen. | Dateiname öffnet die Detailansicht; verständliche Aktionen und gemeinsame Auswahlleiste mit Auswahlzähler. |
| Suche | Namenssuche und Suche über Beschreibungen/Tags haben getrennte Einstiege. | Gemeinsamer sichtbarer Sucheinstieg mit klaren Filtern. Die tatsächlich unterstützte Suchabdeckung hängt von aktuellen Rechten ab. |
| Berechtigungen | Gruppe, Bereich, Profil und Änderungsart werden nacheinander ausgewählt; Status und Aktion stehen in getrennten Blöcken. | Im Bereich direkt „Zugriff verwalten“ öffnen; bestehende Gruppenprofile und konkrete Aktionen gemeinsam darstellen. |
| Bereiche | Auswahlformular, Bearbeitung, Löschung und Neuanlage folgen untereinander; Quoten verlangen Bytes. | Bereichsliste und gezielte Detailbearbeitung, getrennte Neuanlage; Größenwert mit verständlicher Einheit. |
| Mobil | Alle Navigationslinks werden bei schmaler Breite umgebrochen; lange Formulare verdrängen Inhalte. | Kompaktes aufklappbares Menü und reduzierte Werkzeuge; Dateiliste bleibt im Fokus. |
| Kontozugang | Interne Benutzerkennung wird prominent in der Kopfzeile verwendet. | Anzeigename verwenden, technische Kennung nur bei Bedarf im Kontobereich. |
| Rückmeldung | Gemeinsame Meldungsfläche allein erklärt den Kontext eines Fehlers nicht immer. | Feldnahe Fehler, verständlicher nächster Schritt, erhaltene Eingaben und eindeutige Erfolgsrückmeldung. |

## Zielbild

Samla soll sich im Alltag wie eine Dateiverwaltung bedienen lassen. Der Benutzer erkennt sofort seinen aktuellen Bereich, findet Dateien und sieht die nächste passende Aktion. Verwaltung erhält einen eigenen Ort. Referenzen werden weiterhin über Namen und Auswahlfelder gewählt; bei gleichen Namen hilft eine zusätzliche verständliche Kontextangabe. Technische Kennungen bleiben intern.

Die JRTools-Palette mit dunkler Navigation, hellem Inhalt und grüner Primäraktion bleibt erhalten. Typografie, Abstände und Aktionshierarchie werden vereinheitlicht. Pro Arbeitsbereich gibt es eine klar hervorgehobene Hauptaktion. Gefährliche Aktionen erscheinen getrennt und mit Benennung des betroffenen Objekts.

Neue Funktionen verwenden dieselben Komponenten. Insbesondere Offlineverfügbarkeit und Synchronisation benötigen einen eigenen verständlichen Status am betroffenen Inhalt sowie eine Übersicht ausstehender Änderungen und Konflikte. Solche Zustände dürfen erst als verfügbar erscheinen, wenn die Funktion tatsächlich implementiert ist.

## Reihenfolge der Arbeitspakete

| Paket | Umfang | Prüffähiges Ergebnis |
| --- | --- | --- |
| U1: Navigation und Seitenrahmen | Arbeitsplatz und Verwaltung trennen, Kontomenü, aktive Unteransichten, Brotkrumen, kompakter Login und Arbeitsstart, mobile Navigation. | Dateien und Administration sind ohne Suche in einer langen Linkliste erreichbar; aktueller Ort bleibt sichtbar. |
| U2: Dateiverwaltung | Inhaltsliste nach oben, Upload und Ordneranlage bei Bedarf, gemeinsame Auswahl, kontextbezogene Aktionen, Suche und Filter, Details mit Vorschau und Versionen. | Eine Datei hochladen, finden, öffnen und umbenennen funktioniert ohne mehrere dauerhaft sichtbare Verwaltungsformulare. |
| U3: Bereiche und Berechtigungen | Bereichsliste, getrennte Anlage/Bearbeitung, Quoten mit Einheit, Zugriff im Bereichskontext, Gruppenprofile mit Status und passender Aktion. | Ein gemeinsamer Bereich lässt sich anlegen und einer benannten Gruppe zugänglich machen; Wirkung und Grenzen werden vor dem Speichern verständlich. |
| U4: Medien, Katalog und Rückmeldung | Gemeinsame Detailstruktur, konsistente Filter/Auswahl, leere Zustände, Fehler, Lade- und Auftragsstatus, erhaltene Formulareingaben. | Vergleichbare Aktionen funktionieren auf allen Seiten gleich; Fehler nennen eine konkrete Abhilfe. |
| U5: Offline- und Synchronisationsoberfläche | Lokale Verfügbarkeit, ausstehende Änderungen, Wiederaufnahme und Konfliktauflösung passend zum implementierten Synchronisationsvertrag. | Benutzer können lokale und bestätigte Serverstände unterscheiden und beide Konfliktstände erhalten. Abhängig von der Offlineimplementierung. |

U1 und U2 werden vor weiteren umfangreichen neuen Formularoberflächen priorisiert. U3 und U4 folgen in einzeln testbaren und veröffentlichten Paketen. U5 läuft zusammen mit der Offlineimplementierung. Nach jedem abgeschlossenen Paket werden README, Plan und relevante Prüfergebnisse aktualisiert und der Code auf GitHub veröffentlicht. Eine pauschale vollständige Neuentwicklung des Frontends oder zusätzliche Laufzeitbibliothek ist damit nicht beschlossen.

## Wichtige Umsetzungsregeln

- Bestehende Rechteprüfung, CSRF, Revisionsbedingungen und Sperren bleiben verbindlich. Versteckte Schaltflächen ersetzen keine Serverautorisierung.
- Eine vereinfachte Rechteansicht darf keinen alleinigen „effektiven Zugriff“ behaupten, wenn zusätzliche Gruppen, Rollen oder IdP-Zuordnungen wirken. Zugewiesenes Profil und effektive Wirkung bleiben sprachlich unterscheidbar.
- Größen mit Einheit werden verlustfrei und serverseitig validiert in Bytes umgerechnet. Bestehende Quoten werden beim bloßen Öffnen oder Speichern anderer Felder nicht gerundet.
- Zusatzfunktionen müssen mit Tastatur erreichbar sein. Aufklappbare Menüs erhalten Fokusführung, Escape-Verhalten und sinnvolle Beschriftungen. Grundlegende Dateiaktionen bleiben ohne zusätzliches Framework nutzbar.
- Entwürfe und Auswahl bleiben bei Validierungsfehlern erhalten. Nach erfolgreicher Änderung erscheint eine konkrete Rückmeldung am passenden Ort.
- Verfügbarkeit und Statusanzeigen dürfen keine unberechtigten Namen, Inhalte oder Bestandszahlen offenlegen.

## Visuelle und funktionale Abnahme

Die aktuellen angemeldeten Ansichten werden vor U1 anhand einer autorisierten Testsitzung mit synthetischen Daten aufgenommen. Pro Paket werden dieselben Szenarien vorher und nachher verglichen. Dafür sind Desktopansichten bei etwa 1440 und 1024 Pixeln sowie mobile Ansichten bei 390 und 320 Pixeln vorgesehen. Diese Breiten sind Prüfvorgaben, keine bereits durchgeführten Messungen.

Zu prüfen sind mindestens:

1. Persönlichen oder gemeinsamen Bereich öffnen und den aktuellen Ordner erkennen.
2. Datei hochladen, einen Ordner anlegen und per Namen beziehungsweise erlaubten Metadaten suchen.
3. Datei öffnen, umbenennen, verschieben und eine frühere Version finden.
4. Mehrere Dateien auswählen, eine gemeinsame Aktion ausführen und Auswahl aufheben.
5. Einen gemeinsamen Bereich mit verständlicher Quote anlegen und Gruppenrechte im Bereichskontext vergeben.
6. Mit einem Benutzer mit Leserechten dieselben Ansichten prüfen; unzulässige Schreibaktionen sind nicht angeboten und bleiben serverseitig gesperrt.
7. Lange deutsche Dateinamen, gleiche Gruppennamen, leere Bereiche, viele Einträge und Fehlermeldungen betrachten.
8. Alle Aktionen per Tastatur bedienen, Fokus erkennen und bei 200 Prozent Zoom ohne Verlust wichtiger Funktionen arbeiten.
9. Mobile Navigation sowie primäre Bedienelemente ohne horizontales Seitenscrollen verwenden; Touchziele auf mindestens 44 mal 44 CSS-Pixel auslegen.

Eine visuelle Abnahme verlangt echte Browseransichten und dokumentierte Ergebnisse. Templateprüfung und erfolgreiche HTTP-Tests reichen dafür nicht aus. Die vier PHP-/Datenbankkombinationen werden für betroffene Logik weiterhin passend geprüft. Vorhandene fachliche Abläufe und Sicherheitsbedingungen erhalten gezielte Regressionstests.

## Stand dieses Pakets

Die Umsetzung wird nachfolgend paketweise dokumentiert. Eine Veröffentlichung ist keine Installation auf dem Betreiberhost.



### U1: Navigation und Seitenrahmen (23. Oktober 2026)

Arbeitsplatz und Verwaltung sind getrennt. Ein Kontomenü ersetzt verteilte Kontoaktionen, Unteransichten markieren den zugehörigen Abschnitt. Die mobile Navigation ist per Tastatur und Escape bedienbar; ohne JavaScript bleiben alle Links erreichbar. Login und Startseite sind kompakter; der OIDC-Start zeigt nun die Arbeitsfunktionen, während Mein Zugang separat bleibt. Anzeigenamen ersetzen interne Kennungen in der Kopfzeile. Ein lokales optionales Skript wird über script-src self zugelassen, ohne Inline-Skripte oder fremde Quellen.

PHP 8.4.5: 23 gezielte Unitprüfungen mit 133 Assertions erfolgreich; Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Ein erster zusätzlicher OIDC-Clienttestversuch scheiterte an noch nicht installierten Composer-Testabhängigkeiten, nicht an einer fachlichen Assertion. Zielmatrix und HTTP-Durchlauf folgen über GitHub-CI. Die angemeldeten Ausgangsansichten waren nicht live zugänglich; vollständige authentifizierte und mobile visuelle Abnahme bleibt offen.


### U2: Dateien und Aktionen im Mittelpunkt (23. Oktober 2026)

Eine kompakte Werkzeugleiste ersetzt die dauerhaft vorgeschalteten Upload- und Ordnerformulare. Dateinamen öffnen die Details, der Bereichs- und Ordnerkontext bleibt sichtbar. Die Exportauswahl hat einen Zähler und lässt sich aufheben; ohne JavaScript bleibt das Formular nutzbar. Detailaktionen sind gezielt aufklappbar. Namenssuche bietet bei vorhandenen Katalogrechten einen Wechsel zur Metadatensuche. Auswahl gilt ausdrücklich nur für die aktuelle Seite; Tagbearbeitung bleibt ein eigener Bereichsablauf.

PHP 8.4.5: 29 gezielte Unitprüfungen mit 181 Assertions erfolgreich, jetzt einschließlich installierter fixierter Composer-Abhängigkeiten und OIDC-Clientprüfungen. Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Neue Rendererprüfung deckt Nur-Lese-Navigation, Escaping und ausgeschlossene Upload-/Exportformulare ab. Zielmatrix, echter HTTP-Durchlauf und authentifizierte visuelle Abnahme bleiben noch offen.


### U3: Bereiche und Gruppenprofile verständlich verwalten (23. Oktober 2026)

Die Bereichsliste öffnet gezielt einen Bereich; Neuanlage und Löschung sind getrennte Abläufe. Quoten akzeptieren GB, GiB und kleinere Einheiten, mit exakter ganzzahliger Umrechnung und verlustfreier Darstellung bestehender Bytewerte. Profilkarten verbinden Beschreibung, aktuellen Zuweisungsstatus und die passende bestätigte Aktion. Bereichslinks führen direkt in die zugehörige Rechteverwaltung. Bestehende POST-Verträge bleiben kompatibel; widersprüchliche Größenfelder werden abgewiesen.

PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich, einschließlich Grenzwerten, Dezimalkomma, Überlauf, Bruchteilen von Bytes und exakten Quoten-Roundtrips. PHP- und Python-Syntax erfolgreich. Zusätzlicher Integrationstest und HTTP-Prüfablauf für die neue Größeneingabe vorbereitet; Ausführung der Datenbankmatrix erfolgt in CI und wird getrennt dokumentiert. Authentifizierte visuelle Abnahme noch offen.


### U4: Katalog, Medien und hilfreiche Fehlerrückmeldungen (23. Oktober 2026)

Tags werden als benannte Checkboxen gewählt; Tagverwaltung und erneute Medienanalyse öffnen sich bei Bedarf. Sucheinstiege sind verbunden, leere Medien- und Exportansichten nennen den nächsten Schritt. Fehlermeldungen haben konkrete Überschriften. Katalogentwürfe, Bereichsname/Quote, Ordnernamen und Dateiaktionen bleiben bei unterstützten Eingabe- und Konfliktfehlern im Formular erhalten. Ursprüngliche Revisions- und Konfliktbedingungen bleiben bestehen; ein Konflikt erhält keinen still erneuerten Schreibnachweis. Datei-Uploads müssen aus Browsergründen gegebenenfalls erneut ausgewählt werden. Technische Verarbeitungszustände werden nicht als fertig oder synchronisiert umgedeutet.

PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich; alle eigenen PHP-Dateien, das optionale lokale JavaScript und der aktualisierte HTTP-Prüfablauf sind syntaktisch geprüft. Integrationstests prüfen zusätzlich den erhaltenen Katalogentwurf samt alter Revision und Quoteneingaben. Diese Datenbanktests folgen mit der vollständigen CI-Matrix. Zwei überholte UI-Textannahmen aus den vorherigen CI-Läufen wurden auf die neue Struktur angepasst. Ein lokaler synthetischer HTML-Vorschauserver wurde erstellt, ist aber aus dem Cloud-Browser nicht erreichbar (ERR_HTTP_RESPONSE_CODE_FAILURE). Keine vollständige visuelle Abnahme behauptet.


### U4.1: Abschlussprüfung und lokale Skriptauslieferung (23. Oktober 2026)

Der lokale Router liefert das optionale UI-Skript mit korrektem MIME-Typ aus; der HTTP-Prüfablauf kontrolliert die Auslieferung. Assetversionen verhindern alte Layoutdateien nach dem Update. Schriftgrößen und Touchziele der bestehenden Formulare wurden vereinheitlicht. U1 bis U4 sind implementiert. U5 bleibt ausdrücklich abhängig von der noch offenen Entscheidung zwischen einer eigenen installierbaren Samla-Web-App und vorhandenen WebDAV-Apps; eine lokale Warteschlange oder echte Synchronisation ist im aktuellen Serverfrontend nicht vorhanden.

Vollständiger CI-Lauf 39890742597 für U4 ist in allen vier Kombinationen grün: je 221 Unit-/Architekturtests mit 1318 Assertions sowie 336 Integrationstests mit 1766 Assertions, zusätzlich HTTP und HTTPS. Alle vier Testlogs wurden gelesen; archiviert in verification-ui-u1-u4.md. Nachtrag lokal: 31 gezielte Tests mit 202 Assertions sowie tatsächliche HTTP-Auslieferung des UI-Skripts erfolgreich. Der Folgelauf für diesen Nachtrag wird gesondert geprüft. Visuelle Abnahme bleibt wegen des nicht erreichbaren lokalen Vorschauhosts offen.


### U4.2: Isolierte Browserprüfung und visuelle Nachweise (23. Oktober 2026)

Ein zusätzlicher CI-Job rendert die echten Templates mit ausschließlich synthetischen Daten und ohne Datenbank oder Produktivzugriff. Playwright 1.64.1 wird nur im temporären CI-Testverzeichnis installiert, nicht als Samla-Laufzeitabhängigkeit. Die Prüfung deckt Desktop- und Mobilbreiten, Navigation/Escape, Dateiauswahl, aufklappbare Formulare, Leseransicht, erhaltene Entwürfe und den Betrieb ohne JavaScript ab. 44 Bildschirmaufnahmen werden als kurzlebiges CI-Artefakt bereitgestellt und anschließend optisch geprüft.

Alle zwölf synthetischen PHP-Fixturrouten wurden lokal über HTTP ohne PHP-Warnungen gerendert. PHP- und JavaScript-Syntax erfolgreich. Der vorausgehende Nachtrag 39891300634 ist in allen vier PHP-/Datenbankkombinationen erfolgreich. Die neue Browserprüfung ist vorbereitet und wird erst nach ihrem tatsächlichen Lauf als erfolgreich ausgewiesen; eine Produktions- oder native Geräteabnahme ersetzt sie nicht.


### U4.3: Optische Nachprüfung und mobile Verdichtung (23. Oktober 2026)

Die ersten tatsächlichen Chromium-Aufnahmen der echten Templates wurden optisch betrachtet. Daraufhin wurden die zuvor zu hellen Ordnerpfade abgedunkelt, der mobile Kopfbereich auf eine kompakte Marken-/Menüzeile reduziert und Dateititel von Rücksprungaktionen getrennt. Die Exportleiste erscheint mit JavaScript erst nach einer Auswahl. Gruppen-/Bereichswechsel ist bei gewähltem Kontext zusammenklappbar; die Profilkarten stehen früher im Blickfeld. Medienhinweise folgen dem Raster. Die Browserprüfung kontrolliert zusätzlich Pfadkontrast und sichtbaren Beginn der Dateiliste.

Der Browserjob 120213191019 aus Lauf 39891842200 ist erfolgreich: Chromium 156.0.8078.4, Breiten 1440/1024/390/320, 44 Aufnahmen und erfolgreiche Interaktions-/Fallbackprüfungen. Das Artefakt wurde heruntergeladen; Dateiliste, Berechtigungen, Bereichsformular, Katalog einschließlich Entwurf, Dateidetail, Medien und Login wurden an repräsentativen Breiten optisch geprüft. Lokale 31 gezielte Unitprüfungen mit 202 Assertions und Syntax erfolgreich. Für die darauf folgenden optischen Korrekturen läuft ein neuer Browser-/Matrixnachweis. Dies ist eine synthetische Browserprüfung, keine Produktions- oder native Geräteabnahme.


### U5.1: Lokale Entwurfssicherung als Offline-Vorbereitung (23. Oktober 2026)

Im geöffneten Texteditor erzeugt Entwurf herunterladen nun in unterstützten Browsern eine lokale Textdatei ohne Serveranfrage. Damit bleibt die bewusste Sicherung auch nach Verbindungsabbruch möglich; ohne JavaScript bleibt der geschützte Serverdownload erhalten. Keine automatische Speicherung, persistente Offline-Warteschlange oder Synchronisation wird dadurch behauptet. Der vollständige U5-Ablauf hängt weiterhin vom Offlineclient ab, insbesondere vom gewünschten Verhalten bei geschlossener App. Die mobile Marken-/Menüzeile erhält nach erneuter optischer Prüfung eine explizite horizontale Flexrichtung.

Lokale Syntax und 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich. Browserfixture und Prüffälle sind um den Texteditor sowie einen Download bei tatsächlich deaktiviertem Netzwerk ergänzt; sie prüfen UTF-8-Inhalt und portablen Dateinamen. 48 Bildschirmaufnahmen sind nun vorgesehen. Der neue CI-Lauf wird getrennt geprüft. Die vorangehende UI-/Datenbankmatrix einschließlich 44 Browseransichten ist vollständig erfolgreich; repräsentative Screenshots wurden tatsächlich optisch betrachtet.


## Aktueller Abschlussstand

| Paket | Stand |
| --- | --- |
| U1 | Navigation, Konto, mobiler Rahmen und Start/Login implementiert; synthetisch im Browser geprüft |
| U2 | Dateiliste, Aktionen und Auswahl implementiert; synthetisch im Browser geprüft |
| U3 | Bereiche, exakte Quoten und Profilkarten implementiert; reale HTTP-/Datenbanktests erfolgreich |
| U4 | Medien/Katalog und unterstützte Formularrückmeldungen implementiert; optisch anhand echter Templates mit synthetischen Daten geprüft |
| U5.1 | Bewusster lokaler Download eines geöffneten Textentwurfs bei Verbindungsabbruch implementiert und mit deaktiviertem Netzwerk geprüft |
| Vollständiges U5 | Offen: persistente Offlinebearbeitung, Warteschlange, Synchronisation und Konfliktführung benötigen die Clientumsetzung. Dafür ist das erwartete Verhalten bei geschlossener App zu klären. |

Prüfstand: [verification-ui-u1-u4.md](verification-ui-u1-u4.md). Die ursprüngliche Planung wird nicht als vollständig erledigt markiert, solange U5 fehlt. Die Durchführung auf dem Betreiberhost und native iOS-/Android-/Desktopabnahme bleiben gesondert. Es ist keine erneute Go-Freigabe für die übrigen Arbeitspakete erforderlich.


### Prüfabschluss: UI-Pakete und Offline-Entwurf geprüft (23. Oktober 2026)

U1 bis U4 sowie die lokale Entwurfssicherung U5.1 sind veröffentlicht und geprüft. Repräsentative echte Templateansichten mit synthetischen Daten wurden nach Browseraufnahme optisch geprüft und erkannte Darstellungsfehler korrigiert. Vollständiges U5 bleibt offen: Für die Clientumsetzung muss insbesondere feststehen, ob Synchronisation bei geöffneter App genügt oder auch bei geschlossener App erwartet wird. Es wird keine fertige Offline-App behauptet. Betreiberdeployment bleibt getrennt.

Abschließender Codeprüflauf 39892974317 vollständig grün: alle vier PHP-/Datenbankkombinationen jeweils 557 Tests und 3084 Assertions, HTTP/HTTPS erfolgreich; zusätzlich erfolgreicher Chromium-Job mit 48 Aufnahmen, vier Breiten, Kontrast-/Sichtbarkeitsprüfungen, Tastatur, Leseransicht, JavaScript-Fallback und lokalem Download bei abgeschaltetem Netzwerk. Alle fünf Jobzustände und relevanten Logs sowie das Browserresultat wurden gelesen. Archiviert in verification-ui-u1-u4.md. Die abschließende Fortschreibung ändert ausschließlich Dokumentation.
