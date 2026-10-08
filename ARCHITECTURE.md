# Architekturentwurf

Status: Entwurf; YOOtheme-Verträge sind noch anhand der konkreten Installation zu verifizieren.

## Invarianten

Es gibt genau eine WordPress-Objekt-ID und einen persistenten Master-Builder-Baum. Übersetzungen speichern ausschließlich inhaltliche Overrides. Baumkopien dürfen nur vorübergehend im Speicher für Vorschau und Rendering entstehen. Layoutwerte gehören immer zum Master. Sprachwechsel erzeugen weder Seiten noch persistente Layoutkopien.

## Language Context

LocaleContext unterscheidet öffentliche Sprache, Bearbeitungssprache und Master-Locale. Die öffentliche Sprache ergibt sich aus einer validierten Route; die Bearbeitungssprache aus einem autorisierten Editor-Kontext. Ein Benutzerwechsel darf keine globale Option verändern. Ungültige oder deaktivierte Locales werden abgelehnt. WordPress-Admin-Locale und Inhaltssprache bleiben getrennt.

## Translation Overlay

TranslationManager lädt alle Overrides für Objekt-ID und Locale gesammelt. Overlay-Schlüssel bestehen aus Objekttyp, Objekt-ID, Element-UUID, optionaler Item-UUID und einem Feldschlüssel. Arraypositionen sind keine Identitäten. Existierende IDs müssen erst auf Stabilität bei Speichern, Verschieben, Kopieren und Undo geprüft werden.

INHERIT nutzt den Master. TRANSLATE ersetzt einen Inhaltswert. REPLACE ersetzt eine Ressource. Fehlende Overrides fallen auf den Master zurück; ein explizit leerer übersetzter String bleibt leer. Ressourcen-Ziel und beschreibende Metadaten sind unabhängig. Optionale Sprachfallbacks benötigen Zyklenerkennung.

Ein Source Hash wird aus typisiertem, deterministisch serialisiertem Masterinhalt gebildet. Abweichungen markieren vorhandene Übersetzungen als outdated, ohne sie zu löschen. Bei INHERIT wird der Fortschritt separat ausgewiesen, damit eine neue Sprache nicht automatisch als vollständig übersetzt gilt.

## YOOtheme Adapter

Nur Integrations/Yootheme kennt YOOtheme-Klassen, Datenformate und Lifecycle. Vorgesehene Komponenten: YoothemeAdapter, BuilderParser, FieldClassifier, TranslationOverlay und VersionCompatibility.

Der Adapter muss folgende Verträge nachweisen: Master laden, stabile Identitäten lesen bzw. unterstützt persistieren, Feldschema lesen, Editor-Sprache wechseln, Overlay zur Vorschau anwenden und Speichern abfangen. Eine öffentliche API für alle diese Verträge ist bisher nicht nachgewiesen.

Contentfelder werden aus verifizierten Elementkonfigurationen klassifiziert. Ein Textfeldtyp allein genügt nicht: Auch CSS und Layoutoptionen können Strings sein. Unbekannte Felder bleiben global; dynamische Quellen werden nicht durch statische Texte ersetzt. Erweiterungen können die Klassifizierung gezielt ergänzen.

Beim EN-Speichern muss der Server den autoritativen Master mit der Editor-Basis vergleichen. Übersetzbare Änderungen gehen in die Overlay-Tabelle, Layoutänderungen in den Master. Die vollständige englische Vorschau darf niemals direkt als Master gespeichert werden. Revisionen bzw. optimistische Versionsprüfungen verhindern verlorene Änderungen. Master- und Overlay-Speicherung benötigen eine abgestimmte Fehler- und Rollbackstrategie, einschließlich WordPress-Hooks und Cache-Invalidierung.

Unbekannte YOOtheme-Versionen erlauben keine Builder-Schreibintegration. Fähigkeiten werden anhand geprüfter Versionen und Verträge aktiviert, nicht anhand erfundener Hooknamen oder DOM-Manipulationen.

## Datenbankentwurf

Tabellen mit tatsächlichem WordPress-Präfix; Migrationen versioniert und wiederholbar:

