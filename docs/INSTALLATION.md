# Installation auf dem Hetzner-Webhosting

Leonida läuft auf einem Hetzner-Webhosting **L oder XL** (SSH nötig). Gebaut wird auf dem Mac, auf dem Server läuft nur PHP mit PostgreSQL.

Der Webserver von Hetzner darf das Home-Verzeichnis (`/usr/home/<benutzer>`) nicht betreten. Deshalb liegen nur die öffentlichen Dateien im Webverzeichnis, Code und Zugangsdaten im Home:

```
/usr/home/derbto/leonida/              nicht vom Web erreichbar
├─ releases/<datum>-<commit>/          hochgeladene Versionen
├─ current → releases/…                aktive Version
└─ shared/                             bleibt bei Updates erhalten (700)
   ├─ .env                             Zugangsdaten (600)
   ├─ storage/                         Logs, Sessions, Cache
   └─ backups/                         nächtliche Datenbank-Dumps

/usr/www/users/derbto/derbto.de/leonida/   Document Root der Subdomain (= public_html/derbto.de/leonida)
├─ index.php                           Startdatei, lädt die Anwendung aus …/leonida/current
├─ .htaccess, spa/, favicon.svg …      bei jedem Deployment aus der Release kopiert
├─ media/                              hochgeladene Bilder
└─ map/                                Kartenkacheln je Version, map/current → aktive
```

## 1. Voraussetzungen auf dem Mac

- Git, PHP 8.3+, Composer, Node.js 20+ (z. B. `brew install php composer node`)
- SSH-Schlüssel; den öffentlichen Schlüssel in konsoleH unter *Einstellungen → SSH-Schlüssel* hinterlegen
- `deploy.env` aus `deploy.env.example` anlegen und ausfüllen (Host bzw. Eintrag aus `~/.ssh/config`, Benutzer, Port 222, `LEONIDA_REMOTE_DIR` im Home, `LEONIDA_PUBLIC_DIR` = Document Root)

Absoluten Pfad herausfinden: `ssh -p 222 benutzer@wwwXXX.your-server.de pwd` und `/leonida` anhängen.

## 2. Server vorbereiten (einmalig)

1. **Subdomain** `leonida.derbto.de` in konsoleH anlegen, SSL-Zertifikat aktivieren.
2. **Datenbank**: PostgreSQL-Datenbank mit Benutzer anlegen. Host, Name, Benutzer und Passwort notieren.
3. **Server prüfen**: `scripts/server-check.php` nach `~/leonida/` kopieren und per SSH ausführen:
   ```
   scp -P 222 scripts/server-check.php benutzer@wwwXXX.your-server.de:leonida/
   ssh -p 222 benutzer@wwwXXX.your-server.de
   cd leonida && LEONIDA_DB_PASSWORD='…' php server-check.php --db-host=… --db-name=… --db-user=…
   ```
   Die PHP-Version im Browser kann von der auf der Kommandozeile abweichen. Im Zweifel das Skript zusätzlich in den Document Root legen und per Browser aufrufen (Anleitung im Skriptkopf), danach löschen.
   Steht `php` auf dem Server für eine ältere Version, in `deploy.env` `LEONIDA_REMOTE_PHP` auf das passende Binary setzen.
4. **PHP-Einstellungen** der Subdomain in konsoleH: PHP 8.3 oder neuer, `upload_max_filesize` ≥ 20M, `post_max_size` ≥ 25M, `memory_limit` ≥ 256M.
5. **`.env` anlegen**: `backend/.env.example` als `~/leonida/shared/.env` hochladen und ausfüllen:
   - `APP_KEY`: lokal `cd backend && php artisan key:generate --show` und den Wert eintragen
   - `DB_*`: Datenbankzugang
   - `LEONIDA_ADMIN_INITIAL_PASSWORD`: Startpasswort für **BTO** (mind. 10 Zeichen)
   - `LEONIDA_PUBLIC_DIR`: Document Root (`/usr/www/users/derbto/derbto.de/leonida`)
   - `LEONIDA_UPLOAD_DIR`, `LEONIDA_MAP_DIR`: `<Document Root>/media` und `<Document Root>/map`
   - `LEONIDA_BACKUP_DIR`: `/usr/home/derbto/leonida/shared/backups`
   ```
   chmod 600 ~/leonida/shared/.env
   ```

## 3. Karte hochladen

```
scripts/map-import.sh --activate
scripts/map-upload.sh
```

Das überträgt einmalig ca. 90 MB (31.000 Dateien) und dauert je nach Leitung einige Minuten.

## 4. Anwendung ausliefern

```
scripts/deploy.sh
```

Das Skript baut Frontend und Backend aus dem **letzten Commit** (fehlen PHP/Composer/Node lokal, über Docker), lädt eine neue Release ins Home hoch, verlinkt `.env` und `storage`, führt die Datenbank-Migrationen aus, legt beim ersten Mal den Administrator **BTO** an, schaltet `current` um und kopiert die öffentlichen Dateien samt `index.php` in den Document Root. `media/` und `map/` im Document Root bleiben dabei unangetastet.

