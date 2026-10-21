# Experimentelle Plugin-API 0.5

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
| GroupMappingChange / GroupMappingPolicy | Exakter Mappingvorschlag und verpflichtende Delegationsprüfung für bestehende und zukünftige Gruppenmitglieder. |
| GroupDetailsChange / MutationConflict | Typisierter, unveränderlicher Namensvorschlag und sichtbarer Konflikt bei abweichendem Erwartungszustand. |
| GroupMembershipPolicy | Verpflichtende zusätzliche Delegationsprüfung manueller Mitgliedschaften. RBAC prüft sämtliche Rollenzuweisungen der Zielgruppe. |
| SchemaMigration | Unveränderlicher Migrationsbeitrag mit Eigentümer, Kennung, Prüfsumme und einzelnen SQL-Anweisungen. Explizite Zusammenstellung durch den CLI-Einstiegspunkt. |
| AuthorizationRequest | Unveränderlicher Akteur, Aktion, Geltungsbereich und optionale Ressourcenkennung. |

Die Registrierungsreihenfolge ist explizit durch den Aufrufer bestimmt. Zusätzliche Einschränkungen laufen nach absteigender Priorität, dann lexikalisch nach Plugin-ID und schließlich Registrierungsreihenfolge.

Der Runtime-Aufbau wird nur bei vollständigem Erfolg zurückgegeben. Doppelte IDs, doppelte Berechtigungen, falsche Eigentümer und inkompatible API-Versionen brechen ihn ab. Nach dem Aufbau sind Änderungen an der Registrierung gesperrt. Dynamische Deaktivierung ist noch nicht implementiert; der aktive Satz wird beim nächsten Aufbau vollständig neu erstellt.

Autorisierung: aktueller Identitätsstatus, bekannte Aktion und zulässiger Scope, explizites Permit des Pflichtproviders, keine zusätzliche Ablehnung. Fehlender Provider, NotApplicable des Pflichtproviders oder jede Ausnahme führen zu Deny. Grundcodes bleiben intern und werden später nicht ungefiltert über HTTP ausgegeben.

Die zentrale Prüfung darf nicht durch direkten Aufruf des RBAC-Providers ersetzt werden. Der spätere HTTP- und WebDAV-Zugang muss ausschließlich geschützte Anwendungsdienste nutzen. Vollständige Prüfung der Ressourcen- und Bereichszuordnung entsteht erst mit dem Speicherbereichsmodell.

Plugins laufen als vertrauenswürdiger PHP-Code im selben Prozess. Dies ist keine Sandbox. Validatoren für Änderungen werden über PluginRegistrar.validator registriert und bereits vom Rollenzuweisungsdienst verwendet. Abschlussereignisse werden transaktional persistiert; ihre Zustellung und Empfängerverwaltung sind noch offen. UI-Beiträge, allgemeine Provider-Auswahl und Paketlebenszyklus folgen. Migrationsbeiträge werden bereits über SchemaMigration beschrieben; ihre automatische Registrierung über den Plugin-Lebenszyklus ist noch nicht implementiert. Das Referenzplugin belegt Berechtigungsregistrierung, zusätzliche Zugriffsbeschränkung und eine tatsächliche Organisationsvalidierung für Rollenzuweisungen.


Der Benutzerstatusdienst verwendet dieselben unveränderten Verträge. Validatoren können `identity.user.block` beziehungsweise `identity.user.unblock` anhand der internen Benutzerkennung ablehnen. Die Nachbedingung für den verbleibenden Statusadministrator verwendet AuthorizationChecker einschließlich AccessRestriction, ohne RBAC-Interna im Kern. Ereignisformate und Umfangsgrenzen stehen in administration.md.


GroupMembershipPolicy.allows(actorId, userId, groupId, grant) ergänzt die vollständige Kern-Autorisierung für `identity.groups.memberships.manage`. Der vertrauenswürdige Aufbau übergibt RbacGroupMembershipPolicy, dieselbe Identitätsquelle, denselben vollständigen AuthorizationChecker sowie dieselbe Registry und Ressourcensicht. Die Policy ist ein verpflichtendes Konstruktorargument des GroupMembershipService. Es gibt keinen erlaubenden Standard oder Rückgriff auf eine fehlende Policy. False oder jede Ausnahme bricht den Vorgang ab. Der Kern kennt die Policy ausschließlich über diesen öffentlichen Vertrag; das RBAC-Plugin importiert keine Kernklassen.

