# IFIS News – Rechtegesteuerte RSS-Freigabestufen
Stand: 20. Oktober 2026

## Freigabestufen
- **A (manuell):** Quelle darf als selbst gepflegter Link im Quellenverzeichnis stehen; KEIN RSS-Abruf.
- **B (links_activity):** Exakt freigegebener HTTPS-Feed darf abgerufen werden. Gespeichert werden lediglich Artikel-URL, eigene generische Linkbezeichnung, Quellenname und Veröffentlichungszeitpunkt. Keine fremden Titel, Snippets, Bilder, HTML-Auszüge oder Logos.
- **C (Aktivitätskarte):** Auf Basis B dürfen eigene Zähler/Visualisierungen aus den freigegebenen Linkereignissen erzeugt werden. Dies ist keine gesonderte Freigabe zur Weiterverwendung redaktioneller Werke oder umfassender Dritt-Datenbanken. Keine automatische Übertragung in kostenpflichtige Angebote.
- **own:** Eigene IFIS-Artikel dürfen weiter wie bisher verarbeitet werden, vorbehaltlich möglicherweise darin enthaltener Drittinhalte.
- **blocked (Standard):** kein Abruf, keine Speicherung, keine Anzeige.

## Derzeit freigegebene automatisierte Feeds
1. IFIS · Artikel – Eigeninhalte – `https://ifis.net/artikel.rss`
2. NASA · News – B/C mit ausschließlich Link, Anbieter und Zeitpunkt – `https://www.nasa.gov/feed/`
3. NOAA · Environment – B/C unter gleicher Inhaltsbeschränkung – `https://www.noaa.gov/rss.xml`

Die Freigabe ist eine vorsichtige redaktionell-technische Einschätzung, **keine verbindliche Rechtsfreigabe**. Behördenfeeds können Inhalte Dritter enthalten. Keine NASA-/NOAA-Logos, Bilder oder Originaltexte übernehmen. NASA-/NOAA-Webseiten oder der konkrete Feed können sich ändern; bei Vorbehalt/Verbot Feed sperren.

## Herangezogene Originalquellen
- NASA RSS-Verzeichnis: https://www.nasa.gov/rss-feeds/
- NASA Nutzungs-/Markenhinweise: https://www.nasa.gov/nasa-brand-center/
- NOAA/NWS RSS Library: https://www.weather.gov/phi/rssfeed
- ScienceDaily RSS Terms (Datenbankspeicherung beschränkt): https://www.sciencedaily.com/terms.htm
- BBC RSS/Metadata: https://downloads.bbc.co.uk/usingthebbc/bbc_terms_of_use_31March2022english.pdf
- SPIEGEL Syndication: https://gruppe.spiegel.de/syndication/anfrage

## Technische Absicherung
- `config/rss-rights.json` ist der deklarative Rechtekatalog (Standard: blocked).
- `config/feeds.json` nennt die aktiven Feeds.
- `bin/import.php` prüft zusätzlich einen **fest im Code hinterlegten** Satz erlaubter B/C-Feeds. Manipulation des JSON allein kann keinen dritten Fremdfeed freischalten.
- RSS-Items aus B/C erhalten vor der Speicherung eine generische Bezeichnung und leere Beschreibungs-/Bildfelder.
- `public/index.php` begrenzt die öffentlichen Quellen auf dieselben drei Anbieter und redigiert alte NASA/NOAA-Datensätze bei der Anzeige. Keine fremden Texte/Bilder aus der Datenbank ausliefern.
- `public/collection.php` akzeptiert vorerst nur eigene IFIS-Texte für neue feste Sammlungen; alte gemischte Sammlungen bleiben gesperrt.
- Andere Quellen, einschließlich Guardian, ScienceDaily, BBC, DER SPIEGEL usw., bleiben für automatisierten Abruf gesperrt.

## Einschränkungen und nächste Prüfung
- Aus dem bloßen Angebot eines RSS-Feeds lässt sich keine allgemeine Archiv-, Analyse- oder kommerzielle Lizenz ableiten. Betreiberbedingungen, Robots-Vorgaben, Abrufhäufigkeit und Datenbankrecht sind vor jeder Ausweitung zu prüfen.
- Event-Orte werden aus der generischen Linkbezeichnung nicht automatisch zuverlässig bestimmbar; Geo-Auswertungen brauchen eigene, belegte Faktendaten.
- Die aktuelle Linksammlung ist keine Freigabe der fremden Überschriften oder Volltexte.
- Für eine nachhaltige kommerzielle Nutzung der Aktivitätsmetriken den konkreten Datenerfassungs- und Verwertungsumfang anwaltlich prüfen.
- Alte SQLite-Datensätze und Backups können trotz Anzeige-Redaktion noch fremde Texte enthalten; die frühere Bereinigung weiterhin separat ausführen. Wird das Bereinigungsskript nach neuem Import ausgeführt, entfernt es auch die aktuellen NASA/NOAA-Links. Daher erst bereinigen, dann neu importieren.
