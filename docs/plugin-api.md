# Experimentelle Plugin-API 0.9

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

## Gemeinsame Datei-/Sperranlage ab API 0.8

storage.file.create_locked verlangt gleichzeitig StorageChange für den neuen leeren Inhalt und StorageLockChange mit positiver Laufzeit sowie targets.entry und targets.lock. Beide Vorschläge fehlen niemals teilweise. files.create und files.update gelten gemeinsam im exakten Bereich. Die Guards prüfen Namen, Ziel, Eltern, Kapazität, Quote und überlappende Sperren vor und nach Validatoren. Neue Dateireferenz, Lease, Audit und Outbox sind ein SQL-Vorgang. Token und Owner-XML bleiben aus dem Vorschlag und den Ereignissen ausgeschlossen. Ein Veto kann damit beide Teilanlagen gemeinsam verhindern; es gibt keine vorab bereits sichtbare leere Datei.

## Endgültige Bereinigung ab API 0.9

storage.entry.purge verlangt StorageChange für den bisherigen Wurzeleintrag und StoragePurgeChange mit einer kanonisch nach Zeichenfolgen sortierten Map entryRevisions. Sie enthält genau die geplanten festen Eintragskennungen mit positiven Revisionen, höchstens 10000. Der Konstruktor löst auch Referenzen auf übergebene Skalarvariablen auf. MutationContext verlangt den Wurzeleintrag in dieser Map; andere Operationen dürfen keinen Purge-Vorschlag verwenden. files.list und das neue explizite Bereichsrecht files.purge werden vor und nach Validatoren geprüft. Aktive Wurzeln sind nicht zulässig. Der Guard vergleicht den gesamten aktuellen Teilbaum erneut mit dem unveränderlichen Vorschlag.

Ein erfolgreiches Purge erzeugt ein Audit und vollständige Abschlussereignisse in Chunks bis 500 Eintragskennungen. Alle Chunks, Katalog-/Historienlöschung und Leasebereinigung committen gemeinsam. Der Ereignistyp ist storage.entry.purge.completed. payload.id ist die eindeutige Ereigniskennung, batch_id die gemeinsame Audit-/Vorgangskennung, chunk_index beginnt bei 0, chunk_count nennt die Anzahl und purged_entries enthält die jeweilige Kennungsliste. Empfänger deduplizieren nach Ereigniskennung, nicht nach batch_id, und dürfen weitere Chunks derselben Batch nicht verwerfen. Auch ein Fehler im letzten Chunk rollt frühere Chunks und alle Löschungen zurück. Keine Freitextnamen, Objektbytes oder Tokens im Payload. Persistiert bedeutet weiterhin nicht zugestellt.

Physische Objekte werden durch diesen Vorgang nicht entfernt. Die separat berechtigte bestehende GC prüft alle verbleibenden aktuellen/historischen Referenzen und ihre Objektaltergrenze. Ein Plugin darf daher weder aus einem Purge-Ereignis allein auf sofortige physische Vernichtung schließen noch geteilte Inhaltsobjekte ungeprüft entfernen.

## Textbearbeitung

Der eingebaute Texteditor nutzt unverändert storage.file.replace mit StorageChange, ChangeValidator sowie Autorisierungs-/Einschränkungsverträgen und storage.file.replace.completed. Name, Elternkennung, neue Größe und SHA-256 stehen im Vorschlag; Quelltext wird nicht in Audit oder Outbox übernommen. Die Guards prüfen list/read/update sowie Revision und Sperren vor und nach Validatoren. Ein Validatorveto verwirft die neue Referenz und Historienänderung. Der zusätzliche Leseguard ist für Editoraufrufe verbindlich; gewöhnliches Ersetzen eines Uploads verlangt weiterhin kein Leserecht. Es gibt noch keine öffentliche Auswahl zusätzlicher Editor-/Viewerprovider, Syntaxhervorhebung oder Markdown-Renderer. Der experimentelle API-Major bleibt 0.

## Namenssuche

Die eingebaute bereichsweite Namenssuche verwendet files.list und die vorhandenen AuthorizationProvider-/AccessRestriction-Verträge. Ein zusätzliches Restriktionsplugin kann sie trotz positiver RBAC-Zuweisung verweigern. Die Funktion schreibt nichts und erzeugt keine Abschlussereignisse. Treffer enthalten ausschließlich sichtbare logische Verzeichnismetadaten; Dateiinhalte werden über den bestehenden separaten Lesepfad geschützt. Zusätzliche Suchprovider und pluginregistrierte Feldfilter sind noch nicht veröffentlicht.

## Outbox-Empfänger ab experimenteller API 0.10

OutboxSubscription(id,eventTypes,handler,replayExisting=false) und OutboxHandler.handle(OutboxEvent) sind öffentliche Verträge. PluginRegistrar.outboxSubscriber registriert bis 32 eindeutige Empfänger, deren Kennung mit der eigenen Pluginkennung plus Punkt beginnt. Je Empfänger maximal 16 eindeutige exakte Ereignistypen. Nach Registrierungsschluss sind Änderungen ausgeschlossen. Doppelte, fremde oder ungültige Empfänger verhindern die atomare Runtimeerstellung. Der experimentelle API-Major bleibt 0; die Registrar-Schnittstelle ist erweitert.

