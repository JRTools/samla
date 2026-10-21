# Fachliche Rollenzuweisung verwalten

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
- Spätere öffentliche Dienste für Rollenbearbeitung, Gruppen, Benutzerstatus und OIDC-Synchronisierung müssen denselben kontrollierten Änderungsweg beziehungsweise eine dazu passende Sperrstrategie verwenden. Bestehende private Writer sind hierfür noch anzupassen.
- Externe Gruppenänderungen, die der Provider noch nicht übermittelt hat, sind Samla weiterhin unbekannt. Die Gruppenfrische-Richtlinie bleibt eine offene Produktentscheidung.
- Globale Delegation, Rollenbearbeitung, Gruppenmapping-Verwaltung, letzter Administrationszugang und Notfallzugang sind weitere Arbeitspakete. Die gesamte Verwaltungsphase ist noch nicht abgeschlossen.
