# IFIS-Zahlendashboard – Stand 20.10.2026

## Ziel
Ein separates Zahlen- und Fakten-Dashboard für IFIS-Projekte, **nicht** das persönliche Denkdashboard.
Aktuelle Prototyp-Adressen nach Deployment:
- `https://news.ifis.net/numbers.php` – projektübergreifende Startseite (zunächst nur IFIS News angebunden)
- `https://news.ifis.net/metrics.php` – Nutzungsstatistik der IFIS-News-Werkzeuge

Beide Seiten nutzen dieselbe Passwortkontrolle und liefern ohne eingerichtetes Passwort nur HTTP 503.

## Servereinrichtung (einmalig, über SSH)
Im Repo-Verzeichnis ausführen:

```bash
cd /www/htdocs/w0221385/news.ifis.net/repo
git pull origin main
php -r 'file_put_contents("storage/.numbers-admin-password", bin2hex(random_bytes(24)));'
chmod 600 storage/.numbers-admin-password
cat storage/.numbers-admin-password
```

Das letzte Kommando zeigt **nur lokal im Terminal** ein zufällig erstelltes Passwort. Nicht in Chats, Issues oder GitHub einfügen. Beim HTTP-Basic-Dialog:
- Benutzername: `admin`
- Passwort: der lokal angezeigte Zufallswert.

**HTTPS verwenden.** Basic Auth überträgt Zugangsdaten im Request und benötigt TLS. Nur die Startseiten über HTTPS öffnen. Den Passwortwert im Passwortmanager aufbewahren. Durch Neugenerieren der Datei wird das vorherige Passwort sofort ungültig. Wird der Zugriff mit 401 abgewiesen, überprüfen, ob der Webserver PHP_AUTH_USER/PHP_AUTH_PW an PHP weiterreicht.

## Vorhandene Daten
- `public/feature-event.php` sammelt nur erlaubte, anonyme Ereignisnamen als Monats-JSON mit Tageswerten in `storage/metrics` (nicht Webroot).
- `public/metrics.php` zeigt Anzahl Aktionen, Tage, Funktionsarten und die Verteilung über Funktionen und Tage.
- `public/numbers.php` ist der Ort für das projektübergreifende Fakten-Dashboard.
- `lib/numbers-dashboard.php` kapselt Authentifizierung, lesende Metrikfunktionen und HTML-Layout.
- `bin/metrics-summary.php` bleibt als Kommandozeilen-Alternative bestehen.

## Geplante Quellen
| Projekt | Nächste echte Metriken | Status |
|---|---|---|
| IFIS News | Funktionsaktionen, später Seitenaufrufe | Basis vorhanden |
| IFIS.net | Seitenzugriffe, Artikel- und Textwerkzeugnutzung, Lesepfade | ausstehend |
| fitpro.de | Seitenzugriffe, Selbstcheck, Newsletter | ausstehend |
| larsfocke.com | Seitenzugriffe, Projektverweise | ausstehend |
| Planet / Global Compass | Beiträge, gestaltbare Orte, Nutzung | ausstehend |

Wichtige Grundsätze: eindeutige Menschen, Sitzungen, Seitenaufrufe und Bedienhandlungen nicht vermischen. Keine nicht verifizierten Zahlen verwenden. Zugriff auf die Aggregation bleibt administrativ. Für projektübergreifende Datentransfers zunächst serverseitige Import-Schnittstelle mit minimalem, aggregiertem Format und getrennten Zugangsdaten entwerfen; **keinen öffentlichen Export und keine fremden Zugangsdaten im News-Repository**.

## Offene Prüfungen vor Produktivfreigabe
1. PHP-Syntax und HTTP-Basic-Authentifizierung am Live-Server testen (inklusive Fehlerseiten).
2. Dateiberechtigungen und `storage` außerhalb des Webroots prüfen.
3. Datenschutztext, vorhandene Server-Access-Logs und Aufbewahrungsfrist der Metriken festlegen.
4. Zählqualität prüfen, bevor aus den Daten Produktentscheidungen abgeleitet werden.
5. Perspektivisch unter eigener Dashboard-Subdomain bzw. eigenem Repository bereitstellen, um Datenquellen und Deployment sauber zu trennen.
