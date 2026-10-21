# Geschützte Verwaltung

## Gelieferter Anwendungsfall

RoleAssignmentService bietet `grant(actorId, assignment)` und `revoke(actorId, assignment)` für fachliche Rollen innerhalb eines existierenden Speicherbereichs. Der Aufrufer muss den Akteur aus einer authentifizierten Sitzung beziehungsweise einem vergleichbaren vertrauenswürdigen Zugang übernehmen. Ein HTTP-Adapter ist noch nicht vorhanden.

Jeder Aufruf benötigt ein ausdrückliches Permit der vollständigen Kern-Autorisierung für `rbac.assignments.manage` im Zielbereich. Zusätzlich muss eine interne Gruppe des Akteurs eine genaue Delegationsregel für Zielrolle und Zielbereich besitzen. Ein allgemeines Verwaltungsrecht allein genügt nicht. Zielgruppe und Speicherbereich müssen über das öffentliche ResourceDirectory existieren.

Delegationsregeln besitzen eine explizite Berechtigungsobergrenze. Beim Vergeben müssen sämtliche aktuellen Rollenberechtigungen in der Obergrenze einer einzelnen passenden Regel liegen. Unbekannte beziehungsweise deaktivierte Berechtigungen oder unzulässige Scopes verhindern eine Vergabe. Nachträgliches Erweitern einer Rolle erweitert die Delegationsbefugnis somit nicht automatisch. Der Entzug einer fachlichen Rolle bleibt mit passender Regel auch nach einer solchen Erweiterung möglich.

Globale Rollenzuweisungen und Rollen mit `rbac.*`-Berechtigungen können über diesen Dienst weder vergeben noch entzogen werden. Die Bearbeitung administrativer Grundlagen und der umfassende Schutz des letzten Administrators benötigen einen eigenen Anwendungsfall. Der bestehende administrative Zugang kann daher nicht über diesen ersten Dienst entfernt werden.

## Öffentliche Verträge

| Vertrag | Zweck |
|---|---|
| AuthorizationChecker | Vollständige Kern-Autorisierung einschließlich Pflichtprovider und zusätzlicher Einschränkungen |
| ResourceDirectory | Prüfung vorhandener Gruppen und Speicherbereiche ohne Tabellenabhängigkeit des RBAC-Plugins |
| MutationContext | Unveränderliche Vorgangskennung, Akteur, Operation, Scope und begrenzte Zielkennungen |
| ChangeValidator | Zusätzliche Organisationsregel; eine Ausnahme lehnt die Änderung ab |
| MutationRunner | Gemeinsamer Ablauf und Transaktionsgrenze für Prüfung, Änderung, Audit und Outbox |

Der Kern importiert keine RBAC-Interna. Das RBAC-Plugin nutzt die öffentlichen Verträge und eigene Repositories. Das Referenzplugin registriert bereits einen echten Validator: Die Beispielgruppe `example.blocked` darf nicht Ziel einer neuen Rollenzuweisung sein. Das Beispielplugin ist nicht standardmäßig aktiviert.

## Ablauf und Fehlerverhalten

1. Eine Datenbanktransaktion beginnen und die gemeinsame Administrationszeile sperren.
2. Zentrale Autorisierung, Ressourcen und Delegation prüfen.
3. Alle registrierten Validatoren ausführen: Priorität absteigend, dann Plugin-ID und Registrierungsreihenfolge.
4. Die vollständigen Vorbedingungen erneut prüfen.
5. Die Zuweisung im privaten RBAC-Repository ändern.
6. Audit-Eintrag speichern; bei tatsächlicher Änderung zusätzlich das Abschlussereignis persistieren.
7. Gemeinsam committen. Bei jeder Ausnahme einschließlich Audit-/Outbox-Fehlern wird alles zurückgerollt.

Die Validatoren laufen vor der Änderung innerhalb der Transaktion, dürfen Kontexte nicht verändern und keine externen Nebenwirkungen auslösen. Fehler verpflichtender Validierung werden nicht ignoriert. Audit-Daten enthalten Zielkennungen und keine Tokens oder Dateiinhalte. Akzeptierte, unveränderte Wiederholungen erhalten einen Audit-Eintrag mit `changed=0`, aber kein zusätzliches Änderungsereignis.

