# Gemeinsame Quellen- und Profilansicht

Nach `git pull --ff-only origin main` die Seite neu laden. Ein erneuter Import oder Schemawechsel ist nicht erforderlich.

Quellenstandorte und Autorenprofile verwenden auf der News-Karte dieselbe Beitragsansicht und dieselben Quellenmarker. Die Markerziffer zählt die zugeordneten Beiträge. Bereits vertretene Quellen werden nicht zusätzlich als zweiter Quellenpunkt angelegt. Der Kartenfilter „Quellenorte“ steuert beide Arten.

Das gemeinsame **Teilen**-Menü bietet feste und dynamische Sammlungen. Feste Sammlungen dürfen neben IFIS-Artikeln auch ausdrücklich freigegebene manuelle Beiträge enthalten. RSS-Verfügbarkeit ist keine Voraussetzung. Die Inhalte werden aus dem öffentlichen Datenbestand gelesen; übermittelte Titel oder Kommentare aus dem Browser werden nicht übernommen. Offene, abgelehnte und ausschließlich private Netzwerkbeiträge sind für öffentliche Sammlungen nicht zugelassen. Wird eine manuelle Freigabe später zurückgezogen, liefert eine betroffene Sammlung HTTP 410.

Feed- und manuelle Beiträge nutzen `appendSourcePost` als gemeinsame Darstellung: Titel, Quelle, vorhandener Kontext, Ortsangaben, Original-Link und Teilen-Button. Gespeicherte eigene Kommentare bleiben direkt unter ihrem Post und sind nur mit Adminanmeldung sichtbar.

Auch ein angereichertes Profilfeld behält das gemeinsame Sammlungsmenü. Es teilt die freigegebenen Beiträge aus der öffentlichen Auswahl; die private Kommentaransicht verändert diesen Teilen-Inhalt nicht. Im privaten Profilarchiv teilt der obere Button die Original-Links seiner gespeicherten Posts, ohne Kommentartexte. Pro Beitrag kann ebenfalls der Original-Link geteilt werden.

Dynamische Quellenlinks öffnen nach dem Laden auch manuell erfasste Quellenpunkte. Ein bereits geöffneter Quellenpunkt wird beim Nachladen nicht ein zweites Mal geöffnet. Kartenart, Projektion und Helligkeit werden weiterhin im Sammlungslink berücksichtigt.

Die bisherigen Sperren für ungeprüfte externe RSS-Inhalte in alten Sammlungen bleiben bestehen. Der Sammlungscode liest niemals `network.sqlite`.
