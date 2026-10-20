# Sofortmaßnahme: Lizenz-Sicherheitsmodus (20.10.2026)

**Status: fail-closed, nur eigene IFIS-Artikel.** Dieser Modus reduziert Nutzungsrisiken, ersetzt keine anwaltliche Lizenzprüfung und garantiert keine vollständige Rechtssicherheit.

## Technische Sperren
1. `config/feeds.json` enthält ausschließlich den eigenen Feed `https://ifis.net/artikel.rss`.
2. `bin/import.php` prüft zusätzlich die exakte Feed-Quelle (Defense in Depth). Fremdquellen werden nicht abgerufen.
3. `public/index.php` lädt aus SQLite ausschließlich Datensätze mit `source = IFIS · Artikel`. Fremde Bilder werden nicht ausgeliefert, selbst wenn in alten Datensätzen Bild-URLs stehen.
4. `public/collection.php` akzeptiert nur IFIS-Datensätze für neue Sammlungen; historische Sammlungen mit irgendwelchen Fremdartikeln liefern HTTP 410 statt auszugsweise alte fremde Texte.
5. `bin/purge-unlicensed.php --confirm` löscht die bestehenden Fremdartikel inklusive Analyse-Datensätzen sowie komplette gespeicherte Misch-/Fremdsammlungen. Das ist absichtlich irreversibel.

## All-inkl: Aktivierung
**Vor dem Deploy:** Falls aus rechtlichen Beweisgründen die historischen Daten aufbewahrt werden müssen, mit dem Rechtsbeistand absprechen. Die öffentliche Ausgabe ist auch ohne Datenlöschung sofort gesperrt. Nicht ohne Überlegung öffentliche Backups/Kopien anfertigen.

```bash
cd /www/htdocs/w0221385/news.ifis.net/repo
git pull origin main
php bin/purge-unlicensed.php --confirm
```

Optional – SQLite-Speicher bereinigen (bei laufendem Import-Cron besser während Wartungsfenster):
```bash
php -r '$db=new PDO("sqlite:storage/news.sqlite");$db->exec("PRAGMA wal_checkpoint(TRUNCATE)");$db->exec("VACUUM");'
```

Auf anderen Rechnern/Servern gespeicherte Kopien und Backups werden hierdurch **nicht** automatisch gelöscht. Vorhandene CDN- und Browsercaches können temporär alte Seiten ausliefern; Cache-Header, Caches und alte Social-Snippets separat prüfen.

## Nicht ohne neue Prüfung freischalten
- Verlinkung ≠ Lizenz für Feed-Nutzung, dauerhaftes Spiegeln, Volltext/Bild, KI, kommerzielle Verwendung.
- Neue Feeds erst nach Beleg zu Abrufbedingungen, Titel/Snippet, Datenbank-/Archivdauer, Bildern, KI/Analyse, Monetarisierung. Eine Aufnahme erfordert bewusste Änderung beider Sperren.
- Eigene IFIS-Artikel können Drittmaterial enthalten: Bild-/Zitat- und Lizenzrechte separat prüfen.
- Für rechtssichere Wiederaufnahme externer Anbieter Medienrechtler bzw. schriftliche Rechteerteilung einbeziehen.

## Auswirkungen
- Die Rubrik Weltlage hat zunächst keine Fremdmeldungen mehr.
- Alte feste Sammlungen mit Fremdartikeln verlieren ihren Inhalt/werden deaktiviert.
- Die zuvor gepflegten Orts-, Quellen- und Verbindungskarten externer Meldungen werden vorübergehend leer.
- Neue fremde RSS-Daten werden nicht mehr geladen.
