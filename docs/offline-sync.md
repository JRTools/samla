# Offline bearbeiten und automatisch synchronisieren

Stand: 22. Oktober 2026. Verbindliche Anforderung des Betreibers: Die App muss Dateien offline bearbeitbar machen und Änderungen nach Wiederverbindung automatisch synchronisieren. Zielplattformen sind iOS, Android, Windows und macOS.

## Aktueller Stand

WebDAV, App-Passwörter, Versionierung, ETags und bedingte Änderungen bilden die vorhandene Servergrundlage. Eine dauerhafte lokale Änderungswarteschlange, automatische Wiederaufnahme und eine Oberfläche zur Konfliktauflösung sind noch nicht implementiert beziehungsweise auf Geräten abgenommen. Der PHP-Browsereditor allein ist kein Offlineeditor. Die Wahl konkreter Geräteclients bleibt offen; eine bestimmte native oder Browserimplementierung wird durch diese Anforderung nicht als bereits vorhanden ausgewiesen.

## Geplanter Ablauf

1. Benutzer wählen Dateien oder Ordner ausdrücklich für die Offlineverfügbarkeit. Ein Download gilt erst nach vollständig gespeicherten und geprüften Inhalten als offline verfügbar. Lokale Kapazitätsfehler werden angezeigt; ausstehende Änderungen dürfen keiner Cachebereinigung zum Opfer fallen.
2. Die App hält Inhalt, stabile Dateikennung, Bereich und zuletzt bestätigten Serverstand lokal vor. Die lokale Speicherung ist an Konto und Installation gebunden. Eine erneute Anmeldung darf fremde Kontodaten nicht als eigene übernehmen.
3. Bearbeiten und neue Dateien müssen ohne Verbindung möglich sein. Änderungen landen zusammen mit ihrer Ausgangsrevision dauerhaft in einer lokalen Warteschlange. Neustarts dürfen bestätigte lokale Bearbeitungen nicht verlieren. Die Bearbeitungsfunktion hängt vom Dateiformat und einem geeigneten Editor ab; universelles Bearbeiten aller Binärformate wird nicht versprochen.
4. Bei wieder erreichbarem Server startet die App im Rahmen der vom Betriebssystem erlaubten Ausführung automatisch einen Synchronisationsversuch. Erreichbarkeit wird durch einen tatsächlichen Serverzugriff festgestellt. Netzwerksignale allein belegen keine funktionierende Verbindung. Auf mobilen Plattformen kann eine Wiederaufnahme erst beim nächsten App-Aufruf stattfinden, wenn das Betriebssystem Hintergrundarbeit verhindert.
5. Vor dem Übertragen werden Anmeldung, aktuelle Rechte und der Ausgangsstand geprüft. Uploads verwenden eine serverseitig atomar geprüfte Revisionsbedingung. Ein vorheriger separater Vergleich ohne Schreibbedingung reicht nicht aus.
6. Nach bestätigtem Abschluss werden Serverrevision und lokale Warteschlange zusammen fortgeschrieben. Geht nur die Antwort verloren, muss der Client den tatsächlichen Serverstand abgleichen, bevor er erneut schreibt; doppelte Versionen dürfen nicht unbemerkt entstehen.
7. Remoteänderungen werden heruntergeladen, sofern keine lokale Änderung vorliegt. Gleichzeitige Änderungen, Remote-Löschung und Änderungen an übergeordneten Ordnern führen zu einem sichtbaren Konflikt. Eine lokale Löschung darf zwischenzeitliche Remoteänderungen nicht still entfernen.

Die erste Umsetzung soll Inhalte bearbeiten und neue Dateien synchronisieren. Offline-Umbenennen, Verschieben und Löschen benötigen dieselben Konfliktgarantien und werden separat abgenommen. Falls eine Operation noch nicht unterstützt wird, ist sie offline ausdrücklich gesperrt.

## Konflikte und Fehler

