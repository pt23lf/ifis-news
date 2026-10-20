# IFIS News: lizenzkontrollierte Datenquellen (20.10.2026)

## Aktiv
- Eigener IFIS-RSS-Feed: https://ifis.net/artikel.rss
- USGS Earthquake GeoJSON 24h: https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_day.geojson

## Aktuelle rechtliche Grundlage und Grenzen
USGS stellt die GeoJSON-Schnittstelle ausdrücklich als programmatische Datenschnittstelle bereit:
https://earthquake.usgs.gov/earthquakes/feed/v1.0/geojson.php
USGS bittet um Quellennennung; eigene USGS-Daten sind weitgehend gemeinfrei. Externe Inhalte, Fotos und Markenzeichen sind ausgenommen:
https://www.usgs.gov/information-policies-and-instructions/acknowledging-or-crediting-usgs
https://www.usgs.gov/data-management/data-licensing

Nur USGS-Ereignis-ID, Zeit, Magnitude, Koordinaten und Original-Link werden übernommen. Die Anzeige kennzeichnet den USGS als Quelle. **Keine** Bilder, Beschreibungen, fremde Headlines, USGS-Logos oder Webseiten-Volltexte.

NASA/NOAA-Nachrichtenfeeds bleiben trotz früherer technischer Pilotfreigabe bis zur Rechtsklärung deaktiviert. Alle anderen fremden Presse-RSS-Feeds ebenfalls.

## Technische Freigabe
- Aktive RSS-Liste und harte Importsperre: nur erster IFIS-Feed.
- `config/rss-rights.json` dokumentiert Quellen und Standard `blocked`.
- `bin/import-usgs.php` enthält fest codierte HTTPS-API, Formatprüfung, Werteplausibilisierung und Importbegrenzung. Keine freien Feed-URLs.
- `bin/cron-import.sh` startet beide Importer.
- `public/index.php` zeigt nur IFIS und USGS-Daten, redigiert unautorisierte Fremdartikel durch Ausschluss.
- USGS-Koordinaten werden in `event_locations` gespeichert. Die bestehende Karten-Geokodierung muss als gesonderter Integrationsschritt an diese Koordinaten angebunden werden; **die Erfassung allein setzt noch keine geografisch exakten Kartenmarker**.
- Bestehende NASA/NOAA/andere Datensätze sind in der öffentlichen Liste blockiert. Bereinigung von Altbestand/Backups gesondert durchführen.

## Deployment
```bash
cd /www/htdocs/w0221385/news.ifis.net/repo
git pull origin main
php bin/import.php
php bin/import-usgs.php
```
Erst nach Test auf dem Live-Server Datenfluss und grafische Darstellung als verifiziert bezeichnen.

## Nächste Quellen
Beispielsweise weitere offen lizenzierte Forschung, Umwelt- und Ereignisdaten – jeweils mit Original-Lizenz, API-Bedingungen, Quellenkennung, zulässigen Feldern, Aktualisierungsrate und Aufbewahrung. Weder `open access` noch `RSS verfügbar` reicht als Freigabe. Wenn die Rechte unklar sind, bleibt die Quelle gesperrt.

Dieses Audit reduziert Risiken und dokumentiert das Betriebsmodell, ersetzt aber keine verbindliche juristische Freigabe für internationalen, öffentlich erreichbaren Betrieb.
