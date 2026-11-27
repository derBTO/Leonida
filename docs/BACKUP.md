# Backup und Wiederherstellung

Alle eigenen Daten liegen außerhalb des Codes: die Datenbank (PostgreSQL) und die Bilder in `<Document Root>/media`. Kartenkacheln lassen sich jederzeit neu importieren und müssen nicht gesichert werden.

## Was es gibt

| Sicherung | Inhalt | Wie |
|---|---|---|
| Hetzner | gesamtes Webhosting inkl. Datenbank | automatisch jede Nacht, 14 Tage, Wiederherstellung über konsoleH |
| `leonida:backup` | Datenbank: `pg_dump` (Custom-Format, `*.dump`), wenn es zur Serverversion passt, sonst PHP-Sicherung (`*.json.gz`) | Cronjob 03:30 Uhr nach `shared/backups/`, 14 Stück, Protokoll `storage/logs/backup.log` |
| Bilder spiegeln | `public_html/<domain>/leonida/media` | `rsync` auf den Mac, siehe unten |
| Export in der Oberfläche | Pins, Waffen, Fahrzeuge, Auswahllisten, Bilder als ZIP | *Verwaltung → Export & Import* |

Benutzerkonten sind nur im Datenbank-Dump enthalten, nicht im ZIP-Export.

## Datenbank sichern

Auf dem Server:

```
cd ~/leonida/current && php artisan leonida:backup
```

## Auf den Mac holen

Die Sicherungen liegen sonst nur auf demselben Server wie die Seite, und die Bilder sind darin nicht enthalten. Deshalb regelmäßig (z. B. nach größeren Änderungen) auf dem Mac ausführen:

```
scripts/backup-pull.sh
```

Das holt per `rsync` die Datenbank-Sicherungen nach `<Ziel>/db/` und alle Bilder nach `<Ziel>/uploads/`. Das Ziel steht in `deploy.env` als `LEONIDA_BACKUP_TARGET` (z. B. ein Ordner im Nextcloud-Verzeichnis unter `~/Library/CloudStorage/…`, damit es zusätzlich in der Cloud landet; ohne Eintrag `~/LeonidaBackup`). Übertragen wird nur Neues; auf dem Mac wird nie etwas gelöscht, dort bleiben also auch Sicherungen erhalten, die der Server nach 14 Tagen aufräumt. Jeder Lauf schreibt eine Zeile nach `<Ziel>/backup-pull.log`. Anderer Zielordner: `scripts/backup-pull.sh /Pfad/zum/Ordner`. Zusätzlich landen die neueste Sicherung in `data/server-backup/` und die Bilder in `data/uploads/` des Projekts; damit startet `docker compose up` mit dem Server-Stand (siehe `docs/ENTWICKLUNG.md`). Zugangsdaten kommen wie bei `deploy.sh` aus `deploy.env`.

**Hinweis Hetzner:** Dort gibt es nur `pg_dump` 15, die Datenbank läuft mit PostgreSQL 17. `pg_dump` verweigert dann die Sicherung; Leonida sichert deshalb automatisch per PHP als `leonida-JJJJ-MM-TT-HHMMSS.json.gz` (alle Tabellen außer Cache und Sitzungen, in einem konsistenten Stand). Ein passendes `pg_dump` lässt sich mit `LEONIDA_PG_DUMP=/pfad/zu/pg_dump` in `.env` vorgeben.

## Bilder

Die Bilder holt `scripts/backup-pull.sh` mit (siehe oben). Dateinamen enthalten eine zufällige ID und ändern sich nie. Weitere Läufe übertragen daher nur neue Bilder.

## Wiederherstellen

**Datenbank aus einer PHP-Sicherung** (`*.json.gz`, Standard auf Hetzner; überschreibt den aktuellen Stand). Die installierte Programmversion muss denselben Migrationsstand haben wie die Sicherung, sonst bricht der Befehl ohne Änderung ab:

```
cd ~/leonida/current
php artisan leonida:restore ~/leonida/shared/backups/leonida-JJJJ-MM-TT-HHMMSS.json.gz --check   # nur prüfen
php artisan down
php artisan leonida:restore ~/leonida/shared/backups/leonida-JJJJ-MM-TT-HHMMSS.json.gz
php artisan up
```

Alles läuft in einer Transaktion: Bei einem Fehler bleibt der alte Stand erhalten.

**Datenbank aus einem pg_dump-Archiv** (`*.dump`):

```
cd ~/leonida/current
php artisan down
pg_restore --clean --if-exists --no-owner -h HOST -U BENUTZER -d DATENBANK ../shared/backups/leonida-JJJJ-MM-TT-HHMMSS.dump
php artisan migrate --force
php artisan up
```

**Bilder**:

```
rsync -av ~/LeonidaBackup/uploads/ hetzner:public_html/<domain>/leonida/media/
```

**Aus dem ZIP-Export**: *Verwaltung → Export & Import*, Datei wählen, erst „Probelauf“, dann „Importieren“.

- *Zusammenführen* aktualisiert Einträge mit gleicher ID und legt neue an. Vorhandene, nicht im Export enthaltene Einträge bleiben.
- *Ersetzen* löscht vorher alle Pins, Waffen, Fahrzeuge und Auswahllisten.

## Komplett neu aufsetzen

1. Server wie in [INSTALLATION.md](INSTALLATION.md) vorbereiten, `shared/.env` wiederherstellen.
2. `scripts/map-import.sh --activate && scripts/map-upload.sh`
3. `scripts/deploy.sh`
4. Datenbank-Dump und Bilder wie oben zurückspielen, oder den ZIP-Export importieren.