Vorläufiger technischer Sicherheitsstandard: Beide Stände erhalten, keine automatische Auswahl nach Geräteuhr oder letztem Upload. Der Benutzer kann die lokale Bearbeitung als separate Datei sichern oder nach bewusster Auswahl einen neuen aktuellen Stand erzeugen. Automatisches Zusammenführen ist vorerst nicht vorgesehen. Die konkrete Benutzerführung wird mit der Clientumsetzung festgelegt.

Die Oberfläche unterscheidet mindestens offline verfügbar, lokal geändert, Synchronisierung ausstehend, Synchronisierung läuft, synchronisiert, Konflikt und Fehler. Verbindungsfehler werden mit begrenztem Backoff wiederholt; ungültige Anmeldung, fehlende Rechte, Quote und Konflikte verlangen sichtbare Behandlung und keine Endlosschleife. Lokale Änderungen bleiben bei Fehlern erhalten.

Rechteentzug stoppt weitere Serverzugriffe. Bereits heruntergeladene Daten auf einem ausgeschalteten oder getrennten Gerät lassen sich dadurch nicht nachträglich zuverlässig zurückrufen. Abmeldung und Entfernen von Offlinedaten brauchen eine ausdrückliche Behandlung ungesendeter Änderungen.

## Arbeitspakete

1. Bestehende Serverbedingungen und DAV-Verhalten gegen die unten genannten Fälle prüfen; fehlende atomare Konfliktbedingungen und sichere Wiederholungen ergänzen.
2. Clientbasis für lokale Dateien, Revisionen und dauerhafte Warteschlange umsetzen beziehungsweise geeignete vorhandene Clients anhand dieser Anforderungen bewerten. Neue Laufzeitkomponenten werden vor Einführung konkret abgestimmt.
3. Automatische Synchronisation, Authentifizierungserneuerung, Fehlerstatus und Konfliktoberfläche integrieren.
4. Geräteabnahme auf allen vier Plattformen durchführen und Einschränkungen dokumentieren.

Eine bloße WebDAV-Einbindung erfüllt diese Arbeitspakete nicht automatisch. Eine neue Server-API oder ein zusätzlicher Hintergrunddienst wird erst eingeführt, wenn der vorhandene Vertrag nachweislich nicht ausreicht.

## Abnahme

| Fall | Erwartetes Ergebnis |
| --- | --- |
| Datei offline öffnen und bearbeiten | Bearbeitung funktioniert ohne Serverzugriff und bleibt nach App-Neustart erhalten. |
| Offline neu angelegte Datei | Bleibt lokal erhalten und wird nach Verbindung genau einmal sichtbar angelegt. |
| Wiederverbindung | Warteschlange startet innerhalb der verfügbaren App-Ausführungszeit automatisch; Ergebnis wird angezeigt. |
| Server antwortet nach erfolgreichem Schreiben nicht mehr | Wiederholung gleicht den tatsächlichen Stand ab; keine unbemerkte doppelte Version. |
| Zwei Geräte bearbeiten denselben Ausgangsstand | Beide Bearbeitungen bleiben erhalten; Konflikt ist sichtbar und lösbar. |
| Remote gelöscht, lokal bearbeitet | Kein stilles Verwerfen oder Wiederanlegen; Benutzer entscheidet. |
| Upload unterbrochen, Datei groß oder lokaler Speicher voll | Kein veröffentlichter Teilinhalt, kein Verlust des lokalen Entwurfs, verständlicher Status. |
| Passwort widerrufen, Konto gesperrt oder Rechte entzogen | Keine weitere Serversynchronisation, lokale ausstehende Änderung bleibt zugänglich. |
| Quotenfehler | Kein Datenverlust; Warteschlange bleibt nachvollziehbar ausstehend. |
| Anderes Konto meldet sich an | Keine Vermischung privater Offlinedaten oder Warteschlangen. |

Diese Fälle sind geplant und noch nicht als erfolgreich ausgeführt markiert. Die [native Clientabnahme](client-validation.md) hält Gerät, Betriebssystem und Clientversion fest.
