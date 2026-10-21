# Prüfprotokoll: Iteration 8

Datum: 21. Oktober 2026. Projekt: Samla. PHPUnit 13.4.3 aus composer.lock.

| PHP | Datenbank | Unit und Architektur | Integration | Gesamt |
|---|---|---|---|---|
| 8.4.5 | MySQL 8.0.46 | 84 Tests, 172 Assertions | 122 Tests, 542 Assertions | 206 Tests, 714 Assertions |
| 8.5.4 | MySQL 8.0.46 | 84 Tests, 172 Assertions | 122 Tests, 542 Assertions | 206 Tests, 714 Assertions |
| 8.4.5 | MariaDB 11.8.6 | 84 Tests, 172 Assertions | 122 Tests, 542 Assertions | 206 Tests, 714 Assertions |
| 8.5.4 | MariaDB 11.8.6 | 84 Tests, 172 Assertions | 122 Tests, 542 Assertions | 206 Tests, 714 Assertions |

Alle Suiten erfolgreich, ohne PHPUnit-Warnungen oder Deprecations. Die Syntax aller 79 eigenen PHP-Dateien ist unter beiden PHP-Unterversionen geprüft. Die Migrations-CLI meldet unter allen vier Kombinationen ein aktuelles Schema. Keine neuen Migrationen oder Laufzeitabhängigkeiten.

## Neue Nachweise

15 zusätzliche Unitfälle und 27 zusätzliche Integrationsfälle prüfen:

- Begrenzte unveränderliche Mappingkennungen, eindeutige Längenrahmung des Fingerabdrucks und strikte Zuordnung von Operation, Proposal und Zielen.
- Sofortige Vergabe und Entfernung bestehender externer Quellen anhand gespeicherter bestätigter Gruppenstände.
- Abgleich sämtlicher passender Benutzer einschließlich binärer externer Kennungen; Version pro geändertem Benutzer und ein Ereignis pro Änderungsaufruf.
- Exakte Issuer- und Gruppenkennungstrennung; andere Issuer erhalten keine Rechte oder Versionsänderung.
- Erhalt manueller Mitgliedschaften, weiterer externer Quellen und anderer Kanten derselben Quelle.
- Wiederholungen ohne Quellenabweichung erhöhen keine Versionen und erzeugen keine zusätzlichen Abschlussereignisse.
- Mapping wirkt auch auf zukünftige bestätigte Gruppenstände; fehlende aktuelle Mitglieder umgehen die Delegationsprüfung nicht.
- Globales Mappingrecht, vollständiges Bereichsverwaltungsrecht und ausdrückliche Delegation sind notwendig; jede Rolle und jeder Speicherbereich zählen.
- Rollenwachstum überschreitet keine Delegationsobergrenze; fachlicher Entzug bleibt mit passender Regel möglich.
- Globale und administrative RBAC-Gruppen sind auch ohne aktuelle Mitglieder ausgeschlossen.
- Lokale Sperren und Gruppenfrische bleiben durch Mappingpflege unverändert; gesperrte/veraltete Akteure dürfen nicht verwalten.
- Korrektur fehlender abgeleiteter Quellen bei bereits vorhandener Kante sowie Entfernung verwaister Quellen ohne Löschen manueller Mitgliedschaften.
- Validatoren erhalten den vollständigen Proposal; Audit und Outbox speichern stattdessen Gruppe und Fingerabdruck.
- Plugin-Veto und erneute Delegationsprüfung nach Validatoren verhindern unzulässige Änderungen.
- Die Entfernung des letzten nutzbaren Mappingverwaltungszugangs wird atomar zurückgerollt; auch Benutzersperren schützen diesen Zugang.
- Audit- und Outbox-Fehler rollen Kante, Projektionen, Versionen und Protokolleinträge gemeinsam zurück.
- Der interne Snapshotwriter wird durch eine zweite Verbindung mit Administrationssperre bis zum geprüften Lock-Timeout blockiert; danach bleibt keine Transaktion offen.
- Fehlende Zielgruppen oder Speicherbereiche werden abgelehnt; der interne Writer verlangt eine aktive Transaktion.

Alle bisherigen Tests einschließlich Rollen-, Mitgliedschafts- und Stammdatenverwaltung bestehen weiterhin.

## Grenzen und Reproduktion

Kein Frontend, keine HTTP-/OIDC-/WebDAV-Integration und keine Tokenprüfung. Mappingpflege verwendet gespeicherte verifizierte Gruppensichten, ohne ihre Frische zu erneuern. Die interne Snapshotschreibmethode ist noch kein öffentlicher Synchronisierungsanwendungsfall. Mehrere Kanten lassen sich nicht als ein atomarer Wechsel ändern. Der Quellenabgleich läuft synchron für alle betroffenen Benutzer einer Kante; eine Lastabnahme großer Verzeichnisse steht aus.

Die Nachbedingungen schützen den aktuellen Zugang zu vier beschriebenen Verwaltungsfähigkeiten, keinen zukünftigen Provider-Login oder unbegrenzte Gruppenfrische. Gruppenlöschung, Notfallzugang und Outbox-Zustellung fehlen weiterhin. Keine Coverage-Messung, umfassende Last-/Nebenläufigkeitsabnahme oder Restore-Prüfung.

Reproduktion nach Einrichtung der Prozessumgebung und isolierter Testdatenbank:

```sh
composer install
composer test
APP_ENV=test composer test:integration
APP_MAINTENANCE=1 composer migrate
```

Konfiguration: database.md. Vorherige Protokolle: verification-iteration-7.md, verification-iteration-6.md, verification-iteration-5.md, verification-iteration-4.md und verification-iterations-2-3.md. Temporäre Testlaufzeiten und Datenbankdateien gehören nicht zum Repository.

## MySQL-Ausgabe

```text
PHP 8.4, lint 79 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 84 ( 77%)
...................                                               84 / 84 (100%)

Time: 00:00.013, Memory: 20.00 MB

OK (84 tests, 172 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:08.002, Memory: 26.00 MB

OK (122 tests, 542 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 79 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 84 ( 77%)
...................                                               84 / 84 (100%)

Time: 00:00.014, Memory: 20.00 MB

OK (84 tests, 172 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:07.135, Memory: 26.00 MB

OK (122 tests, 542 assertions)


8.5 / migrate CLI: Schema is current.
```

## MariaDB-Ausgabe

```text
PHP 8.4, lint 79 files

8.4 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 84 ( 77%)
...................                                               84 / 84 (100%)

Time: 00:00.019, Memory: 20.00 MB

OK (84 tests, 172 assertions)


8.4 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.5
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:05.049, Memory: 26.00 MB

OK (122 tests, 542 assertions)


8.4 / migrate CLI: Schema is current.


PHP 8.5, lint 79 files

8.5 / phpunit.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.xml

................................................................. 65 / 84 ( 77%)
...................                                               84 / 84 (100%)

Time: 00:00.015, Memory: 20.00 MB

OK (84 tests, 172 assertions)


8.5 / phpunit.integration.xml
PHPUnit 13.4.3 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.5.4
Configuration: Samla-Projekt/phpunit.integration.xml

...............................................................  63 / 122 ( 51%)
...........................................................     122 / 122 (100%)

Time: 00:05.185, Memory: 26.00 MB

OK (122 tests, 542 assertions)


8.5 / migrate CLI: Schema is current.
```
