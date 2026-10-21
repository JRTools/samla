# App-Passwörter

Ab Iteration 15 unter „Mein Zugang“, anschließend „App-Passwörter verwalten“.

Ein angemeldeter aktiver Benutzer mit gültigem bestätigtem Gruppenstand kann eigene Gerätezugänge erzeugen. Die Bezeichnung ist frei wählbar, Gültigkeit 1 bis 90 Tage (Vorschlag 30). Höchstens 20 gleichzeitig aktive Zugänge pro Benutzer. Aktive Einträge werden in der auf 100 Einträge begrenzten Übersicht zuerst angezeigt, ältere Historie kann ausgeblendet sein.

Das vollständige Passwort erscheint nur in der unmittelbaren Erstellungsantwort und wird weder in Sitzung noch Audit, Outbox oder Formularhistorie der Anwendung gespeichert. Die Antwort hat Cache-Control no-store und die bestehenden Sicherheitsheader. Nach Verlassen der Seite kann Samla das Geheimnis nicht erneut anzeigen. Ein erneutes Absenden desselben Erstellungsformulars erzeugt ein zusätzliches Passwort; bei Verlust den Zugang widerrufen und neu anlegen.

Die Datenbank enthält eine zufällige Credential-Kennung, einen Hash des unabhängigen 256-Bit-Geheimnisses, Bezeichnung und Zeitpunkte. PASSWORD_DEFAULT verwendet das Passwort-Hashing der Ziel-PHP-Version. Die Credential-Kennung ist kein Geheimnis und ermöglicht die begrenzte Hashabfrage. Das IdP-Passwort wird nicht übernommen oder abgefragt. Benutzername für eine spätere Geräteanmeldung ist die interne Samla-Kennung aus „Mein Zugang“.

Einzelwiderruf wird bei der nächsten Prüfung wirksam. Fremde Konten dürfen Credentials weder auflisten noch widerrufen. Lokale Sperre, Ablauf und fehlende Gruppenfrische verweigern auch ein korrektes App-Passwort. Die Frischerichtlinie APP_GROUP_MAX_AGE_SECONDS bleibt maßgeblich: Bei Ablauf ist eine erneute OIDC-Anmeldung zum Bestätigen des Gruppenstands erforderlich. Es gibt noch keine automatische IdP-Gruppenaktualisierung für Geräte.

Die Authentifizierungsfunktion begrenzt Hashprüfungen auf zehn Versuche je Benutzer und serverseitiger Clientadresse pro Minutenfenster. Erfolgreiche Prüfung setzt den Bucket zurück. Unbekannte oder nicht aktive/frische Benutzer erzeugen keinen Bucket. Adressen werden nur zusammen mit der internen Kennung und einem eindeutigen Trennzeichen gehasht gespeichert; alte Buckets werden begrenzt bereinigt. Reverse-Proxy-Adressen müssen bei der späteren Protokollintegration korrekt über die vertrauenswürdige Serverkonfiguration bestimmt werden. Diese Begrenzung ersetzt noch keinen umfassenden Überlastschutz der ganzen Anwendung.

App-Passwörter verleihen keine Rollen. Jede spätere WebDAV-Anfrage muss zusätzlich für den Benutzer und den konkreten Speicherbereich autorisiert werden. Ein bestehender Browserlogin verwendet weiterhin OIDC und Session/CSRF. App-Passwörter sind keine Browser-Anmeldedaten. Die Oberfläche ist ausschließlich über die verifizierte OIDC-Sitzung erreichbar, nicht über den lokalen Entwicklungsadapter.

Seit Iteration 18 verwendet der öffentliche WebDAV-Lesezugang diese Credentials für Bereichslisten und Downloads. Bei jeder Ressourcenautorisierung wird der Credentialstatus erneut geprüft. Der Schreibadapter mit gemeinsamen Sperren wird anschließend ergänzt. Einrichtung und derzeitige Grenzen: [webdav.md](webdav.md).
