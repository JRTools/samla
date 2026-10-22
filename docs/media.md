# Medienvorschauen und EXIF

Samla erzeugt private Ableitungen über denselben Worker wie die übrigen Hintergrundaufgaben. Originaldateien werden nicht verändert. Navigation: **Medien**, anschließend Bereich und Ordner. Dateinamen bestimmen das erkannte Bildformat nicht.

## Einrichtung

1. Code und Composer-Abhängigkeiten aktualisieren, Datenbank sichern und die neue Migration `platform.media:0001_assets` ausführen: `APP_MAINTENANCE=1 php bin/migrate.php`.
2. GD und die am 22. Oktober 2026 ausdrücklich freigegebene PHP-Erweiterung EXIF in der Worker-Laufzeit aktivieren. Bei CLI-Cron betrifft dies PHP-CLI, bei HTTP-Trigger PHP-FPM. `php bin/diagnose.php` prüft die lokale Laufzeit. Ein fehlendes Modul sperrt die bisherigen Datei-/Loginfunktionen nicht; der Medienprovider meldet unavailable und der Worker versucht die Zustellung begrenzt erneut.
3. Einer vorhandenen Gruppe ausdrücklich die Medienrechte im gewünschten Bereich geben. Bestehende Rollen werden nicht erweitert. Das vorbereitete Profil vergibt previews.read, metadata.read und process; files.list muss bereits separat vorliegen:

```sh
APP_MAINTENANCE=1 php bin/storage-space-access.php ACTOR_ID SPACE_ID GROUP_ID media
```

4. Wartungsmodus beenden und den bereits eingerichteten CLI-Cron oder authentifizierten HTTPS-POST-Trigger weiter verwenden. Siehe [Hintergrundaufgaben](background-worker.md). Ein normaler Seitenaufruf führt keinen Parser aus.

Upload, Inhaltsersetzung, Versionswiederherstellung, Einzeldateikopie, DAV-Anlage mit Sperre und Wiederherstellung einer Datei liefern Abschlussereignisse. Der neue Empfänger verarbeitet auch noch vorhandene ältere passende Outbox-Ereignisse. Er untersucht dabei den aktuell sichtbaren Inhalt, nicht nachträglich jeden historischen Stand. Ein vorher nicht analysiertes Kind unter einem wiederhergestellten Ordner kann über seine Detailseite ausdrücklich neu vorgemerkt werden. Ein manueller bereichsweiter Neuaufbau ist jetzt seitenweise möglich; ein unbeaufsichtigter Komplettimport ist noch nicht vorhanden.

## Formate und Informationen

| Inhalt | Vorschau | Ausgelesene Informationen |
| --- | --- | --- |
| JPEG | PNG, EXIF-orientiert | MIME, Originalbreite/-höhe, Orientierung, Kamerahersteller/-modell, DateTimeOriginal als Text ohne zugesicherte Zeitzone |
| PNG, GIF, WebP | PNG, bei GIF erstes Bild | MIME, Originalbreite/-höhe |
| TIFF | Keine GD-Vorschau | Begrenzte technische/ausgewählte EXIF-Angaben, Status teilweise verarbeitet |
| Andere Inhalte, SVG, Videos | Keine | Ausdrücklich nicht unterstützt |

Fehlende Angaben, Parserfehler, nicht unterstützte Formate, fehlende Module und Größenlimits werden unterschieden. Ungültige Orientierung erzeugt keine vermeintlich korrekt orientierte Vorschau. Originalwerte, normalisierte Werte, Provider und Quellpfad bleiben getrennt. GPS, Seriennummern und eingebettete EXIF-Thumbnails werden nicht als Felder übernommen. Ab Iteration 30 ergänzt ein zweiter Provider ausgewählte IPTC-/XMP-/PNG-Textfelder und strukturierte KI-Deklarationen, siehe [embedded-metadata.md](embedded-metadata.md). C2PA wird weiterhin nicht untersucht oder validiert. Der Status ist keine Aussage über eine Entstehung ohne KI.

## Zugriffsrechte

Alle Rechte sind bereichsbezogen und werden für jeden Abruf erneut geprüft, einschließlich aktueller Identität, Gruppenfrische, Erweiterungseinschränkungen und sichtbarer Elternordner.

