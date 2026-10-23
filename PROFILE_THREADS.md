# Posts und eigene Kommentare im Profilfeld

Nach dem Aktualisieren des Codes die Seite neu laden. Es ist kein erneuter Import und keine Schemaänderung nötig.

Im Profilfeld erscheinen die gespeicherten Posts des jeweiligen Autors jeweils einmal. Die eigenen Kommentare stehen direkt beim zugehörigen Post. Mehrere Kommentare zum selben Post werden zusammengefasst dargestellt; Posts ohne eigenen Kommentar bleiben enthalten. Die Übersicht nennt die Anzahl der gespeicherten Posts und der eigenen Kommentare.

Die Übersicht ist an diesen Stellen verfügbar:

- Profilorte auf der News-Karte, nach Adminanmeldung. Ohne Anmeldung bleibt die bestehende öffentliche Artikelliste erhalten.
- Profilpunkte der privaten Netzwerkkarte sowie die Profile hinter den eigenen Kommentarlinien.
- „Erfasste Profile“ in der Screenshot-Erfassung unter „Posts und meine Kommentare“.

## Zuordnung

Die private Übersicht verbindet `capture_posts` und eigene `capture_comments` über die gespeicherten Kontakt- und Beitrags-IDs. Eigene Kommentare werden über den Profil-Schlüssel `profile-lars-focke` erkannt. Der ältere CSV-Import wird über `linkedin_comments.contact_id` einbezogen. Derselbe LinkedIn-Post wird anhand seiner Aktivitäts-ID auch über regionale Hosts und Trackingparameter hinweg zusammengefasst; identische eigene Kommentartexte werden nicht doppelt dargestellt. Ohne Post-Link wird ein CSV-Eintrag nur bei eindeutig identischem Beitragskontext einem Screenshot-Post zugeordnet.

Öffentliche Profilfelder werden ausschließlich anhand hinterlegter Post- oder Profillinks einem privaten Profil zugeordnet. Namen allein erzeugen keine Zuordnung. Widersprüchliche Autoren-IDs führen zu keiner Zusammenführung. Fremde Kommentare werden nicht als eigene angezeigt.

## Zugriff

`profile-threads-data.php` prüft die vorhandene Adminsitzung und das eingerichtete Adminpasswort. Die Datenbank wird nur lesend geöffnet, Antworten tragen `private, no-store` und `Vary: Cookie`. Keine privaten Inhalte werden in das öffentliche HTML oder dieses Repository kopiert. Quellen und Profile verwenden dieselbe Beitragsdarstellung und dieselben Teilen-Funktionen. Geteilte Sammlungen enthalten ausschließlich freigegebene öffentliche Beiträge; private Kommentare werden dabei nicht übertragen. Einzelne Original-Links können direkt geteilt werden.

Die Vollständigkeit entspricht dem gespeicherten Bestand. Ausschnitte und fehlende Original-Links bleiben als solche kenntlich.

