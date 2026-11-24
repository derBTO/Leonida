# Installation auf dem Hetzner-Webhosting

Leonida läuft auf einem Hetzner-Webhosting **L oder XL** (SSH nötig). Gebaut wird auf dem Mac, auf dem Server läuft nur PHP mit PostgreSQL.

```
~/leonida/                      (auf dem Server, Pfad frei wählbar)
├─ releases/<datum>-<commit>/   hochgeladene Versionen
├─ current → releases/…         Document Root der Subdomain: current/public
└─ shared/                      bleibt bei Updates erhalten
   ├─ .env                      Zugangsdaten
   ├─ storage/                  Logs, Sessions, Cache
   ├─ uploads/                  Bilder
   ├─ map/                      Kartenkacheln je Version, map/current → aktive
   └─ backups/                  nächtliche Datenbank-Dumps
```

## 1. Voraussetzungen auf dem Mac

- Git, PHP 8.3+, Composer, Node.js 20+ (z. B. `brew install php composer node`)
- SSH-Schlüssel; den öffentlichen Schlüssel in konsoleH unter *Einstellungen → SSH-Schlüssel* hinterlegen
- `deploy.env` aus `deploy.env.example` anlegen und ausfüllen (Host, Benutzer, Port 222, absoluter Zielpfad)

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
   - `LEONIDA_UPLOAD_DIR`, `LEONIDA_MAP_DIR`, `LEONIDA_BACKUP_DIR`: absolute Pfade unter `shared/`
   ```
   chmod 600 ~/leonida/shared/.env
   ```

## 3. Karte hochladen

```
scripts/map-import.sh --activate
scripts/map-upload.sh
```

Das überträgt einmalig ca. 320 MB (58.000 Dateien) und dauert je nach Leitung einige Minuten.

## 4. Anwendung ausliefern

```
scripts/deploy.sh
```

Das Skript baut Frontend und Backend aus dem **letzten Commit**, lädt eine neue Release hoch, verlinkt `.env` und `storage`, führt die Datenbank-Migrationen aus, legt beim ersten Mal den Administrator **BTO** an, hängt `/media` und `/map` ein und schaltet `current` um.

Danach in konsoleH den **Document Root** der Subdomain auf `leonida/current/public` setzen (nur beim ersten Mal).

## 5. Erste Anmeldung

1. https://leonida.derbto.de öffnen, als **BTO** mit dem Startpasswort anmelden.
2. Leonida verlangt sofort ein neues Passwort. Vorher sind keine Änderungen möglich.
3. `LEONIDA_ADMIN_INITIAL_PASSWORD` in `shared/.env` wieder leeren.
4. Weitere Benutzer unter *Verwaltung → Benutzer* anlegen.

## 6. Cronjob für Backups

In konsoleH einen Cronjob anlegen, jede Minute:

```
cd /usr/home/benutzer/leonida/current && php artisan schedule:run >> /dev/null 2>&1
```

Er startet um 03:30 Uhr `leonida:backup` (Datenbank-Dump nach `shared/backups/`, 14 Stück werden aufbewahrt). Falls minütliche Cronjobs nicht erlaubt sind: einmal täglich `php artisan leonida:backup` aufrufen.

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
| Karte leer | `shared/map/current/manifest.json` vorhanden? `php artisan leonida:link` erneut ausführen |
| Bilder fehlen | Folgt Apache Symlinks? `public/media` muss auf `shared/uploads` zeigen |
| Upload „zu groß“ | PHP-Einstellungen der Subdomain (Schritt 2.4) |
| Anmeldung gesperrt | Nach 5 Fehlversuchen pro Minute bzw. 30 pro Stunde wird kurz gesperrt; Wartezeit steht in der Meldung |
