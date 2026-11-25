# Änderungsprotokoll

## v0.9.4 – Backups auf Hetzner (25.11.2026)

- **Fehler behoben:** Auf Hetzner entstand kein einziges Backup, weil dort nur `pg_dump` 15 installiert ist, die Datenbank aber Version 17 hat (`server version mismatch`).
- `leonida:backup` nimmt jetzt ein passendes `pg_dump` (auch unter `/usr/lib/postgresql/*/bin` oder per `LEONIDA_PG_DUMP`) und sichert sonst selbst per PHP als `*.json.gz`: alle Tabellen in einem konsistenten Stand, ohne Cache und Sitzungen.
- Neu: `leonida:restore <datei> [--check]` stellt eine PHP-Sicherung in einer Transaktion wieder her und prüft vorher den Migrationsstand.
- Das Backup-Protokoll des Schedulers steht in `storage/logs/backup.log`; Aufräumen berücksichtigt beide Formate.
- Doku: Cron-Zeile mit dem echten Pfad (`/usr/home/derbto/...`, `/usr/bin/php`), Rücksicherung, rsync über den SSH-Alias `hetzner`.

## v0.9.3 – gtadb-Orte unabhängig vom Ersteller (25.11.2026)

- Der Schalter „Orte von gtadb.org zeigen“ wirkt jetzt unabhängig vom Ersteller-Filter: z. B. „Meine“ + gtadb zeigt die eigenen Pins und zusätzlich alle gtadb-Orte.

## v0.9.2 – Korrektur gtadb-Kennzeichnung (25.11.2026)

- Alle bis dahin vorhandenen Pins werden einmalig als gtadb-Orte gekennzeichnet. Die Erkennung über die UUID aus v0.9.1 hatte auf dem Server nicht gegriffen, sodass der Schalter „Orte von gtadb.org zeigen“ dort wirkungslos war.

## v0.9.1 – Filter gtadb-Orte und Ersteller (25.11.2026)

- Neuer Schalter **„Orte von gtadb.org zeigen“** in der Sidebar, standardmäßig aus. Übernommene Orte sind über das neue Feld `source = 'gtadb'` gekennzeichnet (vorhandene per Migration erkannt).
- **Ersteller-Filter** statt „Nur meine“: Alle, Meine oder ein bestimmter Benutzer, in der Sidebar sowie bei Waffen, Fahrzeugen und Spuren. Nur für Angemeldete sichtbar; `/api/meta` liefert die Benutzerliste (`authors`) nur angemeldet.
- Pin-Index mit Feld `o` (Herkunft); Export/Import übernimmt die Herkunft.

## v0.9 – Rechte für Bearbeiter, Pins direkt aus der Spur (25.11.2026)

- **Rechte**: Bearbeiter dürfen Pins und Spuren anlegen, aber nur ihre eigenen ändern, verschieben oder löschen (auch Bilder). Einträge anderer und solche ohne Ersteller (z. B. importierte Orte) sind für sie gesperrt. Den eigenen Fortschritt in Spuren hakt weiterhin jede Person ab.
- **Katalog nur für Administratoren**: Waffen, Fahrzeuge und Auswahllisten anlegen, ändern und löschen dürfen nur Administratoren. Bearbeiter wählen vorhandene Einträge aus.
- Die Oberfläche blendet nicht erlaubte Knöpfe aus; der Server prüft unabhängig davon (403 mit Hinweis).
- **Pins aus dem Spur-Editor setzen**: „Auf Karte setzen“ bei einem Schritt öffnet die Karte mit Fadenkreuz, danach den Pin-Dialog (Hinweis, Titel vom Schritt) und kehrt mit zugeordnetem Pin in den Editor zurück.
- Benutzermenü: **„Benutzer anlegen“** für Administratoren (springt direkt ins Formular). Rolle „Editor“ heißt jetzt „Bearbeiter“, mit Erklärung der Rechte.

## v0.8 – Spuren, Hinweise, „Nur meine“ (25.11.2026)

- **Spuren**: Stationen in fester Reihenfolge (A → B → C → Loot). Jeder Schritt hat eine kurze Beschreibung, optional Details und optional einen Pin; Schritte ohne Ort („Auf den Anruf warten“) sind erlaubt. Schritte können **parallel** sein (2a/2b, Reihenfolge egal).
- Neue Seite **Spuren** (Liste mit Fortschritt, Filter „Nur meine“/„Nur offene“) und Detailseite mit Bearbeiten, Löschen und **Spoiler-Schutz** (spätere Schritte erst nach dem Abhaken oder per „Aufdecken“).
- **Auf der Karte**: nummerierte Schritte, gestrichelte Verbindungslinie (verzweigt bei parallelen Schritten), übrige Marker abgedunkelt, Leiste mit Schritt zurück/weiter und Abhaken. Im Pin-Detail steht „Schritt 2 von 4 in …“.
- **Fortschritt pro Person**: jede angemeldete Person hakt Schritte für sich ab; zurücksetzbar.
- Neue Pin-Art **Hinweis** (türkise Raute mit Lupe) für Zwischenstationen: Name, Beschreibung, Bilder und Codes. In der Sidebar eigener Modus „Hinweise“ und Schalter „Hinweise zeigen“; Rechtsklick „Neuer Hinweis hier“.
- **„Nur meine Einträge“**: Filter in der Sidebar (Pins), bei Waffen, Fahrzeugen und Spuren. API: `author=<id>` bei `/api/pins/search`, `/api/weapons`, `/api/vehicles`, `/api/trails`; Pin-Index mit Feld `u`.
- Export/Import enthält Spuren (Fortschritt nicht, der gehört zu den Benutzerkonten).