## Audit und Abschlussereignisse

`audit_events` und `outbox_events` gehören zum Kern. Der Ereignistyp ist `rbac.assignment.grant.completed` beziehungsweise `rbac.assignment.revoke.completed`. Der Payload enthält Vorgangskennung, Akteur, Scope, Bereich und Zielkennungen. Audit und Outbox verwenden dieselbe Vorgangskennung. Ein Eintrag wird erst mit erfolgreichem Commit sichtbar.

Die Outbox-Zustellung, Wiederholungssteuerung, Empfängerregistrierung und idempotente Empfänger sind noch nicht implementiert. Persistiert bedeutet hier nicht bereits zugestellt. Abgelehnte Versuche erzeugen kein erfolgreiches Abschlussereignis; eine zusätzliche transaktionsunabhängige Protokollierung abgelehnter Sicherheitsereignisse folgt separat.

## Betriebs- und Umfangsgrenzen

- Delegationsregeln und Speicherbereichskatalog werden vorerst im kontrollierten Einrichtungs-/Migrationskontext gepflegt. Es gibt keine Oberfläche und keinen öffentlichen Endpunkt dafür.
- Die neue storage_spaces-Tabelle ist ein anfänglicher Ressourcenkatalog, noch kein fertiger Dateispeicher oder Quotenmodell.
- Die gemeinsame Administrationssperre serialisiert diese geschützten Verwaltungsänderungen. Direkte Repository- oder SQL-Mutationen sind interne Einrichtungswerkzeuge und keine unterstützten parallelen Verwaltungszugänge.
- Spätere öffentliche Dienste für Rollenbearbeitung, Gruppenstammdaten, Mapping und OIDC-Synchronisierung müssen denselben kontrollierten Änderungsweg beziehungsweise eine dazu passende Sperrstrategie verwenden. Bestehende private Writer sind hierfür noch anzupassen.
- Externe Gruppenänderungen, die der Provider noch nicht übermittelt hat, sind Samla weiterhin unbekannt. Die Gruppenfrische-Richtlinie bleibt eine offene Produktentscheidung.
- Globale Delegation, Rollenbearbeitung, Gruppenmapping-Verwaltung, umfassender Schutz des letzten Administrationszugangs und Notfallzugang sind weitere Arbeitspakete. Die gesamte Verwaltungsphase ist noch nicht abgeschlossen.


## Benutzer sperren und entsperren (Iteration 5)

`UserStatusService.setBlocked(actorId, userId, blocked)` ist ein geschützter Anwendungsdienst. Im vertrauenswürdigen Aufbau müssen IdentityPlugin, RBAC-Pflichtplugin, vollständiger AuthorizationService und gemeinsamer PdoMutationRunner dieselbe Identitätsquelle beziehungsweise Datenbank verwenden. Der Akteur muss aus einem authentifizierten Zugang stammen. Eine HTTP-Route oder Oberfläche existiert noch nicht.

Das neue Recht `identity.users.status.manage` ist ausschließlich global und wird von IdentityPlugin registriert. Es erlaubt ausdrücklich auch das Entsperren privilegierter Konten und darf deshalb nur vertrauenswürdigen Statusadministratoren zugewiesen werden. Eine allgemeine Bereichsrolle genügt nicht. Der Dienst verändert weder Rollen noch Gruppenmitgliedschaften oder bestätigte Gruppendaten. Insbesondere macht Entsperren einen veralteten Gruppenstand nicht wieder gültig.

Der Dienst verwendet dieselbe Administrationssperre, Plugin-Validierung und doppelte Vorprüfung wie Rollenzuweisungen. Der interne Statuswriter benötigt eine aktive Transaktion und erhöht die Autorisierungsversion ausschließlich bei einer tatsächlichen Änderung. Anschließend wird im resultierenden Zustand mindestens ein Benutzer verlangt, für den die vollständige Autorisierung das globale Statusverwaltungsrecht erlaubt. Aktive Identität, frischer Gruppenstand, RBAC und zusätzliche Plugin-Einschränkungen zählen gemeinsam. Fehlt ein solcher Benutzer, wird die gesamte Änderung zurückgerollt. Selbstsperren ist damit nur möglich, wenn ein anderer aktuell nutzbarer Statusadministrator verbleibt.

