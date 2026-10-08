# VMM Multilingual 0.13.0 – technische Testversion

Eine gemeinsame YOOtheme-Seitenstruktur mit sprachabhängigen Inhalten. Lokal geprüft mit WordPress 7.1.3 und YOOtheme Pro 5.0.46. Der Theme-Core bleibt unverändert.

## Bedienung

VMM → Übersetzungen verwaltet normale Seiten, Beiträge und öffentliche Custom Post Types unter derselben Post-ID. Die Ausgangssprache bleibt im gewohnten WordPress-Editor bearbeitbar. Sowohl Blockeditor als auch Classic Editor erhalten die Metabox „VMM · Übersetzungen“. Erst den Ausgangsinhalt speichern, dann die gewünschte Sprachversion öffnen. Links steht die Ausgangssprache, rechts die Übersetzung. Klassische Inhalte erhalten Visuell-/Code-Ansicht und Mediathek; Gutenberg-Inhalte bleiben Blöcke und werden mit dem BlockEditorProvider bearbeitet. Übernehmen entfernt die Abweichung und folgt späteren Änderungen. Kopieren erzeugt einen eigenständigen Wert. Status und öffentliche Verfügbarkeit gelten pro Sprache. Veraltete Quellwerte und parallele Speicherstände werden erkannt.

Die Ansicht umfasst Titel, Inhalt, Auszug, Beitragsbild, Alt-Text, Bildunterschrift, Slug, SEO-/Social-Daten und Custom Fields. VMM → Feldregeln bestimmt gemeinsame beziehungsweise übersetzbare Felder je Inhaltstyp. Geschützte Felder und numerische Werte bleiben standardmäßig gemeinsam. Einfache Felder sowie verschachtelte Textwerte in einzelnen Array-Metafeldern werden unterstützt; numerische IDs und Array-Strukturen bleiben erhalten. Feldsysteme mit eigener Speicherung oder Mehrfach-Metazeilen benötigen eine zusätzliche Integration. Die geteilte Gutenberg-Ansicht arbeitet mit einer lesbaren Ausgangsvorschau und einem aktiven Ziel-Blockeditor; sie ist kein zweiter vollständiger WordPress-Posteditor. Fremde Blöcke müssen ihre Editor-Komponenten auf dieser Verwaltungsseite registrieren.

VMM → Plugin-Inhalte bietet Plugin-/Sprachauswahl, Suche, Übernahme/Abweichung und eine Liste mit 60 Einträgen je Seite. Erkannte statische PHP-gettext-Texte einschließlich Kontext und Pluralformen sowie JavaScript-__/_x-Literale werden unterstützt. JavaScript-Ausgaben benötigen WordPress-i18n und eine entsprechende Script-Abhängigkeit; dynamische, umbenannte oder spät nachgeladene Skripte benötigen Adapter. Backend-Ausgaben bleiben unverändert. Der Scan ist keine vollständige Erfassung aller sichtbaren Inhalte beliebiger Plugins. `vmm_plugin_catalog` ergänzt gezielt angebundene Inhalte.

Für Ferienwohnung-Buchung 1.5.1 besteht ein Adapter für Formulartexte, Optionen, Hinweise, Kalender-/Systemmeldungen, Frontend-Textbausteine, Datenschutz-Link sowie Bilder und Links innerhalb von Formular-HTML. Bilder können über die Mediathek abweichend ausgewählt werden. Vorhandene eigene Formular-/Einstellungsübersetzungen sowie die mitgelieferten englischen Systemtexte werden einmalig übernommen. Die optionalen `fwb_i18n_*`-Hooks wurden in der lokalen Plugin-Kopie ergänzt; ohne VMM behalten sie das vorhandene Verhalten. Der reproduzierbare Patch liegt unter `integrations/booking-hooks.patch`; bei einem Buchungsplugin-Update müssen die Hooks erhalten beziehungsweise erneut eingespielt werden. Fehlende Hooks werden in VMM angezeigt. E-Mail-Betreffzeilen, Nachrichtenvorlagen, Signatur, Gastgeber-Benachrichtigung, PDF-Kopf-/Fußzeilen, Rechnungstext, Rechnungshinweis und Logo sind ebenfalls angebunden. Bilder in E-Mail-/PDF-HTML sind übersetzbar; PDFs betten ausschließlich lokale PNG/JPEG-Bilder aus der Mediathek bis 3 MB ein. Bestehende Buchungssprache steuert die Ausgabe auch im Backend und bei Hintergrundjobs. Bereits ausgestellte PDFs und historische Buchungsbedingungen werden erhalten. Buchungsdaten und Preise bleiben gemeinsam.

