# Entscheidungsvorlage: ZIP-Paketierung für portable Exporte

Stand: 22. Oktober 2026. Vom Betreiber ausdrücklich freigegeben: „ja, du darfst zip verwenden“. Diese Vorlage ist Vorbereitung des vorgesehenen Exportpakets und keine bereits implementierte oder geprüfte Exportfunktion.

## Konkreter Vorschlag

Die native PHP-Erweiterung ext-zip mit ZipArchive soll in der tatsächlich verwendeten PHP-8.4-/8.5-Workerlaufzeit für ZIP-Exporte eingesetzt werden. Ihr PHP-Plattformpaket benötigt libzip. Bei CLI-Cron betrifft die Fähigkeit PHP-CLI, beim Webtrigger PHP-FPM. Ein bereits aktiviertes Modul muss nicht erneut installiert werden; seine tatsächliche Verfügbarkeit wird vor dem Export geprüft.

Der konkrete Zusatzumfang ist diese native ZIP-Fähigkeit einschließlich ihrer vom PHP-/Hostingpaket bereitgestellten libzip-Abhängigkeit. Die bisherigen Datei-, Medien-, OIDC- und DAV-Funktionen dürfen bei fehlender ZIP-Fähigkeit weiterhin funktionieren. Die Exportfunktion bleibt dann ausdrücklich nicht verfügbar.

## Reviewbarer erster Exportumfang

| Bestandteil | Vorgesehenes Verhalten |
| --- | --- |
| Originaldateien | Aktuelle, ausdrücklich ausgewählte und zugängliche Inhaltsstände unter zugeordneten verständlichen Archivnamen |
| manifest.json | Versionskennung des Exportformats, Eintragskennung, Revision, relativer Archivname, Originalname, Größe und SHA-256 sowie jeweils erlaubte exportierbare Metadaten |
| Erweiterungen | Versionierte Beiträge über öffentliche Providerverträge; keine ungeprüften internen Tabellendumps |
| Sensible Angaben | Nur nach den gesonderten Feld-/Exportregeln; keine implizite Prompt-, GPS- oder Gerätekennungsfreigabe |
| Benutzeroberfläche | Geschützter Exportauftrag, nachvollziehbarer Status und eigener autorisierter Download |
| Ausführung | Vorhandener CLI-/HTTPS-Worker; begrenzte Dateianzahl, temporärer privater Speicher, konfigurierbare Größen- und Laufzeitgrenzen |
| Rechte und Änderungen | Erneute Rechte-/Versionsprüfung bei Erstellung und Auslieferung; ein Auftrag friert keine später entzogenen Dateirechte ein |
| Sicherung | Der portable Export ist vom konsistenten Wiederherstellungsbackup getrennt |

Der erste Paketaufbau soll mindestens folgende Zuordnung unterstützen; das Beispiel verwendet ausschließlich fiktive Kennungen:

```json
{
  "format": "samla-export",
  "version": 1,
  "files": [
    {
      "entry_id": "aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa",
      "revision": 2,
      "archive_path": "files/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/beispiel.txt",
      "original_name": "beispiel.txt",
      "size_bytes": 5,
      "sha256": "2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824",
      "metadata": []
    }
  ]
}
```

Das Beispiel beschreibt die fünf UTF-8-Bytes hello. Originale werden beim Export nicht umgeschrieben. Die weitere Umsetzung muss Namen/Kollisionen, private temporäre Dateien, Abbruch, Bereinigung, entziehbare Rechte und wiederholte Workerzustellungen mit echten Fixtures prüfen. Die Freigabe einer Erweiterung allein ist kein Nachweis einer fertigen Exportimplementierung.

## Alternative

Ein Manifest und einzelne autorisierte Dateidownloads lassen sich mit den vorhandenen Schnittstellen anbieten. Ein zusammenhängendes ZIP-Paket wäre dabei zunächst nicht verfügbar. Die Paketierungsentscheidung beeinflusst dieses Transportformat, nicht das übrige Berechtigungsmodell.

## Grundlage und erteilte Freigabe

Konzept Abschnitt 3.7 und 9 verlangen die Abstimmung der konkreten Exportpaketierung und benötigten PHP-Erweiterungen. AGENTS.md schreibt vor: „Keine zusätzlichen Laufzeitkomponenten ohne Abstimmung einführen.“ Der Betreiber hat ext-zip/ZipArchive einschließlich der beschriebenen libzip-Abhängigkeit am 22. Oktober 2026 ausdrücklich freigegeben.

Die folgende Exportimplementierung darf diese native ZIP-Fähigkeit im beschriebenen Umfang verwenden. Die Installation und Prüfung auf dem Betreiberhost stehen noch aus.

Primärquellen: [PHP ZipArchive](https://www.php.net/manual/en/class.ziparchive.php) und [PHP-ZIP-Voraussetzungen](https://www.php.net/manual/en/zip.requirements.php). Eine eigene PHP- oder ZIP-Binärdistribution wird aus dieser Vorlage nicht als bereits installiert oder auf dem Betreiberhost geprüft ausgegeben.
