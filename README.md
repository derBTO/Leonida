# Leonida

Interaktive Karte für GTA VI mit eigener Loot-, Waffen- und Fahrzeugdatenbank. Privates Projekt, erreichbar unter **https://leonida.derbto.de**.

Aktueller Stand: **0.1 – Grundgerüst** (Karten-Import und Server-Prüfung fertig, Anwendung folgt). Änderungen stehen in [CHANGELOG.md](CHANGELOG.md), Entscheidungen in [docs/ENTSCHEIDUNGEN.md](docs/ENTSCHEIDUNGEN.md).

## Überblick

| Teil | Technik | Stand |
|---|---|---|
| Kartengrundlage | Kacheln von [gtadb.org](https://map.gtadb.org), lokal gehostet | fertig: Import, Versionierung, Upload |
| Frontend | Vue 3, TypeScript, Vite, Leaflet | folgt |
| Backend | Laravel 12, PHP 8.3+ | folgt |
| Datenbank | PostgreSQL (Hetzner-Webhosting) | folgt |
| Betrieb | Hetzner-Webhosting L/XL, Deployment per SSH | Prüfskript fertig |

## Verzeichnisse

| Pfad | Inhalt |
|---|---|
| `scripts/map-import.sh` | holt die GTA-VI-Kacheln von gtadb.org als neue Kartenversion |
| `scripts/map-upload.sh` | lädt die aktive Kartenversion auf das Webhosting |
| `scripts/server-check.php` | prüft, ob das Webhosting alles für Leonida bietet |
| `scripts/lib/` | Hilfsprogramme der Skripte |
| `tests/scripts/` | Tests der Skripte |
| `docs/` | Anleitungen und Entscheidungen |
| `data/` | lokale Kartendaten, nicht in Git |

## Kartendaten holen

Voraussetzungen: `git` und `python3` (auf dem Mac mit den Xcode Command Line Tools vorhanden).

```
scripts/map-import.sh --activate
```

Beim ersten Lauf werden ca. 320 MB (rund 58.000 Kacheln) nach `data/map/` geladen. Details, Updates und Upload: [docs/KARTE-AKTUALISIEREN.md](docs/KARTE-AKTUALISIEREN.md).

## Webhosting prüfen

`scripts/server-check.php` per SFTP in den Leonida-Ordner auf dem Server kopieren, dann per SSH:

```
ssh -p 222 benutzer@wwwXXX.your-server.de
cd leonida
LEONIDA_DB_PASSWORD='…' php server-check.php --db-host=… --db-name=… --db-user=…
```

Hetzner nutzt für SSH Port 222. Die Ausgabe zeigt für jede Anforderung OK, WARNUNG oder FEHLER.

## Tests

```
python3 -m unittest discover -s tests/scripts
```

## Quellen und Lizenzen

Die Kartenkacheln stammen von gtadb.org und stehen unter CC BY 4.0. Alle Hinweise: [NOTICE.md](NOTICE.md).