Yoast-Ausgabe verwendet sprachabhängige Titel, Beschreibungen, Social-Daten, Canonical und Schema-Angaben. Yoast-Post-Sitemaps erhalten die verfügbaren Sprach-URLs. Die native Yoast-Inhaltsanalyse wird in der separaten Übersetzungsansicht nicht ausgeführt.

Unter VMM → Einstellungen → Sprachen aktive Sprachen ankreuzen und die Ausgangssprache wählen. Sie liefert die Standardwerte für Builder-Inhalte, Bilder, Links, Widgets, Menübeschriftungen und Seitendaten. Beim Wechsel wird die bisherige Ausgangssprache mit ihren vorhandenen Werten als eigene Sprachversion erhalten. Danach geöffnete Editoren neu laden. Die Ausgangssprache verwendet die Basisadresse; andere Sprachen folgen dem gewählten URL-Modus.

Im YOOtheme-Seiten-Builder bleibt die Sprachwahl unten rechts erreichbar. Für übersetzte Seiten bieten Bildfelder einschließlich Hintergrundbildern, Linkfelder und Widgetelemente die Schalter „Abweichendes Bild“, „Abweichender Link“ und „Abweichendes Widget“. Ausgeschaltet wird der aktuelle Wert der Ausgangssprache übernommen. Eingeschaltet erscheint die native YOOtheme-Auswahl. Ausschalten und Speichern entfernt die Abweichung. Text und Alt-Attribute bleiben separat übersetzbar. Dynamische Datenbindungen bleiben geschützt. Layout, Reihenfolge und Elemente sind gemeinsam.

Unter Design → Menüs das gemeinsame Ausgangsmenü auswählen und links die Bearbeitungssprache wählen. Pro Eintrag: Übernehmen, Anpassen (Beschriftung und/oder Ziel) oder Nicht anzeigen. Ausgeblendete Eltern blenden ihre Unterpunkte aus. Neue Einträge in einer Übersetzung sind nur dort sichtbar; neue Einträge in der Ausgangssprache gehören zur gemeinsamen Grundstruktur. Reihenfolge und Hierarchie bleiben gemeinsam. „Entfernen“ bei einem gemeinsamen Eintrag in einer Übersetzung blendet ihn nur dort aus. Einträge ohne abweichendes Ziel verlinken interne Seiten automatisch in der gewählten Sprache. Bereits vorhandene separate Sprachmenüs werden nicht automatisch zusammengeführt.

Native Widgetbereiche behalten ihre Einstellung „Anzeigen in“. Ein explizit ausgewähltes YOOtheme-Widgetelement verwendet dagegen seine eigene sprachabhängige Auswahl; der Bereichsfilter verhindert dessen Ausgabe nicht.

VMM → Seitendaten enthält Seitentitel, Slug und SEO-/Social-Daten. Die weiteren Einstellungsreiter enthalten Bestandteile/Anordnung/Position des Sprachswitchers sowie Parameter-, Verzeichnis- oder Subdomain-URLs und DNS-Hilfe. Infrastrukturänderungen werden beim Hosting vorgenommen.

## Speicherung und Prüfung

Der persistente Builder-Baum hält Layout und ursprüngliche Basiswerte. Sprachwerte werden für sämtliche Sprachen einschließlich der Ausgangssprache als versionierte Overlays gespeichert. Die Ausgabe wendet zuerst die Ausgangssprache, dann die gewählte Sprachversion an. Explizite Ressourcenabweichungen werden auch dann gespeichert, wenn ihr Wert gerade mit dem Original übereinstimmt. Übernahme entfernt den jeweiligen Overlay-Eintrag. Änderungen der Ausgangssprache oder ihrer Inhalte machen veraltete Speichervorgänge ungültig.

- 36 reine Kernprüfungen: `php vmm-multilingual/tests/run.php`.
- 22 Integrationstests: `local-server/verify-resources-source.php`, ausschließlich in `vmm_verify_20261008`, einer separaten Kopie der lokalen Datenbank. Prüft Ressourcen, Hintergrundbilder, Widgetfelder, Übernahme/Zurücksetzen, Frontend, Speicherkonflikte, Menüs und Ausgangssprachenwechsel.
- 30 Integrationstests: `local-server/verify-native.php`, ebenfalls ausschließlich isolierte Datenbank. Native Inhalte, Blockserialisierung, Sprachisolation, verschachtelte Custom Fields, geschützte Felder, Slugs, Verfügbarkeit, Kontext-/JS-Texte, Pluralregeln, Ressourcen, Platzhalter, Rechte und Ausgangssprachenwechsel.
- Browserprüfung 0.13.0: Classic Visuell/Code, Gutenberg-Blöcke, Speichern/Reload, Übersetzungseinstieg im Classic Editor, Plugin-Formularspeicherung, tatsächliche Buchungs-Frontendmeldungen und englische Beitrags-/SEO-Ausgabe.
- Browserprüfung: native Bildauswahl, Widgetauswahl, YOOtheme-Speichern, Zurücksetzen, Menüeintrag nur für Englisch, Persistenz und Abwesenheit in Deutsch.

