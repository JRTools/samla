# Outbox-Worker per CLI und Webrequest

Der Betreiber hat PHP-CLI per Cron und einen zusätzlichen Webtrigger freigegeben. Beide Wege verwenden denselben OutboxWorker. Es wird kein dauerhafter Prozess oder zusätzlicher Serverdienst benötigt. Die Einrichtung einer tatsächlichen geplanten Aufgabe auf dem Betreiberhost bleibt ein eigener Schritt.

## Update und Migration

Geplante Workeraufrufe für das Update pausieren. Im Wartungsmodus Code und Composer-Abhängigkeiten aktualisieren und php bin/migrate.php mit dem passenden PHP-Interpreter ausführen. Neu sind platform.administration:0002_outbox_delivery und 0003_delivery_revision. Sie ergänzt eine Ereignissequenz und Zustell-/Empfänger-/Referenzquittungstabellen. Eine konsistente Sicherung gehört wie bei anderen Migrationen zum Update. Erst danach Wartungsmodus deaktivieren und geplante Aufgaben wieder einschalten. Die neue Migration verändert keine Dateiinhalte und löscht keine bestehenden Ereignisse.

## CLI-Cron

Mit PHP 8.4 oder 8.5 unter demselben Subscription-Benutzer wie die Anwendung:

```sh
cd /var/www/vhosts/jrtools.org/samla.jrtools.org
/opt/plesk/php/8.4/bin/php bin/worker.php --max-events=50
/opt/plesk/php/8.4/bin/php bin/worker.php --status
```

Den ersten Befehl anschließend als geplante Plesk-Aufgabe einmal pro Minute einrichten. Den tatsächlichen PHP-Pfad des Hosts verwenden. Die bestehende .env wird geladen; Zugangsdaten gehören nicht in die Kommandozeile. APP_MAINTENANCE muss 0 sein. max-events ist 1 bis 50, Standard 50. --status verändert keine Zustellungen und zeigt nur bereits entdeckte Zustände aktiver registrierter Empfänger. Die CLI beendet sich mit 0 bei regulärem oder bereits belegtem Lauf, 2 bei aufgetretenen Handlerfehlern und 1 bei Betriebs-/Konfigurationsfehlern.

## Webtrigger ohne CLI auf dem Hoster

Der Maschinenendpunkt POST https://samla.jrtools.org/tasks/run benötigt keine OIDC-Anmeldung und verwendet keine Browsersitzung. Er ist standardmäßig deaktiviert. Er funktioniert ausschließlich über den konfigurierten HTTPS-Origin der Installation. Wartung, falscher Origin und unzulässige Anfragen starten keine Verarbeitung. Ein neues, ausschließlich dafür vorgesehenes zufälliges 256-Bit-Token authentifiziert den Aufruf. Es gewährt Worker-Ausführung, keine allgemeine Datei- oder Benutzerverwaltung.

Token und Konfiguration lassen sich lokal auf einem eigenen Rechner erzeugen:

```sh
php bin/worker-token.php
```

Ohne PHP-CLI kann tools/worker-token.html heruntergeladen und lokal in einem aktuellen Browser geöffnet werden. Der enthaltene Web-Crypto-Generator erzeugt Token und SHA-256 vollständig lokal, ohne Netzwerkzugriff. Das Werkzeug liegt außerhalb von public und muss dort verbleiben. Das Originaltoken im privaten Scheduler beziehungsweise Passwortmanager verwahren. Ausschließlich die erzeugte Zeile APP_WORKER_HTTP_TOKEN_SHA256=... in Samlas .env übernehmen. Die Beispielkonfiguration enthält kein echtes Token.

Die geplante HTTP-Aufgabe erhält folgende Einstellungen:

| Einstellung | Wert |
|---|---|
| URL | https://samla.jrtools.org/tasks/run, bei anderer Installation deren HTTPS-Origin |
| Methode | POST |
| Authentifizierung bevorzugt | Authorization-Header mit Bearer und dem privaten Token |
| Alternative ohne eigene Header | application/x-www-form-urlencoded mit Feld token und dem privaten Token |
| Cookies / OIDC-Login | Nicht erforderlich |
| URL-Parameter | Keine; insbesondere kein Token in der URL |
| Intervall | Einmal pro Minute |

Ein falscher oder bereits ersetzter Authorization-Header hat Vorrang vor einem eventuell richtigen Formularwert. Token nie als URL-Parameter oder im Browser-JavaScript einer Samla-Seite veröffentlichen. Ein Hoster/Scheduler, der ausschließlich ungeschützte GET-URLs aufrufen kann, benötigt einen POST-fähigen HTTP-Scheduler; GET startet keine Arbeit. Der Webtrigger ist absichtlich kein unauthentifizierter Cron-Link.

