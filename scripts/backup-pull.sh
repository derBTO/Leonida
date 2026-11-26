#!/usr/bin/env bash
# Holt die Sicherungen vom Server auf diesen Rechner (bei Bedarf von Hand ausführen).
#
#   scripts/backup-pull.sh              nach LEONIDA_BACKUP_TARGET aus deploy.env (sonst ~/LeonidaBackup)
#   scripts/backup-pull.sh /Pfad/Ziel   in einen anderen Ordner
#
# Übertragen werden
#   db/       die Datenbank-Sicherungen aus shared/backups (auf dem Server werden nur 14 aufbewahrt,
#             hier bleiben alle erhalten)
#   uploads/  alle hochgeladenen Bilder aus dem Document Root (media/); die sind in der
#             Datenbank-Sicherung nicht enthalten
# rsync überträgt nur Neues und Geändertes; hier wird nie etwas gelöscht.
#
# Zusätzlich für die lokale Testumgebung (docker compose up) im Projekt (nicht in Git):
#   data/server-backup/  nur die neueste Datenbank-Sicherung (wird beim Start eingespielt)
#   data/uploads/        die Bilder
# Zugang wie bei deploy.sh aus deploy.env. Wiederherstellen: docs/BACKUP.md

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
case "${1:-}" in
    -h|--help) sed -n '2,13p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
esac
CONFIG="$PROJECT_DIR/deploy.env"
[[ -f "$CONFIG" ]] || { echo "FEHLER: $CONFIG fehlt (Vorlage: deploy.env.example)." >&2; exit 1; }
# shellcheck source=/dev/null
source "$CONFIG"
TARGET="${1:-${LEONIDA_BACKUP_TARGET:-$HOME/LeonidaBackup}}"
TARGET="${TARGET/#\~/$HOME}"
: "${LEONIDA_SSH_HOST:?}" "${LEONIDA_SSH_USER:?}" "${LEONIDA_REMOTE_DIR:?}" "${LEONIDA_PUBLIC_DIR:?in deploy.env setzen}"
PORT="${LEONIDA_SSH_PORT:-222}"
REMOTE="$LEONIDA_SSH_USER@$LEONIDA_SSH_HOST"
RSYNC=(rsync -a -e "ssh -p $PORT")

mkdir -p "$TARGET/db" "$TARGET/uploads"
LOG="$TARGET/backup-pull.log"

count() { find "$1" -type f ! -name '.*' | wc -l | tr -d ' '; }
before_db=$(count "$TARGET/db")
before_up=$(count "$TARGET/uploads")

echo "==> Datenbank-Sicherungen → $TARGET/db"
"${RSYNC[@]}" "$REMOTE:$LEONIDA_REMOTE_DIR/shared/backups/" "$TARGET/db/"

echo "==> Bilder → $TARGET/uploads"
"${RSYNC[@]}" "$REMOTE:$LEONIDA_PUBLIC_DIR/media/" "$TARGET/uploads/"

echo "==> Für die lokale Testumgebung → $PROJECT_DIR/data"
latest_file="$(ls -1 "$TARGET/db" | grep '^leonida-.*\.json\.gz$' | sort | tail -n 1 || true)"
mkdir -p "$PROJECT_DIR/data/server-backup" "$PROJECT_DIR/data/uploads"
if [[ -n "$latest_file" && ! -f "$PROJECT_DIR/data/server-backup/$latest_file" ]]; then
    find "$PROJECT_DIR/data/server-backup" -name 'leonida-*' -type f -delete
    cp "$TARGET/db/$latest_file" "$PROJECT_DIR/data/server-backup/"
fi
"${RSYNC[@]}" "$REMOTE:$LEONIDA_PUBLIC_DIR/media/" "$PROJECT_DIR/data/uploads/"

new_db=$(( $(count "$TARGET/db") - before_db ))
new_up=$(( $(count "$TARGET/uploads") - before_up ))
latest="$(ls -1 "$TARGET/db" 2>/dev/null | grep '^leonida-' | sort | tail -n 1)"
size="$(du -sh "$TARGET" | cut -f1)"
line="$(date '+%Y-%m-%d %H:%M:%S') OK: $new_db neue Sicherung(en), $new_up neue Bilddatei(en), neueste: ${latest:-keine}, gesamt $size"
echo "$line" >> "$LOG"
echo "$line"
