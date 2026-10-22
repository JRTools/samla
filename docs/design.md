# Samla im JRTools-Design

Die am 22. Oktober 2026 betrachtete öffentliche JRTools-Startseite und deren CSS unter https://jrtools.org/css/main.css dienen als Gestaltungsreferenz. Samla behält seine eigene Navigation und seine Fachfunktionen.

Übernommen sind Schieferblau (#0f172a), hellgrauer Hintergrund (#f1f5f9), weiße Karten, grüne Akzente (#16a34a), kompakte Rundungen und Systemschriften. Kleine Links und weiße Schaltflächenbeschriftungen erhalten das dunklere Grün #15803d für besseren Kontrast; sekundäre Texte #5b6b82. Es werden weder externe Schriften noch Skripte oder Stylesheets vom JRTools-Host geladen. Eine spätere Änderung der JRTools-Seite verändert Samla nicht automatisch.

Gemeinsames Layout und CSS gestalten Anmeldung, Verwaltung, Dateien, Texteditor, Medien und Fehlerseiten. Die Startseite verweist nun auf bestehende Datei- und Medienansichten; die veraltete Ankündigung einer erst noch folgenden Dateiablage entfällt. Administrator-Karten bleiben an ihre bisherigen Rechte gebunden. Links auf Datei-/Medienbereiche vergeben keine zusätzlichen Inhaltsrechte. An der bisherigen Zugangsprüfung der administrativen Startseite ändert diese Gestaltung nichts.

Responsive Regeln erhalten eine schmale Navigation auf kleinen Bildschirmen und horizontal scrollbar lesbare Tabellen. Textfelder, Checkboxen und Dateiauswahl werden getrennt gestaltet; Tastaturfokus und reduzierte Bewegung sind berücksichtigt. Die CSS-URL trägt eine Versionskennung, damit Betreiberupdates nicht unbemerkt eine alte CSS-Datei weiterverwenden.

Die JRTools-Referenz wurde im Browser visuell betrachtet. Die lokale Samla-Vorschau war über den Cloud-Browser nicht erreichbar: Loopback ist getrennt und lokale Datei-URLs werden durch dessen URL-Richtlinie abgelehnt. Diese Sperre wurde nicht umgangen. Eine visuelle Prüfung der geänderten Samla-Seiten in echten Browsergrößen und die vollständige Barrierefreiheitsabnahme bleiben daher offen. PHP-/Datenbank- und HTTP-Prüfungen stehen in verification.md.

Keine neue Migration, Laufzeitabhängigkeit oder Theme-JavaScript. Ein manueller Dark-Mode-Schalter ist nicht Teil dieser Phase.
