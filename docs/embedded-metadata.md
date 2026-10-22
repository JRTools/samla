# Eingebettete Metadaten und deklarierte KI-Angaben

Ab Iteration 30 verwendet die bestehende Medienpipeline zusätzlich EmbeddedMetadataProvider. Er nutzt vorhandenes PHP, DOM/libxml und iptcparse; es kommt keine neue Bibliothek, Erweiterung oder ausführbare Fremdkomponente hinzu. Die Originaldatei bleibt unverändert. Die Auslieferung nutzt die aktuellen Bereichs- und Feldrechte der Medienansicht.

## Abdeckung

| Container | Untersuchte Quellen | Grenze dieser ersten Unterstützung |
| --- | --- | --- |
| JPEG | Standard-XMP im APP1-Segment; IPTC-IIM im Photoshop-APP13-Ressourcenblock 0x0404 | Keine Extended-XMP-Zusammensetzung; kein allgemeiner Photoshop- oder vollständiger JPEG-Validator |
| PNG | tEXt und unkomprimiertes iTXt; XMP unter XML:com.adobe.xmp | Keine Dekompression von zTXt oder komprimiertem iTXt; solche Quellen ergeben teilweise untersucht |
| GIF, WebP, TIFF und weitere Formate | Weiterhin technische Raster-/EXIF-Abdeckung gemäß media.md | Dieser zusätzliche Provider untersucht deren eingebettete Herkunftsmetadaten noch nicht |

Die Extraktion übernimmt ausgewählte Titel, Beschreibungen, Schlagwörter, Urheber, Bildnachweise, Rechteangaben, Quellen und Programmnamen. Rohwert, Normalisierung, Quelle, Provider und Auslesestatus bleiben getrennt. XMP wird anhand der tatsächlichen Namespace-URI gelesen, nicht anhand eines frei gewählten Präfixnamens. Ab Provider-Version 1.1 werden ausschließlich direkte Eigenschaften der Hauptbeschreibungen unter einem unterstützten xmpmeta-/xapmeta-/RDF-Wurzelelement übernommen. Das unterstützte Hauptsubjekt hat kein rdf:about oder einen leeren Wert und keine anders gebundene rdf:nodeID/rdf:ID. XML-Basisumleitungen werden als nicht unterstützte Subjektbindung behandelt. Ausdrücklich benannte andere Subjekte und verschachtelt beschriebene Ressourcen werden nicht als Angaben der aktuellen Datei interpretiert. Besteht nur ein nicht zuordenbares benanntes Subjekt, bleibt der Status teilweise untersucht und die zusammengefasste Herkunft unbekannt. Dies ist eine konservative Abdeckungsgrenze, keine kryptografische Identitätsprüfung. Unterstützte einfache Elemente, Attribute und RDF-Listen können mehrere nachvollziehbare Werte liefern; es erfolgt noch keine automatische Übernahme in manuelle Katalogfelder oder Tags.

PNG-tEXt wird entsprechend seinem Container als Latin-1 gelesen, iTXt als UTF-8. XMP ist in dieser ersten Umsetzung auf UTF-8 begrenzt. IPTC-IIM mit explizitem UTF-8-Marker wird entsprechend ausgewertet; unmarkierte ASCII-Werte bleiben lesbar. Nicht eindeutig unterstützte nicht-ASCII-Zeichenkodierungen werden ausdrücklich als nicht unterstützt ausgewiesen, anstatt eine Kodierung zu erraten.

## Deklaration und Nachweis sind getrennt

Das strukturierte IPTC-XMP-Feld DigitalSourceType wird als kontrollierte URI übernommen. Nur die exakt unterstützten kanonischen Angaben trainedAlgorithmicMedia beziehungsweise compositeWithTrainedAlgorithmicMedia führen zu **als KI-generiert gekennzeichnet** beziehungsweise **als mit KI bearbeitet gekennzeichnet**. Andere deklarierte Herkunft bleibt eine eigene Kategorie. Mehrere unterschiedliche entsprechende Dateiangaben werden als widersprüchlich dargestellt.

Unvollständige oder fehlerhafte Untersuchung ergibt eine entsprechende Verarbeitungsanzeige; ohne vollständig unterstützte eindeutige Angabe bleibt die zusammengefasste Herkunft unbekannt. Die tatsächlich gefundenen zulässigen Rohwerte bleiben sichtbar. Es wird kein allgemeiner KI-Bilddetektor eingesetzt. Dateiname, Programmname und freier Prompttext begründen keine automatische KI-Einstufung.

Zusätzlich werden die dokumentierten XMP-Felder AISystemUsed, AISystemVersionUsed, AIPromptInformation und AIPromptWriterName getrennt ausgelesen. Ein benanntes System ersetzt keine unterstützte DigitalSourceType-Angabe. Promptautor und Bildurheber bleiben verschiedene Felder. PNG-Schlüssel parameters, prompt und workflow werden als begrenzte sensible Zusatzinformationen übernommen, ohne ihre beliebigen Formate auszuführen oder allein daraus eine generative Herkunft abzuleiten.

