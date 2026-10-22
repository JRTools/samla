# Entscheidungsvorlage: native PHP-Erweiterung EXIF

Stand: 22. Oktober 2026. **Freigegeben durch den Betreiber: „Exif erweiterung ist ok“.** Die Freigabe betrifft die native PHP-Erweiterung ext-exif im unten beschriebenen Rahmen. Die Medienintegration und ihre konkrete Prüfabdeckung stehen in [media.md](media.md) und [verification.md](verification.md).

## Konkreter Vorschlag

Zusätzlich zum bereits vorgesehenen GD soll die zur jeweiligen PHP-8.4-/8.5-Installation gehörende Erweiterung ext-exif für Bildorientierung und ausgewählte JPEG-/TIFF-EXIF-Metadaten verwendet werden. Es ist kein eigener ExifTool-Prozess, keine neue Composerbibliothek und kein externer Dienst vorgesehen. mbstring ist bereits Voraussetzung von Samla. Auf dem Betreiberhost muss ext-exif für die verwendete PHP-CLI und für PHP-FPM verfügbar sein; bei alleiniger HTTP-Workerverarbeitung genügt die tatsächlich verwendete FPM-Laufzeit.

Die konkrete neue Betriebsanforderung ist damit die Verfügbarkeit dieses einen PHP-Moduls bei den Medienarbeitern. Ein fehlendes Modul darf die bisherige Dateiablage, OIDC, WebDAV und Downloads nicht lahmlegen. Medienprovider melden stattdessen ausdrücklich unavailable beziehungsweise unsupported; Orientierung wird bei unbekanntem Wert nicht als sicher korrekt interpretiert.

## Freigegebene Verwendung

- EXIF-Orientierung als Grundlage korrekt gedrehter beziehungsweise gespiegelter GD-Ableitungen, insbesondere bei Handyfotos. Alle acht EXIF-Orientierungswerte müssen mit kontrollierten Fixtures geprüft werden.
- Auswahl begrenzter technischer EXIF-Felder über den kommenden öffentlichen Medienprovider-/Feldvertrag. Keine ungeprüfte Ausgabe der vollständigen Parserstruktur.
- Private unveränderliche Inhaltsstreams mit begrenzter Quellgröße; kein vom Request gelieferter Dateipfad oder URL-Wrapper. Die Parserintegration wird an konkrete Dateiversionen gebunden und im gemeinsamen Worker verarbeitet.
- Keine Änderung oder Rückschreibung in Originale. Vorschaudateien sind regenerierbare Ableitungen und werden separat geschützt ausgeliefert.
- Begrenzte Zeichenfolgen/Feldzahlen, typisierte Werte und abgefangene Parserwarnungen. Interne Pfade, Warnungstexte und binäre Vorschaustrukturen gehören nicht ungefiltert ins UI, Audit oder HTTP-Antworten.
- GPS, Seriennummern und vergleichbare sensible Felder erhalten keine automatische allgemeine Sichtbarkeit. Ihre spätere Veröffentlichung braucht die vorgesehenen zusätzlichen Feld-/Metadatenrechte. Eine ausgeblendete Metadatenanzeige entfernt allerdings keine Angaben aus einem ausdrücklich erlaubten Originaldownload.

## Bereits durchgeführte isolierte Evaluation

Außerhalb des Repositories wurde die vorhandene Testlaufzeit mit ext-exif geladen. Unter PHP 8.4.5 und 8.5.4 hat exif_read_data einen privaten lesbaren Stream mit einem künstlichen 2×3-JPEG und Orientierung 6 korrekt gelesen. Eine Datei ohne Bildstruktur wurde als nicht lesbar behandelt; die Parserwarnung wurde lokal kategorisiert. Die Originalprüfsumme blieb in allen vier Fällen identisch. Es wurden ausschließlich synthetische Testbilder verwendet. Diese frühere Evaluation ging der jetzt freigegebenen Integration voraus.

Die offizielle PHP-Schnittstelle unterstützt resource|string für exif_read_data; damit sind keine Requestpfade nötig. Quelle: https://github.com/php/php-src/blob/PHP-8.4/ext/exif/exif.stub.php . Diese kleine Evaluation ist keine vollständige Parser-, Format-, Ressourcen- oder Sicherheitsabnahme. Die anschließende Fixturematrix, GD-Ableitungen, Rechte und Fachintegration werden separat im Prüfprotokoll dokumentiert.

## Abgrenzung der erteilten Freigabe

Die Freigabe betrifft ausschließlich die native PHP-Erweiterung ext-exif in den unterstützten PHP-Laufzeiten. GD bleibt die bereits bestätigte Bildbasis. Zusätzliche Tools wie ExifTool, C2PA-Validatoren oder weitere native Parser sind damit nicht freigegeben. Die konkrete Medienintegration wird umgesetzt und ihre Abnahme dokumentiert.


Die in Konzept Abschnitt 3.4 und AGENTS.md geforderte gesonderte Abstimmung ist für ext-exif erfolgt. Weitere native Parser bleiben davon unberührt.
