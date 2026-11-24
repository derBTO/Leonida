#!/usr/bin/env bash
# Lädt die aktive lokale Kartenversion auf das Webhosting und schaltet sie dort um.
#
#   scripts/map-upload.sh [--data-dir DIR] [--no-activate] [--dry-run]
#
# Liest die Zugangsdaten aus deploy.env (Vorlage: deploy.env.example).
# Unveränderte Kacheln werden auf dem Server per Hardlink aus der dort aktiven Version
# übernommen, es werden also nur geänderte Dateien übertragen.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="${LEONIDA_DATA_DIR:-$PROJECT_DIR/data}"
ACTIVATE=1
DRY_RUN=""

while [[ $# -gt 0 ]]; do
    case "$1" in
        --data-dir) DATA_DIR="$2"; shift 2 ;;
        --no-activate) ACTIVATE=0; shift ;;
        --dry-run) DRY_RUN="--dry-run"; shift ;;
        -h|--help) sed -n '2,9p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unbekannte Option: $1" >&2; exit 2 ;;
    esac
done

CONFIG="$PROJECT_DIR/deploy.env"
[[ -f "$CONFIG" ]] || { echo "FEHLER: $CONFIG fehlt (Vorlage: deploy.env.example)." >&2; exit 1; }
# shellcheck source=/dev/null
source "$CONFIG"
: "${LEONIDA_SSH_HOST:?in deploy.env setzen}"
: "${LEONIDA_SSH_USER:?in deploy.env setzen}"
: "${LEONIDA_REMOTE_DIR:?in deploy.env setzen (absoluter Pfad)}"
LEONIDA_SSH_PORT="${LEONIDA_SSH_PORT:-222}"
[[ "$LEONIDA_REMOTE_DIR" == /* ]] || { echo "FEHLER: LEONIDA_REMOTE_DIR muss ein absoluter Pfad sein." >&2; exit 1; }

CURRENT="$DATA_DIR/map/current"
[[ -L "$CURRENT" ]] || { echo "FEHLER: Keine aktive Kartenversion. Zuerst scripts/map-import.sh --activate." >&2; exit 1; }
VERSION="$(basename "$(readlink "$CURRENT")")"
SOURCE="$(cd "$CURRENT" && pwd -P)"
REMOTE_MAP="${LEONIDA_PUBLIC_DIR:-$LEONIDA_REMOTE_DIR/shared}/map"
SSH=(ssh -p "$LEONIDA_SSH_PORT" "$LEONIDA_SSH_USER@$LEONIDA_SSH_HOST")

echo "==> Kartenversion $VERSION hochladen nach $LEONIDA_SSH_HOST:$REMOTE_MAP/versions/$VERSION"
"${SSH[@]}" "mkdir -p '$REMOTE_MAP/versions/$VERSION'"
REMOTE_CURRENT="$("${SSH[@]}" "cd '$REMOTE_MAP' 2>/dev/null && [ -L current ] && cd current && pwd -P || true")"
LINK_DEST=""
if [[ -n "$REMOTE_CURRENT" && "$(basename "$REMOTE_CURRENT")" != "$VERSION" ]]; then
    LINK_DEST="--link-dest=$REMOTE_CURRENT/"
    echo "    unveränderte Kacheln werden aus $(basename "$REMOTE_CURRENT") übernommen"
fi
rsync -a --delete $DRY_RUN ${LINK_DEST:+"$LINK_DEST"} \
    -e "ssh -p $LEONIDA_SSH_PORT" "$SOURCE/" \
    "$LEONIDA_SSH_USER@$LEONIDA_SSH_HOST:$REMOTE_MAP/versions/$VERSION/"

if [[ -n "$DRY_RUN" ]]; then
    echo "==> Probelauf beendet, nichts umgeschaltet."
elif [[ $ACTIVATE -eq 1 ]]; then
    "${SSH[@]}" "ln -sfn 'versions/$VERSION' '$REMOTE_MAP/current'"
    echo "==> Auf dem Server aktiv: $VERSION"
    echo "    Alte Versionen liegen weiter unter $REMOTE_MAP/versions/ und können gelöscht werden."
else
    echo "==> Hochgeladen, aber nicht aktiviert."
fi
