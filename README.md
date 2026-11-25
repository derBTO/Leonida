# Leonida

Interaktive Karte für GTA VI mit eigener Loot-, Waffen- und Fahrzeugdatenbank. Privates Projekt unter **https://leonida.derbto.de**.

Aktuelle Version: **0.9.1**. Änderungen stehen in [CHANGELOG.md](CHANGELOG.md), Entscheidungen in [docs/ENTSCHEIDUNGEN.md](docs/ENTSCHEIDUNGEN.md).

## Funktionen

- Vollbild-Karte von Leonida (Kacheln von [gtadb.org](https://map.gtadb.org), lokal gehostet)
- Orte (POIs) in 21 Kategorien, z. B. Tankstellen, Haltestellen, Garagen und Wohnungen, mit eigenem Filter
- Pins mit Loottypen (Fahrzeuge, Waffen, Drogen, Schmuck, Geld), Beträgen, Codes, Bildergalerie, Status und Respawn
- Farbige Marker je Loottyp, kombinierter Ring bei mehreren Typen, Clustering beim Herauszoomen
- Sidebar mit Suche, Loottyp-Filtern, Trefferzahl und Liste, optional nur im Kartenausschnitt
- Waffen- und Fahrzeugkatalog mit Werten als Balken, Fahrzeug-Ratings, Fundorten und Direktlinks
- Anlegen per Rechtsklick auf die Karte oder per „+“-Button auf Touchgeräten, Verschieben per Ziehen
- Bilder per Datei, Drag-and-drop oder direkt aus der Zwischenablage; Sortieren, Beschreibungen, Lightbox
- Öffentlich lesbar; Bearbeiten nur nach Anmeldung (Rollen Administrator und Editor)
- Export und Import aller Daten als ZIP, nächtliches Datenbank-Backup

## Technik

| Teil | Technik |
|---|---|
| Frontend | Vue 3, TypeScript, Vite, Pinia, Leaflet + Leaflet.markercluster |
| Backend | Laravel 12, PHP 8.3+ |
| Datenbank | PostgreSQL (Hetzner-Webhosting) |
| Betrieb | Hetzner-Webhosting L/XL, Deployment per SSH |
| Entwicklung | Docker Compose oder lokales PHP + PostgreSQL |

## Verzeichnisse

| Pfad | Inhalt |
|---|---|
| `backend/` | Laravel-Anwendung: API, Datenbank-Migrationen, Tests |
| `frontend/` | Vue-Anwendung |
| `scripts/` | `map-import.sh`, `map-upload.sh`, `deploy.sh`, `server-check.php` |
| `docs/` | Anleitungen, API-Beschreibung, Entscheidungen |
| `docker/`, `docker-compose.yml` | lokale Entwicklungsumgebung |
| `data/` | lokale Kartendaten, nicht in Git |

## Anleitungen

- [Installation auf dem Hetzner-Webhosting](docs/INSTALLATION.md)
- [Lokale Entwicklung und Tests](docs/ENTWICKLUNG.md)
- [Backup und Wiederherstellung](docs/BACKUP.md)
- [Kartengrundlage aktualisieren](docs/KARTE-AKTUALISIEREN.md)
- [Waffen und Fahrzeuge per CSV importieren](docs/KATALOG-IMPORT.md)
- [API](docs/API.md)

## Schnellstart lokal

```
scripts/map-import.sh --activate   # Kartenkacheln holen (ca. 90 MB)
docker compose up                  # http://localhost:5173, Anmeldung BTO / entwicklung-123
```

## Quellen und Lizenzen

Die Kartenkacheln stammen von gtadb.org und stehen unter CC BY 4.0. Alle Hinweise: [NOTICE.md](NOTICE.md).
