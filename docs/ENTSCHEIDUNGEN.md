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
| 25.11.2026 | Orte (POIs) | eigene Pin-Art mit genau einer Kategorie, ohne Loot, Codes und Status; feste Kategorienliste im Code (Enum + Farben/Symbole im Frontend) | Wunsch BTO; serverseitig prüfbar, neue Kategorie = eine Zeile |
| 25.11.2026 | POI-Symbole | Lucide-Symbole statt Spiel-Icons | Spiel-Icons sind geschützt; einheitlich mit dem Rest |
| 25.11.2026 | POI-Import | einmaliger Import der markierten gtadb.org-Landmarken (CC BY 4.0) mit fester UUID je Landmarke | schneller Grundbestand, wiederholbar ohne Dubletten |
| 25.11.2026 | Karte | Nur noch die Satellitenkarte `day`, Community-Karten und Ebenen-Auswahl entfernt | Wunsch BTO; Satellit reicht, ca. 90 MB statt 320 MB |
| 25.11.2026 | Legendäre Waffen | Feld `is_legendary` mit Ja/Nein/unbekannt, Anzeige als gelber Stern | Wunsch BTO; Stern statt Pistole, weil die Pistole schon Ammu-Nation kennzeichnet |
| 25.11.2026 | Ammu-Nation-Symbol | eigene Pistole im Lucide-Stil statt Zielscheibe | Wunsch BTO; Lucide hat kein Waffen-Symbol |
| 25.11.2026 | Datenquelle Katalog | NiaDB (meowdb.com/gta6) statt GTABase | NiaDB erlaubt die Weiterverwendung mit Quellenlink ausdrücklich, auch für KI-Werkzeuge; GTABase untersagt das Auslesen in seinen Nutzungsbedingungen |
| 25.11.2026 | Katalog-Import | CSV mit festen Spaltennamen, Abgleich über den Namen, vorhandene Werte nur mit „Überschreiben“ ersetzen | eigene Pflege hat Vorrang; wiederholbar; auch für eigene Tabellen aus Excel/Numbers nutzbar |
| 25.11.2026 | Legendär aus NiaDB | „ja“, wenn die Beschreibung unique, gold-icon oder „variant of“ nennt; Basismodelle („is its unique variant“) bleiben unbekannt | Ableitung nur aus dem Text; nie „nein“ ohne Beleg |
| 25.11.2026 | Spuren | eigene Tabelle `trails` mit geordneten `trail_steps` (Nummer + Reihenfolge, Pin optional); parallel = gleiche Nummer; keine Verzweigungen | Wunsch BTO; Verzweigungen bisher nicht nötig, Modell bleibt einfach |
| 25.11.2026 | Hinweis-Pins | dritte Pin-Art `hint` mit Name, Beschreibung, Bildern und Codes | Zwischenstationen sollen die Karte nicht mit Loot vermischen und ausblendbar sein |
| 25.11.2026 | Fortschritt | pro Person und Schritt (`trail_step_progress`), nicht im Export | persönlich; Schritte behalten beim Bearbeiten über ihre ID den Fortschritt |
| 25.11.2026 | „Nur meine“ | Filter über `created_by`, im Browser (Pins, Katalog, Spuren) und per `author` in der API | auch für spätere weitere Bearbeiter |
| 25.11.2026 | Rechte (ersetzt „Editoren löschen alles“) | Bearbeiter ändern/löschen nur Selbstangelegtes; Katalog und Auswahllisten nur Admin; Einträge ohne Ersteller nur Admin | Wunsch BTO: niemand außer BTO darf fremde Einträge anfassen |
| 25.11.2026 | Pins aus der Spur setzen | Entwurf der Spur im Browser halten, Karte mit Fadenkreuz, danach zurück in den Editor | kein Kartenwechsel im Dialog nötig, gleiche Bedienung wie „Neuer Pin“ |
| 25.11.2026 | gtadb-Orte ausblendbar | Feld `pins.source`, Filter standardmäßig aus | Wunsch BTO; eigene Einträge stehen im Vordergrund |
| 25.11.2026 | Ersteller-Filter | Auswahl Alle/Meine/Benutzer statt Häkchen; Benutzernamen nur für Angemeldete | mehrere Bearbeiter möglich, Namen nicht öffentlich |
| 25.11.2026 | Backup ohne pg_dump | PHP-Sicherung als JSON-Zeilen (gzip) mit eigenem Restore, pg_dump nur wenn die Version passt | Hetzner: pg_dump 15 gegen Server 17; kein eigenes Binary installierbar |
| 26.11.2026 | Easteregg | Kennzeichen `is_easteregg` an jedem Pin statt eigener Art; Symbol hat Vorrang vor der Art | Eastereggs können Fahrzeug, Ort oder Hinweis sein und behalten deren Felder |
| 26.11.2026 | Pin-Details | zentrierter Dialog statt Seitenleiste, ein durchgehend scrollbarer Inhalt | Wunsch BTO: gleiche Größe wie der Bearbeiten-Dialog, alles auf einer Seite |
| 26.11.2026 | „Spuren“ → „Puzzle“ | nur Oberfläche und Adressen umbenannt, API und Tabellen heißen weiter `trails` | Wunsch BTO; kein Umbau der Datenbank nötig, alte Links leiten weiter |
| 26.11.2026 | Puzzle-Belohnung | am Puzzle als Ganzes (Waffen, Fahrzeuge) | Wunsch BTO |
| 26.11.2026 | Puzzle-Schritte nur Hinweise | Schritte mit anderen Pins verlieren die Verknüpfung, der Schritt bleibt | Wunsch BTO; Ort und Belohnung kommen jetzt über Hinweis bzw. Puzzle |
| 26.11.2026 | Fundort über Hinweis | berechnet (Loot-Pins ∪ Orte von Hinweisen mit demselben Eintrag), nicht gespeichert | bleibt automatisch richtig, wenn Hinweise geändert werden |
| 26.11.2026 | Hinweis-Schritte | eigene Tabelle `pin_steps` (Text und/oder Bild) statt Galerie | Wunsch BTO: durchscrollbare Anleitung statt Galerie |
| 26.11.2026 | Testumgebung mit Server-Daten | neueste Sicherung in `data/server-backup`, Einspielen beim Docker-Start nur bei neuer Datei, ältere Sicherungen über ihren Migrationsstand | echter Datenstand zum Testen, prüft Restore und Migrationen nebenbei |
| 26.11.2026 | Startdatei im Webverzeichnis | löst `current` per `realpath` auf, statt den Symlink-Pfad zu verwenden | OPcache (`revalidate_path=0`) lieferte sonst nach Deploys Skripte der alten Release (fehlende Route `/api/media`) |
