# IFIS News – RSS-Rechteaudit (vorläufig)
Stand: 20. Oktober 2026

**WICHTIG:** Diese technische und quellengebundene Prüfung ist keine verbindliche Rechtsberatung, keine Lizenz und keine Freigabe. Bis einzelne Nutzungsrechte geklärt sind, dürfen die Quellen nicht als uneingeschränkt rechtssicher behandelt werden. Die Prüfung muss mit dem tatsächlichen Einsatz (öffentliches Newsportal, eigene SQLite-Datenbank, Bilder, mögliche Plus-Mitgliedschaften) abgeglichen werden.

## Ist-Zustand (Repository)
- `config/feeds.json`: 22 Feed-Einträge mit 16 externen Anbietern/Behörden und IFIS selbst.
- `bin/import.php`: lädt externe RSS-Dateien regelmäßig; speichert Links, Titel, **bis zu 450 Zeichen fremde Zusammenfassung**, Bild-URLs und Metadaten in `storage/news.sqlite` – ohne Lizenzprüfung und ohne Löschfrist. Bilder werden aus Media RSS, Enclosure oder HTML herausgelesen.
- `public/index.php`: zeigt fremde Titel/Snippets und bindet fremde Bild-URLs als `<img>` ein. Externe Bilder sind lizenzrechtlich **und datenschutzrechtlich** separat zu prüfen (direkte Drittanfragen).
- `public/collection.php`: speichert artikelbezogene Titel und Zusammenfassungen in dauerhaften teilbaren Sammlungen; auch dort sind fremde Inhalte betroffen. Bestehende Sammlungen sind separat zu prüfen.
- Analyseroutine `article_analysis` und Such-/Filterfunktionen greifen auf fremde Zusammenfassungen zu.
- Ein mitgelieferter Feed oder ein Hinweis „Quelle“ ist weder pauschales Recht zur Archivierung noch zur kommerziellen Nutzung.

## Quelle-für-Quelle: Status
**Nicht autorisiert** bedeutet hier: Für die konkret implementierte Nutzung ist bislang **keine hinreichende Lizenz belegt**, nicht automatisch, dass jede bloße Verlinkung verboten ist.

| Quellen in feeds.json | Stand nach gefundenen Primärquellen | Bewertung der aktuellen Implementierung |
|---|---|---|
| IFIS · Artikel (1) | Eigener Inhalt; eigene Rechte und eingebundene Bilder Dritter separat prüfen | prinzipiell freigabefähig |
| The Guardian (2) | RSS für persönliche nichtkommerzielle Nutzung; T&C untersagen weitgehende Aggregation/Analyse/KI ohne Zustimmung | **kritisch: nicht autorisiert** |
| ScienceDaily (5) | Website erlaubt verlinkte Headlines/Zusammenfassungen, aber keine dauerhafte eigene Datenbank; Content nicht verändern; Bilder und Werbeassoziation beschränkt | **kritisch: Speicherung/Archiv, Bildnutzung** |
| Deutsche Welle (1) | Partner-RSS und Content-Box-Angebot mit eigener Kontakt-/Vereinbarungsstruktur | **ungeklärt: Partnerfreigabe einholen** |
| NASA (1) | NASA-Medien meist als US-Regierungswerke nutzbar, Drittmaterial, Logos, Persönlichkeitsrechte ausgenommen | **vergleichsweise günstig; Assets pro Artikel prüfen** |
| Phys.org (1) | RSS-Syndication wird angeboten; genaue Berechtigung zu dauerhafter Archivierung und kostenpflichtigen Features nicht bestätigt | **ungeklärt** |
| BBC News (3) | RSS mit Quellenangabe teilweise erlaubt; Business/Metadaten erfordern separate Freigabe | **kritisch: kommerzielle Nutzung ungeklärt** |
| Al Jazeera (1) | T&C: personenbezogene nichtkommerzielle Nutzung; Contentübernahme, Speicherung und Mining eingeschränkt | **kritisch: nicht autorisiert** |
| France 24 (1) | keine ausreichend eindeutige einschlägige Primärlizenz für diese Nutzung bestätigt | **ungeklärt** |
| NPR (1) | keine ausreichend eindeutige einschlägige Primärlizenz für diese Nutzung bestätigt | **ungeklärt** |
| New York Times (1) | keine belastbare Primärlizenz für öffentliche/kommerzielle Archivierung bestätigt | **ungeklärt/hohes Risiko** |
| Euronews (1) | T&C: grundsätzlich persönliche nichtkommerzielle Nutzung, untersagt größere Datenbankkopien und Verwertung ohne Zustimmung | **kritisch: nicht autorisiert** |
| NOAA (1) | US-Regierungsinhalte oft frei; Drittmaterial, Bilder, Logos/Endorsement separat prüfen | **vergleichsweise günstig; Einzelfallprüfung** |
| IEEE Spectrum (1) | keine spezifische RSS-Syndikationslizenz für die konkrete Nutzung geprüft; sonstige IEEE-Rechte lassen keine pauschale Freigabe zu | **ungeklärt** |
| DER SPIEGEL (1) | Verlags-FAQ erlaubt Überschriften und Teaser aus RSS **ohne Bilder**, mit Verlinkung | **bedingt erlaubt: Bildverzicht; Archivierung/Plus gesondert prüfen** |

