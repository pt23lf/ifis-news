# Privates GitHub-Netzwerkarchiv nach All-inkl übernehmen

Das private Repository `pt23lf/ifis-network-data` speichert neue Screenshot-Erfassungen als unveränderliche JSON-Pakete in `batches/`. Das öffentliche Coderepository enthält nur Importcode und Dokumentation, keine persönlichen Datensätze.

## Einmalige Einrichtung auf dem Server

1. IFIS-News-Code aktualisieren: `git pull origin main`.
2. Nur lesenden SSH-Zugriff auf das private Repository einrichten (zum Beispiel eigener GitHub Deploy Key). Keine Schlüssel oder Tokens in Git, Chat oder URLs speichern.
3. Repository außerhalb öffentlich erreichbarer Webverzeichnisse klonen. Beispielpfad an die tatsächliche Serverkonfiguration anpassen:

```bash
git clone git@github.com:pt23lf/ifis-network-data.git "$HOME/ifis-network-data"
```

Die `.htaccess` im Datenrepository sperrt HTTP-Zugriff zusätzlich. Der PHP-Import lehnt ein Datenrepository innerhalb des IFIS-News-Codeverzeichnisses ab.

## Übernahme

Im Codeverzeichnis `/www/htdocs/w0221385/news.ifis.net/repo`:

```bash
bash bin/sync-network-data.sh "$HOME/ifis-network-data"
```

Das Skript aktualisiert das private Repository per `git pull --ff-only origin main` und ruft den reinen CLI-Import auf. Bei lokalen Änderungen oder einem Git-Fehler wird nicht importiert. Ein nichtinteraktiver Cronjob benötigt bereits funktionierenden SSH-Zugriff einschließlich geprüftem Hostschlüssel.

Nach erfolgreichem Test kann derselbe Sync-Befehl per All-inkl-Cron regelmäßig gestartet werden. Das Skript richtet selbst keinen Cronjob ein. Auf dem Server müssen Bash, Git und PHP mit PDO SQLite und mbstring verfügbar sein.

## Datenverhalten

- Speicherung in der bestehenden `storage/network.sqlite`; Ansicht unter `/admin-erfassung.php`.
- Bereits erfolgreich importierte Pakete werden anhand Dateiname und SHA-256 übersprungen. Veränderte importierte Dateien werden abgelehnt. Neue Ergänzungen immer als neue Paketdatei mit vorhandenen stabilen Objekt-Schlüsseln erfassen.
- Jedes Paket wird als eigene Transaktion importiert. Bei einem Fehler bleiben vorherige erfolgreich importierte Pakete erhalten; das fehlerhafte Paket wird zurückgerollt. Gleichzeitige CLI-Importe werden durch eine Dateisperre verhindert.
- Serverdaten werden nicht zurück nach GitHub exportiert. Frühere CSV-Importe, manuelle Änderungen und Löschungen bleiben eigenständige Serverdaten. Das Datenrepository ist kein vollständiges Datenbankbackup.
- Profile werden anhand eindeutiger Profillinks oder bewusster `contact_id`-Zuordnung verbunden, nicht allein anhand gleicher Namen. Ortsangaben allein erzeugen keine Projektzuordnung.
- Es gibt keinen öffentlichen Schreibendpunkt und keine automatische LinkedIn-Abfrage.
