# IFIS News – regionale RSS-Quellen (Pilot)

Ziel: sichtbar machen, worüber **lokale und regionale Redaktionen** in dicht besiedelten Gebieten berichten. Ein Quellensitz ist kein Ereignisort und RSS-Mengen sind kein direktes Maß öffentlicher Meinung.

- `config/regional-feed-candidates.json`: Kandidaten mit Region, Stadt, Land, Redaktionshinweisen und Verifikationsstatus. **Nicht automatisch importiert.**
- `bin/check-regional-feeds.php`: CLI-Prüfung auf HTTPS, Abrufbarkeit, RSS/Atom, Titel, Einträge, Zeitstempel und Redirects. Die Prüfung aktiviert keine Quelle.
- Nach erfolgreicher Prüfung: redaktionelle Unabhängigkeit, Eigentümer, Korrekturpraxis, Nutzungsrechte, Mehrsprachigkeit und Aktualität manuell prüfen. Dann erst bewusst in `config/feeds.json` aufnehmen.
- Wichtig: Die derzeitige Karte ordnet nur wenige Quellenländer fest zu; vor großem Import muss die Herkunftszuordnung aus Metadaten erfolgen, nicht aus Namensregeln.

Ausbaureihenfolge: Südasien, Ost-/Südostasien, West-/Ostafrika, Lateinamerika, Nahost, Europa, Nordamerika, Ozeanien. Pro Ballungsraum mindestens zwei unabhängige lokale Stimmen anstreben. Kandidatenliste ist ein Pilot, **keine globale Vollabdeckung**.
