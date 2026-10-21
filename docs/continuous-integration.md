# Automatische Prüfungen

Der Workflow `.github/workflows/tests.yml` startet bei Push auf main, Pull Requests und manueller Ausführung. Vier isolierte Jobs kombinieren PHP 8.4/8.5 mit MySQL 8.0/MariaDB 11.8. Die Container verwenden jeweils die aktuelle Patchversion des angegebenen Minor-Tags; konkrete erfolgreiche Versionen stehen im Laufprotokoll. Die zuvor lokal nachgewiesene Matrix bleibt im Prüfprotokoll getrennt dokumentiert.

Jeder Job installiert ausschließlich composer.lock mit gesperrten Composer-Plugins und Scripts, prüft Manifest und PHP-Plattform, Syntax, Unit-/Architektur- und Datenbanktests, anschließend die aktuelle Migrations-CLI. Eine separate virtuelle Python-Umgebung mit `tests/Tools/requirements.txt` stellt den vorhandenen TLS-Testprovider bereit. Der HTTP-Prüfer läuft unter derselben PHP-Version sowohl über lokales HTTP als auch mit HTTPS-Produktionskonfiguration. Dabei werden ausschließlich synthetische Identitäten und Testzugänge benutzt.

Die Datenbank heißt media_platform_test_ci. Das Konto hat Zugriff auf diese Datenbank; veröffentlichte Credentials sind bewusst ausschließlich Testwerte für den kurzlebigen Servicecontainer. Es gibt keine Produktionsdaten, keinen Zugriff auf den echten ZITADEL und kein automatisches Deployment. Pull Requests werden mit pull_request ausgeführt, nicht im privilegierten pull_request_target-Kontext. GITHUB_TOKEN hat nur contents: read. Checkout persistiert keine Zugangsdaten, beide Actions sind auf vollständige Commitkennungen fixiert. Kein Cache mit Zugangsdaten oder Testartefakten wird gespeichert.

Die Actionkennungen wurden an den offiziellen Quellrepos überprüft:

Die Rollbacktests erzeugen gezielt temporäre Fehler-Trigger. Im kurzlebigen MySQL-CI-Container wird dafür vor den Integrationstests einmalig log_bin_trust_function_creators aktiviert. Der Befehl läuft lokal im Container als dessen Test-root. Das Anwendungskonto behält ausschließlich seine Datenbankrechte. Diese Einstellung ist keine Anforderung oder Empfehlung für den Produktivserver; Samlas produktive Migrationen verwenden diese Fehler-Trigger nicht.

- [actions/checkout v7.0.1](https://github.com/actions/checkout/tree/3d3c42e5aac5ba805825da76410c181273ba90b1)
- [shivammathur/setup-php v2](https://github.com/shivammathur/setup-php/tree/eb7c497e18156a6bbabfef1d3a82760b9eda3962)

Der Workflow benötigt aktivierte GitHub Actions sowie die Freigabe dieser beiden Actions in den Repository-Richtlinien. Die zusätzliche Python-Abhängigkeit ist ausschließlich ein bereits lokal verwendetes Testwerkzeug, keine Laufzeitkomponente der Anwendung. Ein syntaktisch geprüftes Workflowdokument allein ist kein erfolgreicher GitHub-Lauf. Laufstatus und Fehler werden nach Veröffentlichung gesondert kontrolliert und dokumentiert.

Der Workflow beweist keine native Gerätekompatibilität, vollständige Crash-/Wiederherstellungsfestigkeit oder definierte Leistungsziele. Solche Nachweise bleiben eigene Abnahmepunkte.

Erste vollständige Abnahme: [Lauf 39693200739](https://github.com/JRTools/samla/actions/runs/39693200739) zum Commit cd92c7df05fba354f188a757052ade6649576d88. Alle vier Jobs erfolgreich, PHP 8.4.27/8.5.12 mit MySQL 8.0.46/MariaDB 11.8.9. Jeweils 166 Unit-/Architekturtests mit 538 Assertions und 210 Integrationstests mit 946 Assertions sowie der echte HTTP- und HTTPS-Ablauf. Die Joblogs wurden gelesen; vorherige Kontext-/Triggerfixture-Fehler sind im Prüfprotokoll dokumentiert.