Die Felder deklarierte Entstehung, untersuchte Quellen und Auslesestatus erscheinen in der Medien-Detailansicht mit media.metadata.read. Fehlende Kennzeichnungen bedeuten keine Entstehung ohne KI. C2PA-Manifeste werden hier weder gesucht noch kryptografisch oder nach Vertrauensankern validiert. Die gesonderte Komponentenentscheidung bleibt offen.

## Zusätzliche Rechte für Promptdaten

media.sensitive.read ist ein zusätzliches Bereichsrecht. Prompt- und Promptautorfelder sind als sensitive registriert, verlangen dieses Recht zusätzlich zur allgemeinen Metadatenansicht und sind nicht automatisch exportierbar. Bisherige media-Rollen erhalten es nicht. Ohne Berechtigung erscheinen weder Werte noch Feldlabels oder Platzhalter, die ihre konkrete Existenz offenlegen. Die allgemeine Quellenabdeckung beschreibt unterstützte Prüfpfade, keine Anzahl verborgener Felder.

Eine ausdrückliche Wartungsinstallation für eine bereits berechtigte Gruppe ist möglich:

```sh
APP_MAINTENANCE=1 php bin/storage-space-access.php ACTOR_ID SPACE_ID GROUP_ID media-sensitive
```

Dieses zusätzliche Profil vergibt media.metadata.read und media.sensitive.read. files.list bleibt separat erforderlich, Vorschau-/Download-/Verarbeitungsrechte werden dadurch nicht vergeben. Welche Gruppen diese Angaben organisatorisch sehen dürfen, bleibt Betreiberentscheidung. GPS und Gerätekennungen werden weiterhin nicht als allgemeine Felder ausgelesen.

Das Original enthält weiterhin seine eingebetteten Angaben und ist nach files.read herunterladbar. Die getrennte PNG-Vorschau wird von GD neu erzeugt und der Vorschauvertrag lehnt Metadaten-/Text-Chunks ab. Das Zusatzrecht ist keine nachträgliche Bereinigung erlaubter Originaldownloads.

## Ressourcen und Parserverhalten

Der bestehende Sourcevertrag begrenzt den Quellinhalt auf 16 MiB. Der zusätzliche Parser begrenzt JPEG-Header auf 1 MiB beziehungsweise 512 Segmente, PNG auf 512 Chunks, untersuchte Metadatenblöcke zusammen auf 256 KiB, Einzelblöcke auf 64 KiB, Photoshop-Ressourcen auf 64 und XMP-Elemente auf 2048. Einzelne gespeicherte Texte bleiben auf 1024 UTF-8-Bytes begrenzt; höchstens 29 ausgelesene Werte plus drei Statuswerte pro Ergebnis. Weitere Werte erzeugen ausdrücklich einen Grenzstatus. Die vorhandene Pipelinegrenze von 32 KiB Metadaten pro Ableitung gilt zusätzlich.

Ein kooperatives Zeitbudget von einer Sekunde wird zwischen Verarbeitungsschritten geprüft. Es unterbricht keine bereits laufende native Funktion; PHP-/FPM-/Hostlimits und das übergeordnete Workerbudget bleiben relevant. Zeit-, Mengen- und Größengrenzen ergeben limit; beschädigte oder nur teilweise unterstützte Angaben bleiben entsprechend gekennzeichnet. Native Warnungen und XML-Fehlertexte werden nicht als Dateiinhalte ins UI oder Log übernommen.

DTD- und Entity-Deklarationen sowie NUL-/nicht unterstützte Kodierungen werden vor dem XML-Aufruf verworfen. DOM verwendet keine Entitysubstitution, keine externen Auflösungen und LIBXML_NONET. Es gibt keine Netzabrufe anhand eingebetteter URLs, keine Stylesheetausführung und keine Dekompression unbekannter Textblöcke. PNG-Chunks werden vor ihrer Verwendung auf Grenzen und CRC geprüft. Der Provider ist keine vollständige Format-, Fuzzing- oder Herkunftsvalidierung.

## Update und Neuaufbau

Keine neue Datenbankmigration über Iteration 29 hinaus. Zusätzliche Feld-/Providerregistrierungen verändern bewusst die Verarbeitungssignatur. Bestehende Ableitungen werden dadurch nicht ungeprüft unter der neuen Abdeckung angezeigt. Im Medienbereich den vorhandenen seitenweisen Neuaufbau verwenden und den eingerichteten CLI-/HTTPS-Worker laufen lassen. Neue Uploads und Inhaltsversionen verwenden die Pipeline automatisch. Bestehende manuelle Angaben werden von diesem lesenden Provider nicht verändert.

## Primärquellen

- [IPTC Photo Metadata Standard 2025.1](https://iptc.org/std/photometadata/specification/IPTC-PhotoMetadata-2025.1.html): Felder, Namespace und IIM-Zuordnung.
- [IPTC Digital Source Type](https://cv.iptc.org/newscodes/digitalsourcetype/), insbesondere [generativ erstellt](https://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia) und [generativ bearbeitet](https://cv.iptc.org/newscodes/digitalsourcetype/compositeWithTrainedAlgorithmicMedia).
- [W3C PNG, dritte Ausgabe](https://www.w3.org/TR/png-3/): Chunkstruktur und Textkodierungen.
- [PHP iptcparse](https://www.php.net/manual/en/function.iptcparse.php): vorhandene native IPTC-Schnittstelle.
