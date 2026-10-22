# Entscheidungsvorlage: native PHP-Erweiterung EXIF

Stand: 22. Oktober 2026. Noch keine produktive Samla-Abhängigkeit oder Einbindung. Der Komponentenentscheid ist ausdrücklich offen.

## Konkreter Vorschlag

Zusätzlich zum bereits vorgesehenen GD soll die zur jeweiligen PHP-8.4-/8.5-Installation gehörende Erweiterung ext-exif für Bildorientierung und ausgewählte JPEG-/TIFF-EXIF-Metadaten verwendet werden. Es ist kein eigener ExifTool-Prozess, keine neue Composerbibliothek und kein externer Dienst vorgesehen. mbstring ist bereits Voraussetzung von Samla. Auf dem Betreiberhost muss ext-exif für die verwendete PHP-CLI und für PHP-FPM verfügbar sein; bei alleiniger HTTP-Workerverarbeitung genügt die tatsächlich verwendete FPM-Laufzeit.

Die konkrete neue Betriebsanforderung ist damit die Verfügbarkeit dieses einen PHP-Moduls bei den Medienarbeitern. Ein fehlendes Modul darf die bisherige Dateiablage, OIDC, WebDAV und Downloads nicht lahmlegen. Medienprovider melden stattdessen ausdrücklich unavailable beziehungsweise unsupported; Orientierung wird bei unbekanntem Wert nicht als sicher korrekt interpretiert.

## Verwendung nach Freigabe

- EXIF-Orientierung als Grundlage korrekt gedrehter beziehungsweise gespiegelter GD-Ableitungen, insbesondere bei Handyfotos. Alle acht EXIF-Orientierungswerte müssen mit kontrollierten Fixtures geprüft werden.
- Auswahl begrenzter technischer EXIF-Felder über den kommenden öffentlichen Medienprovider-/Feldvertrag. Keine ungeprüfte Ausgabe der vollständigen Parserstruktur.
- Private unveränderliche Inhaltsstreams mit begrenzter Quellgröße; kein vom Request gelieferter Dateipfad oder URL-Wrapper. Die Parserintegration wird an konkrete Dateiversionen gebunden und im gemeinsamen Worker verarbeitet.
- Keine Änderung oder Rückschreibung in Originale. Vorschaudateien sind regenerierbare Ableitungen und werden separat geschützt ausgeliefert.
- Begrenzte Zeichenfolgen/Feldzahlen, typisierte Werte und abgefangene Parserwarnungen. Interne Pfade, Warnungstexte und binäre Vorschaustrukturen gehören nicht ungefiltert ins UI, Audit oder HTTP-Antworten.
- GPS, Seriennummern und vergleichbare sensible Felder erhalten keine automatische allgemeine Sichtbarkeit. Ihre spätere Veröffentlichung braucht die vorgesehenen zusätzlichen Feld-/Metadatenrechte. Eine ausgeblendete Metadatenanzeige entfernt allerdings keine Angaben aus einem ausdrücklich erlaubten Originaldownload.

## Bereits durchgeführte isolierte Evaluation

Außerhalb des Repositories wurde die vorhandene Testlaufzeit mit ext-exif geladen. Unter PHP 8.4.5 und 8.5.4 hat exif_read_data einen privaten lesbaren Stream mit einem künstlichen 2×3-JPEG und Orientierung 6 korrekt gelesen. Eine Datei ohne Bildstruktur wurde als nicht lesbar behandelt; die Parserwarnung wurde lokal kategorisiert. Die Originalprüfsumme blieb in allen vier Fällen identisch. Es wurden ausschließlich synthetische Testbilder verwendet. Der produktive Samla-Code hat weder ext-exif aktiviert noch einen EXIF-Provider registriert.

Die offizielle PHP-Schnittstelle unterstützt resource|string für exif_read_data; damit sind keine Requestpfade nötig. Quelle: https://github.com/php/php-src/blob/PHP-8.4/ext/exif/exif.stub.php . Diese kleine Evaluation ist keine vollständige Parser-, Format-, Ressourcen- oder Sicherheitsabnahme. Die größere Fixturematrix, GD-Ableitungen, Rechte und Fachintegration folgen erst nach Komponentenfreigabe.

## Abgrenzung und benötigte Entscheidung

Die Freigabe betrifft ausschließlich die native PHP-Erweiterung ext-exif in den unterstützten PHP-Laufzeiten. GD bleibt die bereits bestätigte Bildbasis. Zusätzliche Tools wie ExifTool, C2PA-Validatoren oder weitere native Parser sind damit nicht freigegeben. Nach Zustimmung wird die konkrete Medienintegration umgesetzt und ihre Abnahme dokumentiert.

Darf Samla ext-exif in diesem begrenzten Rahmen für die folgende Medienphase verwenden? Das Konzept fordert in Abschnitt 3.4 eine gesonderte Abstimmung zusätzlicher Erweiterungen; AGENTS.md verlangt ebenfalls vor zusätzlichen Laufzeitkomponenten eine Abstimmung.