Diese Invariante schützt diesen Dienst gegen das Entfernen des letzten aktuell berechtigten Statusadministrators. Seit Iteration 6 bleibt zusätzlich mindestens ein aktuell nutzbarer Mitgliedschaftsadministrator erhalten. Seit Iteration 7 gilt das auch für die Gruppenstammdatenverwaltung. Die zuvor nutzbaren Fähigkeiten werden unter der Administrationssperre bereits vor den Validatoren erfasst und nach dem Write erneut verlangt. Ohne eingerichtete Mitgliedschaftsverwaltung werden bestehende Statusanwendungsfälle dadurch nicht blockiert. Sie garantiert keinen zukünftigen Login, keine dauerhafte Gruppenfrische und keinen vollständigen Zugang zur gesamten Rollen-/Gruppenverwaltung. Provider-Ausfall, späterer Gruppenablauf oder externe Rechteänderungen benötigen gesonderte Betriebs- und Notfallregeln. Spätere Gruppen-/Mapping-/Rollenschreibdienste und OIDC-Synchronisierung müssen ihre eigenen Nachbedingungen unter der gemeinsamen Sperrstrategie ergänzen. Direkte interne Einrichtungswriter umgehen die Anwendungsfallprüfung und sind keine öffentlichen Verwaltungszugänge.

MutationContext verwendet Scope Global, keinen Speicherbereich und `targets = {"user": "<interne Kennung>"}`. Operationen sind `identity.user.block` und `identity.user.unblock`; zugehörige Outbox-Typen enden auf `.completed`. Der gewünschte Status ist durch die Operation bestimmt. Unveränderte Wiederholungen erhalten nur ein Audit mit `changed=0`. Audit, Status, Autorisierungsversion und Abschlussereignis committen gemeinsam oder werden gemeinsam zurückgerollt. Ein unbekannter Zielbenutzer wird vor einem erfolgreichen Audit abgelehnt. Sitzungen und App-Passwörter sind noch nicht implementiert; es wird daher kein Sitzungswiderruf behauptet.


## Manuelle Gruppenmitgliedschaften (Iteration 6)

GroupMembershipService bietet `grant(actorId, userId, groupId)` und `revoke(actorId, userId, groupId)`. Das ausdrückliche globale Recht `identity.groups.memberships.manage` ist für jeden Aufruf notwendig, auch für sich selbst und für Gruppen ohne Rollenzuweisungen. Der authentifizierte Akteur darf nicht aus frei wählbaren Requestdaten übernommen werden. Der Zielbenutzer und die interne Gruppe müssen existieren. Für blockierte beziehungsweise veraltete Zielbenutzer kann ein berechtigter Administrator Mitgliedschaften pflegen; dies entsperrt die Identität nicht und bestätigt keine Gruppendaten.

Zusätzlich entscheidet die verpflichtende GroupMembershipPolicy aus dem RBAC-Plugin. Sie liest alle Rollenzuweisungen der Zielgruppe. Für jede einzelne Zuweisung müssen Speicherbereich, vollständiges Permit für `rbac.assignments.manage` in diesem Bereich und eine passende explizite Delegationsregel vorhanden sein. Eine Mitgliedschaft erhält sämtliche Rollen einer Gruppe, deshalb genügt eine Regel für nur einen von mehreren Bereichen oder Rollen nicht. Dass der Akteur selbst die Zielrechte besitzt, ersetzt keine Delegation.