Die Sprachleiste ist an den YOOtheme-Seiten-Builder gebunden. Globale Theme-Layouts und Templates haben weiterhin keine eigene VMM-Sprachbearbeitung. Weitere Drittanbieter-Ausgaben benötigen je nach Speicherung und Ausgabetechnik passende Integrationen. Diese Version ist ein lokaler Entwicklungsstand; weitere YOOtheme-Versionen und ein Live-Einsatz benötigen gesonderte Prüfung.


### Plugin-Inhalte in allen Ausgabebereichen (0.13.0)

Die kompakte Liste lässt sich nach Website, E-Mails, PDFs, Textbausteinen und Systemtexten filtern. Gettext-Erkennung schließt Verwaltungsdateien ein. Allgemeine Übersetzungen folgen der Seitensprache beziehungsweise der Benutzersprache im Backend. Integrationen können `PluginTranslations::run($locale, $callback)` für empfängerbezogene E-Mail-/PDF-Erzeugung verwenden; verschachtelte Kontexte werden wiederhergestellt. Beliebige hart codierte Texte, fremde JavaScript-Frameworks und Plugin-Datenbanken sind nicht universell erfassbar und brauchen Adapter. Der Buchungsadapter importiert bestehende Vorlagenübersetzungen einmalig, ohne VMM-Änderungen zu überschreiben.

### Visueller Frontend-Editor (0.14.0)

Auf einer Seite oder einem Beitrag in der WordPress-Werkzeugleiste „Visuell übersetzen“ wählen. Alternativ steht der Link in der VMM-Metabox des WordPress-Editors. Links bleibt die Ausgangssprache sichtbar, rechts werden die zugeordneten Übersetzungsfelder bearbeitet. Die Zielübersetzung öffnet der Vorschau-Link in einem eigenen Tab. Änderungen werden pro ausgewähltem Element gespeichert; ein Sprach-/Elementwechsel fragt bei ungespeicherten Änderungen nach.

YOOtheme verwendet dieselben Element-IDs, Feldregeln und Overlay-Dokumente wie der Builder. `EditorService::saveFields()` speichert ausschließlich die geprüften Felder; der Layoutbaum wird dabei nicht neu gerendert oder normalisiert. Text, Bild, Link, Alt-Text, Link-ARIA-Beschriftung und weitere im Elementschema erlaubte Inhaltsfelder stehen zur Verfügung. Links, Bilder und zugängliche Beschriftungen innerhalb eines HTML-Inhaltsfeldes bleiben Teil desselben Originalfelds. Dynamische Builder-Quellen bleiben geschützt.

Native Beiträge verwenden `_vmm_content` wie der normale/classic Übersetzungseditor. Ein Inhaltsbereich öffnet das komplette vorhandene Inhaltsfeld (HTML); er wird nicht in unabhängige, konkurrierende Textübersetzungen aufgeteilt. Seitenfelder und SEO-Metadaten sind über „Seitenfelder“ erreichbar.

Plugin-Shortcodes, Widgets und dynamische Blöcke erhalten beim Öffnen der Ausgangsvorschau elementbezogene Feldadressen. Diese stehen auch in „Plugin-Inhalte“ und verwenden denselben Speicher-/Validierungsdienst. ARIA-Beschriftung, ARIA-Beschreibung, Bild-Alt-Text, Titel, Platzhalter, Bildquelle und Linkziel werden getrennt geführt. Bei Formularfeldern werden zusätzlich zugeordnete Labels und Texte aus `aria-labelledby`/`aria-describedby` angeboten. Eingabewerte, Skripte und technische Referenz-IDs werden nicht übersetzt. Plugin-Felder benötigen Administratorrechte; Seitenfelder die jeweilige Bearbeitungsberechtigung. Revisionen und Ausgangshashes schützen vor veralteten Schreibzugriffen. Anbindungen können über `vmm_visual_plugin_binding($key, $plugin, $domElement, $slot, $context)` einen vorhandenen Katalogschlüssel zurückgeben; damit wird exakt das bestehende Backend-Feld bearbeitet. Die Buchungsanbindung nutzt dies für Formularbeschriftungen und Platzhalter. Die Zuordnung basiert auf Block-ID/Feldart, nicht auf einem Vergleich gleichlautender Texte.

