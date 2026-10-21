# Dateiablage

## Umfang ab Iteration 13

Die Oberfläche unter `/files` zeigt ausschließlich Speicherbereiche mit explizitem `files.list`. Innerhalb eines Bereichs sind Ordneranlage und neue Datei-Uploads mit `files.create` sowie Downloads mit `files.read` implementiert. Keine dieser Fähigkeiten entsteht aus einer globalen Administratorrolle. Namen sind logische UTF-8-Bezeichnungen, niemals Speicherpfade. Verzeichnisse sind auf 100 Einträge pro Seite begrenzt; die Bereichsliste scannt ebenfalls höchstens 100 Katalogeinträge pro Seite.

Dateien werden im privaten lokalen Dateisystem unter zufälligen Kennungen abgelegt. Downloads erfolgen als gestreamtes `application/octet-stream` mit `Content-Disposition: attachment`, `nosniff` und ohne Browsercache. Auch hochgeladener PHP-/HTML-Code wird dadurch nicht als Anwendungscode ausgeführt. Ein Malware-Scan ist noch nicht eingebaut; das Speichern bedeutet keine inhaltliche Freigabe einer Datei.

## Einrichtung unter Plesk

Zuerst Code und Composer-Abhängigkeiten aktualisieren. Das Schema während eines Wartungsfensters migrieren:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/migrate.php
```

Standardmäßig verwendet Samla `var/storage` im Projektverzeichnis. Lege das Verzeichnis mit Modus 0700 unter dem tatsächlichen PHP-FPM-Benutzer an. Der Betreiber bestätigt ein fehlendes Verzeichnis als Ursache des Uploadfehlers; die Anwendung meldet diesen Fall jetzt verständlich. Alternativ in `.env` einen absoluten privaten Pfad setzen:

```dotenv
APP_STORAGE_ROOT=/var/lib/samla/storage
```

Der Pfad darf nicht in `public` liegen; symbolische Links und nicht kanonische Pfade werden abgelehnt. Alle Elternverzeichnisse müssen für PHP durchquerbar sein. Das Verzeichnis und seine Eltern dürfen nicht von unbefugten lokalen Benutzern veränderbar sein. Der Dateispeicher bleibt beim Deployment erhalten und muss gemeinsam mit der Datenbank gesichert werden.

PHP `file_uploads=On`, `upload_max_filesize=16M` und `post_max_size=17M` einstellen. Auch nginx/Plesk muss Request-Bodys dieser Größe erlauben. Samla akzeptiert derzeit maximal 16 MiB Inhalt pro Datei. Niedrigere Serverlimits bleiben wirksam. Eine Datei wird serverseitig als echter HTTP-Upload geprüft. Leere Dateien sind erlaubt.

Einen ersten Bereich ausdrücklich einer vorhandenen Gruppe zuweisen, beispielsweise der initialen Administratorgruppe. `ACTOR_ID` ist die interne Kennung eines frischen, aktiven Administrators aus „Mein Zugang“. Die Quote wird in Bytes angegeben, hier 1 GiB:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-space-create.php ACTOR_ID samla.administrators Dokumente 1073741824
```

Dieser Offline-Installationsbefehl benötigt globale Benutzerlese- und Mitgliedschaftsverwaltungsrechte. Er legt bei jedem erfolgreichen Aufruf einen neuen Bereich und eine neue explizite Bereichsrolle mit list/read/create an. Er verändert keine vorhandene Rolle und ist kein allgemeiner Delegations-Webendpunkt. Eine reine CLI-Umgebungsvariable schaltet das Web nicht in Wartung. Für konsistente Installations-/Prüffenster auch Webzugriffe über die Betriebswartung sperren und danach wieder freigeben.

Nach dem Anlegen erscheint der Bereich unter „Dateien“. Andere Gruppen erhalten weiterhin keinen Zugriff. Rollenpflege über Webformulare und persönliche Bereiche folgen separat.

