# Experimentelle Plugin-API 0.1

Die API ist vorläufig. Sie ist kein vollständiger stabiler Erweiterungskatalog.

| Vertrag | Phase und Verhalten |
|---|---|
| Plugin | Explizite Registrierung beim Start. ID eindeutig, API-Major exakt passend. |
| PluginRegistrar | Nur während der Registrierung gültig. Danach geschlossen. |
| Permission | Explizite Aktionskennung, verantwortliches Plugin und zulässige Geltungsbereiche. Keine Sternrechte. |
| AuthorizationProvider | Nur das Pflichtplugin `platform.rbac` darf den positiven Provider registrieren. |
| AccessRestriction | Zusätzliche Prüfung nach positivem RBAC. `Deny` blockiert; `Permit` hebt keine vorherige Ablehnung auf. |
| IdentityReader | Nur bestätigte interne Gruppen und aktuellen Zugangsstatus bereitstellen. Implementierung muss lokale Sperren und vereinbarte Gruppenfrische berücksichtigen. |
| SchemaMigration | Unveränderlicher Migrationsbeitrag mit Eigentümer, Kennung, Prüfsumme und einzelnen SQL-Anweisungen. Explizite Zusammenstellung durch den CLI-Einstiegspunkt. |
| AuthorizationRequest | Unveränderlicher Akteur, Aktion, Geltungsbereich und optionale Ressourcenkennung. |

Die Registrierungsreihenfolge ist explizit durch den Aufrufer bestimmt. Zusätzliche Einschränkungen laufen nach absteigender Priorität, dann lexikalisch nach Plugin-ID und schließlich Registrierungsreihenfolge.

Der Runtime-Aufbau wird nur bei vollständigem Erfolg zurückgegeben. Doppelte IDs, doppelte Berechtigungen, falsche Eigentümer und inkompatible API-Versionen brechen ihn ab. Nach dem Aufbau sind Änderungen an der Registrierung gesperrt. Dynamische Deaktivierung ist noch nicht implementiert; der aktive Satz wird beim nächsten Aufbau vollständig neu erstellt.

Autorisierung: aktueller Identitätsstatus, bekannte Aktion und zulässiger Scope, explizites Permit des Pflichtproviders, keine zusätzliche Ablehnung. Fehlender Provider, NotApplicable des Pflichtproviders oder jede Ausnahme führen zu Deny. Grundcodes bleiben intern und werden später nicht ungefiltert über HTTP ausgegeben.

Die zentrale Prüfung darf nicht durch direkten Aufruf des RBAC-Providers ersetzt werden. Der spätere HTTP- und WebDAV-Zugang muss ausschließlich geschützte Anwendungsdienste nutzen. Vollständige Prüfung der Ressourcen- und Bereichszuordnung entsteht erst mit dem Speicherbereichsmodell.

Plugins laufen als vertrauenswürdiger PHP-Code im selben Prozess. Dies ist keine Sandbox. Ereignisse, Validatoren für Änderungen, UI-Beiträge, Provider-Auswahl und Paketlebenszyklus sind noch offen. Migrationsbeiträge werden bereits über SchemaMigration beschrieben; ihre automatische Registrierung über den Plugin-Lebenszyklus ist noch nicht implementiert. Das bisherige Referenzplugin beweist nur Berechtigungsregistrierung und zusätzliche Einschränkungen.
