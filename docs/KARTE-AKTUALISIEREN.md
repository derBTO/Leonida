# Kartengrundlage aktualisieren

Leonida hostet die Kartenkacheln von gtadb.org selbst. gtadb.org bittet ausdrücklich darum, keine Daten live von seinen Servern abzurufen, sondern das GitHub-Repository zu nutzen. Genau das tun die Skripte.

## Ablauf

1. **Lokal importieren** (Mac):
   ```
   scripts/map-import.sh
   ```
   - lädt nur die GTA-VI-Kacheln, `maps/maps.js`, die Landmarken und die Lizenz (Sparse-Checkout, Cache unter `data/cache/gtadb.org`)
   - legt die Version unter `data/map/versions/<JJJJMMTT>-<commit>/` an
   - übernimmt unveränderte Kacheln per Hardlink aus der aktiven Version, belegt also nur für geänderte Kacheln neuen Platz
   - schreibt `manifest.json` und prüft: alle Kacheln im Bereich vorhanden, Kartenparameter unverändert, Landmarken nicht systematisch verschoben
2. **Ergebnis ansehen.** Bei Problemen bleibt die Version inaktiv und das Skript nennt den Grund.
3. **Aktivieren**:
   ```
   scripts/map-import.sh --activate
   ```
   setzt `data/map/current` auf die neue Version.
4. **Hochladen**:
   ```
   scripts/map-upload.sh --dry-run   # zeigt nur, was übertragen würde
   scripts/map-upload.sh
   ```
   überträgt nur geänderte Kacheln nach `<Document Root>/map/versions/` auf dem Server, schaltet `map/current` um, trägt die Version in die Datenbank ein und zeigt eine Vorschau neuer Orte aus gtadb.org. Voraussetzung: `deploy.env` (Vorlage `deploy.env.example`) und ein SSH-Schlüssel.
5. **Neue Orte übernehmen** (optional): den Befehl, den `map-upload.sh` am Ende ausgibt.
6. **Aufräumen** (optional): alte Ordner unter `data/map/versions/` bzw. `<Document Root>/map/versions/` löschen, sobald die neue Version läuft.

Die eigenen Pins sind davon nicht betroffen. Sie liegen in der Datenbank und speichern Spielkoordinaten, keine Kachelpositionen.

## Optionen von map-import.sh

| Option | Bedeutung |
|---|---|
| `--set NAME` | nur dieses Tile-Set (mehrfach möglich). Standard: `day`. Die Community-Karten (`yanis,18`, `yanis,15`, `dupzor,51`) lassen sich so wieder holen, werden von Leonida aber derzeit nicht angezeigt |
| `--ref REF` | Branch oder Commit von gtadb.org, Standard `main` |
| `--data-dir DIR` | anderer Datenordner, Standard `data/` im Projekt (oder `LEONIDA_DATA_DIR`) |
| `--activate` | neue Version direkt aktivieren |
| `--force` | auch bei Problemen weitermachen bzw. aktive Version neu importieren |

## Koordinatensystem

gtadb.org arbeitet in Spielmetern, y wächst nach Norden. Die Welt ist 32.768 Einheiten breit, der Nullpunkt liegt bei Pixel 16.384, auf Zoom z ist die Welt `1024 · 2^z` Pixel breit:

```
px = (x + 16384) · 2^z / 32
py = (16384 − y) · 2^z / 32
```

In Leaflet: `L.CRS.Simple` mit `L.Transformation(1/32, 512, −1/32, 512)`. Kachel-URL: `{z}/{z},{y},{x}.jpg` – y steht vor x.

Grenzen des Tile-Sets `day` (Zoom 6, Stand 24.11.2026): x −12.032 bis 5.120, y −10.112 bis 12.032.

## Koordinatensystem geändert

Das Import-Skript bricht ab, wenn

- die Konstanten in `maps.js` (`mapW`, `mapH`, `zeroX`, `zeroY`, `tileSize`) oder die Formel `1024 · 2^z` nicht mehr stimmen, oder
- der Landmarken-Vergleich eine Transformation findet, die die Karte um mehr als 1 m verschiebt. Einzelne von der Community verschobene Landmarken zählen dabei nicht; erkannt wird nur eine gemeinsame Verschiebung, Drehung oder Skalierung.

Dann gilt:

1. `data/map/versions/<neu>/manifest.json`, Abschnitt `landmarkComparison`, ansehen. `transform` enthält die Umrechnung alt → neu (`x' = a·x + b·y + c`, `y' = d·x + e·y + f`).
2. Die Pins werden mit dem Werkzeug zur Koordinaten-Migration umgerechnet (folgt mit dem Backend; es nutzt genau diese Transformation, zeigt vorher eine Vorschau und ändert erst nach Bestätigung).
3. Erst danach die neue Kartenversion aktivieren und hochladen.

Zum manuellen Vergleich zweier Stände:

```
python3 scripts/lib/map_manifest.py compare --old data/map/versions/A/landmarks.json --new data/map/versions/B/landmarks.json
```