Bei einer Vergabe müssen alle aktuellen Rollenberechtigungen innerhalb der gespeicherten Obergrenze einer einzelnen Regel liegen. Ein Rollenwachstum erweitert die delegierbaren Rechte nicht. Ein Entzug fachlicher Mitgliedschaften bleibt bei passender Regel auch nach einer Überschreitung der Obergrenze möglich. Gruppen mit irgendeiner globalen Rollenzuweisung oder einer Rolle mit `rbac.*`-Berechtigungen können weder hinzugefügt noch entfernt werden. Diese Begrenzung gilt auch für unveränderte Wiederholungen. Für eine Gruppe ohne Rollen genügt das globale Mitgliedschaftsrecht; zukünftige Rollenzuweisungen unterliegen weiterhin ihrem eigenen geschützten Dienst.

Der gemeinsame Ablauf prüft zunächst Recht, Ressourcen und Delegation, führt Plugin-Validatoren aus und prüft dieselben Bedingungen erneut. Der Writer ändert ausschließlich die manuelle Quelle. Eine extern abgeleitete Mitgliedschaft bleibt mitsamt sämtlichen Quellen erhalten. Daher kann nach dem Entfernen einer manuellen Mitgliedschaft derselbe effektive Gruppenzugang weiterhin durch den Provider bestehen. Eine Änderung der manuellen Quelle erhöht die Autorisierungsversion auch dann, wenn externe Quellen den effektiven Zugang bereits vermitteln. Ohne Änderung gibt es keinen Versionsanstieg.

Nach dem Write verlangt AdministrationAccessGuard jeweils einen aktuell nutzbaren Statusadministrator und einen aktuell nutzbaren Mitgliedschaftsadministrator. Die Benutzer können verschieden sein. Die Prüfung verwendet die vollständige zentrale Autorisierung inklusive Gruppenfrische und zusätzlicher Plugin-Einschränkungen. Sie schützt auch indirekte Auswirkungen von Mitgliedschaften in Gruppen ohne RBAC-Rolle, wenn ein Plugin seinen Zugriff von einer solchen Mitgliedschaft abhängig macht. Die Transaktion wird bei fehlendem verbleibendem Zugang vollständig zurückgerollt. Beim kontrollierten Einrichten müssen beide Fähigkeiten vorhanden sein, bevor öffentliche Mitgliedschaftsänderungen verwendet werden können.

Operationen sind `identity.membership.grant` und `identity.membership.revoke`; Kontext und Audit enthalten Scope Global, keinen Speicherbereich sowie `targets = {"user": "<interne Kennung>", "group": "<interne Kennung>"}`. Bei einer Änderung wird das entsprechende `.completed`-Ereignis mitpersistiert. Mitgliedschaft, Autorisierungsversion, Audit und Outbox bilden eine Transaktion. Audit-/Outbox-Fehler oder fehlgeschlagene Nachbedingungen rollen alle Änderungen zurück. Unveränderte Wiederholungen werden mit `changed=0` protokolliert und erzeugen kein zusätzliches Abschlussereignis.

Der Aufbau muss GroupMembershipService mit RbacGroupMembershipPolicy, vollständigem AuthorizationService, PdoResourceDirectory und PdoMutationRunner aus derselben vertrauenswürdigen Zusammensetzung und Datenbank versorgen. Es gibt keinen Policy-Standard, der bei fehlender Integration erlaubt. Der Kern importiert keine RBAC-Klassen und fragt keine RBAC-Tabellen ab. Der interne Membershipwriter verlangt eine bestehende Transaktion; direkte Repository-Schreibmethoden bleiben interne Einrichtungswerkzeuge.

Gruppen löschen, globale administrative Delegation und der öffentliche OIDC-Synchronisierungsablauf bleiben weitere Arbeitspakete. Mappingkanten sind seit Iteration 8 im folgenden Dienst geschützt bearbeitbar. Anlegen und Umbenennen sind seit Iteration 7 im folgenden Dienst umgesetzt. Dieser Schutz ist eine Prüfung des aktuellen Zustands unter der gemeinsamen Sperre. Er ersetzt weder zukünftige Gruppenbestätigung, Provider-Verfügbarkeit noch einen vollständig getesteten Notfallzugang. Eine Oberfläche und öffentliche HTTP-Routen existieren noch nicht.


## Gruppen anlegen und umbenennen (Iteration 7)

