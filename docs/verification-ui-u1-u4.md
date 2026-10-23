# UI-Überarbeitung U1 bis U4 und U5.1: Prüfstand

Stand: 23. Oktober 2026.

## Vollständiger erfolgreicher CI-Lauf

Commit f2865a623ba496a55efa8163015a64302176576e, [Lauf 39890742597](https://github.com/JRTools/samla/actions/runs/39890742597).

| Kombination | Job | Unit/Architektur | Integration | HTTP und HTTPS |
| --- | --- | --- | --- | --- |
| PHP 8.4.27 / MySQL 8.0 | 120209633386 | 221 Tests, 1318 Assertions | 336 Tests, 1766 Assertions | erfolgreich |
| PHP 8.4.27 / MariaDB 11.8 | 120209633354 | 221 Tests, 1318 Assertions | 336 Tests, 1766 Assertions | erfolgreich |
| PHP 8.5.12 / MySQL 8.0 | 120209633155 | 221 Tests, 1318 Assertions | 336 Tests, 1766 Assertions | erfolgreich |
| PHP 8.5.12 / MariaDB 11.8 | 120209633387 | 221 Tests, 1318 Assertions | 336 Tests, 1766 Assertions | erfolgreich |

Alle vier Jobzustände und die relevanten Testausgaben wurden gelesen. Je Kombination sind das 557 Tests mit 3084 Assertions. Syntax, Composer-Plattformprüfung und Migrationstest gehören ebenfalls zum erfolgreichen Workflow. Die HTTP-Abläufe prüfen unter anderem Anmeldung, Profilzuweisung, Quoten mit Einheit, Dateioperationen, Checkbox-Tags, Versionskonflikte und Editorentwürfe.

Frühere Zwischenläufe scheiterten an überholten UI-Textannahmen für die Startseite und eine Profilbezeichnung. Diese Erwartungen wurden auf die beabsichtigte Darstellung aktualisiert; das Ergebnis oben stammt aus dem vollständigen Folgelauf.

## Nachtrag nach diesem Lauf

Der lokale Router wird um die ausdrücklich erlaubte Datei /assets/workspace.js ergänzt; der HTTP-Test prüft deren Auslieferung. Ein isolierter lokaler HTTP-Aufruf unter PHP 8.4.5 hat Status 200, den JavaScript-MIME-Typ, nosniff und identische ausgelieferte Dateibytes geprüft. Die 31 gezielten Unitprüfungen mit 202 Assertions sind erneut erfolgreich. CSS und JavaScript erhalten eine neue Assetversion. Für diesen Nachtrag wird der nächste CI-Lauf gesondert geprüft; der oben angegebene Lauf wird nicht nachträglich als Beleg für spätere Änderungen ausgegeben.

## Grenzen

Der Cloud-Browser konnte den lokalen synthetischen Vorschauhost nicht erreichen (ERR_HTTP_RESPONSE_CODE_FAILURE). Authentifizierte Ansichten auf dem Betreiberhost wurden nicht verändert oder als visuell abgenommen ausgegeben. Vollständige visuelle Desktop-/Mobilabnahme und native Geräteprüfung bleiben offen. Erfolgreiche PHP-/HTTP-Tests beweisen keine intuitive Bedienung und keine Offline-Synchronisation. U5 hängt weiterhin von der Wahl des Offlineclients und dessen tatsächlicher Synchronisationsbasis ab.


## Synthetische visuelle Abnahme und Nachkorrektur

Der zusätzliche Browserjob 120213191019 in Lauf 39891842200 erzeugte erfolgreich 44 Chromium-Aufnahmen der echten Templates bei 1440, 1024, 390 und 320 Pixeln. Die Artefakte wurden heruntergeladen und repräsentative Datei-, Detail-, Katalog-, Entwurfs-, Medien-, Berechtigungs-, Bereichs- und Loginansichten optisch betrachtet. Erkannte Schwächen an Pfadkontrast, mobilem Seitenrahmen und vorgeschalteten Auswahlformularen wurden korrigiert.

Lauf 39892301526 ist anschließend einschließlich des Browserjobs 120214646902 sowie aller vier PHP-/Datenbankkombinationen erfolgreich. Die 44 erneuten Aufnahmen wurden heruntergeladen und die korrigierten mobilen Datei-/Detail-/Berechtigungs-/Medienansichten betrachtet. Die mobile Navigation benötigte noch eine explizite Flexrichtung; diese zusätzliche Korrektur erhält eine eigene Browserassertion. Der nächste Lauf prüft außerdem den lokalen Entwurfsdownload bei tatsächlich abgeschaltetem Browsernetzwerk.

Es handelt sich um synthetische Ansichten und Interaktionen auf einem isolierten CI-Server, nicht um Zugriff auf Produktionsdaten oder eine native iOS-/Android-Abnahme.


## Abschließender Codeprüfstand

Commit b1687e509bc6d65209ec84ec8d7903b0ced85137: [Lauf 39892974317](https://github.com/JRTools/samla/actions/runs/39892974317) ist vollständig erfolgreich. Alle fünf Jobzustände und Logs wurden gelesen.

| Kombination | Job | Ergebnis |
| --- | --- | --- |
| PHP 8.4.27 / MySQL 8.0 | 120216781169 | 221 Unit-/Architekturtests, 1318 Assertions; 336 Integrationstests, 1766 Assertions; HTTP und HTTPS erfolgreich |
| PHP 8.4.27 / MariaDB 11.8 | 120216781118 | dieselben Testzahlen; HTTP und HTTPS erfolgreich |
| PHP 8.5.12 / MySQL 8.0 | 120216781003 | dieselben Testzahlen; HTTP und HTTPS erfolgreich |
| PHP 8.5.12 / MariaDB 11.8 | 120216781201 | dieselben Testzahlen; HTTP und HTTPS erfolgreich |
| Synthetische Browseransichten | 120216780812 | Chromium 156.0.8078.4; 48 Aufnahmen bei 1440/1024/390/320 Pixeln, Interaktionen und Netzwerkausfallprüfung erfolgreich |

Das letzte Browserartefakt wurde heruntergeladen und result.json gelesen. Korrigierte Datei-/Berechtigungsansichten bei 390 Pixeln, Editor bei 320 Pixeln und Dateiliste bei 1440 Pixeln wurden erneut optisch betrachtet. Die Navigation ist nun horizontal kompakt; Pfadlinks sind kontrastreich, Dateien ohne vorgeschaltete Exportkarte sichtbar. Der Test prüft mindestens 4,5:1 Pfadkontrast, eine kompakte mobile Navigationshöhe und den sichtbaren Beginn der Dateiliste.

Der Offline-Downloadtest schaltet das Browsernetzwerk tatsächlich ab, lädt den geöffneten Entwurf herunter und vergleicht UTF-8-Inhalt und Dateinamen. Dies belegt die bewusste lokale Sicherung eines geöffneten Entwurfs. Eine dauerhafte lokale Warteschlange, geräteübergreifende Synchronisation oder Verarbeitung bei geschlossener App ist dadurch nicht implementiert oder abgenommen.

Nach diesem Codeprüfstand wird ausschließlich Dokumentation fortgeschrieben. Keine weitere Laufzeitänderung wird auf die obigen Ergebnisse bezogen. Betreiberdeployment und echte Geräteprüfung stehen weiterhin aus.