Der Document Root der Subdomain ist der von Hetzner angelegte Ordner `public_html/derbto.de/leonida`. Er muss ein echter Ordner sein, kein Symlink.

## 5. Erste Anmeldung

1. https://leonida.derbto.de öffnen, als **BTO** mit dem Startpasswort anmelden.
2. Leonida verlangt sofort ein neues Passwort. Vorher sind keine Änderungen möglich.
3. `LEONIDA_ADMIN_INITIAL_PASSWORD` in `shared/.env` wieder leeren.
4. Weitere Benutzer unter *Verwaltung → Benutzer* anlegen.

## 6. Orte aus gtadb.org übernehmen (optional, einmalig)

```
ssh hetzner 'cd leonida/current && php artisan leonida:import-gtadb-pois'           # Vorschau
ssh hetzner 'cd leonida/current && php artisan leonida:import-gtadb-pois --apply'   # anlegen
```

Erneute Läufe (z. B. nach einem Karten-Update) legen nur neu hinzugekommene Orte an.

## 7. Cronjob für Backups

Einmal ein kleines Skript im Home anlegen (bleibt bei jedem Deploy gleich):

```bash
ssh hetzner 'printf "%s\n" "#!/bin/sh" "cd /usr/home/derbto/leonida/current && /usr/bin/php artisan leonida:backup >> /usr/home/derbto/leonida/shared/storage/logs/backup.log 2>&1" > ~/leonida/cron-backup.sh && chmod 705 ~/leonida/cron-backup.sh && ~/leonida/cron-backup.sh && tail -1 ~/leonida/shared/storage/logs/backup.log'
```

Dann in konsoleH unter *Cronjobs → Hinzufügen* anlegen: Interpreter „Anderer Interpreter/Direktaufruf“, Skript `/usr/home/derbto/leonida/cron-backup.sh`, manuell Minute 30, Stunde 03:00, sonst „jeden“ (Zusammenfassung `30 03 * * *`). Hetzner verlangt mindestens die Rechte 705 auf dem Skript. Es sichert die Datenbank nach `shared/backups/` (14 Stück werden aufbewahrt) und schreibt Erfolg oder Fehler in `shared/storage/logs/backup.log`.

Hetzner hat nur `pg_dump` 15, die Datenbank ist Version 17; deshalb sichert Leonida dort automatisch per PHP (`*.json.gz`). Prüfen am nächsten Tag: `ssh hetzner 'ls -lh ~/leonida/shared/backups; tail -3 ~/leonida/shared/storage/logs/backup.log'`.

Alternative bei minütlichen Cronjobs: `cd /usr/home/derbto/leonida/current && /usr/bin/php artisan schedule:run` jede Minute; der Laravel-Scheduler startet das Backup dann um 03:30 Uhr.

## 8. Installation prüfen

```bash
ssh hetzner 'cd leonida/current && php artisan leonida:check'
```

Prüft Konfiguration (`APP_DEBUG` aus, `APP_KEY`), Datenbank und Migrationen, Administrator, Schreibrechte, Kartenversion und das Alter der letzten Sicherung. `deploy.sh` ruft den Befehl nach jedem Deploy auf; „Problem“-Zeilen sollten behoben werden, „Warnung“ ist ein Hinweis.

## Aktualisieren

| Was | Befehl |
|---|---|
| Anwendung | Änderungen committen, dann `scripts/deploy.sh` |
| Zurück zur vorigen Version | `scripts/deploy.sh --rollback` (Datenbank bleibt, siehe unten) |
| Karte | siehe [KARTE-AKTUALISIEREN.md](KARTE-AKTUALISIEREN.md) |

Migrationen werden bei jedem Deployment automatisch ausgeführt. Vor größeren Updates ein Backup ziehen (`docs/BACKUP.md`). Ein Rollback setzt nur den Code zurück; enthielt die neuere Version eine Migration, muss die Datenbank aus dem Backup zurückgespielt werden.

## Fehlersuche

| Problem | Prüfen |
|---|---|
| Weiße Seite / Fehler 500 | `shared/storage/logs/laravel-<datum>.log` |
| „Frontend noch nicht gebaut“ | Deployment lief nicht vollständig, erneut `scripts/deploy.sh` |
| 403 Forbidden | Document Root ist ein Symlink oder zeigt ins Home – der Webserver darf `/usr/home` nicht betreten |
| Karte leer | `<Document Root>/map/current/manifest.json` vorhanden? |
| Bilder fehlen | `<Document Root>/media` vorhanden und `LEONIDA_UPLOAD_DIR` zeigt dorthin? |
| Upload „zu groß“ | PHP-Einstellungen der Subdomain (Schritt 2.4) |
| Anmeldung gesperrt | Nach 5 Fehlversuchen pro Minute bzw. 30 pro Stunde wird kurz gesperrt; Wartezeit steht in der Meldung |
