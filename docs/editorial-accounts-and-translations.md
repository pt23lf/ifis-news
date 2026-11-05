# IFIS News · Redaktion, Konten und Sprachen (Vorbereitung)

**Status:** Datenmodell vorbereitet, noch **keine** aktive Nutzerregistrierung, API, Freigabeoberfläche oder Übersetzungsanbindung. Kein automatisches Ausführen der SQL-Migration.

## Inhaltstypen
- `external_link`: Original-URL, Quelldaten und optionale persönliche Einordnung. Quelle und ursprünglicher Autor bleiben erkennbar.
- `original`: direkt bei IFIS News veröffentlichter eigener Text mit dauerhafter URL. Quellenangaben bleiben möglich.
- Beide Typen benötigen vor Veröffentlichung eine redaktionelle Entscheidung.

## Rollen
- `admin`: Einladungen, Konten, Rollen, Review und Veröffentlichung.
- `editor`: eingereichte Beiträge prüfen, redigieren, veröffentlichen.
- `contributor`: eigene Entwürfe erstellen, einreichen und auf Änderungswünsche reagieren; keine Freigabe.
- `reader`: gespeicherte Inhalte und Spracheinstellungen; kein Redaktionszugriff.

Alle Berechtigungen serverseitig durchsetzen; niemals Rollen aus Formularfeldern übernehmen. Die bestehende öffentliche Seite bleibt ohne Login lesbar.

## Sicherheitsanforderungen vor Aktivierung
1. Backup von `storage/news.sqlite`, Testmigration in einer Kopie und transaktionale Migration; bestehende Artikeltabellen bleiben unangetastet.
2. Invite-only: Einladungs-Token mit `random_bytes(32)`, nur SHA-256-Hash speichern, 48-Stunden-Ablauf, einmalige Einlösung, Widerruf.
3. Passwort-Hash mit `password_hash(..., PASSWORD_DEFAULT)`; HttpOnly/Secure/SameSite-Session-Cookies, Session-ID nach Login erneuern, CSRF-Schutz bei Schreibaktionen.
4. Rate-Limits für Login und Einladungsannahme, generische Fehlermeldungen, Zugriffssperre bei suspendierten Konten.
5. URL-Eingaben validieren; beim serverseitigen Abruf externer Links SSRF-Schutz (nur HTTP(S), keine privaten/Loopback-Adressen, Redirects erneut validieren, Timeout und Größenlimits).
6. Originaltexte vor HTML-Ausgabe escapen oder mit einer expliziten Sanitizer-Allowlist rendern; keine direkten HTML-Skripte.
7. Beim Einreichen Status `pending`; Übergänge und jede Änderung/Entscheidung mit Akteur im Audit-Log speichern.
8. Keine redaktionellen Publikationen ohne Prüfung von Urheberrechten, Quelle und Nutzungsrechten von Bildern.

## Mehrsprachigkeit
- Vorhandene DE/EN-Schalter **weiterverwenden**, UI-Texte in zentralen Schlüsselkatalog auslagern.
- Inhalte im Original speichern, maschinelle Übersetzungen pro Sprache/Feld und `source_hash` cachen; bei Änderungen invalidieren.
- Optional Google Cloud Translation **serverseitig** (API-Schlüssel nie im Frontend, Quoten und Kostenlimit), oder manuell übersetzen.
- Kennzeichnung „automatisch übersetzt“, Schalter „Original anzeigen“ und manuelle Korrekturen durch Redaktion.
- DE/EN zuerst vollständig; zusätzliche Sprachen erst nach Qualitätsprüfung.

## Empfohlene Umsetzung in Stufen
1. Bestehende Auth-/Admin-Logik und Deployment-Mechanik prüfen; Migration integrieren, mit Tests.
2. Anmeldung / Einladung / Kontenverwaltung samt Rollenprüfung.
3. Einreichungsformular für beide Typen, Warteschlange, Review-Workflow und redaktionelles Audit.
4. Publikation in bestehendes Nachrichten-/Kartenmodell integrieren, Duplikate über kanonische URLs erkennen.
5. UI-Sprachkatalog und Übersetzungsdienst plus Cache.

**Wichtig:** Diese Vorbereitungsdateien allein aktivieren nichts auf `news.ifis.net`.