GroupDetailsService bietet `create(actorId, name): string` und `rename(actorId, groupId, expectedName, name): bool`. Für beide Funktionen ist das separate globale Recht `identity.groups.details.manage` erforderlich. Status- oder Mitgliedschaftsverwaltung allein genügt nicht. Der Aufbau verwendet dieselbe Identitätsquelle, denselben vollständigen AuthorizationService und denselben PdoMutationRunner. Akteur und Zugang müssen aus einer vertrauenswürdig authentifizierten Quelle stammen. Ein öffentlicher HTTP-Adapter und eine Oberfläche existieren weiterhin nicht.

Anlegen erzeugt eine neue zufällige interne Kennung (`grp.` plus 32 Hexzeichen) und gibt diese zurück. Ein Aufrufer kann keine alte Kennung wiederverwenden. Eine Anlage erzeugt weder Mitgliedschaften noch Rollen oder Mappings. Namen sind Anzeigetexte und nicht eindeutig; zwei Anlagen desselben Namens erzeugen unterschiedliche Gruppen. Eine wiederholte Anlage ist daher kein idempotenter Retry. Ein späterer HTTP-Adapter benötigt für sichere automatische Wiederholung einen gesonderten Idempotenzvertrag.

Umbenennen verändert ausschließlich den Namen einer existierenden Gruppe. Ihre Kennung, Mitgliedschaften einschließlich externer Quellen, Mappingbezüge und Rollenzuweisungen bleiben erhalten. Auch Autorisierungsversionen und Gruppenbestätigungszeitpunkte werden durch reine Namensänderungen nicht erhöht beziehungsweise erneuert. Die Kernrechte sind weiterhin an Kennungen gebunden. Plugins, die zusätzliche Regeln auf Namen aufbauen, können den Vorschlag vorab validieren und müssen eigene Caches entsprechend behandeln; es gibt im Kern weiterhin keinen positiven Autorisierungscache.

Der Aufrufer muss den zuletzt gelesenen Namen als expectedName übergeben. Der Writer sperrt die Zeile und vergleicht diesen Namen bytegenau mit dem aktuellen Zustand. Bei Abweichung entsteht MutationConflict; der aktuelle Name wird erhalten, der Aufruf erzeugt kein erfolgreiches Audit oder Abschlussereignis. Ein erneut gelesener Zustand muss ausdrücklich Grundlage eines weiteren Änderungsversuchs sein. Derselbe aktuelle und gewünschte Name ist ein protokollierter No-op mit `changed=0` ohne weiteres Abschlussereignis. Eine zwischenzeitliche Änderung mit anschließender Rückkehr zum gleichen Namen wird durch diesen Namensvergleich nicht erkannt. Er ersetzt kein allgemeines Revisionsmodell.

Die öffentliche GroupDetailsChange-Proposal enthält den gewünschten und gegebenenfalls erwarteten Namen. Namen sind gültiges UTF-8, 1 bis 255 Unicode-Codepoints lang, ohne Steuerzeichen oder umgebenden Leerraum. Es gibt keine automatische Normalisierung. MutationContext ordnet den Proposal strikt den Operationen `identity.group.create` beziehungsweise `identity.group.rename`, Scope Global und dem Ziel `group` zu. Damit können die bestehenden ChangeValidatoren Namensrichtlinien durchsetzen, ohne Freitext in den Kennungsfeldern des Audits zu transportieren.

Nach dem Write bleiben alle zuvor aktuell nutzbaren Fähigkeiten aus der festen Liste Statusverwaltung, Mitgliedschaftsverwaltung und Gruppenstammdatenverwaltung erhalten. Jede Fähigkeit benötigt mindestens ein vollständiges Permit für einen aktiven Benutzer mit frischen Gruppendaten und ohne Plugin-Veto; die Benutzer können verschieden sein. Die Liste wird einmal unter der Administrationssperre vor den Validatoren erfasst. Die gemeinsame AdministrationAccessGuard-Prüfung wird auch von Status- und Mitgliedschaftsdienst verwendet, damit diese Dienste den letzten bereits nutzbaren Stammdatenadministrator ebenfalls erhalten. Noch nicht eingerichtete Fähigkeiten blockieren den Stammdatendienst nicht. Für Mitgliedschaftsänderungen bleibt zusätzlich mindestens ein nutzbarer Statusverwaltungszugang erforderlich.

