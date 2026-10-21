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

Diese Invariante schützt diesen Dienst gegen das Entfernen des letzten aktuell berechtigten Statusadministrators. Seit Iteration 6 bleibt zusätzlich mindestens ein aktuell nutzbarer Mitgliedschaftsadministrator erhalten, sofern ein solcher vor dem Statuswrite eingerichtet und nutzbar ist. Ohne eingerichtete Mitgliedschaftsverwaltung werden bestehende Statusanwendungsfälle dadurch nicht blockiert. Sie garantiert keinen zukünftigen Login, keine dauerhafte Gruppenfrische und keinen vollständigen Zugang zur gesamten Rollen-/Gruppenverwaltung. Provider-Ausfall, späterer Gruppenablauf oder externe Rechteänderungen benötigen gesonderte Betriebs- und Notfallregeln. Spätere Gruppen-/Mapping-/Rollenschreibdienste und OIDC-Synchronisierung müssen ihre eigenen Nachbedingungen unter der gemeinsamen Sperrstrategie ergänzen. Direkte interne Einrichtungswriter umgehen die Anwendungsfallprüfung und sind keine öffentlichen Verwaltungszugänge.

MutationContext verwendet Scope Global, keinen Speicherbereich und `targets = {"user": "<interne Kennung>"}`. Operationen sind `identity.user.block` und `identity.user.unblock`; zugehörige Outbox-Typen enden auf `.completed`. Der gewünschte Status ist durch die Operation bestimmt. Unveränderte Wiederholungen erhalten nur ein Audit mit `changed=0`. Audit, Status, Autorisierungsversion und Abschlussereignis committen gemeinsam oder werden gemeinsam zurückgerollt. Ein unbekannter Zielbenutzer wird vor einem erfolgreichen Audit abgelehnt. Sitzungen und App-Passwörter sind noch nicht implementiert; es wird daher kein Sitzungswiderruf behauptet.


## Manuelle Gruppenmitgliedschaften (Iteration 6)

GroupMembershipService bietet `grant(actorId, userId, groupId)` und `revoke(actorId, userId, groupId)`. Das ausdrückliche globale Recht `identity.groups.memberships.manage` ist für jeden Aufruf notwendig, auch für sich selbst und für Gruppen ohne Rollenzuweisungen. Der authentifizierte Akteur darf nicht aus frei wählbaren Requestdaten übernommen werden. Der Zielbenutzer und die interne Gruppe müssen existieren. Für blockierte beziehungsweise veraltete Zielbenutzer kann ein berechtigter Administrator Mitgliedschaften pflegen; dies entsperrt die Identität nicht und bestätigt keine Gruppendaten.

Zusätzlich entscheidet die verpflichtende GroupMembershipPolicy aus dem RBAC-Plugin. Sie liest alle Rollenzuweisungen der Zielgruppe. Für jede einzelne Zuweisung müssen Speicherbereich, vollständiges Permit für `rbac.assignments.manage` in diesem Bereich und eine passende explizite Delegationsregel vorhanden sein. Eine Mitgliedschaft erhält sämtliche Rollen einer Gruppe, deshalb genügt eine Regel für nur einen von mehreren Bereichen oder Rollen nicht. Dass der Akteur selbst die Zielrechte besitzt, ersetzt keine Delegation.

Bei einer Vergabe müssen alle aktuellen Rollenberechtigungen innerhalb der gespeicherten Obergrenze einer einzelnen Regel liegen. Ein Rollenwachstum erweitert die delegierbaren Rechte nicht. Ein Entzug fachlicher Mitgliedschaften bleibt bei passender Regel auch nach einer Überschreitung der Obergrenze möglich. Gruppen mit irgendeiner globalen Rollenzuweisung oder einer Rolle mit `rbac.*`-Berechtigungen können weder hinzugefügt noch entfernt werden. Diese Begrenzung gilt auch für unveränderte Wiederholungen. Für eine Gruppe ohne Rollen genügt das globale Mitgliedschaftsrecht; zukünftige Rollenzuweisungen unterliegen weiterhin ihrem eigenen geschützten Dienst.

Der gemeinsame Ablauf prüft zunächst Recht, Ressourcen und Delegation, führt Plugin-Validatoren aus und prüft dieselben Bedingungen erneut. Der Writer ändert ausschließlich die manuelle Quelle. Eine extern abgeleitete Mitgliedschaft bleibt mitsamt sämtlichen Quellen erhalten. Daher kann nach dem Entfernen einer manuellen Mitgliedschaft derselbe effektive Gruppenzugang weiterhin durch den Provider bestehen. Eine Änderung der manuellen Quelle erhöht die Autorisierungsversion auch dann, wenn externe Quellen den effektiven Zugang bereits vermitteln. Ohne Änderung gibt es keinen Versionsanstieg.

Nach dem Write verlangt AdministrationAccessGuard jeweils einen aktuell nutzbaren Statusadministrator und einen aktuell nutzbaren Mitgliedschaftsadministrator. Die Benutzer können verschieden sein. Die Prüfung verwendet die vollständige zentrale Autorisierung inklusive Gruppenfrische und zusätzlicher Plugin-Einschränkungen. Sie schützt auch indirekte Auswirkungen von Mitgliedschaften in Gruppen ohne RBAC-Rolle, wenn ein Plugin seinen Zugriff von einer solchen Mitgliedschaft abhängig macht. Die Transaktion wird bei fehlendem verbleibendem Zugang vollständig zurückgerollt. Beim kontrollierten Einrichten müssen beide Fähigkeiten vorhanden sein, bevor öffentliche Mitgliedschaftsänderungen verwendet werden können.

Operationen sind `identity.membership.grant` und `identity.membership.revoke`; Kontext und Audit enthalten Scope Global, keinen Speicherbereich sowie `targets = {"user": "<interne Kennung>", "group": "<interne Kennung>"}`. Bei einer Änderung wird das entsprechende `.completed`-Ereignis mitpersistiert. Mitgliedschaft, Autorisierungsversion, Audit und Outbox bilden eine Transaktion. Audit-/Outbox-Fehler oder fehlgeschlagene Nachbedingungen rollen alle Änderungen zurück. Unveränderte Wiederholungen werden mit `changed=0` protokolliert und erzeugen kein zusätzliches Abschlussereignis.

Der Aufbau muss GroupMembershipService mit RbacGroupMembershipPolicy, vollständigem AuthorizationService, PdoResourceDirectory und PdoMutationRunner aus derselben vertrauenswürdigen Zusammensetzung und Datenbank versorgen. Es gibt keinen Policy-Standard, der bei fehlender Integration erlaubt. Der Kern importiert keine RBAC-Klassen und fragt keine RBAC-Tabellen ab. Der interne Membershipwriter verlangt eine bestehende Transaktion; direkte Repository-Schreibmethoden bleiben interne Einrichtungswerkzeuge.

Gruppen anlegen/umbenennen/löschen, Mappingverwaltung, globale administrative Delegation und OIDC-Synchronisierung bleiben weitere Arbeitspakete. Dieser Schutz ist eine Prüfung des aktuellen Zustands unter der gemeinsamen Sperre. Er ersetzt weder zukünftige Gruppenbestätigung, Provider-Verfügbarkeit noch einen vollständig getesteten Notfallzugang. Eine Oberfläche und öffentliche HTTP-Routen existieren noch nicht.
