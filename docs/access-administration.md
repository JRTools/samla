# Bereiche und Berechtigungen im Browser verwalten

Nach dem Codeupdate erscheinen für berechtigte Administratoren die Navigationseinträge **Berechtigungen** und **Bereiche verwalten**. Für diese Phase sind keine neue Datenbankmigration und keine zusätzliche Laufzeitkomponente nötig. Normale Bedienung benötigt keinen Wartungsmodus. Die vorhandenen CLI-Werkzeuge bleiben für kontrollierte Betreiberarbeiten verfügbar.

## Bedienung

Unter `/administration/spaces` einen vorhandenen Bereich nach seinem Namen im Dropdown auswählen oder einen neuen Bereich mit Name und Quote anlegen. Die Quote wird ausdrücklich in Bytes angegeben; aktuelle Inhalte, frühere Versionen und Papierkorb zählen zum Verbrauch. Name und Quote können anschließend bearbeitet werden. Eine neue Quote unterhalb des aktuellen Verbrauchs wird zurückgewiesen.

Die Bereichsanlage vergibt keine automatischen Dateirechte, auch nicht an den ausführenden Administrator. Über **Gruppenrechte für diesen Bereich** zu `/permissions` wechseln. Dort Gruppe und Geltungsbereich per Dropdown auswählen und **Zuweisungen anzeigen** wählen. Anschließend Berechtigungsprofil und Änderung (zuweisen oder entziehen) auswählen und die Änderung bestätigen. Das UI zeigt die aktuell zugewiesenen Profile und ihre Bedeutung.

| Geltungsbereich | Profil | Rechteumfang |
| --- | --- | --- |
| Speicherbereich | Lesen | Auflisten und Originale lesen |
| Speicherbereich | Hochladen | Lesen und neue Dateien/Ordner anlegen |
| Speicherbereich | Dateien verwalten | Lesen, anlegen, ändern, Papierkorb und Wiederherstellung |
| Speicherbereich | Versionen lesen | Zusätzliches Historienleserecht; sonstige Dateirechte bleiben erforderlich |
| Speicherbereich | Endgültig löschen | Separates Purge-Recht; konkrete Löschung bleibt bestätigungspflichtig |
| Speicherbereich | Medien verwenden | Vorschauen, allgemeine Metadaten und Analyseaufträge; Bereichsliste zusätzlich nötig |
| Speicherbereich | Sensible Metadaten | Allgemeine und zusätzlich geschützte Metadaten einschließlich vorhandener Prompts |
| Global | Hintergrundaufgaben ansehen | Zustellstatus lesen |
| Global | Hintergrundaufgaben verwalten | Zustellstatus lesen und fehlgeschlagene Zustellungen erneut vormerken |

Die Profile entsprechen den bisherigen festen CLI-Profilen. Alte, von `storage-space-create.php` erzeugte `files.<Zufallskennung>`-Zuweisungen mit exakt den ursprünglichen Lese-/Uploadrechten werden zusätzlich mit der Bezeichnung **Hochladen (bisherige Zuweisung)** angezeigt und können entzogen werden. Neue Zuweisungen verwenden die festen Standardprofile. Unbekannte oder abweichende Rollen werden nicht still umgeschrieben.

Mehrere Profile und Gruppen wirken ergänzend. Das Entfernen eines Profils garantiert daher keinen vollständigen Rechteentzug: andere Rollen, manuelle oder gemappte Gruppenzugehörigkeiten können weiter Rechte verleihen. Die Übersicht ist eine Zuweisungsansicht für diese Profile, keine vollständige Erklärung aller effektiven Rechte.

Unter **Gruppen** wird bei manuellen Mitgliedschaften ein Benutzer anhand seines Anzeigenamens ausgewählt. Gruppen-, Benutzer-, Bereichs- und Profilkennungen bleiben technische Formularwerte; die Auswahl zeigt Namen. Namensgleiche Einträge sind möglich und werden nicht zusammengeführt. Bei mehr als 1000 Gruppen, Bereichen oder Benutzern wird die jeweilige Auswahl ausdrücklich abgelehnt; sie wird nicht still abgeschnitten. Pro ausgewählter Gruppe/Bereich werden höchstens 100 vorhandene Zuweisungen untersucht. Suchbare Auswahllisten für größere Verzeichnisse bleiben ein späterer Ausbau.

