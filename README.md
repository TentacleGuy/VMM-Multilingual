# VMM Multilingual

Eine WordPress-Seite, ein YOOtheme-Layout, mehrere sprachabhängige Inhalte.

## Projektstatus

Stand 8. Oktober 2026. Die lokale Kopie enthält WordPress 7.1.3 und YOOtheme Pro 5.0.46. VMM Multilingual 0.13.0 ist dort installiert und aktiviert. Seitendaten-/SEO-Backend, Switcher und drei Sprach-URL-Modi sind per HTTP geprüft; die native YOOtheme-Sprachleiste ist für den Seiten-Builder implementiert und mit Speichern geprüft. Der reine Overlay-Kern besteht zusätzlich 36 Prüfungen.

Die aktuelle Version unterstützt aktive Sprachen aus dem Katalog, eine wählbare Ausgangssprache, Textübersetzungen, abweichende Bilder/Links/Widgetelemente und eine integrierte Menü-Sprachansicht. Details und verbleibende Grenzen stehen in [vmm-multilingual](vmm-multilingual/README.md). 22 zusätzliche Integrationstests prüfen Ressourcen, Menüs und den Wechsel der Ausgangssprache in einer separaten Datenbankkopie.

Version 0.13.0 ergänzt die geteilte Übersetzungsansicht für Block- und Classic Editor, öffentliche Custom Post Types, Custom-Field-Regeln, Plugin-Stringverwaltung, den Ferienwohnung-Buchungsadapter und Yoast-Ausgabe. 30 weitere Integrationstests prüfen diese Funktionen in der isolierten Datenbank. Bedienung und Integrationsgrenzen sind im Plugin-README dokumentiert.

## Lokaler Testserver

Der lokale Testserver läuft unter http://localhost:8087/. Die eigene Testseite ist http://localhost:8087/vmm-test/ (Page-ID 353). Serverbedienung und Zugangsdatenpfad stehen in [local-server/README.md](local-server/README.md). Der Editor bietet Deutsch/English direkt im Builder. Alle drei vorhandenen Builder-Seiten wurden mit stabilen IDs vorbereitet.

## Dokumentation

- [Architektur](ARCHITECTURE.md): geplante Grenzen und Datenmodell.
- [Entwicklung](DEVELOPMENT.md): Untersuchung, Freigabekriterien und Testplan.
- [YOOtheme-Untersuchung](docs/YOOTHEME-RESEARCH.md): belegte APIs und offene Fragen.






Plugin-Inhalte umfassen jetzt auch E-Mail- und PDF-Vorlagen des Buchungsplugins, Signaturen, Rechnungshinweise und Bilder. Die Bereichsauswahl trennt Website, E-Mails, PDFs, Textbausteine und Systemtexte.

Die universelle Plugin-Übersetzung bietet eine gemeinsame Inhaltsregistrierung und eine zeitlich begrenzte Erfassung tatsächlicher WordPress-Übersetzungsaufrufe. Alle Anbindungen erscheinen in derselben Liste.
