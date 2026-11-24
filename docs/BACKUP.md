# Backup und Wiederherstellung

Alle eigenen Daten liegen außerhalb des Codes: die Datenbank (PostgreSQL) und die Bilder in `shared/uploads`. Kartenkacheln lassen sich jederzeit neu importieren und müssen nicht gesichert werden.

## Was es gibt

| Sicherung | Inhalt | Wie |
|---|---|---|
| Hetzner | gesamtes Webhosting inkl. Datenbank | automatisch jede Nacht, 14 Tage, Wiederherstellung über konsoleH |
| `leonida:backup` | Datenbank-Dump (`pg_dump`, Custom-Format) | Cronjob 03:30 Uhr nach `shared/backups/`, 14 Stück |
| Bilder spiegeln | `shared/uploads` | `rsync` auf den Mac, siehe unten |
| Export in der Oberfläche | Pins, Waffen, Fahrzeuge, Auswahllisten, Bilder als ZIP | *Verwaltung → Export & Import* |

Benutzerkonten sind nur im Datenbank-Dump enthalten, nicht im ZIP-Export.

## Datenbank sichern

Auf dem Server:

```
cd ~/leonida/current && php artisan leonida:backup
```

Auf den Mac holen:

```
rsync -av -e "ssh -p 222" benutzer@wwwXXX.your-server.de:leonida/shared/backups/ ~/LeonidaBackup/db/
```

## Bilder sichern

```
rsync -av -e "ssh -p 222" benutzer@wwwXXX.your-server.de:leonida/shared/uploads/ ~/LeonidaBackup/uploads/
```

Dateinamen enthalten eine zufällige ID und ändern sich nie. Weitere Läufe übertragen daher nur neue Bilder.

## Wiederherstellen

**Datenbank** aus einem Dump (überschreibt den aktuellen Stand):

```
cd ~/leonida/current
php artisan down
pg_restore --clean --if-exists --no-owner -h HOST -U BENUTZER -d DATENBANK ../shared/backups/leonida-JJJJ-MM-TT-HHMMSS.dump
php artisan migrate --force
php artisan up
```

**Bilder**:

```
rsync -av -e "ssh -p 222" ~/LeonidaBackup/uploads/ benutzer@wwwXXX.your-server.de:leonida/shared/uploads/
```

**Aus dem ZIP-Export**: *Verwaltung → Export & Import*, Datei wählen, erst „Probelauf“, dann „Importieren“.

- *Zusammenführen* aktualisiert Einträge mit gleicher ID und legt neue an. Vorhandene, nicht im Export enthaltene Einträge bleiben.
- *Ersetzen* löscht vorher alle Pins, Waffen, Fahrzeuge und Auswahllisten.

## Komplett neu aufsetzen

1. Server wie in [INSTALLATION.md](INSTALLATION.md) vorbereiten, `shared/.env` wiederherstellen.
2. `scripts/map-import.sh --activate && scripts/map-upload.sh`
3. `scripts/deploy.sh`
4. Datenbank-Dump und Bilder wie oben zurückspielen, oder den ZIP-Export importieren.
