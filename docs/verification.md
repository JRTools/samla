# Prüfung Iteration 20

Die Änderungen betreffen ausschließlich GitHub-Workflow, fixierte Python-Testabhängigkeit und Dokumentation. Die produktive Laufzeit bleibt unverändert zum erfolgreich geprüften Stand der Iteration 19: vier Kombinationen mit jeweils 376 Tests und 1484 Assertions, 173 eigene PHP-Dateien sowie echte HTTP-/HTTPS-Abläufe.

Workflow-YAML mit PyYAML 6.0.3 als YAML-Struktur geprüft: Push/Pull-Request/manuelle Trigger, zwei PHP-Versionen mal zwei Datenbanken, vollständige 40-stellige Actionpins, ausschließlich contents:read und Checkout ohne persistierte Credentials. Beide Actionkennungen wurden über die offiziellen GitHub-Quellen aufgelöst und action.yml gelesen. Die vorhandene lokal verwendete cryptography-Version 50.0.2 wird separat nur für die isolierte HTTP-Testumgebung fixiert. Kein zusätzlicher produktiver Laufzeitcode.

Der erste veröffentlichte Lauf 39692714418 wurde vor Jobanlage wegen einer ungültigen Kontextposition abgewiesen: jobs.matrix.env konnte job.services.database.ports noch nicht auswerten. Die öffentliche Annotation wurde gelesen. Der Port wird nun in einem Ausführungsschritt aus dessen gültigem job-Kontext in GITHUB_ENV übernommen. Die YAML-Struktur wurde erneut gelesen. Ein erfolgreicher Folge-Lauf wird erst nach tatsächlicher Ausführung behauptet.

Referenznachweise und vollständige Anwendungsmatrix: verification-iteration-19.md. Native Clients und vollständige Crash-/Last-/Restore-Abnahme bleiben offen.
