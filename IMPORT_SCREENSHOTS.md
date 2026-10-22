# Private Screenshot-Erfassung

Aufruf nach Deployment: `/admin-erfassung.php`, Anmeldung über `/admin-netzwerk.php`.
Gemeinsame Datenbank: `storage/network.sqlite`. Keine öffentliche Freigabe und keine externen LinkedIn-Abfragen. Der Screenshotimport verändert die alten CSV-Kommentare nicht.

## Workflow für den Chat

1. Nutzer gibt Profil-, Beitrags- und Kommentarscreenshots. Nur tatsächlich lesbare Angaben extrahieren. Nicht lesbare Angaben leer lassen; keine Links, Orte, Namen oder Textfortsetzungen erfinden. Projektort niemals aus Profilstandort ableiten. Unklare Autor- oder Beitragszuordnung vor dem verknüpften Import klären; Profile können zunächst allein gespeichert werden.
2. Ein JSON-Paket mit `schema_version: 1` und Listen `profiles`, `posts`, `comments` erstellen. Pro Objekt einen stabilen `key` und einen `evidence`-Verweis auf den Screenshot und sichtbaren Abschnitt vergeben. Bei Folgeimporten vorhandene Schlüssel aus dem geschützten Importbereich wiederverwenden.
3. `profiles`: `key`, `name`, `linkedin_url`, `homepage_url`, `headline`, `city`, `country`, `tags`, `evidence`. Optional `contact_id` für eine ausdrücklich geprüfte Zuordnung zu einem bestehenden Kontakt. Profillinks müssen eindeutige LinkedIn `/in/…` oder `/company/…` URLs sein. Links werden ohne Tracking normalisiert; Name allein bewirkt keine Zusammenführung.
4. `posts`: `key`, `author_key` (optional), `url`, `title`, `excerpt`, `place`, `tags`, `evidence`. Titel in eigenen Worten, excerpt nur sichtbarer Text. `author_key` verweist auf ein Profil dieses Pakets oder einen bestehenden Profil-Schlüssel. Ein bereits unter einem anderen Schlüssel gespeicherter Beitragslink wird mit einer Fehlermeldung zur Zuordnung angezeigt.
5. `comments`: `key`, `post_key`, `author_key`, `text`, `text_status` (`excerpt` oder `complete`), `url`, `evidence`. Lars als eigenes Profil erfassen, wenn er als Kommentarautor erscheint. Andere Kommentatoren nur erfassen, soweit sie tatsächlich eingelesen sind. Keine impliziten Kontakte oder persönlichen Beziehungen ableiten.
6. Paket in der Vorschau validieren, danach unverändert privat speichern. Bis 200 Objekte / 1 MB. Vorschau führt den Import in einer zurückgerollten Transaktion aus. Fehler rollen das gesamte Paket zurück. Identische Schlüssel verhindern doppelte Einträge; bestehende manuelle Kontaktfelder werden nur ergänzt, wenn sie leer sind. Textänderungen bestehender Kommentare benötigen eine bewusste spätere Bearbeitungsfunktion und werden hier nicht still überschrieben.

## Verbindung zum Chat

Der Import ist über die bestehende Adminsitzung und CSRF geschützt. Ein angemeldeter, für die Assistenz zugänglicher Browser kann das Paket über das Formular übertragen. Es gibt keinen anonymen Schreibendpunkt, keine hinterlegten Zugangsdaten und keinen behaupteten direkten Datenbankzugang aus dem Chat. Ohne verfügbare Adminsitzung muss das Paket manuell eingefügt werden. Zugangsdaten nicht in Chat, Importpaket oder Git speichern.

## Darstellung

`admin-erfassung.php` zeigt ein interaktives Profil–Beitrag-Netz mit den letzten 12 Beiträgen und höchstens 40 beteiligten Profilen sowie eine durchsuchbare Gesprächsliste (bis 100 Beiträge). Linien stehen für Autorenschaft oder erfasste Kommentare am selben Beitrag, nicht für Freundschaft oder eine bestätigte persönliche Verbindung. Profilorte erscheinen über die vorhandene private Kontaktkarte und deren manuell gestartete Ortszuordnung. Ein Profil ohne bekannten Ort bleibt im Gesprächsnetz sichtbar.

Bestehende CSV-Daten werden weiterhin in ihren bisherigen Ansichten angezeigt. Ihre oft unvollständigen Beitragszuordnungen werden nicht automatisch in das neue Gesprächsnetz umgedeutet.
