# Konfiguration, Datenbank und Migrationen

## Konfiguration

Samla liest Prozessumgebungsvariablen. Eine dotenv-Bibliothek wurde nicht eingeführt. `config/environment.example` ist eine Vorlage für das Hosting, keine ausführbare Konfigurationsdatei. `APP_ENV` ist entweder `production`, `development` oder `test`. Fehlende Datenbankangaben führen zu einem Konfigurationsfehler.

Der Testmodus verlangt ausschließlich `TEST_DB_*`, einen Namen mit Präfix `media_platform_test_` und einen Namen, der sich von einem gegebenenfalls zusätzlich konfigurierten `DB_NAME` unterscheidet. Der Schutz ersetzt keine getrennten Konten: Das Testkonto darf nur auf seine Testdatenbank zugreifen. Eine anders benannte oder mit einem anderen Hostalias erreichbare Produktivdatenbank lässt sich nicht automatisch erkennen.

`APP_GROUP_MAX_AGE_SECONDS` ist eine noch fachlich festzulegende Richtlinie. Ohne Wert lehnt der persistente Identitätsadapter den Zugriff ab. Die Tests verwenden einen eigenen Testwert und eine kontrollierte Uhr. Eine lokale Sperre gilt unabhängig vom Alter des Gruppenstands. Auch manuelle Mitgliedschaften ersetzen keine gültige zentrale Identitätsbestätigung.

PDO verwendet native vorbereitete Abfragen, keine persistenten Verbindungen, keine Mehrfachstatements und kein LOCAL INFILE. Datenbankkennungen werden validiert. Der konfigurierte Hersteller und die Releasefamilie müssen zur tatsächlich gemeldeten Serverversion passen. Verbindungen verwenden utf8mb4, UTC und einen strikten SQL-Modus. Unix-Sockets sind optional; TCP ist über Host und Port konfigurierbar.

## Schema und Eigentum

Identity besitzt Benutzer, Gruppen, Mapping, bestätigte externe Gruppen und Mitgliedschaften. `(issuer, subject)` wird binär verglichen, damit Groß-/Kleinschreibung und nachgestellte Leerzeichen keine Identitäten zusammenführen. Mappingquellen bleiben pro Mitgliedschaft erhalten; manuelle Quellen werden bei neuer externer Bestätigung nicht gelöscht.

Das RBAC-Plugin besitzt ausschließlich `rbac_*`-Tabellen. Es liest keine Identity-Tabellen. Rollenentscheidungen beziehen aktuelle Gruppen über den öffentlichen IdentityReader. Gruppen- und Ressourcenexistenz sowie Delegation sind später durch öffentliche Verwaltungsdienste zu prüfen. Private Repository-Schreibmethoden sind noch keine freigegebene Admin-API.

Benutzermutationen sperren die Benutzerzeile innerhalb ihrer Transaktion. Rollenänderungen und Rollenzuweisungen sperren dieselbe Rollenwurzel. Ein Bereichswechsel einer bereits zugewiesenen Rolle wird abgelehnt. Positive Autorisierungs-Caches sind weiterhin nicht vorhanden. Ein laufender Vorgang benötigt vor seiner späteren fachlichen Änderung zusätzliche Prüfung der veränderlichen Vorbedingungen.

## Migrationen

`bin/migrate.php` lädt die freigegebenen Kern- und Plugin-Migrationsbeiträge aus bootstrap/migrations.php. Die öffentliche SchemaMigration beschreibt Eigentümer, Kennung und einzelne SQL-Anweisungen. Der Runner verwendet pro Datenbank eine benannte Verbindungssperre und speichert Prüfsumme sowie Zustand (`running`, `applied`, `failed`).

DDL ist nicht Teil einer gemeinsamen rückrollbaren Datentransaktion. Daher wird vor der Ausführung `running` gespeichert. Nach Fehlern bleibt `failed` beziehungsweise bei Verbindungsverlust `running` erkennbar. Ein weiterer Lauf verweigert unbekannte, veränderte oder nicht vollständig abgeschlossene Migrationen. Auch das Weglassen eines bereits installierten Plugins wird nicht stillschweigend als zulässiger Migrationszustand behandelt.

Vor Migrationen ist ein konsistentes Backup erforderlich. Schreibzugriffe sind durch das Deployment anzuhalten. Die CLI-Vorbedingung `APP_MAINTENANCE=1` ist noch kein zentraler Wartungsschalter für mehrere Webprozesse. Nach einem Fehler Zustand und tatsächlich vorhandene Tabellen prüfen und gegebenenfalls aus Backup wiederherstellen. Ledger-Einträge niemals ohne Prüfung löschen oder auf erfolgreich setzen. Ein automatischer Reparatur- oder Rückmigrationsbefehl ist nicht implementiert.

## Testbefehle

```sh
composer test
APP_ENV=test composer test:integration
```

Die Integrationstests erwarten die isolierte Testdatenbank bereits vorhanden. Sie spielen Migrationen ein und löschen ausschließlich die bekannten Testdaten innerhalb dieser Datenbank. Eine Fehlerprobe legt eine eigene Testtabelle an und entfernt sie anschließend. Ohne gültige Testkonfiguration schlagen die Tests fehl.

Die vorhandenen Tests prüfen unter anderem persistente Rechteentscheidungen, lokale Sperren, Gruppenfrische, mehrfache Mappingquellen, Datenbank-Rollback, Prüfsummendrift, sichtbare Migrationsfehler und die Migrationssperre über eine zweite Verbindung. Vollständige Nebenläufigkeits- und Update-/Restore-Abnahmen stehen weiterhin aus.

## Technische Primärquellen

- https://www.php.net/manual/en/ref.pdo-mysql.php
- https://dev.mysql.com/doc/refman/8.0/en/implicit-commit.html
- https://dev.mysql.com/doc/refman/8.0/en/locking-functions.html

## Erweiterungen in Iteration 4

Neue versionierte Migrationen ergänzen administration_lock, audit_events und outbox_events sowie den anfänglichen storage_spaces-Katalog. Das RBAC-Plugin ergänzt eigene Delegationsregeln und deren explizite Berechtigungsobergrenzen. Bereits veröffentlichte Migrationen wurden nicht verändert. Bestehende Installationen führen die neuen Beiträge regulär im Wartungszustand aus. Die Tests wurden auf beiden Datenbankfamilien gegen das bereits vorhandene Schema aus Iteration 2/3 und die ergänzenden Migrationen ausgeführt. Vollständiger Anwendungs-Update und Restore bleiben eine eigene Betriebsabnahme.