Validatoren erhalten `identity.membership.grant` beziehungsweise `identity.membership.revoke`, Scope Global und die Zielkennungen `user` und `group`. Eine Änderung betrifft alle Rollen der Gruppe und ist deshalb keinem einzelnen Speicherbereich zugeordnet. Die Policy prüft die betroffenen Bereiche jeweils gesondert. Die Abschlussereignisse verwenden dieselben Operationen mit `.completed`. Externe Mitgliedschaften werden durch diese Anwendungsfälle nicht verändert. Beide vollständigen Prüfungen, die Nachbedingungen und der Writer laufen unter derselben Administrationssperre. API-Major bleibt experimentell 0; es werden keine neuen Laufzeitabhängigkeiten eingeführt.


Seit API 0.4 hat MutationContext das optionale Feld `groupDetails: ?GroupDetailsChange`. Es ist für `identity.group.create` und `identity.group.rename` erforderlich und für andere Operationen unzulässig. Scope ist Global und das einzige Ziel ist `group`. GroupDetailsChange enthält den gewünschten Namen und optional `expectedName`: null bedeutet Anlage, ein Name bedeutet Umbenennung. Der Kontext prüft diese Zuordnung. Validatoren können Namen vor dem Write prüfen und ablehnen. Die Felder sind readonly. Audit und Outbox serialisieren ausschließlich Kennungen und übernehmen den Namensvorschlag nicht.

Namen sind gültiges UTF-8 mit 1 bis 255 Unicode-Codepoints, ohne Steuerzeichen oder umgebenden Leerraum. Sie werden nicht automatisch getrimmt oder normalisiert. Derselbe Name darf mehrere unterschiedliche Gruppen bezeichnen. Sie sind reine Anzeigetexte; künftige HTML-Adapter müssen sie korrekt escapen. MutationConflict signalisiert eine abweichende gespeicherte Erwartung oder eine bereits belegte Kennung. Ein späterer Adapter muss den Konflikt sichtbar machen und vor erneutem Schreiben den aktuellen Zustand laden. Der Konflikt erzeugt kein erfolgreiches Audit oder Abschlussereignis.


API 0.5 ergänzt `GroupMappingChange(issuer, externalId, groupId, grant)` und das verpflichtende `GroupMappingPolicy.allows(actorId, change)`. RbacGroupMappingPolicy nutzt dieselbe vollständige fachliche Gruppendelegation wie RbacGroupMembershipPolicy. Es werden alle Rollenzuweisungen der Zielgruppe geprüft, auch wenn noch keine passenden Benutzer existieren. Der Kern verwendet nur den öffentlichen Policy-Vertrag; es gibt keinen erlaubenden Standard bei fehlender Policy.

MutationContext hat zusätzlich das optionale Feld `groupMapping`. Es ist für `identity.mapping.grant` und `identity.mapping.revoke` verpflichtend und für andere Operationen verboten. Scope ist Global. Die exakten Ziele sind `group` und `mapping`; `mapping` ist ein stabiler SHA-256-Fingerabdruck des längengerahmten Tupels aus Issuer, externer Gruppenkennung und interner Gruppenkennung. Grant und Revoke derselben Kante haben denselben Fingerabdruck. Kontext, Zielkennungen und Operation müssen zum Proposal passen. Die Zuordnung ist unabhängig vom GroupDetailsChange-Vertrag.

Issuer und externe Gruppenkennung werden als unveränderte, nicht leere Bytefolgen mit höchstens 1024 beziehungsweise 256 Bytes behandelt. Die interne Kennung folgt dem bestehenden ASCII-Vertrag. Das sind Strukturgrenzen und keine Prüfung eines OIDC-Issuers oder Tokens. Eine zukünftige OIDC-Integration muss weiterhin ihren konfigurierten Issuer und alle Tokenbedingungen prüfen. Validatoren erhalten das vollständige unveränderliche Mapping, Audit und Outbox übernehmen nur interne Gruppe und Fingerabdruck. Opaque Bytefolgen müssen dadurch nicht als JSON-Freitext serialisiert werden.

Bei positiver Änderung werden vorhandene Quellenprojektionen für diesen Issuer und diese externe Kennung aus den gespeicherten bestätigten Gruppenständen abgeglichen. Abschlussereignisse sind `identity.mapping.grant.completed` und `identity.mapping.revoke.completed`. Quellenkorrekturen bei unveränderter Mappingkante zählen ebenfalls als Änderung. Details zu Atomizität und Versionsverhalten stehen in administration.md.