Grenzen: Automatische Plugin-Adressen sind aus Renderer-Kontext, Ausgabeinstanz und DOM-Pfad abgeleitet. Nach strukturellen Änderungen des Plugins muss die Zuordnung neu geprüft werden. Sie sind keine vom Drittanbieter dauerhaft garantierten Element-IDs. Bereits angebundene Plugin-Vorlagen und neu erkannte Elementfelder können parallel existieren; automatische Zusammenführung anhand identischer Texte findet absichtlich nicht statt. Noch nicht zugeordnete, nachträglich erzeugte Elemente, globale Theme-Bereiche, fremde Iframes und Canvas-Inhalte sind nicht uneingeschränkt visuell bearbeitbar. Nicht zugeordnete Elemente zeigen ihre Informationen und einen Hinweis. Der Editor ist auf das Frontend begrenzt; PDF-/E-Mail-Vorlagen bleiben in der Inhaltsliste.

### Gemeinsame Inhaltsregistrierung (0.13.0)

### Ausgabeerfassung

Zusätzlich zu gettext erfasst VMM bei aktiver Administrator-Erfassung Plugin-eigene Shortcode-Ausgaben, dynamische Blöcke und Widgets. HTML-Texte, Beschriftungen, Platzhalter, Bildadressen und Links erscheinen unter „Beim Durchsehen erfasst“. Die Website muss dazu in der Ausgangssprache geöffnet werden. Shortcodes, Widgets und dynamische Blöcke erhalten einen Container mit `display:contents`; dessen MutationObserver erkennt nachträglich erzeugte Texte und wendet gespeicherte Übersetzungen auch nach einer Neuzeichnung an. Die Zuordnung erfolgt über den Plugin-Pfad des Render-Callbacks, Kontext, Inhaltstyp und Originaltext. Die Erfassung endet nach 15 Minuten. Eingabewerte, Textarea-Inhalte, Skripte und Code werden nicht erfasst. Pro Anfrage sind höchstens 500 und pro Plugin 3.000 Einträge vorgesehen.

Erfassung und Übersetzung sind auf diese Ausgabewege begrenzt. Unabhängige Plugin-Ausgaben außerhalb der Container, externe Iframes, Canvas-Inhalte sowie fertige PDF-Dateien werden nicht automatisch erschlossen. Dynamisch berechnete Texte ergeben Einträge für die tatsächlich gesehenen Werte; für wiederkehrende variable Texte sind registrierte Vorlagen mit Platzhaltern vorzuziehen. E-Mail-/PDF-Plugins sollten Vorlagen vor dem Einsetzen persönlicher Daten registrieren oder ihren HTML-Ausgabeweg an den gemeinsamen Filter anbinden:

```php
$html = PluginTranslations::run($recipientLocale, static fn() =>
    apply_filters('vmm_plugin_output', $templateHtml, 'example/example.php', 'Rechnung', 'pdf'), 'pdf');
```

Der Filter wird vor der PDF-Erzeugung aufgerufen; für E-Mails lautet der Bereich `email`. Die Vorlage muss beim Erfassen in der Ausgangssprache vorliegen. Für Vorlagen, die unabhängig vom Besuch vollständig in der Liste stehen sollen, ist `PluginContent::register()` der zuverlässigere Weg. VMM verändert keine fertigen PDF-Binärdaten und speichert nicht automatisch persönliche E-Mail-Nachrichten.

Die Inhaltsliste bietet Reiter für Beschriftungen, Inhalte/Hinweise, Hilfetexte/Eingabehinweise, Meldungen, Vorlagen und Bilder/Links. Innerhalb eines Reiters werden Einträge nach Element oder Kontext gruppiert. Der Herkunftsfilter unterscheidet Dateiscan, Erfassung beim Durchsehen und Anbindungen; ein Text kann mehrere Herkünfte haben. Leere Ausgangsfelder werden standardmäßig ausgeblendet, bereits vorhandene Übersetzungen bleiben sichtbar. Suche, Bereich und Herkunft wirken gemeinsam auf die Reiter und bleiben beim Blättern und Speichern erhalten.

