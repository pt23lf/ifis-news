# IFIS-News-Kompass – Modell v0.1 (20.10.2026)

## Grundsatz
Der Kompass zeigt **Art und Richtung der Informationsaktivität**, nicht Wahrheit, journalistische Qualität oder moralischen Wert einzelner Medien. Medienreichweite und Konstruktivität sind zwei getrennte Achsen. Geringe Lautstärke bedeutet nicht automatisch konstruktiv.

## Geplante Datendimensionen
1. **Aktivität:** GDELT-Ereignisbeobachtungen je Ort und Zeitfenster (bereits technisch vorhanden; keine deduplizierten Nachrichten).
2. **Vielfalt:** verschiedene normalisierte First-Source-Domains, ausdrücklich nicht gleich Medienunternehmen und nicht vollständig.
3. **Ereignistyp:** Klassifikation aus ausdrücklich zur Nutzung geprüften strukturierten GDELT-CAMEO-Feldern; noch nicht implementiert.
4. **Handlungsorientierung:** gesonderte redaktionelle Einstufung mit Beleg, z. B. Problembericht, Problemanalyse, Lösungsvorschlag, dokumentierte Umsetzung, nachgewiesene Wirkung; vorerst **unbekannt**.
5. **IFIS-Relevanz:** separate inhaltliche Kategorisierung (menschliche Funktionsfähigkeit, strukturelle Entlastung, gesellschaftliche Systeme, Naturregeneration), ebenfalls vorerst **unbekannt**.

## Farben und Kompass
- Grau: nicht geprüft / nicht zuordenbar.
- Orange: hohe Aufmerksamkeit oder Aktivität, **keine** Aussage über Sensationalismus.
- Grün: belegte konstruktive Lösungs- oder Umsetzungsperspektive, niemals allein wegen Domain, geringer Reichweite oder niedriger Aktivität.
- Richtung der Nadel: später gesonderte, erklärte Bewertungsachse, nicht rein aus GDELT-Zahlen ableiten.
- Punktgröße = skalierte Aktivitätsmenge; Helligkeit oder Ring = Quellenvielfalt. Farbsemantik darf dieselben Daten nicht doppelt bewerten.

## Quellenprofil
`public/source-offices.json` enthält derzeit nur vorläufig zugeordnete Redaktionsstandorte. Es ist **keine** Datenbank des redaktionellen Tons oder von konstruktiven Inhalten. Quellenprofile müssen zwischen Organisationsstandort, Artikelstandort und tatsächlich beobachteten Beiträgen unterscheiden.

## Rechtliche Schranke
Keine automatisierte Auswertung journalistischer Volltexte, Snippets oder Überschriften ohne nachweisbare Nutzungsrechte. GDELT-Eventcodes können eine Ereigniskategorie liefern, aber sie beweisen keine positive Wirkung, keine Lösung und keine reißerische Berichterstattung.

## Datentechnik und Qualität
Zeitfilter seit 20.10.2026 mit minutengenauen Aktivitäts-Bins je Rasterzelle, Daten weiterhin auf die im API ermittelten Top-250-Zellen der letzten 48 Stunden begrenzt. Quellenliste je Zelle bleibt auf bis zu 45 First-Source-URLs begrenzt und ist daher für Quellenvielfalt nur eine Untergrenze. Browser- und Produktionsvalidierung ausstehend.
