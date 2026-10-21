# Experimentelle Plugin-API 0.2

Die API ist vorläufig. Sie ist kein vollständiger stabiler Erweiterungskatalog.

| Vertrag | Phase und Verhalten |
|---|---|
| Plugin | Explizite Registrierung beim Start. ID eindeutig, API-Major exakt passend. |
| PluginRegistrar | Nur während der Registrierung gültig. Danach geschlossen. |
| Permission | Explizite Aktionskennung, verantwortliches Plugin und zulässige Geltungsbereiche. Keine Sternrechte. |
| AuthorizationProvider | Nur das Pflichtplugin `platform.rbac` darf den positiven Provider registrieren. |
| AccessRestriction | Zusätzliche Prüfung nach positivem RBAC. `Deny` blockiert; `Permit` hebt keine vorherige Ablehnung auf. |
| IdentityReader | Nur bestätigte interne Gruppen und aktuellen Zugangsstatus bereitstellen. Implementierung muss lokale Sperren und vereinbarte Gruppenfrische berücksichtigen. |
| AuthorizationChecker / ResourceDirectory | Vollständige Autorisierung und Ressourcenprüfung für Plugin-Anwendungsdienste. |
| MutationContext / ChangeValidator / MutationRunner | Unveränderlicher Änderungskontext, vorab ablehnende Plugin-Regeln und gemeinsame Transaktionsgrenze. Details in administration.md. |
| SchemaMigration | Unveränderlicher Migrationsbeitrag mit Eigentümer, Kennung, Prüfsumme und einzelnen SQL-Anweisungen. Explizite Zusammenstellung durch den CLI-Einstiegspunkt. |
| AuthorizationRequest | Unveränderlicher Akteur, Aktion, Geltungsbereich und optionale Ressourcenkennung. |

Die Registrierungsreihenfolge ist explizit durch den Aufrufer bestimmt. Zusätzliche Einschränkungen laufen nach absteigender Priorität, dann lexikalisch nach Plugin-ID und schließlich Registrierungsreihenfolge.

Der Runtime-Aufbau wird nur bei vollständigem Erfolg zurückgegeben. Doppelte IDs, doppelte Berechtigungen, falsche Eigentümer und inkompatible API-Versionen brechen ihn ab. Nach dem Aufbau sind Änderungen an der Registrierung gesperrt. Dynamische Deaktivierung ist noch nicht implementiert; der aktive Satz wird beim nächsten Aufbau vollständig neu erstellt.

Autorisierung: aktueller Identitätsstatus, bekannte Aktion und zulässiger Scope, explizites Permit des Pflichtproviders, keine zusätzliche Ablehnung. Fehlender Provider, NotApplicable des Pflichtproviders oder jede Ausnahme führen zu Deny. Grundcodes bleiben intern und werden später nicht ungefiltert über HTTP ausgegeben.

Die zentrale Prüfung darf nicht durch direkten Aufruf des RBAC-Providers ersetzt werden. Der spätere HTTP- und WebDAV-Zugang muss ausschließlich geschützte Anwendungsdienste nutzen. Vollständige Prüfung der Ressourcen- und Bereichszuordnung entsteht erst mit dem Speicherbereichsmodell.

Plugins laufen als vertrauenswürdiger PHP-Code im selben Prozess. Dies ist keine Sandbox. Validatoren für Änderungen werden über PluginRegistrar.validator registriert und bereits vom Rollenzuweisungsdienst verwendet. Abschlussereignisse werden transaktional persistiert; ihre Zustellung und Empfängerverwaltung sind noch offen. UI-Beiträge, allgemeine Provider-Auswahl und Paketlebenszyklus folgen. Migrationsbeiträge werden bereits über SchemaMigration beschrieben; ihre automatische Registrierung über den Plugin-Lebenszyklus ist noch nicht implementiert. Das Referenzplugin belegt Berechtigungsregistrierung, zusätzliche Zugriffsbeschränkung und eine tatsächliche Organisationsvalidierung für Rollenzuweisungen.


Der Benutzerstatusdienst verwendet dieselben unveränderten Verträge. Validatoren können `identity.user.block` beziehungsweise `identity.user.unblock` anhand der internen Benutzerkennung ablehnen. Die Nachbedingung für den verbleibenden Statusadministrator verwendet AuthorizationChecker einschließlich AccessRestriction, ohne RBAC-Interna im Kern. Ereignisformate und Umfangsgrenzen stehen in administration.md.
