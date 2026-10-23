# Ausgewählte LinkedIn-Beiträge veröffentlichen

Die sechs von Lars am 23.10.2026 ausdrücklich ausgewählten Beiträge stehen in `config/curated-posts-2026-10-23.json`. Jeder Eintrag enthält einen eigenen Titel und eine kurze redaktionelle Zusammenfassung, den Urheber, Suchbegriffe und den direkten LinkedIn-Post-Link. Die vollständigen Transkriptionen und Profilinformationen im privaten Netzwerk-Repository werden nicht veröffentlicht.

Nach Aktualisierung des Codes auf dem Server:

```bash
php bin/publish-saved-posts.php
```

Der Befehl übernimmt die ausgewählten Linkkarten als freigegebene Einträge in `storage/source-submissions.sqlite`. Die vorhandene öffentliche Linkübersicht zeigt diese Daten bereits an. Das Erfassungsdatum ist der Zeitpunkt der Veröffentlichung auf IFIS News, kein behauptetes Originaldatum des LinkedIn-Posts.

Ein erneuter Aufruf erzeugt keine Duplikate. Ein bereits freigegebener Eintrag bleibt unverändert. Ein vorhandener offener Eintrag desselben Posts wird freigegeben. Frühere Ablehnungen und doppelte Bestandszuordnungen führen zu einem Abbruch ohne Teilübernahme. LinkedIn-Ansichtsparameter und regionale URL-Varianten verändern die erkannte Aktivitäts-ID nicht.

Optional kann `--dry-run` angehängt werden. Dann wird die gesamte Übernahme einschließlich Schema-Ergänzungen zurückgerollt. Ohne vorhandene Datenbank verwendet die Vorschau eine temporäre Datenbank im Arbeitsspeicher.

Die Beiträge bekommen keinen aus dem Autorenprofil abgeleiteten Ereignis- oder Projektort. Sie erscheinen als Linkkarten; Karteneinträge können später mit belegten Projektorten ergänzt werden.