OutboxEvent enthält feste Kennung, Typ, Zeit und eine begrenzte JSON-Wertkopie des Payloads ohne Referenzen des Aufrufers. Der Worker validiert auch die Übereinstimmung mit payload.id. Das Ereignis ist kein aktueller Berechtigungsnachweis. Das Plugin implementiert idempotente Wirkungen nach Empfänger-/Ereigniskennung und schließt eigene Transaktionen vor der Rückgabe. Offene Transaktionen werden zurückgerollt und als Fehler behandelt. Externe Wirkungen benötigen ihre eigene Deduplication. Ein wirksamer Effekt vor fehlgeschlagener Quittierung kann wiederholt zugestellt werden.

Standardmäßig startet ein neuer Empfänger am Wasserstand seines ersten Workeraufrufs. Replay vorhandener Ereignisse ist explizit und begrenzt. Die persistierte Signatur fixiert Ereignistypen und Replaypolitik; eine Änderung erfordert eine neue Empfängerkennung. Deaktivierte Empfänger werden nicht aufgerufen. Der aktuelle Referenzempfänger konsumiert ausgewählte Storage-Abschlussereignisse und speichert ausschließlich eindeutige technische Quittungen. [Zustellablauf und Grenzen](background-worker.md).

## Manuelles Zustell-Retry ab experimenteller API 0.11

OutboxRetryChange(subscriberId,eventId,expectedRevision) ist ein unveränderlicher Vorschlag. MutationContext verlangt ihn genau für operations.delivery.retry im Global-Scope, mit targets.event gleich eventId und targets.subscriber gleich SHA-256 der vollständigen Empfängerkennung. Das hält die identitätsbasierte Audit-Zielgrenze ein, ohne Empfängerkennungen auf 64 Zeichen zu beschneiden. Fremde Operationen dürfen keinen solchen Vorschlag tragen.

Der Guard verlangt operations.worker.read und operations.worker.retry sowie einen aktuell registrierten Empfänger und den unveränderten failed-Zustand. Outbox-Zustellungen erhalten dafür eine dauerhafte Revision, die auch Claim, Quittierung und Lease-Endstatus erhöhen. Reset, Audit und operations.delivery.retry.completed werden gemeinsam geschrieben. Vor und nach ChangeValidator gilt dieselbe aktuelle Rechte-/Zustandsprüfung. Ein Veto oder Auditfehler lässt die Zustellung unverändert. Retry führt keine Handler direkt aus und verarbeitet keine aktiven Claims. Bestehende Rollen bekommen die neuen globalen Rechte nicht automatisch.

## Medienbasis und bereichsweiser Neuaufbau

MediaField/MediaProvider werden über PluginRegistrar registriert. Eigentümernamensraum, registrierte Bereichsrechte, Sensitivität, begrenzte Feld-/Providerzahlen, Prioritäten und nach Boot geschlossene Registrierung sind verbindlich. MediaSource liefert begrenzte immutable Bytes samt SHA-256 und frischen privaten Streams. MediaValue unterscheidet Rohwert, normalisierten Wert, Quelle und Auslesestatus. MediaPreview erlaubt ausschließlich begrenzte PNG-Ableitungen ohne EXIF-/Text-/unbekannte Chunks. Der Quellstand und die Signatur der registrierten Felder/Provider binden den privaten Cache. Details: [media.md](media.md).

MediaBatchRefreshChange enthält 1 bis 100 kanonisch sortierte Eintragskennungen mit positiven erwarteten Revisionen. Übergebene PHP-Referenzen werden kopiert. MutationContext verlangt den Vorschlag genau für media.batch.refresh im Space-Scope und bindet targets.batch an seinen SHA-256. Validatoren können den vollständigen Vorschlag prüfen und ablehnen. Rechte und Dateizustände werden vor und nach ihnen geprüft.

Eine erfolgreiche Vormerkung erzeugt media.batch.refresh.completed sowie je Datei media.asset.refresh.completed. Kindereignisse enthalten zusätzlich batch_id, batch_index, batch_count und requested_revision und haben eigene eindeutige IDs. Übergeordnetes Audit, einzelne Dateiaudits, Vormerkstände und Outbox sind eine Transaktion. Technische Empfänger dürfen dadurch keine Benutzerzugriffe freigeben. Die aktuelle technische Verarbeitung erzeugt nur inhaltsgebundene private Ableitungen; ausgeliefert wird ausschließlich nach aktueller Rechteprüfung.

Der zusätzliche technische Empfänger platform.media.purged_v1 beobachtet storage.entry.purge.completed. Er prüft aktuelle und historische Quellreferenzen unter dem gemeinsamen Mutationslock und entfernt nur unreferenzierte Ableitungen. Er verändert keine Originale, Versionen, Papierkorbinhalte oder Benutzerrechte. Sein eigener Registrierungsvertrag lässt den bereits registrierten assets_v1-Empfänger unverändert.


Iteration 36 ergänzt den vorläufigen GroupMappingChange um optionales expectedPresent (null/true/false). Mit true/false verlangt der vorhandene Mappingdienst innerhalb seiner Mutationstransaktion den exakten erwarteten Kantenbestand und wirft andernfalls MutationConflict. Ohne Erwartung bleibt das bisherige idempotente Verhalten erhalten. Der Proposal ist für Validatoren sichtbar; Kantenfingerabdruck und datensparsame Auditziele bleiben unverändert. Hinzufügen mit erwarteter Abwesenheit ist auf 100 Kanten je Zielgruppe begrenzt, damit die Browserverwaltung begrenzt bleibt.
