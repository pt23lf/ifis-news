# GDELT activity map – initial rollout (2026-10-20)

This uses the **classic GDELT 2.0 Event stream** (NOT GDELT Cloud). Source: https://gdeltproject.org/data.html ; codebook: https://data.gdeltproject.org/documentation/GDELT-Event_Codebook-V2.0.pdf

## Data and legal scope
- GDELT credit mandatory: https://www.gdeltproject.org/
- No publisher text, headlines, snippets, photographs or downloaded publisher pages.
- Collected fields: GDELT global event ID, UTC date, ActionGeo coordinates and place, first source URL and its host, and GDELT's NumMentions/NumSources/NumArticles.
- GDELT's source URL is a *first reported source*, **not a list of all publishers reporting on that event**.
- At hover the popup shows nearby clustered event places and the hosts of the available first-source citations.
- On click only those original HTTPS source URLs are shown. Counts refer to clustered GDELT event observations, not number of independently verified real-world events.
- Grouping is approximate 1-degree cells, so a displayed marker is not a precisely geocoded single event.
- No claims of comprehensive source coverage, completeness or factual correctness.
- Original rights of linked pages remain with their publishers. This ingestion does not bypass the source feed restrictions.

## Operations
```sh
cd /www/htdocs/w0221385/news.ifis.net/repo
git pull origin main
php bin/import-gdelt.php
```

The importer requires PHP CLI with `ZipArchive` and `pdo_sqlite`, fetches the latest official classical GDELT 15-minute export, and appends up to 8000 raw event rows to `gdelt_observations`, keeping 48 hours. Background cron invokes it alongside the existing first-party and USGS importers. The public API `public/activity-data.php` returns a maximum of 250 active map cells and at most 45 source URLs per cell. Poll frequency currently follows the configured server cron (typically 30 minutes).

If the GDELT index, zip, PHP zip extension or database is unavailable, importer fails without clearing previous records. Map shows no GDELT markers until first successful import. Check CLI output and server logs. No production smoke test has been performed.

## Next iterations
- Retrieve GDELT Mentions table or an explicitly licensed dataset when complete per-event publisher lists are needed; keep counts and unique publishers distinct.
- Validate actual GDELT export schema columns (ActionGeo FullName 51, Latitude 55, Longitude 56, NumMentions 31, NumSources 32, NumArticles 33, DATEADDED 58, SOURCEURL 59) against a live downloaded file.
- Apply map time slider to GDELT markers and permit user toggles/zoom-dependent aggregation after first live verification.
- Ensure caching, permissions, retention, and operational reliability meet production policies. A legal review is still recommended.

## Phase 2 — Zeitfilter und Quellenstandorte (2026-10-20)
- `public/activity-data.php` liefert zusätzlich stündliche Aktivitäts-Buckets je 1°-Zelle (bis zu 48 Stunden); die stündliche Zuordnung ist an den Rändern des gewählten Zeitraums maximal um eine Stunde unscharf.
- Der bereits bestehende `mapTimeRange`-Slider filtert GDELT-Marker, stündliche Beobachtungszahlen und die verfügbaren Erstquellen-URLs. Bei einer Auswahl wird auch die Verbindungsanzeige neu gefiltert.
- `public/source-offices.json` verwaltet manuell zu prüfende Redaktionsorte separat. Die Liste ist eine Positivliste, keine automatische TLD-Geolokalisierung. `manual_review` stellt **keine** abschließende rechtliche oder geografische Verifikation dar.
- Linien zeigen die ausgewählte GDELT-Erstquelle zu einer angenäherten Ereigniszelle. Sie zeigen weder den tatsächlichen Rechercheort, einen Nachrichtenfluss noch sämtliche berichtenden Medien.
- Ein bereits geöffneter Popup-Inhalt ist ein Schnappschuss zum Klickzeitpunkt; für die neue Auswahl erneut klicken.
- Die zurückgelieferten Ereignisorte sind 1°-Rasterzellen und die 250 Zellen werden vor der Slider-Filterung nach 48-Stunden-Gesamtaktivität ausgewählt. Der Filter ist daher keine vollständige weltweite Topliste für kürzere Zeitfenster.
- Aktivitätspunkte selbst sind keine deduplizierten Ereignisse; Summen der GDELT NumSources-Werte sind keine Anzahl einzigartiger Verlage.
- Deploy: `git pull origin main`. Vor produktiver Bewertung Browserkonsole, PHP-Ausgabe und Realzeitdaten überprüfen.

## Quellensteuerung (2026-10-20)
- USGS wurde aus Cron und Artikelanzeige entfernt. Alt-Daten einmalig nach Deployment mit `php bin/purge-usgs.php` aus `storage/news.sqlite` löschen; das historische Skript `bin/import-usgs.php` wird nicht mehr vom Cron aufgerufen.
- Im Kartenmenü stehen zwei unabhängige Schalter `showGdeltData` und `showLinkedInData`, die per Browser-localStorage gespeichert werden.
- LinkedIn-Karte: `public/linkedin-sources-data.php` veröffentlicht ausschließlich `status='approved'`-Einreichungen, die zusätzlich geprüfte `lat`/`lon`-Spalten besitzen. Solange diese Daten und eine Freigabeoberfläche fehlen, bleibt die Ebene leer. Keine öffentliche Anzeige ungeprüfter Beiträge, kein LinkedIn-Crawler.
- Der Standort einer eingereichten Aktivität muss manuell verifiziert werden und ist **nicht** automatisch der Standort des LinkedIn-Profils.
