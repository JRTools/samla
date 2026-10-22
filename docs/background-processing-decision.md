# Entscheidungsvorlage: Hintergrundverarbeitung

Stand: 22. Oktober 2026. Vorschlag zur Freigabe, noch nicht eingerichtet oder als Worker implementiert.

## Warum diese Entscheidung jetzt nötig ist

Samla schreibt Abschlussereignisse bereits atomar in die Datenbank-Outbox. Sie werden noch nicht an Empfänger zugestellt. Medienextraktion, Vorschauen und spätere größere Exporte benötigen einen abgestimmten Ablauf außerhalb langer Browseranfragen. Das Konzept, Abschnitt 8, erklärt PHP-CLI/Cron ausdrücklich zum noch nicht freigegebenen Vorschlag. Diese Vorlage legt einen prüfbaren Betriebsrahmen fest; sie installiert keinen Cronjob.

## Empfohlener Rahmen für etwa 1000 Dateien und fünf Benutzer

- Vorhandenes PHP 8.4 oder 8.5 als CLI verwenden, mit denselben benötigten Erweiterungen und derselben .env wie PHP-FPM. Kein zusätzlicher Serverdienst, Redis, Node-Prozess oder Message-Broker.
- Ein geplanter Plesk-/Cron-Aufruf pro Minute unter dem Subscription-Benutzer. Kein root-Betrieb. Die geplante Schnittstelle ist php bin/worker.php --max-events=50; diese Datei existiert noch nicht und der Befehl ist noch nicht ausführbar.
- Höchstens 50 fällige Ereigniszustellungen pro Aufruf. Nach 45 Sekunden beginnt der Worker keine neue Zustellung mehr. Das ist eine kooperative Laufzeitgrenze zwischen Aufgaben, keine Zusage, eine hängende Providerfunktion oder ein blockierendes I/O nach exakt 45 Sekunden abzubrechen.
- Ein eigener Datenbank-Ausführungslock verhindert überlappende Worker derselben Installation. Der Worker hält während Providerarbeit nicht den globalen Lock für Benutzer- und Dateimutationen. Ein folgender Cronaufruf beendet sich ohne Parallelverarbeitung, wenn die vorige Ausführung noch aktiv ist. Verbindungsabbruch gibt den Datenbanklock frei.
- Neue tabellarische Zustellzustände und Pluginverträge werden implementiert und über versionierte Migrationen eingeführt. Bestehende Dateien und Ereignisse bleiben erhalten. Keine automatische endgültige Löschung, Objekt-GC oder stillschweigende Aufbewahrungsregel durch diesen Cronjob.

## Vorgesehener Zustellvertrag

Ein registrierter Empfänger besitzt eine stabile Kennung und explizite Ereignistypen. Zustellung ist mindestens einmal; Empfänger müssen nach Empfängerkennung und Ereigniskennung idempotent arbeiten. Eine Ereignis-Batchkennung ersetzt niemals die einzelne Ereigniskennung. Erfolgreiche Zustellungen werden je Empfänger protokolliert. Deaktivierte oder unbekannte Empfänger werden nicht aufgerufen. Rückwirkende Zustellung historischer Ereignisse wird ausdrücklich pro Empfänger festgelegt und erfolgt nicht automatisch unbeschränkt.

Claim, begrenzte Lease, Versuchszähler und Quittierung liegen in der Datenbank. Unterbrochene Claims können nach Ablauf erneut übernommen werden. Fehler erhalten steigende Wiederholabstände, maximal acht automatische Versuche und anschließend einen sichtbaren Fehlerstatus. Eine spätere manuelle Wiederholung ist eine ausdrücklich autorisierte administrative Aktion. DB-Zustellung und lokale fachliche Ergebnisse werden, soweit möglich, gemeinsam quittiert; externe Effekte bleiben auf echte Idempotenz angewiesen. Ein Abbruch nach Effekt vor Quittierung muss im Test dieselbe Wirkung höchstens einmal sichtbar machen.

Technische Medienaufgaben binden Ergebnisse an feste Datei-/Versionskennungen und veröffentlichen keine Ableitung als aktuellen Inhalt einer inzwischen geänderten Datei. Das Original bleibt unverändert. Ein im Benutzerauftrag erstellter Export prüft aktuelle Rechte erneut. Technische Pflege erhält keinen automatischen Zugriff als ehemaliger Benutzer und darf aus alten Ereignissen keine aktuelle Freigabe ableiten. Das konkrete Berechtigungsmodell wird mit den jeweiligen Handlern implementiert und getestet.

CLI-Ausgaben enthalten Zähler, Ereignis-/Empfängerkennungen und Fehlerklassen, keine Zugangsdaten, Tokens, Dateiinhalte oder beliebige Parserausgaben. Fehlerstatus und Retryzeit bleiben überprüfbar. Die Verarbeitung wird nicht als zuverlässig zugestellt bezeichnet, bevor diese Fälle automatisiert geprüft sind.

## Umsetzung und Abnahme nach Freigabe

1. Öffentliche Ereignis-/Empfängerverträge und atomare Registrierung ergänzen. Unverträgliche Pflichtkomponenten verweigern geschützte Arbeit.
2. Zustellmigration, Claim/Lease, Quittierung, Fehler-/Retryzustände und begrenzte CLI umsetzen. Parallelität und Abbrüche mit beiden Datenbanken prüfen.
3. Einen idempotenten Referenzempfänger und administrative Diagnose liefern. Noch keine unbegrenzten Provider oder destruktiven Hintergrundaktionen aktivieren.
4. Fachliche Medienhandler mit Versionsbindung und ihren eigenen Rechten ergänzen. Parser-/Vorschaugrenzen separat nachweisen.
5. Die echte Plesk-Aufgabe nach Betreiberfreigabe mit dem passenden PHP-Pfad einrichten lassen. Ein manueller erfolgreicher Workeraufruf und danach ein beobachteter automatischer Lauf sind getrennte Abnahmepunkte. Codeveröffentlichung allein richtet keinen Cronjob auf dem Host ein.

## Alternative ohne Cron

Ein manueller begrenzter CLI-Aufruf kann denselben Verarbeitungskern verwenden, benötigt aber regelmäßige Ausführung durch den Betreiber. Er ist keine verlässliche automatische Hintergrundverarbeitung. Eine Ausführung allein bei Browseraufrufen garantiert bei Inaktivität keine Verarbeitung und wird nicht als gleichwertig zugesagt. Ein dauerhafter Systemdienst oder externer Scheduler wäre eine neue Betriebsentscheidung und wird nicht ohne Abstimmung eingeführt.

## Benötigte Betreiberentscheidung

Kann und darf auf dem Plesk-Host ein PHP-CLI-Aufruf pro Minute unter dem Subscription-Benutzer als geplante Aufgabe laufen? Bei Zustimmung wird der beschriebene Workerrahmen implementiert und getestet. Die konkrete Hostaufgabe wird erst anhand der fertigen CLI und des tatsächlichen PHP-Pfads eingerichtet. Bei fehlender Cronmöglichkeit ist zuerst die gewünschte Alternative festzulegen.
