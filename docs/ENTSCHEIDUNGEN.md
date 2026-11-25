# Entscheidungen

Vollständiges Konzept mit Datenmodell und Oberfläche: Claude-Dokument „Leonida – Analyse, Architektur & Implementierungsplan“ (24.11.2026). Hier stehen die Entscheidungen, die den Code betreffen.

| Datum | Thema | Entscheidung | Grund |
|---|---|---|---|
| 24.11.2026 | Hosting | Hetzner-Webhosting L/XL mit SSH | vorhanden; kein eigener Server nötig |
| 24.11.2026 | Backend | Laravel 12 (PHP 8.3+) statt FastAPI | Webhosting kann weder Docker noch dauerhaft laufende Python-Prozesse |
| 24.11.2026 | Frontend | Vue 3, TypeScript, Vite, Pinia, Leaflet + markercluster | wie gewünscht; lokal gebaut, als statische Dateien ausgeliefert |
| 24.11.2026 | Datenbank | PostgreSQL aus dem Webhosting | Check-Constraints, `pg_trgm` für Suche |
| 24.11.2026 | Docker | nur für lokale Entwicklung und Tests | Webhosting hat kein Docker |
| 24.11.2026 | Karte | Tile-Sets `day` (Standard), `yanis,18`, `yanis,15`, `dupzor,51` mit Overlays | Wunsch; ca. 320 MB, 58.000 Dateien |
| 24.11.2026 | Karte | kein Landmarken-Layer | Wunsch; Landmarken werden nur zur Prüfung des Koordinatensystems genutzt |
| 24.11.2026 | Koordinaten | Pins speichern Spielmeter (x, y) + Kartenversion | Karten-Updates ändern nur die Darstellung |
| 24.11.2026 | Karten-Import | Kopie mit Python statt rsync, Hardlinks für unveränderte Kacheln | läuft ohne Zusatzinstallation auf macOS und Linux |
| 24.11.2026 | Unbekannte Werte | immer `NULL`, nie 0 oder Standardwert | Anforderung Abschnitt 8 |
| 24.11.2026 | Fahrzeug-Sicherheit | „Nicht angegeben“ ist ein echter Spielwert, leer = unbekannt | Rückmeldung BTO |
| 24.11.2026 | Rechte | Editoren dürfen Pins, Waffen und Fahrzeuge löschen; Benutzer und Zusammenführen von Auswahllisten nur Admin | Rückmeldung BTO |
| 24.11.2026 | Geldbeträge | ganze US-Dollar | im Spiel keine Cent-Beträge |
| 24.11.2026 | Pins ohne Loottyp | erlaubt, Filter „Ohne Loottyp“ | nur Titel und Position sind Pflicht |
| 24.11.2026 | Pin-Links | `/pin/<id>` mit fortlaufender Zahl, zusätzlich UUID für Export/Import | kurze Links, stabiler Abgleich |
| 24.11.2026 | Suchmaschinen | `noindex` | öffentlich lesbar, aber privat gedacht |
| 24.11.2026 | Bilder | JPEG/PNG/WebP bis 20 MB, gespeichert als WebP (2560 px, Vorschau 400 px), Metadaten entfernt | Speicher und Datenschutz |
| 24.11.2026 | Design | GTA-VI-Anmutung mit freien Schriften und eigenen Icons | keine geschützten Rockstar-Inhalte |
| 24.11.2026 | „MODS“ im Referenzbild | nicht umgesetzt | nicht in den Anforderungen |
| 24.11.2026 | Projektlizenz | keine Lizenzdatei, alle Rechte vorbehalten | privates Projekt; Drittlizenzen in `NOTICE.md` |
| 24.11.2026 | API und Anmeldung | API unter `/api` im Web-Middleware-Stack: Session-Cookie, CSRF per `X-XSRF-TOKEN` | gleiche Domain wie das Frontend, kein Token im Browser-Speicher |
| 24.11.2026 | Login-Drosselung | Laravel-RateLimiter (Cache-Tabelle) statt eigener Tabelle `login_attempts` | gleiche Wirkung, weniger Code |
| 24.11.2026 | Loottyp-Felder | Felder zu nicht gewählten Loottypen werden beim Speichern geleert (Beträge, Waffen, Fahrzeuge) | Datensatz entspricht immer der Anzeige |
| 24.11.2026 | Pin-Liste | `/api/pins` liefert alle Pins kompakt in einem Aufruf (mit ETag), Suche und Filter laufen im Browser | sofortige Reaktion auch bei tausenden Pins |
| 24.11.2026 | Waffe/Fahrzeug löschen | mit Fundorten nur nach Bestätigung (`?force=1`), Zuordnungen werden entfernt | kein versehentliches Löschen |
| 24.11.2026 | Auswahllisten | eine Tabelle `lookup_values` mit Kategorie; Fremdschlüssel über (id, Kategorie) | keine falsche Liste zuordenbar, zusammenführen in einer Transaktion |
| 24.11.2026 | Export-Format | ZIP mit `data.json` (Format `leonida-export`, Version 1) und Bildern; Abgleich über UUIDs | unabhängig von Datenbank und IDs |
| 24.11.2026 | Schriften und Icons | Inter, Barlow Condensed (OFL), Lucide (ISC), lokal ausgeliefert | keine externen Abrufe, einheitliche Symbole |
| 24.11.2026 | API-Dokumentation | automatisch aus dem Code (Scramble), `/docs/api` nur angemeldet, Export nach `docs/openapi.json` | bleibt aktuell |
| 25.11.2026 | Server-Layout | Code, `.env` und Backups im Home; nur `index.php`, Frontend, Bilder und Kacheln im Document Root `public_html/derbto.de/leonida` | Hetzner-Webserver darf `/usr/home` nicht betreten; Zugangsdaten liegen damit sicher außerhalb des Webs |