Alle Plugins verwenden dieselbe Oberfläche. Die optionale Erfassung läuft für 15 Minuten ausschließlich bei Aufrufen des Administrators, der sie gestartet hat. Sie erfasst tatsächliche PHP-gettext-Aufrufe sowie die oben beschriebenen Plugin-Ausgaben mit Zuordnung und Kontext; eine allgemeine Datenbank-Erfassung findet nicht statt. Erfassung beendet sich automatisch oder über den Button. Kataloge sind auf 3.000 erfasste Einträge je Plugin begrenzt.

Plugins können Inhalte unabhängig vom Speicherort registrieren und beim Ausgeben abfragen:

```php
use VMM\Multilingual\Site\PluginContent;
use VMM\Multilingual\Site\PluginTranslations;
add_action('vmm_register_plugin_contents', static function () {
    PluginContent::register('example/example.php', 'mail.subject',
        static fn() => (string) get_option('example_mail_subject', ''),
        ['label' => 'E-Mail · Betreff', 'type' => 'text', 'area' => 'email']);
});
// Die Integration übergibt die gespeicherte Sprache des Empfängers.
$subject = PluginTranslations::run($recipientLocale,
    static fn() => PluginContent::value('example/example.php', 'mail.subject'), 'email');
```

Typen: text, html, url, image (Bild-URL). Bereiche: website, email, pdf, shared, system. Kataloganbieter können alternativ PluginContent::provider($pluginFile, $callback) verwenden. Bestehende Inhalte und Übersetzungen behalten ihre Schlüssel; der interne Buchungsanbieter nutzt ebenfalls diesen gemeinsamen Weg. Registrierung allein ersetzt noch keine Ausgabe: das jeweilige Plugin muss den Wert beim Rendern abfragen.

### Nachgeladene Elemente im visuellen Editor
Administratoren können noch nicht zugeordnete Elemente innerhalb erkannter Plugin-Ausgaben anklicken. Direkte Textknoten und vorhandene Bild-, Link-, Titel-, Platzhalter- und ARIA-Attribute werden dabei mit derselben Elementadresse wie die serverseitige Erfassung im gemeinsamen Plugin-Katalog registriert. Die Erfassung benötigt keine laufende allgemeine Scan-Sitzung. Eingabewerte und bearbeitbare Benutzerinhalte werden nicht erfasst.

Zum Auslösen nachgeladener Inhalte zunächst „Navigieren“ aktivieren, die gewünschte Ansicht laden und danach mit „Elemente bearbeiten“ zurückwechseln. Die Übersetzung erscheint in der Zielsprache; das Editorfenster zeigt weiterhin die Ausgangssprache. Die Elementadresse besteht aus Plugin, Ausgabe-Kontext, Instanz und DOM-Pfad. Änderungen an der Plugin-Struktur oder wechselnde Inhalte am selben DOM-Pfad benötigen eine erneute Prüfung. Fremde iFrames, Shadow DOM und Ausgaben ohne erkennbaren Plugin-Bereich sind weiterhin nicht automatisch zugeordnet. Normale WordPress-Inhalte bleiben im gemeinsamen Inhaltsfeld, damit Block- und Classic-Editor dieselben Daten verwenden.

### Updates über GitHub (ab 0.15.0)
WordPress liest die öffentliche Datei `https://github.com/TentacleGuy/VMM-Multilingual/releases/latest/download/update.json` und bietet neuere Versionen unter Plugins bzw. Dashboard → Aktualisierungen an. Einmalig diese ZIP installieren; ältere Versionen ohne Update-Anbindung müssen manuell aktualisiert werden. Automatische Installation bleibt eine WordPress-Einstellung und wird nicht eigenständig aktiviert. Die Prüfung wird sechs Stunden zwischengespeichert; „Erneut prüfen“ lädt die Metadaten neu. Bei Netzfehlern bleibt die installierte Version unverändert. Nur versionierte ZIP-Dateien aus diesem Repository werden akzeptiert. Es werden keine Zugangsdaten oder Seiteninhalte übertragen.

Neue Releases mit `tools/build-release.ps1 -Version x.y.z` aus dem Repository-Stamm bauen (Plugin-Header vorher anpassen). Die erzeugte ZIP, SHA256-Datei und `update.json` als Assets eines öffentlichen, regulären GitHub-Releases mit Tag `vx.y.z` hochladen. Keine GitHub-Quellcode-ZIP verwenden. Die ZIP enthält genau den installierbaren Ordner `vmm-multilingual`; Tests und lokale WordPress-Daten sind ausgeschlossen.