| Recht | Wirkung |
| --- | --- |
| files.list | Namen, Bereich und sichtbarer Ordnerpfad; zusätzlich Voraussetzung der Medienansichten |
| media.previews.read | Geschützte PNG-Vorschau; kein automatisches Recht am Original |
| media.metadata.read | Registrierte allgemeine Metadatenfelder; sensible Erweiterungsfelder brauchen zusätzlich ihr eigenes registriertes Leserecht |
| media.process | Analyse des aktuellen Inhaltsstands vormerken; CSRF, exakte Revision, Audit und Pluginvalidierung |
| files.read | Originaldownload mit dessen vollständigen eingebetteten Informationen |

Vorschauen werden ausschließlich unter `/media/preview` nach Rechteprüfung ausgegeben, ohne öffentlichen Dateipfad, ohne Cachefreigabe und mit aktueller Revision. GD schreibt ein neues begrenztes PNG; der öffentliche Vorschauvertrag lehnt EXIF-, Text- und unbekannte PNG-Chunks ab. Metadatenrechte ersetzen keine Bereinigung eines ausdrücklich erlaubten Originaldownloads.

## Grenzen und Cache

- Maximal 16 MiB Quellinhalt für die Medienanalyse, 16.777.216 Pixel, 16.384 Pixel pro Achse. Größere Originale bleiben nach den gewöhnlichen Dateirechten nutzbar.
- Zusätzliche Speicherabschätzung vor GD/EXIF; kein harter Zeitabbruch eines laufenden nativen Parsers. PHP-/Hostlimits bleiben erforderlich. Der gemeinsame Worker prüft seine kooperative Laufzeit vor der nächsten Aufgabe.
- Vorschau maximal 480 × 480 Pixel unter Erhalt des Seitenverhältnisses, ohne Vergrößerung, höchstens 1 MiB PNG. Alpha bleibt für PNG erhalten; Animation wird nicht übernommen.
- Höchstens acht registrierte Provider, 64 Felder, 32 Werte pro Provider; Zeichenfolgen und gespeicherte Metadaten sind begrenzt. Kein vollständiger unbeschränkter EXIF-Dump.
- Private SQL-Tabelle media_assets, gebunden an unveränderliche Blobkennung, Quellprüfsumme/-größe und Signatur der Provider-/Feldkonfiguration. CLI und FPM dürfen unterschiedliche unterstützte PHP-Versionen verwenden. Provider müssen ihre Verarbeitungsversionskennung bei relevanten Änderungen erhöhen.
- Technische Gesamtkapazität 20.000 Ableitungssätze und 256 MiB Vorschauinhalt; zusätzlich maximal 32 KiB Metadaten pro Satz. Ableitungen zählen nicht zur Originaldateiquote, aber zur Datenbankgröße und zum Backup. Ohne Vorschaukapazität wird limit gespeichert. Nach ausdrücklich berechtigter endgültiger Dateilöschung entfernt der Worker ausschließlich Ableitungen ohne verbleibende Referenz in aktiven Dateien, Papierkorb oder Historien. Keine automatische Verdrängung noch verwendeter Ableitungen oder zeitbasierte Retention. Allgemeine Cacheverwaltung und Bereinigung alter Providersignaturen bleiben offen.
- Fehlende Module oder geworfene Providerfehler nutzen die begrenzten Wiederholungen des Workers; beschädigte/ungeeignete Bilder bleiben als Fehler beziehungsweise nicht unterstützt sichtbar. Nach Installation fehlender Module kann eine fehlgeschlagene Zustellung in der Worker-Verwaltung erneut vorgemerkt werden.
- Manuelles Vormerken höchstens einmal pro Datei und Minute; ein doppeltes Formular erzeugt keine zusätzliche Zustellung. Die Verarbeitung erfolgt beim nächsten Worker-Aufruf.

## Erweiterungsverträge