## Löschen eines Bereichs

Nur ein vollständig leerer Bereich kann nach ausdrücklicher Bestätigung gelöscht werden. Dateien und Ordner im Papierkorb zählen dabei als Inhalt. Die bisherigen berechtigten Purge-Abläufe müssen diese Inhalte vorher separat bereinigen. Aktive Bereichs-/Dateisperren verhindern die Löschung. Abgelaufene Sperren verleihen keinen Schutz mehr.

Die Löschung entfernt im selben auditierten Vorgang auch die bereichsbezogenen RBAC-Zuweisungen und Delegationen. Globale Zuweisungen, Rollen und andere Bereiche bleiben erhalten. Es gibt keinen rekursiven Sofortlöschknopf für einen gefüllten Bereich. Die Löschung des leeren Bereichs selbst ist endgültig.

## Schutz und Erweiterbarkeit

Für diese Onlineverwaltung werden die aktuellen globalen Rechte `identity.users.read`, `identity.groups.read` und `identity.groups.memberships.manage` gemeinsam verlangt. Der bereits eingerichtete Standardadministrator besitzt diese Rechte; ein erneuter Bootstrap oder eine zusätzliche Konsolenzuweisung ist nicht nötig. Dies macht administrative Namen und Bereichsquoten sichtbar und erlaubt die beschriebenen festen Profile sowie die Bereichskatalogverwaltung. Es gewährt keine impliziten Inhaltsrechte.

Die festen Profile enthalten keine Rechte zur Bearbeitung administrativer Rollen oder Delegationsobergrenzen. Gruppen mit ausschließlich den beschriebenen Standardprofilen beziehungsweise exakt bekannten alten Bereichsanlageprofilen können durch diese globalen Administratoren auch hinsichtlich manueller Mitgliedschaften verwaltet werden. Für unbekannte, eigene oder administrative Rollen bleibt die bestehende Delegations-/Schutzpolitik maßgeblich. Administrative Gruppen werden hierdurch nicht frei bearbeitbar.

Jeder Schreibvorgang verwendet POST, CSRF-Prüfung, aktuelle Autorisierung, den gemeinsamen Administrationslock, Prüfung vor und nach Pluginvalidierung sowie transaktionales Audit/Outbox. Berechtigungsformulare binden den beobachteten Rollen-/Zuweisungszustand; Änderungen durch eine andere Sitzung ergeben 409. Bereichsformulare binden Name und Quote; vor Löschen/Quotenänderung werden aktueller Inhalt, Verbrauch und aktive Sperren erneut geprüft. Rollen mit abweichender Definition oder fehlenden registrierten Pluginrechten werden gesperrt.

Der Kern verwendet `AccessProfileAdministration` und `SpaceAccessCleanup`, ohne RBAC-Tabellen zu lesen. `AccessProfileChange` und `StorageSpaceChange` binden die tatsächlichen Vorschläge an `MutationContext`; Plugins können sie vor dem Schreiben prüfen oder ablehnen. Der Cleanup-Vertrag ist ein vertrauenswürdiger transaktionaler Lifecycle-Hook, kein eigenständiger HTTP-Schreibendpunkt. Bei Fehlern werden Bereichsänderung, Rechtebereinigung, Audit und Outbox gemeinsam zurückgerollt.

## Noch getrennte Aufgaben

Freies Erstellen/Bearbeiten beliebiger Rollen, administrative Rollenzuweisungen, allgemeine Delegations- und IdP-Mappingverwaltung sind nicht Teil dieser Oberfläche. Ebenso erzeugt die Bereichsverwaltung noch keine automatischen persönlichen Bereiche und keine Aufbewahrungsrichtlinie. Diese Grenzen ersetzen nicht die übrigen offenen Konzeptpunkte.

Prüfungen und verbleibende visuelle-/Gerätegrenzen: [verification.md](verification.md). Gestaltung: [design.md](design.md).
