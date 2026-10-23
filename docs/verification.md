# Prüfung Iteration 40: Katalogsuche

Alle vier isolierten lokalen Kombinationen PHP 8.4.5/8.5.4 und MySQL 8.0/MariaDB 11.8 erfolgreich: jeweils 216 Unit-/Architekturtests mit 1280 Assertions und 335 Integrationstests mit 1755 Assertions (551 Tests/3035 Assertions je Kombination). Syntax aller 291 eigenen PHP-Dateien erfolgreich. Keine neue Migration; CLI meldet Schema is current.

Ein neuer Unitfall prüft begrenzte öffentliche FileCatalogEntry-Werte. Fünf neue Integrationsfälle prüfen Namen/manuelle Beschreibungen/Tags, Unicode-groß-/kleinschreibungsunabhängige wörtliche Suche mit Prozent/Unterstrich, kombinierte Tag-/Endungsfilter, sichtbare Ordnerpfade, gelöschte Dateien/Eltern, öffentliche sichtbare Katalogwerte, aktuelle Original-/Katalogrechte, fremde Tags, Eingabetypen/Größen/Cursor, sichere Namensdarstellung sowie 101 Treffer ohne doppelte Cursorergebnisse oder Gesamttrefferzahl. Vorhandene Namenssuchtests einschließlich kaputter/zyklischer Eltern und Kataloggrenzen laufen unverändert erfolgreich mit der geteilten Pfadprüfung.

Vollständiger synthetischer HTTP-/HTTPS-Ablauf erfolgreich: PHP 8.4/8.5 Entwicklungs-HTTP und PHP 8.4 Produktions-HTTPS. Neu: tatsächliche Suchseite aus dem Bootstrap, kombinierter Beschreibungs-/Tag-/Endungsfilter mit .PHP, Suche nach Taglabel, Ausschluss unpassender Endung, Unicode-Suche in einer 16-KiB-Beschreibung mit gekürztem Treffertext sowie sofortiger Zugriffsentzug nach Profilwiderruf. Persönliche Bereiche, ZIP, Katalog/Batch, Medien, DAV und OIDC bleiben erfolgreich. Keine echten Betreiberzugangsdaten.

GitHub-CI von Paket 39 ist vollständig erfolgreich; gelesene Logs in verification-iteration-39.md. CI dieses Pakets folgt nach Veröffentlichung. Keine zusätzlichen sensitiven Medienfelder oder Originalinhalte werden durchsucht. Keine neue Laufzeitkomponente und keine visuelle/native/produktive Lastabnahme behauptet. Die maximal 10000 Katalogeinträge sind eine technische Begrenzung, keine vollständige Performancezusage.


## Dokumentationsfortschreibung: verbindliche Offline-Synchronisation (22. Oktober 2026)

README, Plan 1.45, Betreiberentscheidungen und native Clientabnahme verweisen auf den konkretisierten Offline-Ablauf und dessen Abnahmefälle. Dokumentationsänderung ohne Laufzeitcode oder Schemaänderung; deshalb keine neue PHP-/Datenbankmatrix ausgeführt. Vorhandene Matrixnachweise beziehen sich ausschließlich auf die dort genannten Implementierungsstände. Keine Offline-Geräteprüfung und keine produktive Installation vorgenommen.


## UI-Analyse und Überarbeitungsplan (22. Oktober 2026)

Öffentliche Anmeldeseite im Browser als DOM und Screenshot betrachtet. Ein vollständiger Screenshotversuch lief in einen Timeout; die anschließende Aufnahme des sichtbaren Bereichs war erfolgreich. Angemeldete Ansichten waren nicht zugänglich; sechs aktuelle Template-/CSS-Dateien wurden ergänzend gelesen. Analyse und Grenzen: ui-overhaul.md. Ausschließlich Dokumentationsänderung, keine neue PHP-/Datenbankmatrix ausgeführt und keine authentifizierte oder mobile visuelle Abnahme behauptet.



## UI-Paket U1: Navigation und Seitenrahmen (23. Oktober 2026)

PHP 8.4.5: 23 gezielte Unitprüfungen mit 133 Assertions erfolgreich; Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Ein erster zusätzlicher OIDC-Clienttestversuch scheiterte an noch nicht installierten Composer-Testabhängigkeiten, nicht an einer fachlichen Assertion. Zielmatrix und HTTP-Durchlauf folgen über GitHub-CI. Die angemeldeten Ausgangsansichten waren nicht live zugänglich; vollständige authentifizierte und mobile visuelle Abnahme bleibt offen.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.


