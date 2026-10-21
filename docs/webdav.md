# WebDAV

Ab Iteration 18 ist ein geschützter Lesezugang unter `/dav/` vorhanden. Ordnerlisten (PROPFIND Depth 0/1), OPTIONS, GET und HEAD einschließlich bedingtem Download und Bytebereichen sind angeschlossen. Schreibmethoden werden vor jedem Seiteneffekt mit 405 verweigert. Es handelt sich noch nicht um den vollständigen WebDAV-Schreibzugang.

## Einrichtung

Die öffentliche Adresse und HTTPS-Prüfung stammen aus derselben OIDC_REDIRECT_URI wie die Browseranmeldung. Für die bekannte Installation ist die Basisadresse `https://samla.jrtools.org/dav/`. Webserver-Rewrites müssen `/dav/` einschließlich Unterpfaden und Methoden an `public/index.php` weiterreichen. Es wird kein zusätzliches öffentliches Speicherverzeichnis angelegt. Basic-Authorization muss PHP unverändert erreichen; bei abgewiesener Anmeldung zuerst die serverseitige Weitergabe dieses Headers prüfen. Keine Header oder Passwörter protokollieren.

Nach OIDC-Anmeldung unter „Mein Zugang“ ein App-Passwort anlegen. Benutzername ist die dort angezeigte interne Samla-Kennung, Passwort der einmal angezeigte Gerätezugang. Das IdP-Passwort und die Browser-Cookies sind keine DAV-Zugangsdaten. Eine Anfrage ohne gültiges App-Passwort erhält 401 mit Basic-Challenge. Gültige Credentials verleihen keine zusätzlichen Bereichsrechte.

Die Basisliste enthält nur Bereiche mit files.list. Jeder Bereich besitzt eine stabile Kennung als URL-Komponente, etwa `/dav/space.<kennung>/`. DAV-displayname liefert den lesbaren Bereichsnamen. Darunter liegen die tatsächlichen Ordner- und Dateinamen, pro Komponente einmal URL-kodiert. Ein konkreter Bereich kann direkt eingebunden werden. Samla akzeptiert keine beliebigen absoluten Fremd-URLs oder mehrdeutigen Pfade.

Für Dateiinhalte ist zusätzlich files.read nötig, auch bei HEAD und bedingten Anfragen mit bekanntem ETag. Credentials werden bei jeder Ressourcenautorisierung auf Eigentümer, Ablauf, Widerruf, Kontosperre und Gruppenfrische geprüft. APP_GROUP_MAX_AGE_SECONDS gilt auch für Geräte; nach Ablauf muss der Benutzer seinen Gruppenstand durch OIDC-Anmeldung erneuern. Geräte erhalten keine automatische dauerhafte Anmeldung am IdP.

## Grenzen und Nachweise

XML-Requests sind auf 64 KiB begrenzt; DTDs, externe Entitäten, unendliche PROPFIND-Tiefe, Pfadtraversierung und kodierte Trennzeichen werden abgewiesen. Listen traversieren höchstens 10000 Katalogeinträge. Downloads bleiben Streams, einschließlich exakt begrenzter Range-Antworten. Dateiinhalte werden als application/octet-stream und Attachment geliefert. ETags binden stabile Kennung und Revision. Last-Modified wird mangels eigener Änderungszeit noch nicht behauptet.

Einbetten, Upload, Umbenennen, Verschieben, Kopieren, Löschen, LOCK/UNLOCK und Eigenschaftsänderungen sind in dieser Phase gesperrt. Das gemeinsame Sperrmodell ist vorhanden, aber noch nicht als DAV-Lock-Backend angeschlossen. OPTIONS meldet nur die tatsächlich freigeschalteten Methoden. Der anschließende Schreibadapter muss bestehende Sperren, Versionierung, Revisionserwartungen und atomare Namenskonflikte verwenden.

Ziel sind etwa 1000 Dateien und fünf aktive Benutzer. iOS, Android, Windows und macOS sind Zielplattformen. Die automatisierten Protokollprüfungen ersetzen noch keine Abnahme konkreter nativer Clients und Betriebssystemversionen. Die Änderungen werden nicht automatisch auf dem Betreiberhost ausgerollt. Keine neue Migration und keine zusätzliche Laufzeitabhängigkeit in dieser Phase.