Anlegen beziehungsweise Umbenennen, Audit und Outbox sind eine Transaktion. Ereignistypen sind `identity.group.create.completed` und `identity.group.rename.completed`. Audit und Ereignispayload enthalten ausschließlich Vorgang, Akteur, Scope und Gruppenkennung; die neuen Namensdaten werden nicht automatisch kopiert. Audit-/Outbox-Fehler, Plugin-Vetos und verletzte Nachbedingungen rollen alles zurück. Unbekannte Gruppen und ungültige Eingaben hinterlassen keinen erfolgreichen Änderungseintrag.

Der Dienst umfasst keine Löschung, kein Umhängen von Mappings und keine Bearbeitung von Mitgliedschaften oder Rollen. Er erlaubt auch Anzeigetextänderungen einer administrativen Gruppe bei ausdrücklich vorhandenem Stammdatenrecht, da ihre Kennung und RBAC-Bezüge unverändert bleiben. Organisationsregeln für geschützte Namen gehören in einen Validator. Die aktuelle Nachbedingung garantiert weder zukünftige Provider-Anmeldung noch dauerhafte Gruppenfrische oder einen umfassenden Notfallzugang.


## Mappingkanten verwalten (Iteration 8)

GroupMappingService bietet `grant(actorId, issuer, externalId, groupId)` und `revoke(actorId, issuer, externalId, groupId)`. Jede Kante verbindet einen exakt bezeichneten Issuer und eine externe Gruppenkennung mit einer internen Gruppe. Ein Quellwert kann mehrere interne Gruppen versorgen; verschiedene externe Quellen können dieselbe interne Mitgliedschaft vermitteln. Es gibt keinen impliziten Ersetzungs- oder Umhängemodus.

Jeder Aufruf verlangt das eigene globale Recht `identity.groups.mappings.manage`, eine existierende interne Gruppe und die verpflichtende GroupMappingPolicy. Die vollständige Autorisierung einschließlich Plugin-Einschränkungen und Gruppenfrische wird vor und nach Validatoren geprüft. Die RBAC-Policy benötigt für jede Rolle der Zielgruppe in jedem Bereich ein vollständiges bereichsbezogenes Verwaltungsrecht und die ausdrückliche passende Delegation. Für Vergaben gilt die eingefrorene Berechtigungsobergrenze. Ein fachlicher Entzug bleibt nach Rollenwachstum mit passender Regel möglich. Globale und administrative RBAC-Gruppen sind vollständig ausgeschlossen. Die Prüfung gilt auch ohne aktuell passende Benutzer, weil eine Kante zukünftigen bestätigten Gruppenständen Rechte vermitteln kann.

Das globale Mappingrecht umfasst die Pflege von Kanten aller angegebenen Issuer. Es ist nicht allein auf einen aktuell vorhandenen Benutzer beschränkt und darf daher nur dafür vorgesehenen Administratoren gegeben werden. Der Dienst authentifiziert keinen Provider und registriert keinen neuen OIDC-Vertrauensanker. Issuer und externe Kennung sind exakte, begrenzte Bytefolgen; Groß-/Kleinschreibung und ein zusätzliches Slash sind verschieden. Gruppenstammdaten- oder manuelle Mitgliedschaftsrechte ersetzen das Mappingrecht nicht.

Mappingkante und Quellenprojektionen werden unter der gemeinsamen Administrationssperre in derselben Transaktion geändert. Alle Benutzer mit passendem gespeicherten Issuer und passender externer Quellkennung werden berücksichtigt. Der Abgleich umfasst auch verwaiste Quellenprojektionen dieser Kante. Bei einer Vergabe entsteht eine externe Mitgliedschaftsquelle nur dort, wo die gespeicherte externe Gruppensicht den Wert enthält. Beim Entzug wird ausschließlich diese Quelle entfernt. Manuelle Mitgliedschaften, andere externe Quellen und andere Mappingkanten bleiben bestehen. Benutzer anderer Issuer sind nicht betroffen.

