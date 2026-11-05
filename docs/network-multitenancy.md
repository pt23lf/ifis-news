# IFIS News: persönliche Netzwerke und Sprachen

**Arbeitsstand:** Vorbereitung; noch keine Live-Freischaltung.

## Ist-Zustand
`public/admin-netzwerk.php` verwendet eine einzelne Passwortdatei (`storage/admin-password.hash`) und `storage/network.sqlite`. Die Tabelle `contacts` hat bisher keinen Kontobesitzer; eine globale Unique-Regel besteht für LinkedIn-URLs. Das darf nicht direkt für Contributor freigeschaltet werden.

## Migration (vor Freischaltung)
1. SQLite-Backup anlegen und Kopie für Testmigration verwenden. Bestehende Kontakte eindeutig dem ersten Admin-Konto zuordnen.
2. Netzwerk-Datenmodell pro Nutzer (`owner_user_id`) plus explizite Freigaben pro Kontakt einführen; Index für LinkedIn-URLs nach Besitzer statt global. Ähnliche Änderungen an `capture_profiles`, `capture_posts`, `capture_comments` und `linkedin_comments` berücksichtigen.
3. **Jede** lesende und schreibende Abfrage (inkl. CSV, Geocoding, Löschen, Kommentarzählung, JSON und Kartenmarkers) serverseitig auf den eingeloggten Besitzer oder seine expliziten Freigaben begrenzen.
4. Private Notizen grundsätzlich **niemals** über Kontaktfreigaben veröffentlichen.
5. Bestehendes Admin-Passwort nur nach abgesicherter Migration und erfolgreichem Admin-Login ablösen; vorher kein Contributor-Zugang.
6. Integrationstests mit mindestens zwei Nutzern auf ID-Manipulation, Kontakt-Dubletten, CSV-Import und Karten-Lecks durchführen.

## I18n
Gemeinsame Sprachkataloge in `lang/de.php` und `lang/en.php` sowie `lib/I18n.php`. Sie sind derzeit nur die Grundlage und **noch nicht** an die bestehenden Seiten angeschlossen. Sprachwahl pro Benutzerprofil speichern; auf öffentlichen Seiten ggf. Session/Cookie, bei Login Profilpräferenz verwenden. Oberflächentexte nicht an Google Translate senden; diese redaktionell pflegen. Inhalte später über einen serverseitigen Übersetzungsdienst mit Source-Hash-Cache, Kennzeichnung und Originalansicht übersetzen.

## Ausgangslage
Bei persönlicher Netzwerkansicht alle Daten privat halten. Eine explizite Freigabe schafft nur Sichtbarkeit des ausgewählten Kontakts, nie der internen Notizen oder Import-Rohdaten.
