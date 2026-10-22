# UI-Abdeckung

Stand: 22. Oktober 2026, veröffentlichte Funktionsphasen bis 26. Codeveröffentlichung ist keine Installation auf dem Betreiberhost. Die Funktionen erscheinen nach Codeupdate und nur mit ihren jeweiligen aktuellen Rechten. Diese Übersicht ist eine Quellcode-/HTTP-Abdeckung, keine vollständige visuelle oder native Geräteabnahme.

| Funktion | Einstieg | Status |
|---|---|---|
| Bereichs- und Ordnerliste, Upload, Ordneranlage, Download | Dateien, danach Speicherbereich/Ordner | Weboberfläche vorhanden |
| Umbenennen, Verschieben, Einzeldateikopie und Papierkorb | Eintrag verwalten auf der Dateizeile | Webformulare vorhanden; rekursive Ordnerkopien und bereichsübergreifende Operationen fehlen |
| Versionsliste, Download früherer Inhalte, Wiederherstellung | Eintrag verwalten → Dateiversionen ansehen | Weboberfläche vorhanden; separates Historienleserecht erforderlich |
| Wiederherstellen und endgültiges Löschen | Papierkorb öffnen im Speicherbereich | Webformulare vorhanden; endgültiges Löschen braucht separates files.purge und ausdrückliche Bestätigung |
| Namenssuche über Unterordner | In diesem Bereich suchen | Weboberfläche mit Text-/Typfilter und Seitenwechsel vorhanden |
| Textansicht und Editor | Eintrag verwalten → Text öffnen | Für unterstützte UTF-8-Dateien bis 64 KiB vorhanden; ohne update nur lesbar |
| Konfliktentwurf sichern | Editor nach 409/423/Quotenfehler → Entwurf herunterladen | Formular und Attachment vorhanden; Entwürfe werden nicht automatisch serverseitig gespeichert |
| App-Passwörter anlegen und widerrufen | Mein Zugang → App-Passwörter verwalten | Weboberfläche vorhanden |
| Benutzer-/Gruppenverzeichnisse, Status und manuelle Mitgliedschaften | Benutzer beziehungsweise Gruppen | Bisher beschriebene Verwaltungsoberflächen vorhanden |
| WebDAV-Dateioperationen und LOCK/UNLOCK | Externer WebDAV-Client über /dav/ | Protokolladapter vorhanden; keine eigene browserseitige Sperrenverwaltung oder native Clientabnahme |
| Rollenbearbeitung, Zuweisungen, Gruppenmapping und Delegationsverwaltung | Noch kein umfassender UI-Einstieg | Bestehende Backendverträge/geschützte Teilfunktionen und Wartungs-CLI ersetzen keine vollständige Verwaltungsoberfläche |
| Bereichsanlage, initiale Administration, Konsistenzcheck und Objekt-GC | Dokumentierte CLI-Befehle | Noch keine vollständigen Webformulare |
| Outbox-Worker und Zustellstatus | bin/worker.php beziehungsweise authentifizierter POST /tasks/run | CLI und Maschinen-Webtrigger implementiert, Token-Erzeugung auch lokal im Browser möglich; noch kein administrativer Zustell-/Retrybildschirm |
| Medienraster, Vorschauen, Metadaten, Tags und Sammlungen | Noch offen | Nicht als fertig implementiert oder im UI vorhanden ausgewiesen |

Die bloße Sichtbarkeit eines Buttons gewährt keine Berechtigung. Jeder tatsächliche Dienstaufruf prüft seine Rechte erneut. Fehlende Rechte blenden passende Aktionen aus oder verhindern direkte Aufrufe. Dateisperren schützen Browseränderungen auch ohne zusätzliche Browser-Sperrenschaltfläche.
