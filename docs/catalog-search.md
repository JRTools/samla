# Katalogsuche mit Beschreibungen und Tags

Seit Iteration 40 öffnet die Dateiliste **Beschreibungen und Tags durchsuchen**. Der manuelle Katalog enthält zusätzlich einen Link zur Suche dieses Bereichs. Es gelten files.list, files.read und catalog.read im exakten Bereich; die bestehenden Katalogprofile enthalten diese Rechte. Die bloße Namenssuche mit files.list bleibt separat unter /files/search verfügbar. Keine neue Migration, Laufzeitkomponente oder externer Suchindex.

Unter /catalog/search?space=... den Suchtext, optional einen vorhandenen Tag nach Namen und eine Dateiendung eingeben. Die Filter werden kombiniert: Text muss in mindestens einem Dateinamen, einer manuellen Beschreibung oder einem zugeordneten Tag vorkommen; gewählter Tag und Endung gelten zusätzlich. Leerer Text durchsucht alle sichtbaren Dateien. Beispiel: Text Sommer, Tag Veranstaltung und Endung png. Ein führender Punkt und Großschreibung der eingegebenen Endung werden normalisiert. Der Endungsfilter behauptet keine technische Format-/MIME-Prüfung des Originalinhalts.

Der Text ist wörtlich und Unicode-groß-/kleinschreibungsunabhängig, maximal 128 Zeichen/512 UTF-8-Bytes ohne Steuerzeichen. Prozent und Unterstrich sind keine Wildcards. Unterschiedliche Unicode-Normalformen werden nicht zusätzlich vereinheitlicht. Die Endung enthält höchstens 16 ASCII-Buchstaben/Ziffern. Ungültige Eingaben oder fremde/unbekannte Bereichstags ergeben 400, fehlende aktuelle Rechte 403, andere HTTP-Methoden 405.

Ergebnisse zeigen den Dateinamen, logischen Ordnerpfad, höchstens 160 Zeichen Beschreibung und die zulässigen Taglabels. Namen und Texte werden escaped; HTML/Markdown wird nicht ausgeführt. Die Treffer verlinken auf Katalog und Dateiverwaltung. Es gibt bis zu 100 Treffer je Seite und einen Cursor nach stabiler Dateikennung, keine Gesamttrefferzahl. Suchtext und Filter bleiben in Folgelinks erhalten. Gleichzeitige Änderungen ergeben keinen eingefrorenen Suchsnapshot und können Folgeseiten verändern.

## Rechte und Begrenzung

Papierkorb, Nachfahren gelöschter Ordner und kaputte/zyklische Elternketten liefern keine Dateinamen, Pfade oder Beschreibungen. Die Suchkandidaten stammen aus demselben sichtbaren Katalog wie die vorhandene Namenssuche. Vor Ausgabe wird jeder tatsächliche Treffer erneut auf aktive Datei/Sichtbarkeit und unveränderte Dateirevision geprüft; abschließend gelten erneut aktuelle Lese-/Katalogrechte. Das Bereichstagwörterbuch bleibt ausdrücklich gemeinsamer Kataloginhalt für Berechtigte und ist keine private Attributliste einzelner versteckter Dateien.

Die Suche liest ausschließlich Dateinamen, manuelle Beschreibungen und Tagreferenzen. Originalbytes, alte Versionen, ausgelesene EXIF-/IPTC-/XMP-/Promptfelder, OIDC-Claims oder andere Pluginfelder werden nicht nebenbei durchsucht. Sensible Medienfelder erhalten dadurch keine neue Suchfreigabe. Zusätzliche Feldtypen/Quellen benötigen einen eigenen ausdrücklichen Vertrag und aktuelle Rechte.

Der bestehende Katalogschutz beträgt 10000 Einträge je Bereich einschließlich Papierkorb und höchstens 128 Elemente pro Elternkette. Überschreitung schlägt begrenzt fehl, ohne Vollständigkeit von Teilergebnissen zu behaupten. Manuelle Werte werden in Blöcken bis 100 Kandidaten gelesen; die bestehenden Grenzen von 16 KiB Beschreibung, 100 Bereichstags und 50 Dateitags bleiben maßgeblich. Große Beschreibungen werden nicht vollständig als Treffertext ausgegeben. Die technische Begrenzung ist keine vollständige Lastabnahme für beliebige 10000-Dateien-Bestände.

## Öffentlicher Leservertrag

FileCatalogReader.visibleEntries(actorId,spaceId) liefert unter aktuellem files.list bis zu 10000 sichtbare FileCatalogEntry-Werte in stabiler ID-Reihenfolge. Die Werte enthalten nur Bereichs-/Eintrags-/Elternkennungen, Namen, Art, Revision, Größe und logischen Anzeigeordnerpfad; keine lokalen Speicherpfade, Tokens oder Originalbytes. Originallesen verlangt weiterhin gesonderte Rechte. FileService implementiert den Vertrag; die vertrauenswürdige Zusammenstellung kann ihn Erweiterungen bereitstellen. Sichtbarkeit und Pfadprüfung werden mit der bestehenden Namenssuche geteilt. Ein Leservertrag ist kein automatischer Plugin-Installations-/Servicecontainer.

Automatisierte PHP-/Datenbank- und synthetische HTTP-/HTTPS-Prüfungen decken Namen, Beschreibung, Tags, kombinierte Filter, Unicode/literale Sonderzeichen, Sichtbarkeit, Rechteentzug, Escaping und Cursorseiten ab. Visuelle, native Geräte- und produktive Lastabnahme bleiben getrennte Aufgaben.
