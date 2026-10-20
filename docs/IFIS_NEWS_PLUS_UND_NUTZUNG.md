# IFIS News – Offenheit, Kostendeckung und Nutzungsstatistik

Stand: 20. Oktober 2026

## Leitlinien
- IFIS News bleibt als öffentliches Nachrichten- und Orientierungswerkzeug nutzbar.
- Bereits öffentlich angebotene Kernfunktionen (Nachrichten, Globus, Quellen und Ereignisorte, Verbindungen, grundlegende Filter, Zeiträume, Teilen und lokales Merken) nicht hinter eine Bezahlschranke verschieben.
- Zukünftiges IFIS News Plus finanziert den offenen Zugang mit: etwa dauerhafte geräteübergreifende Sammlungen, Beobachtungslisten, Benachrichtigungen, tiefere Zeitvergleiche, Analysen und Exporte.
- Neben Plus kann es freiwillige Förderbeiträge sowie professionelle/institutionelle Lizenzen geben.
- Preise erst nach tatsächlicher Nutzungs- und Nachfrageprüfung festlegen. Frühe Hypothese: 3–5 € monatlich.
- Monetarisierung vor allem dort, wo zusätzlicher Speicher-, Rechen- und Betreuungsaufwand entsteht.

## Schritt 1: Anonyme Basiszählung (eingebaut)
- `public/feature-event.php`: akzeptiert nur fest definierte Ereignisse aus einer Allowlist, keine Zusatzdaten.
- Ablage außerhalb des Webroots in `storage/metrics/YYYY-MM.json`, als UTC-Tageszähler und Ereignisnamen.
- Gezählt werden Bedienhandlungen, **keine eindeutigen Menschen**, Sitzungen, Klickpfade oder Aufenthaltszeiten.
- Kein Tracking-Cookie, keine eindeutige Kennung, keine IP, keine Browserkennung, keine Artikel-URL, keine Suchbegriffe im Zähler. Normale Server-Access-Logs sind davon unabhängig.
- Die clientseitige Erfassung läuft beim Anklicken bestimmter Funktionen und bei einzelnen Änderungen. Wiederholte Hover-Ereignisse werden grob gebremst, aber die Zahlen sind keine Nutzerschätzung.
- Ein Browser kann Ereignisse blockieren; es gibt keine Garantie vollständiger Erfassung.
- Keine öffentliche Auswerteseite: JSON-Dateien bleiben im geschützten serverseitigen Speicher. Vor einer öffentlichen Darstellung oder einer Kontoverknüpfung sind Datenschutztext, Serverlogs, Aufbewahrungsfrist, Zugriffsschutz und Rechtsgrundlage zu prüfen.

## Nach Schritt 1
1. Tages-/Wochenübersicht der häufigsten Werkzeugaktionen erstellen, nur authentifiziert für Admin.
2. Nach einigen Wochen prüfen, welche Funktionen wiederkehrenden Nutzwert zeigen.
3. Dann erst dauerhafte Nutzerkonten und Plus-Merkmale planen.

## Erfasste Ereignisnamen
`map_open`, `map_projection`, `map_settings`, `time_range`, `filter`, `saved_view`, `save_article`, `hide_article`, `share_collection`, `share_location`, `marker_open`, `marker_hover`, `source_links`.

Wichtig: Die Zähler zeigen nur Interaktionen; sie sind weder Reichweitenmessung noch verlässliche Conversion-Raten.
