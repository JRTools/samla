# UI-Überarbeitung U1 bis U4: Prüfstand

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
