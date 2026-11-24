# Änderungsprotokoll

## v0.2 – Erste vollständige Version (25.11.2026)

**Karte und Pins**
- Vollbild-Karte mit eigener Leaflet-Projektion für die gtadb.org-Kacheln; Ebenen „Satellit (Tag)“ und drei Community-Karten, Detail-Overlays zuschaltbar.
- Pins mit Titel, Beschreibung, Loottypen, Waffen, Fahrzeugen, Beträgen (Drogen, Schmuck, Geld), Codes, Bildern, Hinweisen, Respawn und Status. Nur Titel und Position sind Pflicht.
- Marker in Loottyp-Farben, Ring mit Segmenten bei mehreren Typen, gestrichelt bei „unbestätigt“, grau bei „nicht mehr verfügbar“. Clustering mit Farbanteilen.
- Anlegen per Rechtsklick oder „+“-Button mit Fadenkreuz, Bearbeiten in schwebenden Dialogen, Verschieben per Ziehen, Löschen, Direktlink `/pin/<id>`.
- Sidebar: Suche (Titel, Beschreibung, Waffen, Fahrzeuge, Codes), Loottyp-Filter (ODER, auch „Ohne Loottyp“), Trefferzahl, alphabetische Liste mit virtuellem Scrollen, „Nur im Kartenausschnitt“.
- Kartenposition, Zoom, Ebene, Filter und Sidebar-Zustand bleiben im Browser gespeichert.

**Katalog**
- Waffen und Fahrzeuge als eigene Übersichten und Detailkarten nach Vorgabe, Werte als Balken, Fahrzeug-Ratings hervorgehoben, Fundorte mit Sprung zur Karte.
- Werte-Eingabe mit synchronem Schieberegler und Zahlenfeld, „unbekannt“ getrennt von 0.
- Dynamische Auswahllisten mit Neuanlage im Dropdown und Dublettenschutz; Admin kann umbenennen, zusammenführen und löschen.
- Neue Waffe bzw. neues Fahrzeug direkt aus dem Pin-Dialog anlegen.

**Bilder**
- Upload per Datei, Drag-and-drop und Zwischenablage; Prüfung anhand des Inhalts, Neukodierung als WebP, Vorschaubilder, Metadaten entfernt.
- Galerie mit Pfeilen, Zähler, Beschreibung und Lightbox; Sortieren und Beschreibungen im Bearbeiten-Dialog.

**Benutzer und Sicherheit**
- Rollen Administrator und Editor, Administrator **BTO** aus der `.env`, Passwortwechsel beim ersten Anmelden erzwungen, Argon2id/bcrypt.
- Serverseitige Rechteprüfung, CSRF-Schutz, Login-Drosselung, Sicherheits-Header (CSP, `noindex`), Audit-Protokoll.

**Betrieb**
- Laravel 12 für Hetzner-Webhosting, `scripts/deploy.sh` mit Release-Verzeichnissen und Rollback.
- Export/Import aller Daten als ZIP mit Probelauf; `leonida:backup` per Cronjob; `leonida:migrate-coords` für Kartenwechsel.
- Docker-Compose-Umgebung für die lokale Entwicklung.
- Tests: 75 Backend-Tests, 8 Frontend-Tests, 10 Skript-Tests; Kernabläufe im Browser durchgespielt.

Bekannte Einschränkungen:
- `deploy.sh`, `map-upload.sh` und `server-check.php` sind noch nicht gegen das echte Webhosting gelaufen.
- `docker-compose.yml` ist noch nicht getestet (in der Entwicklungsumgebung stand kein Docker-Dienst zur Verfügung).
- `leonida:backup` braucht `pg_dump` auf dem Server; fehlt es, bleiben die Hetzner-Sicherungen und der ZIP-Export.

## v0.1 – Grundgerüst (24.11.2026)

- Konzept freigegeben: Vue 3 + Leaflet, Laravel 12, PostgreSQL auf Hetzner-Webhosting L/XL.
- `scripts/map-import.sh`, `scripts/map-upload.sh`, `scripts/server-check.php`, Dokumentation, Skript-Tests.
