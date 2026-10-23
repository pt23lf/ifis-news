# Ausgewählte LinkedIn-Beiträge veröffentlichen

Die sechs ausgewählten Beiträge stehen in `config/curated-posts-2026-10-23.json`: eigene redaktionelle Kurzfassungen, Urheber, Suchbegriffe und direkte Original-Links. Private Netzwerknotizen und vollständige Transkriptionen werden nicht übernommen.

Nach Aktualisierung des Codes im News-Repository ausführen:

```bash
php bin/publish-saved-posts.php
```

Der Befehl speichert freigegebene Linkkarten in `storage/source-submissions.sqlite`. Das angezeigte Datum ist das Erfassungsdatum auf IFIS News, kein behauptetes Originaldatum.

Erneutes Ausführen erzeugt keine Duplikate und ergänzt bei bereits veröffentlichten Beiträgen fehlende Profilstandorte. Inhaltliche Änderungen und bereits eingetragene Quellenkoordinaten bleiben erhalten. Offene Einträge derselben LinkedIn-Aktivität werden freigegeben; abgelehnte Einträge oder mehrfache Zuordnungen brechen die gesamte Übernahme ohne Teiländerungen ab. LinkedIn-Trackingparameter und regionale URL-Varianten werden bei der Zuordnung ignoriert.

`--dry-run` prüft die Übernahme und rollt auch Schemaänderungen zurück. Ohne vorhandene Datenbank verwendet die Vorschau eine Datenbank im Arbeitsspeicher.

## Profilstandorte auf der Karte

`source_place`, `source_lat` und `source_lon` speichern den Quellenort getrennt von den bisherigen Ereignisfeldern `place`, `lat` und `lon`. Die aus den bereitgestellten Profilaufnahmen bekannten Städte werden als ungefähre Stadtmittelpunkte hinterlegt. Sie bezeichnen keine Wohnadresse und keinen Ereignisort.

Unter „Quellenorte“ erscheinen fünf Autorenmarker: Daniel Pink in Washington, Egemen Toprak in Prag, Deon Grobbelaar in Pretoria, Murat Aydin in Brüssel und Khizer Abbas in Islamabad. Jeder Marker öffnet seine zugehörigen Beiträge; Daniel Pinks Marker bündelt zwei Posts. Die Ziffer am Profilmarker zählt die Beiträge. Profilstandorte bleiben für die kuratierten Beiträge auch außerhalb des Nachrichten-Zeitfensters erreichbar.

Ältere Datenbanken bleiben bis zum erneuten Import lesbar; ohne die neuen Quellenfelder erscheinen noch keine Autorenmarker. Nach dem Import die Seite neu laden und „Quellenorte“ einschalten, falls dieser Kartenfilter abgewählt ist.
