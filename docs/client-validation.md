# Native Clientabnahme

Zielplattformen: iOS, Android, Windows und macOS. Die Betriebssystemversionen und konkreten Clients sind noch nicht benannt. Die vorhandenen automatisierten HTTP-/HTTPS-Prüfungen weisen keine native App-Kompatibilität nach. Diese Liste ergänzt die Servermatrix und bleibt bis zur tatsächlichen Geräteprüfung offen.

## Testumgebung

Vorher Code, Composer-Lockfile und Migrationen nach installation.md aktualisieren. Ein separater Testbereich mit nicht sensiblen Dateien reicht aus. Ein frisches eigenes App-Passwort für genau dieses Testgerät anlegen, interne Samla-Kennung als Benutzername verwenden. Für die bekannte Installation ist die Basisadresse `https://samla.jrtools.org/dav/`; alternativ direkt einen freigegebenen Bereich unterhalb dieser Adresse einbinden. Konkrete Bereichskennungen stammen aus der authentifizierten DAV-Liste. Produktive Dateien nicht für Lösch-, Überschreib- oder Konflikttests verwenden.

| Plattform | Betriebssystemversion | Client und Version | Lesen | Schreiben | Sperren/Konflikte | Status |
| --- | --- | --- | --- | --- | --- | --- |
| iOS | offen | offen | offen | offen | offen | nicht ausgeführt |
| Android | offen | offen | offen | offen | offen | nicht ausgeführt |
| Windows | offen | offen | offen | offen | offen | nicht ausgeführt |
| macOS | offen | offen | offen | offen | offen | nicht ausgeführt |

## Prüffälle je Referenzclient

1. Verbinden und Ordnerliste öffnen. Ausschließlich explizit freigegebene Bereiche erscheinen. Unterordner, Leerzeichen und Umlaute werden korrekt dargestellt.
2. Eine kleine Textdatei und ein Bild herunterladen. Inhalt beziehungsweise Prüfsumme mit dem Original vergleichen. Ein bestehender Browserlogin allein darf keine DAV-Anmeldung ersetzen.
3. Einen Testordner anlegen, eine neue Datei hochladen und sie erneut herunterladen. Auch eine leere Datei prüfen. Größen-/Quotenüberschreitung muss ohne stillen Teilinhalt sichtbar scheitern.
4. Den Inhalt der Testdatei ändern. In der Browserhistorie müssen vorheriger und neuer Inhalt unterscheidbar sein. Eine alte erwartete Revision darf neuere Inhalte nicht verdrängen.
5. Datei umbenennen, in einen Unterordner verschieben und einzeln kopieren. Anschließend Datei- und Ordnerlisten erneut laden.
6. Eine temporäre Testdatei auf einen bestehenden ungesperrten Dateinamen verschieben beziehungsweise kopieren. Der neue Inhalt erscheint; das bisherige Ziel bleibt im Browserpapierkorb erhalten. Overwrite F darf nicht überschreiben. Die aktuell dokumentierte Einschränkung bei aktiv gesperrten Zielen bleibt maßgeblich.
7. Falls der Client Sperren nutzt: Aus demselben Konto über den Browser eine Änderung versuchen, während die Sperre aktiv ist. Sie muss mit 423 scheitern. Nach ausdrücklichem Entsperren oder Ablauf darf die normale Berechtigung wieder greifen. Zwei Geräte dürfen sich nicht unbemerkt gegenseitig überschreiben.
8. Datei löschen und unter demselben Namen neu anlegen. Der alte Inhalt bleibt im Papierkorb. Eine Wiederherstellung mit belegtem Namen meldet einen Konflikt.
9. Das Geräte-App-Passwort im Browser widerrufen. Eine neue DAV-Anfrage mit diesem Passwort muss verweigert werden. Auch lokales Sperren des Kontos und abgelaufene Gruppenfrische bleiben wirksam.
10. Einen Benutzer ohne Rechte auf einen anderen Testbereich verwenden. Weder dessen Inhalt noch Namen oder Vorschaudaten dürfen über direkte URLs zugänglich werden.

Aktuelle noch gesperrte Protokollfälle stehen in [webdav.md](webdav.md). Ein erwartetes Ablehnen eines dort dokumentierten Falls wird nicht als erfolgreiche vollständige Clientabnahme gewertet. Insbesondere tatsächliches Speichern aus Büroprogrammen muss separat getestet werden, weil Programme unterschiedliche Folgen aus PUT, LOCK und MOVE verwenden.

## Ergebnis zurückmelden

Ausreichend sind Plattform, Betriebssystemversion, Client samt Version, betroffener Prüfschritt und sichtbare Fehlermeldung beziehungsweise HTTP-Status. Keine App-Passwörter, Authorization-Header, Cookies oder IdP-Tokens in Berichte aufnehmen. Ein Serverlog darf die vorhandene gekürzte Exceptionklasse mit Quelldatei/Zeile nennen; vollständige Requests sind dafür nicht erforderlich.

Abnahme erfordert tatsächlich ausgeführte Schritte und dokumentierte Einschränkungen. Eine gefüllte Planungstabelle allein ist kein erfolgreicher Test.

## Zusätzliche verpflichtende Offlineabnahme

Für jede der vier Plattformen sind zusätzlich sämtliche Fälle aus [offline-sync.md](offline-sync.md) tatsächlich durchzuführen. Lesen und Schreiben bei bestehender Verbindung reichen nicht aus. Insbesondere Offlinebearbeitung mit App-Neustart, automatische Wiederaufnahme, Antwortverlust und konkurrierende Änderungen sind bislang nicht auf Geräten abgenommen.