PluginRegistrar.mediaField/mediaProvider registrieren namensraumgebundene, nach Boot abgeschlossene Definitionen. MediaSource liefert begrenzte unveränderliche Bytes mit geprüfter SHA-256 und frischen privaten Streams; keine Requestpfade oder URLs. MediaValue trennt Quelle, Rohwert, normalisierten Wert und Status. MediaResult enthält begrenzte Werte und optional MediaPreview. Sensitive Felder verlangen ein zusätzliches eigenes Bereichsrecht. MediaRefreshChange ergänzt den öffentlichen MutationContext und wird vor und nach ChangeValidatoren geprüft. Die erste API unterstützt Text und Ganzzahlen; weitere Feldtypen, manuelle Katalogpflege, Tags, Sammlungen, Mediensuche und Export folgen.


## Bereichsweiter Neuaufbau ab Iteration 29

Die Medienansicht bietet mit media.process den Link **Medienanalyse dieses Bereichs neu aufbauen**. Die Vorschau zeigt bis zu 100 sichtbare Dateien über alle Unterordner des Bereichs. Bestätigen merkt die gesamte angezeigte zulässige Seite atomar vor und führt zur nächsten Dateiseite. Ein Seitenwechsel ohne Vormerken ist möglich. Für etwa 1000 Dateien sind ungefähr zehn Bestätigungen erforderlich. Nach dem letzten Cursor am Anfang erneut beginnen, um während des Laufs neu hinzugekommene Dateien zu erfassen. Es gibt keinen unbeaufsichtigten Endloslauf im Webrequest.

Der Server überprüft Bereich, aktuelle Rechte, Sichtbarkeit, Dateityp und jede angegebene Revision vor und nach Pluginvalidatoren. Eine geänderte Datei oder ein konkurrierender frischer Auftrag führt zu 409 und rollt die gesamte Seite zurück. Kürzlich vorgemerkte Dateien werden auf der Planungsseite angezeigt, aber für eine Minute ausgenommen. Fehlerhafte Formulare werden vollständig vor der Änderung verworfen.

MediaBatchRefreshChange ist ein unveränderlicher öffentlicher Vorschlag mit höchstens 100 Kennungen und erwarteten Revisionen. Der MutationContext bindet den Vorschlag an seinen kanonischen Hash und Bereich. Ein übergeordnetes Audit und Abschlussereignis kennzeichnen den Batch. Jede Datei erhält zusätzlich ihr eigenes Audit und media.asset.refresh.completed mit batch_id, batch_index, batch_count und requested_revision. Ein Fehler auch beim zweiten Kind rollt Vormerkungen, alle Audits und sämtliche Ereignisse zurück. Keine Dateinamen oder Inhalte in den Ereignissen.

Die gespeicherte Revision beschreibt den bestätigten Auftrag. Der technische Worker untersucht zum Ausführungszeitpunkt den dann aktuell sichtbaren Inhaltsstand und bindet jede Ableitung an dessen Blob, Hash, Größe und Providersignatur; keine nachträgliche Freigabe historischer Inhalte. Parser laufen pro Datei im vorhandenen Worker mit dessen Grenzen. Eine wiederholte Zustellung bleibt bezüglich des Cachebestands idempotent; ein bewusst erzwungener Neuaufbau kann dieselben Bytes erneut auslesen.

## Ableitungen nach endgültiger Löschung

Die neue Migration platform.media:0002_reference_indexes ergänzt Blobindizes an aktuellen Dateien und Historien. Der zusätzliche Empfänger platform.media.purged_v1 verarbeitet storage.entry.purge.completed, einschließlich noch vorhandener älterer Ereignisse. Unter dem gemeinsamen Mutationslock entfernt er Cachezeilen nur, wenn weder ein Dateieintrag einschließlich Papierkorb noch ein historischer Stand die Blobkennung referenziert. Gemeinsam genutzte Kopien und erhaltene Historien schützen die Ableitung weiterhin. Ein Absturz kann idempotent wiederholt werden. Es gibt keinen zusätzlichen Dienst oder PHP-Modulbedarf.

Diese technische Bereinigung verändert weder Dateien noch Versionen und erzeugt keine neue automatische Papierkorb-/Historienfrist. Physische Originalobjekte bleiben der bestehenden ausdrücklich angewendeten Objekt-GC vorbehalten. Noch verwendete alte Providersignaturen und eine allgemeine Cacheverwaltungsoberfläche werden dadurch nicht bereinigt.
