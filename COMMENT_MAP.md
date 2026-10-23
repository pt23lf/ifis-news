# Eigene Kommentare auf der News-Karte

Nach `git pull --ff-only origin main` auf All-inkl die News-Seite neu laden. Es ist kein neuer Import oder Datenbankschemawechsel notwendig, wenn die Kommentare bereits im privaten Netzwerk gespeichert sind.

1. Unter `admin-netzwerk.php` mit dem bestehenden Adminpasswort anmelden.
2. Zur News-Karte zurückkehren und in den Karteneinstellungen **Meine Kommentare · privat** einschalten.
3. Eine violette Linie oder einen violetten Profilpunkt anklicken: Das Overlay zeigt die zugehörigen Posts, die gespeicherten eigenen Kommentare und vorhandene Original-Links. Fehlende Original-Links sind gekennzeichnet.

Die Linien führen vom eigenen Profilort (ersatzweise dem vereinbarten Ausgangspunkt Lübeck) zu den Profilorten der Beitragsautoren. Sie sind keine geografischen Aussagen über den Inhalt eines Posts. Mehrere Kommentare beim selben Autor teilen sich eine Verbindung. Auch in der flachen Karte bleiben die Verbindungen gebogen.

Verwendet werden eigene Kommentare aus `capture_comments`, zugeordnet über `profile-lars-focke`, sowie `my_comment` aus dem älteren `linkedin_comments`-Import. Fremde Kommentare, eigene Posts und nur interessante Profile erzeugen keine Verbindung. Identische Kommentar-/Post-Paare aus beiden Importwegen werden zusammengefasst.

Autoren ohne gültige Koordinaten erscheinen in der aufklappbaren Liste der noch zuzuordnenden Profilorte. Orte im privaten Netzwerk ergänzen bzw. dort über „Fehlende Orte zuordnen“ geocodieren, anschließend die Kommentarebene aus- und wieder einschalten.

## Zugriff und Speicherung

Die öffentliche HTML-Seite enthält keine privaten Kommentare oder Kontaktlisten. `comment-map-data.php` prüft die bestehende Adminsitzung und das eingerichtete Adminpasswort, bevor es die private SQLite-Datenbank liest. Ohne Anmeldung liefert es HTTP 403. Antworten sind `private, no-store`; die Ebene ist zunächst ausgeschaltet. Ihre Daten werden erst beim bewussten Einschalten abgerufen und beim Ausschalten entfernt. Ein Stilwechsel stellt eingeschaltete Linien wieder her; ein erneuter Fensterfokus prüft die Sitzung durch einen neuen Abruf. Die persönlichen Inhalte werden nicht im Browser-Lokalspeicher abgelegt und nicht ins öffentliche GitHub-Repository kopiert.

Die Änderung liest nur aus der bestehenden Netzwerkdatenbank. Sie schreibt keine Kontakte, Kommentare oder neuen Ortsannahmen.
