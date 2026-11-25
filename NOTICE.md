# Quellen und Lizenzen

## Kartenkacheln – gtadb.org

- Quelle: https://github.com/rolux/gtadb.org (Karte: https://map.gtadb.org)
- Lizenz der Daten und Kacheln: Creative Commons Attribution 4.0 International (CC BY 4.0), https://creativecommons.org/licenses/by/4.0/
- Copyright (C) 2025-2026 GTADB.ORG and contributors
- Namensnennung in Leonida: „GTA VI Map Tiles (CC BY 4.0) — https://map.gtadb.org“ und „gtadb.org and all contributors“, unten rechts auf der Karte und auf der Seite „Quellen & Lizenzen“.
- Genutztes Tile-Set: `day` (Satellitenkarte).
- Änderungen durch Leonida: Die Kacheln werden unverändert übernommen. Hinzugefügt wird nur eine `manifest.json` mit Kachelbereichen und Prüfsummen. Die Zuordnung der Kachelbereiche stammt aus `maps/maps.js` von gtadb.org (Code unter MIT-Lizenz, Copyright (C) 2025-2026 GTADB.ORG); Leonida übernimmt daraus nur die Umrechnungsformel, keinen Code.
- Die Lizenzdatei von gtadb.org (`LICENSE-DATA`) liegt jeder importierten Kartenversion bei.

## Orte (POIs) – gtadb.org-Landmarken

Ein Teil der Orte wurde aus `map/data/6/landmarks.json` von gtadb.org übernommen (CC BY 4.0, „GTA VI Landmarks Data (CC BY 4.0) — https://map.gtadb.org“). Übernommen werden Name, Position und reales Vorbild; jede Beschreibung verlinkt auf den Originaleintrag. Kategorien wurden aus den gtadb-Tags abgeleitet.

## Grand Theft Auto VI

Grand Theft Auto VI, Leonida und zugehörige Namen sind Marken von Take-Two Interactive bzw. Rockstar Games. Leonida ist ein privates Fanprojekt ohne Verbindung zu diesen Unternehmen und verwendet keine Logos, Schriften oder Grafiken aus dem Spiel.

## Bibliotheken

| Bibliothek | Lizenz |
|---|---|
| Laravel, Intervention Image, Scramble | MIT |
| Vue, Vue Router, Pinia, Vite | MIT |
| Leaflet, Leaflet.markercluster | BSD-2-Clause, MIT |
| Lucide Icons (auch die Marker-Symbole) | ISC |
| Inter, Barlow Condensed (über Fontsource) | SIL Open Font License 1.1 |

Vollständige Lizenztexte liegen in `backend/vendor` bzw. `frontend/node_modules` der jeweiligen Pakete.