## v0.7 – Katalog-Import und NiaDB-Daten (25.11.2026)

- **CSV-Import für Waffen und Fahrzeuge** unter Verwaltung → Export & Import (Probelauf, Vorlage zum Herunterladen) und als Befehl `php artisan leonida:catalog-import`. Zuordnung über den Namen, leere Zellen ändern nichts, vorhandene Werte bleiben ohne „Überschreiben“ erhalten. Neue Listenwerte werden angelegt. Fehlerhafte Zeilen werden mit Zeilennummer gemeldet und übersprungen.
- Neue Felder **Quelle** (`source_name`, `source_url`) bei Waffen und Fahrzeugen; Anzeige als „Daten von …“ mit Link auf der Karte.
- **Startbestand aus NiaDB** (meowdb.com/gta6, Weiterverwendung mit Quellenlink erlaubt): 153 Waffen (118 mit Werten, 81 als legendär erkannt) und 235 Fahrzeuge (122 mit Werten und Ratings) in `imports/niadb/`. Bilder wurden nicht übernommen.
- `deploy.sh` liefert den Ordner `imports/` mit aus.

## v0.6 – Legendäre Waffen (25.11.2026)

- Neues Waffenfeld **Legendär / Unique** (Ja, Nein oder unbekannt). Legendäre Waffen tragen einen gelben Stern hinter dem Namen: auf der Waffenkarte, in der Übersicht, im Pin und in der Waffenauswahl.
- Waffenübersicht mit Filter „Nur legendäre“; API: `?legendary=1`.

## v0.5 – Aufräumen Karte (25.11.2026)

- Community-Karten und Ebenen-Auswahl entfernt, Leonida zeigt nur noch die Satellitenkarte. `map-import.sh` holt standardmäßig nur noch `day` (ca. 90 MB).
- Ammu-Nation hat jetzt eine Pistole als Symbol.

## v0.4 – Orte (POIs) (25.11.2026)

- Neue Pin-Art **Ort (POI)** mit 21 Kategorien (POI Allgemein, Photo, Ammu-Nation, Bar, Tankstelle, Lebensmittel, Kleidung, Tattoo, Garage, Auto-Tuning, Frisör, Pay & Spray, Autowerkstatt, Flughafen, Hafen, Militär, Restaurant, Wohnung, Rennen, Bus- und Bahnhaltestelle). Orte haben nur Name, Beschreibung und Bilder.
- Marker als abgerundete Quadrate in der Kategoriefarbe, graue Kategorien kleiner; eigene Cluster getrennt von Loot.
- Sidebar: Umschalter Alles / Loot / Orte, aufklappbare Kategorie-Filter mit Anzahl. Loot-Filter wirken nur auf Loot, Kategorie-Filter nur auf Orte.
- Rechtsklick-Menü: „Neuer Loot-Pin hier“ und „Neuer Ort (POI) hier“; im Dialog lässt sich die Art umschalten.
- `php artisan leonida:import-gtadb-pois`: übernimmt markierte Orte aus den gtadb.org-Landmarken (aktuell 159), mit Quellenlink; erneute Läufe legen nur neue Orte an.
- Links in Beschreibungen sind klickbar.
- API: Felder `kind`, `poi_type`; Suche mit `kind` und `poi[]`; Export/Import enthält Orte.

## v0.3 – Live auf leonida.derbto.de (25.11.2026)

- Erstes Deployment auf dem Hetzner-Webhosting (PHP 8.4, PostgreSQL 17).
- Server-Layout angepasst: öffentliche Dateien im Document Root, Code und `.env` im Home (der Webserver darf das Home nicht betreten).
- `deploy.sh`: Build über Docker, wenn PHP/Composer/Node lokal fehlen; kein `view:cache`; Rollback kopiert auch die öffentlichen Dateien zurück.
- `map-upload.sh`: lädt in `<Document Root>/map`, nur rsync-Optionen, die auch macOS kennt.
- Doku für Installation und Backup auf den echten Aufbau aktualisiert.

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