## Primärquellen
- Guardian RSS: https://www.theguardian.com/help/feeds
- Guardian Nutzungsbedingungen: https://www.theguardian.com/help/terms-of-service
- ScienceDaily RSS: https://www.sciencedaily.com/terms.htm
- BBC RSS: https://downloads.bbc.co.uk/usingthebbc/bbc_terms_of_use_31March2022english.pdf
- DW Partnerangebote: https://amp.dw.com/en/news-from-germany/a-65919184
- Spiegel Syndication-FAQ: https://gruppe.spiegel.de/syndication/haeufige-fragen
- Spiegel Impressum: https://gruppe.spiegel.de/impressum
- Al Jazeera Bedingungen: https://www.aljazeera.com/news/2017/10/15/terms-and-conditions
- Euronews: https://www.euronews.com/terms-and-conditions
- Phys.org RSS: https://phys.org/feeds/
- NASA: https://www.nasa.gov/nasa-brand-center/images-and-media/
- NOAA: https://www.fisheries.noaa.gov/national/about-us/website-policies-and-disclaimers
- Deutsches Urheberrecht: https://www.gesetze-im-internet.de/urhg/__87g.html und https://www.gesetze-im-internet.de/urhg/__44b.html und https://www.gesetze-im-internet.de/urhg/__51.html

## Empfohlener Sicherheitsplan
1. **Vor weiterer ungeprüfter kommerzieller/öffentlicher Nutzung:** Anbieter mit ausdrücklichen Beschränkungen auf Pause setzen; offene Quellen bis zur Lizenzklärung nicht neu importieren. Bestehende fremde Snippets/Bilder (auch gespeicherte Sammlungen) gesondert ausblenden oder entfernen. Nicht eigenmächtig mit eigener Paraphrase aus möglicherweise unzulässig aggregierten Texten umgehen.
2. Eine **explizite Allowlist** pro Quelle führen: Quelle, Feed-URL, Rechtsgrundlage/Vertrag, erlaubte Felder, Bildlizenz, Archivdauer, KI/Analyse, kommerzielle Nutzung, Datum der Prüfung, Beleg/Ansprechpartner. Ohne dokumentierte Freigabe: **kein Import**, nicht nur "keine Bilder".
3. Umsetzbare Modellvarianten: (a) reine Link- und Faktenhinweise ohne fremde Text- oder Bildübernahme unter rechtlich geprüften Abrufbedingungen, (b) je Quelle lizenzierte verlinkte Headlines/Teaser, (c) offene Behörden-/CC-Inhalte nach Einzelnachweis, (d) kommerzielle Content-API/Partnerverträge.
4. Bilder standardmäßig ausschalten, bis pro Asset die Nutzung geklärt ist (einschließlich Hotlinking, Tracking, Urhebercredits).
5. Archiv, Snapshots und Backups erfassen; Lizenz widerrufen bedeutet auch Altbestände prüfen. Nur Daten im nötigen Umfang und nur solange erlaubt speichern.
6. § 87g UrhG schützt Hyperlinks und einzelne Wörter/sehr kurze Auszüge, **nicht pauschal 450-Zeichen-Teaser**. § 51 UrhG ist kein allgemeines Snippetrecht; § 44b UrhG (TDM) ist kein allgemeines Recht zur öffentlichen Wiederveröffentlichung.
7. Vor Plus/Monetarisierung verbindliche Prüfung durch Medien-/Urheberrechtsanwalt mit dem Feedinventar und sämtlichen Nutzungsbedingungen.

**Keine automatische Änderung der Feed-Auswahl vorgenommen:** Eine umfassende Pausierung wäre ein erheblicher Eingriff in das öffentliche Produkt und muss bewusst freigegeben werden.