Für jeden Benutzer mit tatsächlich veränderter Quellenzeile erhöht sich die Autorisierungsversion einmal je Kantenänderung. Das gilt auch dann, wenn eine andere Quelle denselben effektiven Gruppenzugang erhält. Ohne Quellenänderung wird die Version nicht erhöht. Lokaler Sperrstatus, bestätigte externe Gruppensicht und deren Bestätigungszeitpunkt werden durch Mappingpflege nicht geändert. Eine veraltete Gruppensicht bleibt veraltet, eine gesperrte Identität bleibt gesperrt. Der Dienst verwendet bereits gespeicherte verifizierte Aussagen und führt keine neue Provider-Anfrage oder Tokenprüfung durch.

Ein bereits vorhandenes Grant beziehungsweise fehlendes Revoke ist ohne Projektionsabweichung ein No-op: Audit mit `changed=0`, keine zusätzlichen Ereignisse oder Versionserhöhungen. Besteht eine Abweichung zwischen gespeicherter Kante und abgeleiteten Quellen, kann derselbe Aufruf diese korrigieren und meldet dann eine tatsächliche Änderung. Die Korrektur bleibt auf die bezeichnete Kante begrenzt. Sie ist keine allgemeine Datenbankreparatur.

GroupMappingChange stellt Issuer, externe und interne Kennung sowie Grant/Revoke für Plugin-Validatoren bereit. MutationContext muss zu Operation, Zielgruppe und Mappingfingerabdruck passen. Audit und Outbox enthalten `targets = {"group": "<interne Kennung>", "mapping": "<64-stelliger Fingerabdruck>"}`. Roh-Issuer und externe Kennung werden nicht automatisch übernommen. Ein Fingerabdruck ist eine stabile Kennung und keine zugesicherte Anonymisierung. Pro geändertem Aufruf gibt es ein Abschlussereignis, nicht eines pro betroffenem Benutzer. Ereigniszustellung bleibt offen.

Die zuvor nutzbaren Fähigkeiten aus Status-, Mitgliedschafts-, Stammdaten- und Mappingverwaltung werden vor Validatoren erfasst und nach dem Write erneut verlangt. Der gemeinsame AdministrationAccessGuard berücksichtigt jetzt alle vier Fähigkeiten auch in den bestehenden Diensten. Bei fehlendem verbleibendem Zugang werden Kante, sämtliche betroffenen Quellen, Autorisierungsversionen und Protokolleinträge zurückgerollt. Dasselbe gilt bei Plugin-Veto, Audit- oder Outbox-Fehlern.

Der interne Writer für bereits verifizierte Gruppensnapshots verwendet jetzt dieselbe Administrationssperre vor der Benutzerzeilensperre. Damit kann ein Snapshotwechsel nicht parallel einen veralteten Mappingstand projizieren. Dieser Writer ist weiterhin ein interner Adapter, kein authentifizierter OIDC-Anwendungsfall: Tokenprüfung, öffentliche Synchronisierung, Audit und deren Regeln folgen mit der OIDC-Integration. Interne Einrichtungszugriffe dürfen nicht als parallele öffentliche Verwaltung verwendet werden.

Der Dienst wird aus derselben Identitätsquelle, vollständigem AuthorizationService, RbacGroupMappingPolicy, ResourceDirectory und MutationRunner zusammengesetzt. Es werden keine neuen Laufzeitkomponenten oder Migrationen benötigt. Der Abgleich erfolgt synchron für alle passenden Benutzer innerhalb einer Transaktion. Eine Lastabnahme für große Verzeichnisse oder ein Verfahren für aufgeteilte Hintergrundjobs ist noch nicht implementiert. Mehrere Kanten lassen sich noch nicht in einem gemeinsamen atomaren Auftrag ändern; ein Umhängen durch getrenntes Grant/Revoke ist kein atomarer Wechsel.
