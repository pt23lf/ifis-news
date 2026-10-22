# Eigene Screenshot-Kommentare

`admin-kommentare.php` zeigt nach Adminanmeldung die eigenen Einträge aus `capture_comments`, zugeordnet über `capture_profiles.source_key = profile-lars-focke`. Fremde Kommentare erscheinen weiterhin im Gesprächsnetz. Die bisherigen CSV-Daten bleiben separat aufklappbar; es werden keine unsicheren Text- oder Namensvergleiche zur Zusammenführung verwendet.

Für ausdrücklich geprüfte Ergänzungen bestehender Kommentartexte unterstützt der Import optional `previous_text_sha256`: SHA-256 des bisherigen UTF-8-Texts. Autor und Beitrag müssen unverändert sein. Nur ein passender bisheriger Hash erlaubt eine Text- oder Statusänderung. Ein bereits übernommener neuer Text ist beim Wiederholen idempotent. Ein fehlender Zielkommentar mit gesetztem bisherigen Hash führt zum Abbruch. Änderungen werden innerhalb der vorhandenen Pakettransaktion übernommen. Originalpakete im privaten Repository bleiben unverändert.

Deployment: Code aktualisieren, anschließend privaten Datensync ausführen. Das neue Datenpaket setzt den ergänzten Importcode voraus. Kein automatischer Livezugriff aus dem Chat.
