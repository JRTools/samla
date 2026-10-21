# Entwicklung von Samla

- Projektname: Samla. Repository: https://github.com/JRTools/samla.
- Nach jeder Implementierungsphase README.md, docs/Umsetzungsplan_Datei_und_Medienplattform.md und die relevanten Prüfergebnisse aktualisieren.
- Jede abgeschlossene Implementierungsphase im Repository committen und den Commit veröffentlichen, wenn der GitHub-Zugriff verfügbar ist. Einen fehlgeschlagenen Push klar berichten; keine Veröffentlichung behaupten.
- Tests passend zur Änderung ausführen. Nicht ausgeführte Zielmatrix und Integrationsprüfungen ausdrücklich offen lassen.
- PHP 8.4 und 8.5 sowie MariaDB 11.8 und MySQL 8.0 sind Zielplattformen.
- Keine zusätzlichen Laufzeitkomponenten ohne Abstimmung einführen.
- Kern und Plugins verwenden öffentliche Verträge. Der Kern greift nicht auf RBAC-Interna zu.
- Jede neue öffentliche Fachfunktion erhält Rechteprüfung, Erweiterungsverträge und Tests.
- Testdatenbanken müssen isoliert sein. Keine Secrets oder temporären Laufzeiten committen.
