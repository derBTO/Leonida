# Änderungsprotokoll

## v0.1 – Grundgerüst (24.11.2026)

- Konzept freigegeben: Vue 3 + Leaflet, Laravel 12, PostgreSQL auf Hetzner-Webhosting L/XL (siehe `docs/ENTSCHEIDUNGEN.md`).
- `scripts/map-import.sh`: holt die GTA-VI-Kacheln von gtadb.org per Sparse-Checkout, legt sie als Version `JJJJMMTT-<commit>` ab, übernimmt unveränderte Kacheln per Hardlink, schreibt `manifest.json` und prüft Vollständigkeit und Koordinatensystem. Getestet mit gtadb.org-Commit `b6334a3`: 58.216 Kacheln, 321 MB, keine fehlenden Kacheln.
- `scripts/map-upload.sh`: lädt die aktive Kartenversion per rsync über SSH (Port 222) hoch und schaltet sie um.
- `scripts/server-check.php`: prüft PHP-Version, Erweiterungen, Upload-Grenzen, Symlinks, Schreibrechte und PostgreSQL inkl. `pg_trgm`.
- Tests für den Manifest-Generator (`tests/scripts`).

Bekannte Einschränkung: `map-upload.sh` ist noch nicht gegen das echte Webhosting getestet.