## Konsistenz, Quoten und Wiederherstellung

Ein Upload wird in 64-KiB-Stücken auf demselben Dateisystem zwischengespeichert, auf 16 MiB begrenzt, gehasht, mit 0600 geschützt, synchronisiert und durch Umbenennen veröffentlicht. Anschließend werden Metadaten unter dem gemeinsamen Administrationslock eingetragen. Autorisierung und Zielordner werden vor und nach Plugin-Validatoren geprüft. Quotenprüfung, eindeutiger Geschwistername, Metadaten, Audit und Outbox sind eine Datenbanktransaktion. Gleichnamige Einträge ergeben 409; kein vorhandener Inhalt wird überschrieben.

Dateisystem und SQL haben keine gemeinsame Transaktion. Ein Absturz, Namenskonflikt, Quotenfehler oder Plugin-Veto nach Objektveröffentlichung kann ein verwaistes Objekt hinterlassen. Die Anwendung löscht veröffentlichte Objekte bei unklarem Commit-Ergebnis ausdrücklich nicht, da die Metadaten bereits gespeichert sein könnten. Verwaiste Objekte zählen nicht zur logischen Quote, belegen aber physischen Speicher. Daher Dateisystemkapazität überwachen. Automatische Bereinigung und mengenbezogene Schutzmaßnahmen gegen wiederholt abgelehnte Uploads sind vor einer breiten Betriebsfreigabe noch erforderlich.

Bei gestoppten Webschreibzugriffen prüfen:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-check.php
```

Der rein lesende Prüfer vergleicht Referenzen, Größe und SHA-256, meldet fehlende/beschädigte, verwaiste und liegengebliebene Stagingobjekte sowie unbekannte Verzeichniseinträge. Exitcode 0 bedeutet keine Befunde, 2 bedeutet Befunde und 1 einen fehlgeschlagenen Check. Es wird nichts automatisch gelöscht. Verwaiste Kennungen erst nach Abgleich mit konsistentem Backup und gespeicherten Referenzen manuell entfernen. Beschädigte referenzierte Objekte aus passendem Backup wiederherstellen. Direkte Änderungen an Originalobjekten sind nicht unterstützt.

Backup: Webschreibzugriffe stoppen, Datenbank und privaten Speicher im selben Wartungsfenster sichern. Restore: zusammengehöriges Datenbank-/Dateisystembackup einspielen, Besitzrechte herstellen, Migrationstand prüfen, `storage-check.php` ausführen und erst danach Webzugriff öffnen. Diese Anleitung ersetzt noch keinen getesteten vollständigen Disaster-Recovery-Nachweis.

## Weitere Dateioperationen ab Iteration 14

Über „Eintrag verwalten“ sind Umbenennen, Verschieben innerhalb desselben Bereichs, Einzeldateikopien und Papierkorb verfügbar. Zielordner werden aus einer begrenzten Liste mit Pfadbezeichnungen gewählt. Verschieben benötigt update/create/delete, Kopieren read/create, Papierkorb delete und Wiederherstellen restore/create. Die zentrale Autorisierung gilt bei jedem Zugriff erneut. Ein Beitragender erhält diese Verwaltungsrechte nicht automatisch.

Explizite Offline-Freigabe mit frischem Administrator und Wartung:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-space-access.php ACTOR_ID SPACE_ID GROUP_ID manage
```

Alternativ read oder contribute verwenden. Der Befehl ergänzt eine passende Bereichszuweisung und verändert keine vorhandene Rolle. Existierende reservierte Profilrollen müssen exakt passen. Wiederholung derselben Zuweisung ist idempotent; Profile addieren sich und sind keine Herabstufung bereits vorhandener Rechte. Allgemeine Zuweisungsentzüge bleiben Aufgabe der Rollenverwaltung. Der initiale Administratorbootstrap ist weiterhin ausschließlich für die Ersteinrichtung bestimmt.