Leerer Hash deaktiviert den Endpunkt (404). GET liefert bei aktiviertem und korrekt adressiertem Endpunkt 405, fehlendes/falsches Token 401, URL-Parameter 400, mehr als 4096 Requestbytes 413, Wartung beziehungsweise Betriebsfehler 503. Der normale erfolgreiche Lauf liefert 200 mit begrenzten JSON-Zählern. 409 mit busy=true bedeutet, dass bereits ein Worker arbeitet. Einzelne Handlerfehler stehen im Feld errors; 200 ist keine Zusage, dass alle Ereignisse erfolgreich oder bereits zugestellt sind. queue.failed benennt endgültig fehlgeschlagene Zustellungen. Antworten sind no-store und enthalten weder Token noch Ereignisinhalte.

Rotation: Neues Token/Hash erzeugen, Hash in der tatsächlichen .env beziehungsweise vorrangigen Prozesskonfiguration ersetzen und Scheduler auf das neue Originaltoken umstellen. Der alte Wert wird bei folgenden Anfragen nicht mehr akzeptiert. Leerer Hash deaktiviert nur den Webtrigger; CLI bleibt nutzbar. Es gibt kein Webformular zum Ändern dieser Betreiberkonfiguration. Zustellstatus und Retry sind davon getrennte Verwaltungsfunktionen.

## Begrenzung, Parallelität und Zustellung

Pro Lauf höchstens 50 Zustellversuche. Nach einem kooperativen Budget von 45 Sekunden beginnt kein neuer Handler mehr. Das unterbricht keinen bereits laufenden Provider oder blockierendes I/O. Der Raster-/EXIF-Provider begrenzt Quellgröße, Pixel und geschätzten Speicher, hat aber keinen harten Abbruch einer laufenden nativen Parserfunktion. HTTP-Hoster können ein niedrigeres Requestlimit haben; abgebrochene Claims laufen nach 120 Sekunden aus und können erneut übernommen werden.

Ein separater Datenbanklock verhindert parallele CLI-/Webläufe derselben Installation sowie rekursive Aufrufe. Handler halten nicht den globalen fachlichen Administrationslock. Nur die kurze Ermittlung neuer Zustellungen sperrt diesen Lock, damit niedrigere noch nicht abgeschlossene Ereignissequenzen nicht versehentlich übersprungen werden. Claims und Bestätigungen werden getrennt gespeichert. Ein Handler, der eine SQL-Transaktion offen lässt, wird zurückgerollt und als fehlgeschlagen behandelt.

Je aktivem registriertem Empfänger werden höchstens 100 passende neue Ereignisse pro Lauf vorgemerkt; höchstens 32 Empfänger und 16 exakte Ereignistypen je Empfänger sind zulässig. Die tatsächlichen Zustellversuche bleiben zusammen auf 50 begrenzt. Eine dauerhaft gespeicherte Bedienreihenfolge verhindert, dass kleine Batches spätere Empfänger immer verdrängen. Die Reihenfolge ist kein globales Ereignis-Ordnungsversprechen: fehlgeschlagene ältere Aufgaben warten auf ihren Retry, während andere fällige Aufgaben verarbeitet werden können.

Zustellung ist mindestens einmal. Handler deduplizieren Wirkungen nach Empfängerkennung und Ereigniskennung. Nach einem Absturz zwischen Wirkung und Quittierung kann dasselbe Ereignis erneut ankommen. Der Referenzempfänger speichert Kennungen atomar mit einem eindeutigen Schlüssel; er kopiert weder Dateiinhalte noch Tokens. Fehler werden nach 5, 10, 20 und entsprechend weiter steigenden Sekunden erneut fällig, maximal acht Zustellversuche. Ein abgebrochener achter Versuch wird nach Leaseablauf ebenfalls endgültig failed. Meldungen speichern nur feste Fehlerkategorien. Ein ausdrücklich berechtigtes Retry ist über die Verwaltungsansicht möglich; fehlerhafte Zustellungen werden nicht automatisch unbegrenzt wiederholt.

Die ursprüngliche Spalte outbox_events.status ist für die neue empfängerbezogene Zustellung nicht maßgeblich. Maßgeblich ist outbox_deliveries je aktivem Empfänger. Nicht abonnierte Ereignisse werden nicht fälschlich als an alle möglichen zukünftigen Plugins zugestellt bezeichnet. Alte Daten und deaktivierte Empfänger werden nicht automatisch gelöscht.

## Referenzempfänger und nächste Handler

platform.receipts.storage_v1 empfängt Datei-Upload, Ersetzung, Versionswiederherstellung, endgültiges Purge und Ordneranlage. Er darf auch vorhandene passende Ereignisse in begrenzten Batches übernehmen und legt eine eindeutige technische Quittung ab. Das beweist den Zustellweg und die Idempotenz, erzeugt aber noch keine Vorschaubilder oder extrahierte Metadaten. Es werden keine Objekt-GC, endgültigen Löschungen oder neuen Aufbewahrungsregeln automatisch gestartet.

