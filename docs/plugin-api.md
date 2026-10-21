# Experimentelle Plugin-API 0.7

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

Die zentrale Prüfung darf nicht durch direkten Aufruf des RBAC-Providers ersetzt werden. HTTP- und WebDAV-Zugang verwenden ausschließlich geschützte Anwendungsdienste. Der WebDAV-AuthorizationChecker-Dekorator ergänzt die vollständige Kernprüfung um das weiterhin aktive konkrete App-Passwort. Dateimutationen und Sperren verwenden dieselben MutationContext-/Validator-/Audit-/Outbox-Verträge wie Browseränderungen. Zusätzliche Transportrechte oder eine zweite DAV-Sperrtabelle existieren nicht.

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


Die erste Weboberfläche registriert zusätzlich die globalen Berechtigungen `identity.users.read` und `identity.groups.read`. Die zentralen Prüfungen einschließlich AccessRestriction gelten vor jeder Verzeichnisabfrage. Verwaltungsrechte implizieren diese Leserechte nicht. Schreibformulare verwenden die vorhandenen Anwendungsdienste und damit deren Validatoren, Delegationspolicies, Audit-/Outboxtransaktionen und Nachbedingungen. Die bestehenden öffentlichen API-Verträge bleiben unverändert. Navigationseinträge, eigene Pluginseiten und Zeilenfilter sind noch keine öffentliche UI-Beitrags-API. HTTP- und Sitzungsgrenzen stehen in web.md.


## OIDC-Anmeldung ab Iteration 12

Der Login verwendet nach vollständiger Tokenverifikation den bestehenden MutationRunner-/ChangeValidator-Vertrag. Operation identity.login, globaler Scope und Ziel user enthalten ausschließlich die interne Benutzerkennung. Validator-Veto und lokale Sperren verhindern Provisionierung, Namens-/Gruppenänderung, Audit und Outbox gemeinsam. Authentifizierung ersetzt keine Rollenvergabe. Verwaltungszugriffe verwenden weiterhin die vollständige zentrale Autorisierung.

Der Offline-Installer verwendet identity.bootstrap für die begrenzte Erstadministration. Er ist kein öffentlicher Webendpunkt und verlangt vertrauenswürdigen CLI-/Wartungszugang. Die neuen transaktionalen Gruppen-/Rollenwriter sind interne Persistenzzusammensetzung, keine frei autorisierten Rollen-/Gruppenfunktionen. Der API-Vertrag bleibt experimentell 0.5.


## Dateioperationen ab Iteration 13

Dateilisten und Downloads verwenden den zentralen AuthorizationChecker mit files.list beziehungsweise files.read im exakten Space-Scope. Autorisierungsprovider und AccessRestriction sind auch für diese Lesezugriffe wirksam. Kein globales Administrationsrecht impliziert Dateizugriff.

Neue Ordner und Uploads verwenden MutationRunner/ChangeValidator mit storage.folder.create beziehungsweise storage.file.upload, Scope Space und Ziel entry sowie optional parent. Der Kontext und Audit/Outbox enthalten interne Kennungen, keine Inhalte, freien Namen oder Hostpfade. Validatoren können die Veröffentlichung der Metadaten verhindern. Autorisierung und Zielordner werden nach Validatoren erneut geprüft. Der bestehende API-Vertrag 0.5 bleibt unverändert; eine inhaltliche Uploadprüfung über einen typisierten Vorschlag wird separat ergänzt.

Der vertrauenswürdige Offline-Installer verwendet storage.space.create, Space-Scope sowie Ziele group und role. Er benötigt Wartung und einen aktiven frischen Administrator, vergibt ausschließlich die ausdrücklich angeforderte Gruppenrolle im neu angelegten Bereich und ist kein allgemeiner Rollen-HTTP-Endpunkt.


## StorageChange ab API 0.6

MutationContext erhält das optionale readonly storageChange. Für storage.folder.create, storage.file.upload, storage.entry.rename, storage.entry.move, storage.file.copy, storage.entry.trash und storage.entry.restore ist es im Space-Scope mit Ziel entry zwingend. Andere Operationen dürfen keinen StorageChange einschleusen. StorageChange enthält optional den vorgeschlagenen logischen name, den internen parent und bei neuen Inhalten gepaarte size/sha256. UTF-8-/Namensregeln, Elternkennung und Hashform werden im öffentlichen Vertrag geprüft. Plugins können beispielsweise einen Namen oder eine Inhaltssignatur ablehnen. Es ist noch kein Raw-Content-/Virenscanner-Vertrag. Der Vorschlag wird nicht in Audit/Outbox serialisiert.

Kopieren/Ändern verwendet zusätzlich Ziel result; Metadatenoperationen prüfen nach Validatoren noch einmal Identität, sämtliche benötigten Rechte, aktuelle Name-/Elternerwartung, Papierkorbstatus, Elternkette und gegebenenfalls Zyklen. Eine Dateikopie referenziert unveränderlichen vorhandenen Inhalt, aber benötigt erneut logische Quote.