Gleichnamige Einträge werden nicht überschrieben. Name und bisheriger Elternordner sind optimistische Erwartungen jeder Änderung; ein abweichender Stand erzeugt 409. Ordnerzyklen werden abgewiesen. Bäume haben höchstens 128 Ordnerebenen; Verschiebungen von Ordnerbäumen werden auf höchstens 1000 Ordner begrenzt. Rekursive Ordnerkopien und bereichsübergreifende Operationen sind noch nicht enthalten.

Der Papierkorb markiert den ausgewählten Eintrag. Nachfahren eines gelöschten Ordners bleiben logisch erhalten, sind aber auch über direkte Datei-/Ordnerlinks gesperrt. Wiederherstellen verlangt einen noch erreichbaren ursprünglichen Elternordner; gegebenenfalls zuerst den übergeordneten Ordner wiederherstellen. Inhalte im Papierkorb zählen weiterhin zur Quote. Namen bleiben bis zur späteren endgültigen Löschung reserviert. Eine Dateikopie teilt sich unveränderlichen physischen Inhalt, zählt logisch jedoch erneut zur Quote.

### Kontrollierte Bereinigung

Alle Web- und CLI-Schreiber stoppen und Wartung aktivieren. Zuerst nur Kandidaten anzeigen:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-gc.php
```

Nach Prüfung anwenden:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-gc.php --apply
```

Pro Lauf werden höchstens 1000 nicht referenzierte reguläre Objekte beziehungsweise Stagingdateien mit internen Kennungen und mindestens 24 Stunden Alter berücksichtigt. Referenzprüfung erfolgt vor und nach Validatoren unter dem gemeinsamen Administrationslock. Referenzierte Kopien und Papierkorbinhalte werden nicht entfernt. Symlinks, Hardlinks, fremde Namen und junge Objekte bleiben unangetastet. Jeder angewandte Löschvorgang verwendet einen MutationContext und erzeugt Audit/Outbox. Die physische Entfernung selbst kann bei nachfolgendem SQL-Fehler nicht zurückgerollt werden; nur nicht referenzierte Objekte werden angefasst. Die Betriebsvoraussetzung gestoppter Schreiber ist insbesondere für lange laufende, noch nicht publizierte Uploads zwingend.

## Noch offene Produktfunktionen

Endgültige Löschung, rekursive Ordnerkopien, bereichsübergreifende und Massenoperationen, persönliche Speicherbereiche, Medienmetadaten und WebDAV werden schrittweise ergänzt. Die Dateiansicht ist keine Freigabe des gesamten Konzeptumfangs.


## Einrichtungswerkzeug ab Iteration 15

Der Elternordner muss vorhanden und für PHP durchquerbar sein. Unter dem PHP-FPM-Benutzer ausführen:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-init.php
```

Als root stattdessen den tatsächlichen Plesk-Systembenutzer ausdrücklich angeben (Platzhalter ersetzen):

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-init.php PLESK_SYSTEMBENUTZER
```

Der Befehl legt ausschließlich das konfigurierte Speicherverzeichnis an, setzt 0700, bei ausdrücklicher Angabe dessen Eigentümer und führt einen Schreibprobe durch. Vorhandene Originaldateien und deren Eigentümer werden nicht rekursiv verändert. Eine Eigentümeränderung ist daher kein Migrationswerkzeug für bestehende Bestände. Übergeordnete Verzeichnisse werden nicht automatisch mit fremdem Eigentümer angelegt. PHP-FPM muss das Verzeichnis zusätzlich innerhalb seines open_basedir erreichen können.


## Dateiversionen ab Iteration 16