| Tabelle | Zweck / zentrale Constraints |
| --- | --- |
| vmm_languages | Locale eindeutig; URL-Code eindeutig; Aktivierung, Sortierung, Fallback |
| vmm_translations | Objekt, UUIDs, Feld, Locale, Modus, typisierter Wert, Source Hash, Revision, orphaned_at |
| vmm_routes | Locale + normalisierter vollständiger Pfad eindeutig; Objekt + Locale eindeutig |
| vmm_resource_overrides | Objekt, Element, Item, Property, Locale, Ressourcentyp und Ziel |
| vmm_seo | Objekt + Locale eindeutig; Title, Description und OpenGraph |

Für nullable Item-IDs wird im eindeutigen Schlüssel ein nicht-nullbarer Leerwert verwendet. Lange Feldpfade benötigen einen deterministischen Schlüsselhash mit Kollisionsprüfung, um MySQL-Indexgrenzen einzuhalten. Datenbank-DDL und Transaktionsgarantien sind auf den unterstützten WordPress-Datenbankversionen zu testen. Ein Default-Locale wird konsistent in den Einstellungen verwaltet.

## Routing

RouteResolver bildet Locale und vollständigen Pfad auf dieselbe Objekt-ID ab. UrlGenerator verwendet diese Zuordnung statt Prefix-Ersetzung. Zu berücksichtigen: Unterverzeichnisinstallation, statische Startseite, Seitenhierarchie, Trailing Slashes, Unicode, reservierte Endpunkte, unbekannte Routen und Kollisionen. Unbekannte Sprachrouten ergeben 404; kein stilles Mapping auf eine beliebige Seite. Sprachprefixe und optional prefixfreie Standardsprache werden zentral konfiguriert.

## SEO

Canonical zeigt auf die aktuelle Sprachroute. Hreflang enthält öffentliche Sprachvarianten mit passenden Sprach-/Regionscodes. x-default ist optional. Core-, Yoast- und Rank-Math-Sitemaps erhalten separate Adapter; es darf nur ein Anbieter zuständig sein. Nicht veröffentlichte oder nicht indexierbare Inhalte erscheinen nicht in öffentlichen Alternates oder Sitemaps. Fehlende Übersetzungen und deren Indexierung benötigen eine explizite Produktregel.

## Resource Overrides

OverrideManager behandelt Medien, Menüersatz und Widgetersatz. Links haben unabhängig vom Text GLOBAL, AUTO_LANGUAGE oder OVERRIDE. AUTO_LANGUAGE speichert eine WordPress-Objektreferenz. Ressourcen werden auf Typ, Existenz und Zugriff geprüft. Block-Widgets benötigen einen eigenen Adapter; beliebiges serialisiertes Widget-Markup wird nicht blind ersetzt.

## Sicherheit, Cache und Lebenszyklus

REST-Schreibzugriffe benötigen authentifizierte Benutzer, WordPress-Nonce für Cookie-Authentifizierung und objektbezogenes edit_post-Recht. Sprach-/Systemkonfiguration benötigt manage_options. HTML wird feldbezogen und entsprechend unfiltered_html behandelt. SQL wird vorbereitet, Ausgabe kontextbezogen escaped.

Cache-Schlüssel enthalten Blog-ID, Objekt, Locale und Revision. Schreibzugriffe invalidieren betroffene Einträge nach erfolgreicher Persistierung. Vorschauen dürfen keine öffentlichen Sprachcaches vergiften. Kein Datenbankzugriff je Feld.

Gelöschte UUIDs erhalten orphaned_at; Undo kann sie reaktivieren. Duplikate bekommen neue IDs. Übersetzungen werden nur bei nachgewiesener Herkunft kopiert, sonst als fehlend behandelt. Deaktivierung löscht keine Daten; Uninstall nur bei vorher ausdrücklich aktivierter Löschoption.

## Entwickler-API

Geplant: vmm_multilingual_current_language, vmm_multilingual_translate_value, vmm_multilingual_resolve_resource, vmm_multilingual_resolve_url und vmm_multilingual_translatable_field. Signaturen und Reihenfolge werden vor Implementierung festgelegt. Eine spätere TranslateServiceInterface bleibt außerhalb von Phase 1.