Seit Iteration 21 enthält ein atomar überschreibendes storage.entry.move/storage.file.copy zusätzlich targets.replaced als stabile Kennung des bisherigen Ziels. StorageChange beschreibt den neuen Namen/Elternort; vor und nach Validatoren werden auch Art, erwartete Revision, Ort, Name und Sperren dieses Ziels sowie die zusätzlichen update/delete-Rechte geprüft. Die alte Referenz bleibt im Papierkorb, alle Teiländerungen und das eine abgeschlossene Fachereignis sind gemeinsam transaktional. Ein storage.entry.trash entfernt nach erfolgreicher Nachweisprüfung auch die Leases des betroffenen Teilbaums innerhalb derselben Transaktion. Plugins, die Lösch-/Sperrpolitik ergänzen, können diesen gesamten Vorschlag vorab ablehnen. Einzelne Tokens bleiben aus den öffentlichen Ereignissen ausgeschlossen.

Der Offline-Installer storage.access.grant verwendet Space-Scope mit group/role. Die Offline-Bereinigung storage.orphan.purge verwendet Global-Scope, Systemakteur system.storage und Ziel object. Der Löschguard prüft alte nicht referenzierte reguläre Objekte unter dem Administrationslock erneut; Validatoren können jeden Schritt verhindern. Physische Löschungen haben keine gemeinsame Transaktion mit SQL und werden ausschließlich für nicht referenzierte Objekte eingesetzt.


## Eigene App-Passwörter ab Iteration 15

identity.app_password.create und identity.app_password.revoke verwenden Global-Scope und Ziel credential. Der Akteur stammt ausschließlich aus der verifizierten OIDC-Websitzung, nie aus dem Formular. Die eigene Kontoberechtigung verlangt eine aktive frische Identität, Änderungen an fremden Credentials werden abgewiesen. Es erfolgt keine automatische Dateirechtevergabe. Der bestehende MutationRunner ermöglicht Veto und erneute Identitäts-/Eigentümerprüfung sowie atomare Credentials/Audit/Outbox. Geheimnis, Hash und Label werden nicht in Audit/Outbox übernommen.

Die Authentifizierung eines App-Passworts liefert nur ein positives Credential-Ergebnis; die spätere Protokollanbindung muss jede konkrete Ressource erneut über die zentrale Autorisierung prüfen. Ein gültiges App-Passwort ersetzt keine Bereichsrolle. API 0.6 bleibt unverändert.


## Versionierte Inhalte ab Iteration 16

MutationContext akzeptiert storage.file.replace und storage.version.restore mit verpflichtendem StorageChange im exakten Space-Scope und Zielen entry/version. Vorschlag: unveränderlicher logischer Name, Elternkennung, neue Größe und SHA-256. Das Ziel version ist die Kennung der neu archivierten bisherigen Revision. Audit/Outbox behalten ausschließlich Kennungen. Die Revisionserwartung, aktuelle Rechte, Eltern-/Papierkorbsichtbarkeit und Metadaten werden unter MutationRunner vor und nach Validatoren geprüft. Quotenberechnung, Archivierung, Referenzwechsel und Audit/Outbox sind atomar.

Das zusätzliche atomare Bereichsrecht files.versions.read erlaubt keine aktuelle Dateioperation und wird nicht automatisch vergeben. Historienanzeige/-download verlangen außerdem files.read und files.list. Wiederherstellung benötigt zusätzlich files.update. Plugin-Veto lässt aktuellen Inhalt und Historie unverändert; ein bereits physisch publiziertes unreferenziertes Objekt kann bis zur kontrollierten Bereinigung erhalten bleiben.


## Ressourcensperren ab API 0.7

StorageLockChange(recursive, seconds) ist ein unveränderlicher Vorschlag für storage.lock.acquire/renew/release. MutationContext verlangt Space-Scope, Ziel lock und diesen Vorschlag genau für diese Operationen. Acquire/Renew verlangen 1 bis 3600 Sekunden, Release genau 0. Ein direkt gesperrter Eintrag kann zusätzlich als entry angegeben sein; leere Eintragskennung bedeutet Bereichswurzel und wird nicht als leeres Auditziel serialisiert. Fremde Operationen dürfen keinen Lock-Vorschlag verwenden.

Token und frei übermitteltes Owner-Label werden weder im Vorschlag noch in Audit/Outbox gespeichert. Der persistente Lock-Datensatz enthält sie für die Protokollintegration. Ein Token ist ein Nachweis zur eigenen Sperre, ersetzt aber keine Ressourcenberechtigung. Andere Benutzer können auch mit bekanntem Token die Sperre nicht übergehen.

Alle geschützten Speichermutationen prüfen aktive Sperren vor und nach Validatoren unter dem gemeinsamen Administrationslock. Dateiinhalt, Elternmitgliedschaft und betroffene Teilbäume werden getrennt berücksichtigt. Ein Plugin-Veto rollt Sperranlage/-verlängerung/-freigabe samt Audit/Outbox zurück. Ressourcensperren geben keine Erlaubnis durch generische Hooks. Der API-Major bleibt experimentell 0.