## UI-Paket U2: Dateien und Aktionen im Mittelpunkt (23. Oktober 2026)

PHP 8.4.5: 29 gezielte Unitprüfungen mit 181 Assertions erfolgreich, jetzt einschließlich installierter fixierter Composer-Abhängigkeiten und OIDC-Clientprüfungen. Syntax aller 44 Templates/Webklassen und JavaScript erfolgreich. Neue Rendererprüfung deckt Nur-Lese-Navigation, Escaping und ausgeschlossene Upload-/Exportformulare ab. Zielmatrix, echter HTTP-Durchlauf und authentifizierte visuelle Abnahme bleiben noch offen.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.


## UI-Paket U3: Bereiche und Gruppenprofile verständlich verwalten (23. Oktober 2026)

PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich, einschließlich Grenzwerten, Dezimalkomma, Überlauf, Bruchteilen von Bytes und exakten Quoten-Roundtrips. PHP- und Python-Syntax erfolgreich. Zusätzlicher Integrationstest und HTTP-Prüfablauf für die neue Größeneingabe vorbereitet; Ausführung der Datenbankmatrix erfolgt in CI und wird getrennt dokumentiert. Authentifizierte visuelle Abnahme noch offen.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.


CI-Nachtrag zu U2: Lauf 39889957275 scheiterte in allen vier Kombinationen an einer alten Erwartung der Startseitenüberschrift in WebAdministrationTest. Der betrachtete PHP-8.4/MariaDB-Job bestand 219 Unit-/Architekturtests mit 1295 Assertions und führte 335 Integrationstests mit genau diesem einen Fehler aus. Die Erwartung wird auf die bewusst geänderte Arbeitsstartseite aktualisiert. Die übrigen Matrixlogs wurden noch nicht vollständig ausgewertet. Ein vollständiger grüner Folgelauf steht weiterhin aus.


## UI-Paket U4: Katalog, Medien und hilfreiche Fehlerrückmeldungen (23. Oktober 2026)

PHP 8.4.5: 31 gezielte Unitprüfungen mit 202 Assertions erfolgreich; alle eigenen PHP-Dateien, das optionale lokale JavaScript und der aktualisierte HTTP-Prüfablauf sind syntaktisch geprüft. Integrationstests prüfen zusätzlich den erhaltenen Katalogentwurf samt alter Revision und Quoteneingaben. Diese Datenbanktests folgen mit der vollständigen CI-Matrix. Zwei überholte UI-Textannahmen aus den vorherigen CI-Läufen wurden auf die neue Struktur angepasst. Ein lokaler synthetischer HTML-Vorschauserver wurde erstellt, ist aber aus dem Cloud-Browser nicht erreichbar (ERR_HTTP_RESPONSE_CODE_FAILURE). Keine vollständige visuelle Abnahme behauptet.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.


## UI-Paket U4.1: Abschlussprüfung und lokale Skriptauslieferung (23. Oktober 2026)

Vollständiger CI-Lauf 39890742597 für U4 ist in allen vier Kombinationen grün: je 221 Unit-/Architekturtests mit 1318 Assertions sowie 336 Integrationstests mit 1766 Assertions, zusätzlich HTTP und HTTPS. Alle vier Testlogs wurden gelesen; archiviert in verification-ui-u1-u4.md. Nachtrag lokal: 31 gezielte Tests mit 202 Assertions sowie tatsächliche HTTP-Auslieferung des UI-Skripts erfolgreich. Der Folgelauf für diesen Nachtrag wird gesondert geprüft. Visuelle Abnahme bleibt wegen des nicht erreichbaren lokalen Vorschauhosts offen.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.


## UI-Paket U4.2: Isolierte Browserprüfung und visuelle Nachweise (23. Oktober 2026)

Alle zwölf synthetischen PHP-Fixturrouten wurden lokal über HTTP ohne PHP-Warnungen gerendert. PHP- und JavaScript-Syntax erfolgreich. Der vorausgehende Nachtrag 39891300634 ist in allen vier PHP-/Datenbankkombinationen erfolgreich. Die neue Browserprüfung ist vorbereitet und wird erst nach ihrem tatsächlichen Lauf als erfolgreich ausgewiesen; eine Produktions- oder native Geräteabnahme ersetzt sie nicht.

Keine produktiven Daten verändert, keine Installation auf dem Betreiberhost und keine neue native Geräteabnahme.
