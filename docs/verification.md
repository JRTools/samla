# Prüfung Iteration 40: Katalogsuche

Alle vier isolierten lokalen Kombinationen PHP 8.4.5/8.5.4 und MySQL 8.0/MariaDB 11.8 erfolgreich: jeweils 216 Unit-/Architekturtests mit 1280 Assertions und 335 Integrationstests mit 1755 Assertions (551 Tests/3035 Assertions je Kombination). Syntax aller 291 eigenen PHP-Dateien erfolgreich. Keine neue Migration; CLI meldet Schema is current.

Ein neuer Unitfall prüft begrenzte öffentliche FileCatalogEntry-Werte. Fünf neue Integrationsfälle prüfen Namen/manuelle Beschreibungen/Tags, Unicode-groß-/kleinschreibungsunabhängige wörtliche Suche mit Prozent/Unterstrich, kombinierte Tag-/Endungsfilter, sichtbare Ordnerpfade, gelöschte Dateien/Eltern, öffentliche sichtbare Katalogwerte, aktuelle Original-/Katalogrechte, fremde Tags, Eingabetypen/Größen/Cursor, sichere Namensdarstellung sowie 101 Treffer ohne doppelte Cursorergebnisse oder Gesamttrefferzahl. Vorhandene Namenssuchtests einschließlich kaputter/zyklischer Eltern und Kataloggrenzen laufen unverändert erfolgreich mit der geteilten Pfadprüfung.

Vollständiger synthetischer HTTP-/HTTPS-Ablauf erfolgreich: PHP 8.4/8.5 Entwicklungs-HTTP und PHP 8.4 Produktions-HTTPS. Neu: tatsächliche Suchseite aus dem Bootstrap, kombinierter Beschreibungs-/Tag-/Endungsfilter mit .PHP, Suche nach Taglabel, Ausschluss unpassender Endung, Unicode-Suche in einer 16-KiB-Beschreibung mit gekürztem Treffertext sowie sofortiger Zugriffsentzug nach Profilwiderruf. Persönliche Bereiche, ZIP, Katalog/Batch, Medien, DAV und OIDC bleiben erfolgreich. Keine echten Betreiberzugangsdaten.

GitHub-CI von Paket 39 ist vollständig erfolgreich; gelesene Logs in verification-iteration-39.md. CI dieses Pakets folgt nach Veröffentlichung. Keine zusätzlichen sensitiven Medienfelder oder Originalinhalte werden durchsucht. Keine neue Laufzeitkomponente und keine visuelle/native/produktive Lastabnahme behauptet. Die maximal 10000 Katalogeinträge sind eine technische Begrenzung, keine vollständige Performancezusage.


## Dokumentationsfortschreibung: verbindliche Offline-Synchronisation (22. Oktober 2026)

README, Plan 1.45, Betreiberentscheidungen und native Clientabnahme verweisen auf den konkretisierten Offline-Ablauf und dessen Abnahmefälle. Dokumentationsänderung ohne Laufzeitcode oder Schemaänderung; deshalb keine neue PHP-/Datenbankmatrix ausgeführt. Vorhandene Matrixnachweise beziehen sich ausschließlich auf die dort genannten Implementierungsstände. Keine Offline-Geräteprüfung und keine produktive Installation vorgenommen.