„Eintrag verwalten“ bietet „Dateiinhalt ersetzen“. Die Datei behält ihre Kennung und ihren Namen; ihr vorheriger unveränderlicher Inhalt wird als Historie gesichert. Ein Formular enthält die erwartete Revision. Bei inzwischen geänderten Inhalten oder Metadaten wird mit 409 abgebrochen, ohne den aktuellen Stand zu überschreiben. Umbenennen, Verschieben und Papierkorbänderungen erhöhen ebenfalls die Revision. Der alte Inhalt wird bei einer Ersetzung nicht automatisch gelöscht.

Historische Inhalte verlangen zusätzlich zu files.read/list das eigene Bereichsrecht files.versions.read. Es wird bestehenden Gruppen nicht automatisch hinzugefügt. Ausdrücklich ergänzen:

```bash
APP_MAINTENANCE=1 /opt/plesk/php/8.4/bin/php bin/storage-space-access.php ACTOR_ID SPACE_ID GROUP_ID history
```

Das Profil history ergänzt ausschließlich dieses zusätzliche Recht. Die Gruppe braucht weiterhin ihre passenden list/read- und bei Änderungen update-Rechte. Vorhandene reservierte manage/read/contribute-Rollen werden nicht umgedeutet. Über „Dateiversionen ansehen“ sind frühere Inhalte als geschützte Attachments downloadbar. „Als neuen Stand wiederherstellen“ benötigt zusätzlich files.update, sichert den bisherigen Stand und legt den ausgewählten alten Inhalt als neue aktuelle Revision fest. Die Historie wird neueste Revision zuerst auf höchstens 100 Zeilen pro Seite angezeigt. Kopieren einer Datei übernimmt ihren aktuellen Inhalt, nicht ihre gesamte Historie.

Alle aktuellen, historischen und Papierkorbinhalte zählen zur logischen Bereichsquote. Wiederherstellen zählt den neuen aktuellen Inhalt erneut, auch wenn er sich unveränderlichen physischen Speicher mit einer alten Version teilt. Die Quotenprüfung erfolgt unter dem gemeinsamen Administrationslock. Eine Schutzgrenze von 1000 historischen Zuständen pro Datei stoppt weitere Ersetzungen, ohne alte Zustände automatisch zu entfernen. Das ist keine vereinbarte Aufbewahrungs-/Löschrichtlinie. Ein Mechanismus zur gezielten endgültigen Löschung folgt separat.

Konsistenzprüfung und Garbage-Collection berücksichtigen auch historische Referenzen. Bei unklarem Commit werden neue veröffentlichte Objekte weiter erhalten; verwaiste Objekte bleiben über den Wartungsablauf prüfbar. Für Backup/Restore müssen SQL und alle physischen Objekte gemeinsam gesichert werden.

### Uploadgrenze

APP_MAX_UPLOAD_BYTES setzt die maximale Inhaltsgröße als ganze Bytezahl, Standard 16777216. Zulässig sind technische Werte von 1 Byte bis 1 TiB; dadurch wird keine entsprechende Kapazität des Hosts zugesichert. PHP upload_max_filesize, post_max_size und das Webserver-Requestlimit müssen dazu passen; für Multipart einen kleinen zusätzlichen Overhead erlauben. Samlas Browser-Requestgrenze folgt dem konfigurierten Maximum plus 1 MiB. Die eigentliche Inhaltsgrenze wird während des Streams unabhängig vom deklarierten Content-Length geprüft. Die Bereichsquote ist davon getrennt.

### Vereinbarter Prüfbereich

Geplanter Bestand: ungefähr 1000 Dateien, fünf aktive Benutzer; Zielplattformen iOS/Android/Windows/macOS. Die Integrationstests lesen einen 1000-Dateien-Katalog über zehn Seiten ohne Verluste/Dubletten und starten fünf PHP-Prozesse über eine gemeinsame Startbarriere. Bei Quote für drei der fünf Uploads werden genau drei gespeichert und zwei abgewiesen. Das belegt dieses begrenzte Parallelitätsverhalten; weitere Crash-/Leistungs-/native Clientprüfungen bleiben erforderlich.
