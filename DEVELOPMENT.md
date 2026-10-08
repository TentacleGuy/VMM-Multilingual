# Entwicklung

## Phase 0: Installation untersuchen

1. Lokale Testinstallation oder Staging und Wiederherstellungsmöglichkeit festlegen.
2. WordPress, PHP, YOOtheme Parent/Child Theme und aktive SEO-/Cache-Plugins erfassen.
3. Eine Testseite mit Headline, Text, Button und Image erstellen; Page-ID dokumentieren.
4. Builder-Daten vor und nach einer einzelnen Änderung vergleichen. Lade-, Vorschau-, Save-, Autosave- und Revision-Pfade nachvollziehen; keine Zugangsdaten protokollieren.
5. Element- und Item-IDs durch Verschieben, Kopieren, Reload und Undo prüfen.
6. Die konkreten öffentlichen Erweiterungspunkte mit Dateiverweis, Signatur, Version und minimalem reproduzierbarem Beispiel dokumentieren.
7. Erst bei bestätigtem Editor-/Save-Vertrag Phase 1 implementieren. Wenn kein unterstützter Vertrag existiert, die konkrete API-Lücke dokumentieren und mit YOOtheme klären.

## Phase 1: Proof of Concept

Nur de_DE als Master und en_GB als Übersetzung. Implementierung in kleinen Schritten: Plugin-Bootstrap und Migration; Repository; klassifiziertes Overlay; abgesicherte REST-API; verifizierter YOOtheme-Adapter; Sprachauswahl und Vorschau. Noch keine Ausweitung auf Menü-, Widget- oder SEO-Funktionen.

## Pflichtprüfungen vor Phase 2

- DE-Master bleibt nach EN-Änderung und Reload bytegleich in seinen Inhaltsfeldern.
- Beide Sprachen verwenden dieselbe Page-ID und denselben persistenten Baum.
- Headline, Text, Buttontext und Alt-Text wechseln korrekt im Editor und Frontend.
- Fehlender Override nutzt Master; explizit leerer Wert bleibt leer; INHERIT entfernt die Wirkung eines Overrides.
- Eine Layoutänderung im EN-Modus erscheint danach in beiden Sprachen.
- Verschieben erhält Übersetzungen. Duplikate erhalten unabhängige Identitäten.
- Dynamic Bindings bleiben erhalten und werden nicht statisch überschrieben.
- Masteränderung markiert Übersetzung outdated und erhält deren Wert.
- Nicht autorisierte REST-Zugriffe, falsche Nonce, fremde Objekt-ID und ungültige Locale werden abgelehnt.
- Konflikte zwischen zwei Editor-Sitzungen werden erkannt; fehlgeschlagene Saves hinterlassen keinen vermischten Master.
- Anzahl der Translation-Abfragen wächst nicht mit der Anzahl der Felder; Sprachcaches bleiben getrennt.
- Unbekannte YOOtheme-Version verhindert die Schreibintegration verständlich und ohne Datenänderung.

Unit-Tests prüfen Overlay, Identität, Klassifizierung und Hashes. WordPress-Integrationstests prüfen Datenbank, Rechte und Cache. Browser-Tests im echten YOOtheme-Editor sind zwingend; Fixtures allein belegen keine Kompatibilität. Ein synthetischer Beispielbaum darf nicht als echtes YOOtheme-Speicherformat ausgegeben werden.

## Weitere Phasen

Nach bestandenem Phase-1-Gate: Routing; statische Felder und Items; Ressourcen/Links; Menüs; Widgets; SEO/Sitemaps; Switcher-Element; vollständiger Status; Hardening. Jede Phase erhält eigene Integrationstests und dokumentierte Versionsmatrix. Produktive Freigabe erst nach den vollständigen Akzeptanztests der Projektbeschreibung.

## Aktuell ausgeführte Validierung

Dateianalyse von Beispielseite und rein lesende Untersuchung des aufgeteilten SQL-Exports. Der Entwicklungskern in vmm-multilingual besteht 32 Prüfungen, darunter Vertragstests mit den tatsächlichen YOOtheme-PHP-Klassen. Alle eigenen PHP-Dateien bestehen php -l. Die Tests booten WordPress nicht; Live-Builder, Datenbank, REST und Frontend bleiben ungeprüft. Details stehen in docs/YOOTHEME-RESEARCH.md.
