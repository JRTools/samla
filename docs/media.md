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

Upload, Inhaltsersetzung, Versionswiederherstellung, Einzeldateikopie, DAV-Anlage mit Sperre und Wiederherstellung einer Datei liefern Abschlussereignisse. Der neue Empfänger verarbeitet auch noch vorhandene ältere passende Outbox-Ereignisse. Er untersucht dabei den aktuell sichtbaren Inhalt, nicht nachträglich jeden historischen Stand. Ein vorher nicht analysiertes Kind unter einem wiederhergestellten Ordner kann über seine Detailseite ausdrücklich neu vorgemerkt werden. Es gibt noch keinen vollständigen Rebuild- oder Importlauf.

## Formate und Informationen

| Inhalt | Vorschau | Ausgelesene Informationen |
| --- | --- | --- |
| JPEG | PNG, EXIF-orientiert | MIME, Originalbreite/-höhe, Orientierung, Kamerahersteller/-modell, DateTimeOriginal als Text ohne zugesicherte Zeitzone |
| PNG, GIF, WebP | PNG, bei GIF erstes Bild | MIME, Originalbreite/-höhe |
| TIFF | Keine GD-Vorschau | Begrenzte technische/ausgewählte EXIF-Angaben, Status teilweise verarbeitet |
| Andere Inhalte, SVG, Videos | Keine | Ausdrücklich nicht unterstützt |

Fehlende Angaben, Parserfehler, nicht unterstützte Formate, fehlende Module und Größenlimits werden unterschieden. Ungültige Orientierung erzeugt keine vermeintlich korrekt orientierte Vorschau. Originalwerte, normalisierte Werte, Provider und Quellpfad bleiben getrennt. GPS, Seriennummern und eingebettete EXIF-Thumbnails werden nicht als Felder übernommen. IPTC, XMP, KI-Kennzeichnungen und C2PA werden noch nicht untersucht. Der Status ist keine Aussage über eine Entstehung ohne KI.

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
- Technische Gesamtkapazität 20.000 Ableitungssätze und 256 MiB Vorschauinhalt; zusätzlich maximal 32 KiB Metadaten pro Satz. Ableitungen zählen nicht zur Originaldateiquote, aber zur Datenbankgröße und zum Backup. Ohne Vorschaukapazität wird limit gespeichert. Keine automatische Löschung, Verdrängung oder Retention; eine gesonderte Cachepflege folgt.
- Fehlende Module oder geworfene Providerfehler nutzen die begrenzten Wiederholungen des Workers; beschädigte/ungeeignete Bilder bleiben als Fehler beziehungsweise nicht unterstützt sichtbar. Nach Installation fehlender Module kann eine fehlgeschlagene Zustellung in der Worker-Verwaltung erneut vorgemerkt werden.
- Manuelles Vormerken höchstens einmal pro Datei und Minute; ein doppeltes Formular erzeugt keine zusätzliche Zustellung. Die Verarbeitung erfolgt beim nächsten Worker-Aufruf.

## Erweiterungsverträge

PluginRegistrar.mediaField/mediaProvider registrieren namensraumgebundene, nach Boot abgeschlossene Definitionen. MediaSource liefert begrenzte unveränderliche Bytes mit geprüfter SHA-256 und frischen privaten Streams; keine Requestpfade oder URLs. MediaValue trennt Quelle, Rohwert, normalisierten Wert und Status. MediaResult enthält begrenzte Werte und optional MediaPreview. Sensitive Felder verlangen ein zusätzliches eigenes Bereichsrecht. MediaRefreshChange ergänzt den öffentlichen MutationContext und wird vor und nach ChangeValidatoren geprüft. Die erste API unterstützt Text und Ganzzahlen; weitere Feldtypen, manuelle Katalogpflege, Tags, Sammlungen, Mediensuche und Export folgen.
