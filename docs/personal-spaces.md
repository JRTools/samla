# Automatische persönliche Speicherbereiche

Der Betreiber hat die automatische Bereitstellung bei der ersten Anmeldung gewählt. Seit Iteration 39 legt eine erfolgreiche OIDC-Anmeldung einmalig einen persönlichen Bereich für das stabile interne Samla-Konto an. Für bereits vorhandene Konten erfolgt dies bei der nächsten erfolgreichen Anmeldung nach dem Update. Bestehende Sitzungen oder WebDAV-App-Passwörter legen selbst keinen neuen Bereich an. Der normale Kontolink öffnet die persönlichen und gemeinsamen Dateien.

## Einrichtung

Nach Codeupdate `php bin/migrate.php` im dokumentierten Wartungsablauf ausführen: neue Migration platform.storage:0009_personal_spaces. Keine neue Laufzeitkomponente. .env beziehungsweise example.env enthalten APP_PERSONAL_SPACE_ENABLED=1 und APP_PERSONAL_SPACE_QUOTA_BYTES=10737418240. **10 GiB ist ein vorläufiger konfigurierbarer Startwert, keine vom Betreiber bestätigte fachliche Quote.** Eine andere positive Quote bis 1 TiB kann ausdrücklich gesetzt werden.

Die Voreinstellung gilt nur für neue Bereiche. Vorhandene Namen und Quoten bleiben bei späteren Anmeldungen und geänderter Standardkonfiguration erhalten. Unter **Bereiche verwalten** kann die Administration Name und Quote ändern; die Quote darf nicht unter dem Verbrauch liegen. Versionen und Papierkorb zählen weiter mit. Logische Quoten reservieren keinen physischen Hosting-Speicher; die geplanten 100 GB Gesamtbestand werden nicht automatisch als globale Grenze durchgesetzt.

APP_PERSONAL_SPACE_ENABLED=0 deaktiviert ausschließlich neue automatische Anlagen. Vorhandene Bereiche, Daten, Eigentümerrechte und explizite Freigaben bleiben erhalten. Die Konfiguration selbst ist ein kontrollierter Betreiberablauf; es gibt keinen ungeschützten Provisionierungsendpunkt und keine Auswahl eines fremden Eigentümers im Loginformular.

## Eigentümerrechte und gemeinsame Bereiche

Persönliche Bereiche sind in Dateiliste und Verwaltung gekennzeichnet, in der Verwaltung wird der Eigentümername angezeigt. Die Kennung wird aus dem internen Konto abgeleitet und ändert sich bei Änderung des Anzeigenamens nicht. Bestehende gemeinsame Bereiche und deren Profile bleiben unverändert; neue gemeinsame Bereiche werden weiterhin im Verwaltungs-UI angelegt und benötigen ausdrückliche Gruppenrechte.

Die persönliche Erstanlage erstellt eine verwaltete Gruppe und eine unveränderlich definierte eigene RBAC-Rolle samla.personal.owner.v1 im konkreten Bereich. Der Eigentümer erhält die vorhandenen Standardrechte auf Originale, Upload/Änderung/Papierkorb/Wiederherstellung/Purge/Historie, Medien einschließlich separat geschützter Standardmetadaten, manuelle Katalogpflege und Originalexport. Die Rolle enthält keine globale Administration oder Delegation und keine pauschale Freigabe unbekannter Pluginrechte. Andere Konten und globale Administratoren erhalten dadurch **keinen impliziten Inhaltszugriff**. Explizite zusätzliche Gruppenprofile im Bereich bleiben wie bei gemeinsamen Bereichen möglich und werden über die bestehende Verwaltung protokolliert; persönlich bedeutet keine Verschlüsselung gegenüber einem Serverbetreiber.

Die Systemgruppe bleibt außerhalb gewöhnlicher manueller Mitgliedschaften, IdP-Mappings, Gruppenumbenennung und Rechteprofilbearbeitung. Die Auswahloberflächen blenden sie bei Mapping-/Profilzuweisungen aus; das normale Gruppenverzeichnis zeigt sie ohne Bearbeitungsformular. Reservierte Eigentümerrollen/-gruppen können nicht über den öffentlichen Delegationsdienst auf andere Ziele übertragen werden. Der Namespace samla.personal. ist reserviert. Eine vorher bestehende kollidierende Kennung oder abweichend definierte Eigentümerrolle führt zu einem Fehler und wird nicht still übernommen oder überschrieben.

## Atomizität und Lebenszyklus

Die Anlage erfolgt erst nach bestätigter OIDC-Tokenprüfung und Konto-/Gruppenfrische, vor Aufbau der neuen angemeldeten Sitzung. Bereich, Gruppe, lokale Eigentümermitgliedschaft, Rollenbindung, Eigentümerdatensatz, Audit und Outbox werden unter dem gemeinsamen Administrationslock transaktional angelegt. Fremde Besitzer können nicht über Formularwerte gewählt werden. Wiederholung bleibt idempotent; parallele Erstversuche werden serialisiert und durch eindeutige Eigentümer-/Bereichskennungen abgesichert. Fehler hinterlassen keine teilweise eingerichtete Eigentümerschaft. Nach einem Fehler kann eine neue Anmeldung erneut versucht werden.

Der öffentliche PersonalSpaceChange bindet Eigentümer, abgeleitete Kennungen, initialen Namen und Quote an die genaue Selbst-Provisionierung. Validatoren dürfen vetoieren; bestehende Administrationszugänge bleiben über die Kontinuitätsprüfung geschützt. PersonalSpaceAccess ist der verpflichtende öffentliche Transaktionsvertrag zur Rechteanlage; der Kern importiert keine RBAC-Interna und schreibt keine RBAC-Tabellen. Audit und storage.space.personal.provision.completed enthalten interne Kennungen, keine Namen oder OIDC-Rohclaims. Der Gruppenstand wird durch die lokale Mitgliedschaft nicht künstlich aufgefrischt.

Kontosperre und abgelaufene Gruppenfrische sperren auch den persönlichen Inhalt. Persönliche Bereiche werden derzeit auch leer nicht über die allgemeine Bereichslöschung gelöscht. Fremdschlüssel verhindern das versehentliche Entfernen ihrer Eigentümer-/Gruppengrundlage. Es gibt keine automatische Datenlöschung bei Austritt, Kontosperre oder Abschalten der Bereitstellung und keine automatische Eigentumsübertragung. Dafür sind weiterhin konkrete Betreiberregeln erforderlich. Eine spätere Lösch-/Übergabefunktion muss diese Regeln ausdrücklich und auditierbar umsetzen.

Automatisierte PHP-/Datenbank- und HTTP-/HTTPS-Prüfungen ersetzen keine produktive IdP-/Hosting-, visuelle oder native Geräteabnahme. Details der aktuellen Prüfung stehen in verification.md.
