# VMM Multilingual 0.10.0 – technische Testversion

Eine gemeinsame YOOtheme-Seitenstruktur mit sprachabhängigen Inhalten. Lokal geprüft mit WordPress 7.1.3 und YOOtheme Pro 5.0.46. Der Theme-Core bleibt unverändert.

## Bedienung

Unter VMM → Einstellungen → Sprachen aktive Sprachen ankreuzen und die Ausgangssprache wählen. Sie liefert die Standardwerte für Builder-Inhalte, Bilder, Links, Widgets, Menübeschriftungen und Seitendaten. Beim Wechsel wird die bisherige Ausgangssprache mit ihren vorhandenen Werten als eigene Sprachversion erhalten. Danach geöffnete Editoren neu laden. Die Ausgangssprache verwendet die Basisadresse; andere Sprachen folgen dem gewählten URL-Modus.

Im YOOtheme-Seiten-Builder bleibt die Sprachwahl unten rechts erreichbar. Für übersetzte Seiten bieten Bildfelder einschließlich Hintergrundbildern, Linkfelder und Widgetelemente die Schalter „Abweichendes Bild“, „Abweichender Link“ und „Abweichendes Widget“. Ausgeschaltet wird der aktuelle Wert der Ausgangssprache übernommen. Eingeschaltet erscheint die native YOOtheme-Auswahl. Ausschalten und Speichern entfernt die Abweichung. Text und Alt-Attribute bleiben separat übersetzbar. Dynamische Datenbindungen bleiben geschützt. Layout, Reihenfolge und Elemente sind gemeinsam.

Unter Design → Menüs das gemeinsame Ausgangsmenü auswählen und links die Bearbeitungssprache wählen. Pro Eintrag: Übernehmen, Anpassen (Beschriftung und/oder Ziel) oder Nicht anzeigen. Ausgeblendete Eltern blenden ihre Unterpunkte aus. Neue Einträge in einer Übersetzung sind nur dort sichtbar; neue Einträge in der Ausgangssprache gehören zur gemeinsamen Grundstruktur. Reihenfolge und Hierarchie bleiben gemeinsam. „Entfernen“ bei einem gemeinsamen Eintrag in einer Übersetzung blendet ihn nur dort aus. Einträge ohne abweichendes Ziel verlinken interne Seiten automatisch in der gewählten Sprache. Bereits vorhandene separate Sprachmenüs werden nicht automatisch zusammengeführt.

Native Widgetbereiche behalten ihre Einstellung „Anzeigen in“. Ein explizit ausgewähltes YOOtheme-Widgetelement verwendet dagegen seine eigene sprachabhängige Auswahl; der Bereichsfilter verhindert dessen Ausgabe nicht.

VMM → Seitendaten enthält Seitentitel, Slug und SEO-/Social-Daten. Die weiteren Einstellungsreiter enthalten Bestandteile/Anordnung/Position des Sprachswitchers sowie Parameter-, Verzeichnis- oder Subdomain-URLs und DNS-Hilfe. Infrastrukturänderungen werden beim Hosting vorgenommen.

## Speicherung und Prüfung

Der persistente Builder-Baum hält Layout und ursprüngliche Basiswerte. Sprachwerte werden für sämtliche Sprachen einschließlich der Ausgangssprache als versionierte Overlays gespeichert. Die Ausgabe wendet zuerst die Ausgangssprache, dann die gewählte Sprachversion an. Explizite Ressourcenabweichungen werden auch dann gespeichert, wenn ihr Wert gerade mit dem Original übereinstimmt. Übernahme entfernt den jeweiligen Overlay-Eintrag. Änderungen der Ausgangssprache oder ihrer Inhalte machen veraltete Speichervorgänge ungültig.

- 36 reine Kernprüfungen: `php vmm-multilingual/tests/run.php`.
- 22 Integrationstests: `local-server/verify-resources-source.php`, ausschließlich in `vmm_verify_20261008`, einer separaten Kopie der lokalen Datenbank. Prüft Ressourcen, Hintergrundbilder, Widgetfelder, Übernahme/Zurücksetzen, Frontend, Speicherkonflikte, Menüs und Ausgangssprachenwechsel.
- Browserprüfung: native Bildauswahl, Widgetauswahl, YOOtheme-Speichern, Zurücksetzen, Menüeintrag nur für Englisch, Persistenz und Abwesenheit in Deutsch.

Die Sprachleiste ist an den YOOtheme-Seiten-Builder gebunden. Globale Theme-Layouts und Templates haben weiterhin keine eigene VMM-Sprachbearbeitung. Drittanbieter-Shortcodes, Buchungsdaten, E-Mails und JavaScript-Texte benötigen passende Integrationen. Diese Version ist ein lokaler Entwicklungsstand; weitere YOOtheme-Versionen und ein Live-Einsatz benötigen gesonderte Prüfung.
