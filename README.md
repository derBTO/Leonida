# Leonida

Interaktive Karte für GTA VI mit eigener Loot-, Waffen- und Fahrzeugdatenbank. Privates Projekt unter **https://leonida.derbto.de**.

Aktuelle Version: **1.1.2**. Änderungen stehen in [CHANGELOG.md](CHANGELOG.md), Entscheidungen in [docs/ENTSCHEIDUNGEN.md](docs/ENTSCHEIDUNGEN.md).

## Funktionen

- Vollbild-Karte von Leonida (Kacheln von [gtadb.org](https://map.gtadb.org), lokal gehostet)
- Drei Pin-Arten: **Loot-Fundorte**, **Orte (POIs)** in 21 Kategorien und **Hinweise** (Zwischenstationen mit Codes); jeder Pin kann zusätzlich **Easteregg** sein (eigenes Ei-Symbol)
- Übernommene gtadb.org-Orte, per Schalter ein- und ausblendbar (Standard aus)
- **Puzzle**: Hinweise in fester Reihenfolge bis zur Belohnung (Waffen, Fahrzeuge), parallele Schritte, Anzeige mit Linie auf der Karte, Spoiler-Schutz, Fortschritt pro Person
- **Hinweise** mit verknüpftem Ort, Fahrzeugen und Waffen (der Ort wird dort automatisch Fundort) und Schritt-für-Schritt-Anleitung aus Text und Bildern
- Pins mit Loottypen (Fahrzeuge, Waffen, Drogen, Schmuck, Geld), Beträgen, Codes, Bildergalerie, Status und Respawn
- Farbige Marker je Loottyp, kombinierter Ring bei mehreren Typen, Clustering beim Herauszoomen
- Sidebar mit Suche, Loottyp- und Kategorie-Filtern, Ersteller-Filter, Trefferzahl und Liste, optional nur im Kartenausschnitt
- Waffen- und Fahrzeugkatalog mit Werten als Balken, Fahrzeug-Ratings, legendären Waffen, Fundorten und Direktlinks; Grundbestand aus NiaDB, CSV-Import
- Anlegen per Rechtsklick auf die Karte oder per „+“-Button auf Touchgeräten, Verschieben per Ziehen
- Bilder per Datei, Drag-and-drop oder direkt aus der Zwischenablage; Sortieren, Beschreibungen, Lightbox
- Öffentlich lesbar; Bearbeiten nur nach Anmeldung. **Administratoren** dürfen alles, **Bearbeiter** legen Pins und Puzzle an und ändern nur ihre eigenen; den Katalog pflegt nur der Administrator
- Export und Import aller Daten als ZIP, nächtliches Datenbank-Backup mit Wiederherstellung, Installationsprüfung `leonida:check`

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
| `scripts/` | `map-import.sh`, `map-upload.sh`, `deploy.sh`, `backup-pull.sh`, `server-check.php`, `e2e-server.sh`, `niadb_convert.py` |
| `imports/` | Katalogdaten zum Import (NiaDB) |
| `docs/` | Anleitungen, API-Beschreibung, Entscheidungen |
| `docker/`, `docker-compose.yml` | lokale Entwicklungsumgebung |
| `data/` | lokale Kartendaten, nicht in Git |

## Anleitungen

- [Bedienung für Bearbeiter](docs/ANLEITUNG.md)
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

Die Kartenkacheln und übernommenen Orte stammen von gtadb.org (CC BY 4.0), der Waffen- und Fahrzeug-Grundbestand von NiaDB (mit Quellenlink je Eintrag). Alle Hinweise: [NOTICE.md](NOTICE.md).