Weitere Empfänger werden über OutboxSubscription und PluginRegistrar registriert. Ohne ausdrückliches replayExisting startet ein neuer Empfänger erst am aktuellen Wasserstand seines ersten Workeraufrufs. Änderung der Ereignistypen oder Replaypolitik verlangt eine neue Empfängerkennung. Ereignisse sind keine aktuellen Rechte: Benutzeraufträge prüfen ihre Rechte erneut; technische Medienergebnisse müssen an feste Inhaltsversionen gebunden werden. Die Raster-/EXIF-Integration mit privaten, inhaltsgebundenen Ableitungen ist in [media.md](media.md) beschrieben.


## Verwaltungsansicht und Rechte

Die Navigation zeigt „Hintergrundaufgaben“ mit dem gesonderten globalen Recht operations.worker.read. GET /operations/worker zeigt Zähler, Empfänger-/Ereigniskennungen, Typ, Status, Versuche, Retry-/Leasezeit, feste Fehlerkategorie und Revision. Ereignispayloads, Dateiinhalte, Tokens und Actor-Claims werden nicht angezeigt. Die Liste umfasst bis 100 Zustellungen pro Cursorseite, mit Statusfilter. Ihre Zähler umfassen auch derzeit deaktivierte Empfänger, während der Maschinenstatus ausschließlich aktive Empfänger zählt. Noch nicht entdeckte Ereignisse stehen in keiner dieser Zustelllisten.

operations.worker.retry erlaubt zusätzlich zum Leserecht das explizite erneute Vormerken eines endgültig failed gewordenen Eintrags eines aktuell registrierten Empfängers. POST /operations/worker/retry verlangt Session-CSRF und exakt die angezeigte Revision. Es führt den Handler nicht sofort aus, sondern setzt die Zustellung für den nächsten Workeraufruf auf pending zurück. Rechte und Zustand werden vor und nach Pluginvalidatoren unter der Mutationstransaktion erneut geprüft. Reset, Revisionsänderung, Audit und Abschlussereignis sind atomar. Alte Formulare bleiben auch dann ungültig, wenn nach einer früheren manuellen Wiederholung erneut acht Fehler auftraten. Processing-, pending-, zugestellte oder deaktivierte Empfänger können nicht auf diese Weise wiederholt werden.

Neue Rechte werden nicht automatisch bestehenden Administratorrollen hinzugefügt. Der vertrauenswürdige Wartungshelfer kann sie ausdrücklich an eine interne Gruppe vergeben oder entziehen. ACTOR_ID ist die Kennung eines frischen, aktiven Administrators mit den bereits vorhandenen Benutzerlese- und Mitgliedschaftsverwaltungsrechten:

```sh
APP_MAINTENANCE=1 php bin/worker-access.php ACTOR_ID samla.administrators read grant
APP_MAINTENANCE=1 php bin/worker-access.php ACTOR_ID samla.administrators read revoke
APP_MAINTENANCE=1 php bin/worker-access.php ACTOR_ID samla.administrators manage grant
```

read enthält nur das Leserecht, manage Lese- und Retryrecht. Profile sind getrennte Zuweisungen; Entzug von read entfernt kein parallel vorhandenes manage. Die reservierten Rollen samla.worker.read/manage müssen exakt dem Profil entsprechen. Der Helfer ist eine lokale Betreiberfunktion und kein ungeschützter Webendpunkt. Aktuelle Gruppenfrische und lokale Kontosperren gelten für Webansicht und Retry. Gruppen mit globalen Rollen bleiben von den bestehenden Delegationsgrenzen der normalen Mitgliedschafts-/Mappingdienste geschützt. Eine allgemeine Rollenpflege im Browser bleibt ein eigenes offenes Arbeitspaket.


## Technische Aufgaben und Exportpakete

Iteration 35 ergänzt öffentliche WorkerTask-Registrierungen für begrenzte vertrauenswürdige technische Aufgaben. Sie laufen nach Überlappungsschutz vor der Ereignisentdeckung, soweit Laufzeitbudget bleibt. Fehler und offen gelassene SQL-Transaktionen werden isoliert/zurückgerollt und erhöhen errors; processed/delivered bleiben Zustellzähler. Der Exportempfänger verarbeitet Auftragsereignisse idempotent; eine zusätzliche technische Aufgabe bereinigt bis zu 50 abgelaufene/abgebrochene Pakete auch ohne neue Ereignisse. Fachliche Paketfehler sind am eigenen Exportauftrag sichtbar und werden dort gezielt wiederholt; sie sind keine endlos wiederholte technische Zustellstörung. Einrichtung: [portable-export.md](portable-export.md).
