# UI-Abdeckung

Stand: 22. Oktober 2026, Funktionsphasen bis 40. Codeveröffentlichung ist keine Installation auf dem Betreiberhost. Die Funktionen erscheinen nach Codeupdate und nur mit ihren jeweiligen aktuellen Rechten. Diese Übersicht ist eine Quellcode-/HTTP-Abdeckung, keine vollständige visuelle oder native Geräteabnahme.

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
| Benutzer-/Gruppenverzeichnisse, Status und manuelle Mitgliedschaften | Benutzer beziehungsweise Gruppen | Verwaltungsoberflächen vorhanden; manuelle Mitgliedschaften verwenden Benutzernamen im Dropdown |
| WebDAV-Dateioperationen und LOCK/UNLOCK | Externer WebDAV-Client über /dav/ | Protokolladapter vorhanden; keine eigene browserseitige Sperrenverwaltung oder native Clientabnahme |
| Feste Rechteprofile für Gruppen | Berechtigungen unter /permissions | Namensbasierte Dropdowns für Gruppe, Bereich und Profil; Zuweisen/Entziehen, Bestätigung und Schutz vor veraltetem Formular; Datei-, Medien-, sensible Metadaten- und Workerprofile sowie bekannte alte CLI-Bereichszuweisungen |
| IdP-Gruppenmapping | IdP-Gruppen zuordnen unter /administration/group-mappings | Lokale Gruppennamen im Dropdown; Hinzufügen für konfigurierten signierten Claim, bestätigtes Entfernen, aktuelle Delegations-/Kontinuitätsprüfung |
| Beliebige Rollen, administrative Zuweisungen und Delegationsverwaltung | Noch kein umfassender UI-Einstieg | Weiterhin offen; die festen Profile sind keine allgemeine Rollenbearbeitung |
| Persönliche Bereiche | Automatische Anlage nach erfolgreicher OIDC-Anmeldung; Dateien und Bereiche verwalten | Ein Bereich je Konto, Eigentümerrechte, Kennzeichnung und Eigentümername; Name/Quote administrativ änderbar, persönliche Löschung bis zur Lebenszyklusrichtlinie gesperrt |
| Bereichsanlage, Name/Quote und Löschen | Bereiche verwalten unter /administration/spaces | Webformulare und Dropdowns; bestätigte Löschung nur vollständig leerer Bereiche einschließlich atomarer Rechtebereinigung |
| Initiale Administration, Konsistenzcheck und Objekt-GC | Dokumentierte CLI-Befehle | Weiterhin kontrollierte Betreiberabläufe außerhalb der neuen Bereichsoberfläche |
| Outbox-Worker, Zustellstatus und Retry | Hintergrundaufgaben unter /operations/worker; Ausführung über CLI oder authentifizierten POST /tasks/run | Browseransicht mit getrennten Rechten, Filter/Pagination und revisionsgebundenem Retry; Betreiberkonfiguration des Maschinen-Tokens bleibt außerhalb des UI |
| Medienraster, Vorschauen und ausgewählte EXIF-Felder | Medien unter /media mit Ordnern und Detailseite | Rechtegeschützte PNG-Vorschau, getrennte Metadatenrechte, Quelle/Originalwert, einzelne Analysevormerkung und bereichsweiter seitenweiser Neuaufbau; GD/EXIF und Worker erforderlich |
| Ausgewählte IPTC-/XMP-/PNG-Textangaben und strukturierte KI-Deklaration | Medien-Detailansicht | Quelle/Normalisierung und Auslesestatus; separate Rechte auf Prompt-/Autorfelder; C2PA nicht untersucht oder validiert |
| Private ZIP-Exporte aktueller Dateien | Dateiauswahl unter Dateien; Meine Exporte unter /exports | Eigene Aufträge, Status/Ablauf, geschützter Download, Retry und Entfernen; separates Exportprofil und ZIP-fähiger Worker erforderlich |
| Manuelle Beschreibungen und Tags | Dateien → Eintrag verwalten → Beschreibung und Tags | Texte, Namens-Mehrfachauswahl, Taganlage/-umbenennung und bestätigtes Löschen unbenutzter Tags; gesonderte Bereichsprofile, neue Migration |
| Tag-Mehrfachbearbeitung | Dateiliste → Tags für mehrere Dateien ändern | Ordnerweise Auswahl bis 100 Dateien, benannter Tag und Add/Remove, Bestätigung, atomare Revisions-/Rechteprüfung und Einzelaudit |
| Katalogsuche | Dateiliste → Beschreibungen und Tags durchsuchen | Name/Beschreibung/Tags, Namens-Dropdown und Endungsfilter, Ordnerpfad, begrenzte Treffertexte und Cursorseiten; separate aktuelle Katalog-/Originalleserechte |
| Sammlungen, C2PA, frei konfigurierbare manuelle Felder | Noch offen | Noch nicht als im UI vorhanden ausgewiesen |

Die bloße Sichtbarkeit eines Buttons gewährt keine Berechtigung. Jeder tatsächliche Dienstaufruf prüft seine Rechte erneut. Fehlende Rechte blenden passende Aktionen aus oder verhindern direkte Aufrufe. Dateisperren schützen Browseränderungen auch ohne zusätzliche Browser-Sperrenschaltfläche.
