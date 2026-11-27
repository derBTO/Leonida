#!/usr/bin/env bash
# Importiert die GTA-VI-Kartenkacheln von gtadb.org als neue, versionierte Kartenversion.
#
#   scripts/map-import.sh [--data-dir DIR] [--set NAME]... [--ref REF] [--activate] [--force] [--upscale|--no-upscale]
#
# Ablauf: Sparse-Checkout von github.com/rolux/gtadb.org (nur GTA-VI-Kacheln, maps.js,
# Landmarken, Lizenz) → Kopie nach DIR/map/versions/<datum>-<commit>/ → Manifest und
# Vollständigkeitsprüfung → optional Umschalten von DIR/map/current.
# Unveränderte Kacheln werden per Hardlink aus der aktiven Version übernommen (spart Platz).
# Gibt es schon eine KI-Version (scripts/map-upscale.sh), wird die neue Version danach
# automatisch hochgerechnet (nur geänderte Kacheln) und mit --activate als KI-Version aktiviert.
# --upscale erzwingt das, --no-upscale lässt es aus. Details: docs/KARTE-AKTUALISIEREN.md

set -euo pipefail

REPO_URL="https://github.com/rolux/gtadb.org.git"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="${LEONIDA_DATA_DIR:-$PROJECT_DIR/data}"
REF="main"
ACTIVATE=0
FORCE=0
UPSCALE=""   # leer = automatisch (wenn es schon eine KI-Version gibt)
SETS=()
DEFAULT_SETS=("day")
OVERLAYS=("aiwe,1" "martipk,5" "rickrick,3")

usage() { sed -n '2,14p' "$0" | sed 's/^# \{0,1\}//'; }

while [[ $# -gt 0 ]]; do
    case "$1" in
        --data-dir) DATA_DIR="$2"; shift 2 ;;
        --set) SETS+=("$2"); shift 2 ;;
        --ref) REF="$2"; shift 2 ;;
        --activate) ACTIVATE=1; shift ;;
        --force) FORCE=1; shift ;;
        --upscale) UPSCALE=1; shift ;;
        --no-upscale) UPSCALE=0; shift ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unbekannte Option: $1" >&2; usage >&2; exit 2 ;;
    esac
done
[[ ${#SETS[@]} -eq 0 ]] && SETS=("${DEFAULT_SETS[@]}")

for tool in git python3; do
    command -v "$tool" >/dev/null || { echo "FEHLER: '$tool' wird benötigt." >&2; exit 1; }
done

CACHE="$DATA_DIR/cache/gtadb.org"
MAP_DIR="$DATA_DIR/map"
mkdir -p "$DATA_DIR/cache" "$MAP_DIR/versions"

# Pfade, die aus dem großen Repository ausgecheckt werden
PATTERNS=("/maps/maps.js" "/LICENSE" "/LICENSE-DATA" "/map/data/6/landmarks.json")
NEED_OVERLAYS=0
for set in "${SETS[@]}"; do
    PATTERNS+=("/maps/tiles/6/$set/")
    if [[ "$set" != "day" ]]; then
        NEED_OVERLAYS=1
        PATTERNS+=("/maps/tiles/6/$set,$(IFS=,; echo "${OVERLAYS[*]}")/")
    fi
done
if [[ $NEED_OVERLAYS -eq 1 ]]; then
    for overlay in "${OVERLAYS[@]}"; do PATTERNS+=("/maps/tiles/6/$overlay/"); done
fi

echo "==> gtadb.org abrufen ($REF)"
if [[ ! -d "$CACHE/.git" ]]; then
    git clone --quiet --filter=blob:none --no-checkout --depth 1 --branch "$REF" "$REPO_URL" "$CACHE"
else
    git -C "$CACHE" fetch --quiet --filter=blob:none --depth 1 origin "$REF"
fi
git -C "$CACHE" sparse-checkout init --no-cone >/dev/null
printf '%s\n' "${PATTERNS[@]}" > "$CACHE/.git/info/sparse-checkout"
if git -C "$CACHE" rev-parse --verify --quiet FETCH_HEAD >/dev/null; then
    TARGET_REF="FETCH_HEAD"
else
    TARGET_REF="HEAD"
fi
echo "==> Kacheln auschecken (beim ersten Mal mehrere hundert MB, das dauert)"
git -C "$CACHE" -c advice.detachedHead=false checkout --quiet --detach "$TARGET_REF"
git -C "$CACHE" sparse-checkout reapply

COMMIT="$(git -C "$CACHE" rev-parse HEAD)"
COMMIT_DATE="$(git -C "$CACHE" log -1 --format=%cI HEAD)"
VERSION="$(git -C "$CACHE" log -1 --format=%cd --date=format:%Y%m%d HEAD)-${COMMIT:0:7}"
TARGET="$MAP_DIR/versions/$VERSION"
CURRENT="$MAP_DIR/current"

# aktive Version ohne KI-Zusatz vergleichen (…-ki ist dieselbe gtadb-Version)
ACTIVE_BASE=""
if [[ -L "$CURRENT" ]]; then ACTIVE_BASE="$(basename "$(readlink "$CURRENT")")"; ACTIVE_BASE="${ACTIVE_BASE%-ki-test}"; ACTIVE_BASE="${ACTIVE_BASE%-ki}"; fi
if [[ "$ACTIVE_BASE" == "$VERSION" && $FORCE -eq 0 ]]; then
    echo "Kartenversion $VERSION ist bereits aktiv. Nichts zu tun (mit --force neu importieren)."
    exit 0
fi

echo "==> Version $VERSION nach $TARGET kopieren"
mkdir -p "$TARGET/tiles"
PREVIOUS_TILES=""
[[ -d "$CURRENT/tiles" ]] && PREVIOUS_TILES="$(cd "$CURRENT/tiles" && pwd -P)"
for dir in "$CACHE"/maps/tiles/6/*/; do
    name="$(basename "$dir")"
    python3 "$SCRIPT_DIR/lib/map_manifest.py" copy --source "${dir%/}" --target "$TARGET/tiles/$name" \
        --previous "${PREVIOUS_TILES:+$PREVIOUS_TILES/$name}"
done
cp "$CACHE/LICENSE-DATA" "$TARGET/LICENSE-DATA"
cp "$CACHE/map/data/6/landmarks.json" "$TARGET/landmarks.json"

echo "==> Manifest schreiben und prüfen"
MANIFEST_ARGS=(build --source "$CACHE" --version-dir "$TARGET" --version "$VERSION"
               --commit "$COMMIT" --commit-date "$COMMIT_DATE")
for set in "${SETS[@]}"; do MANIFEST_ARGS+=(--set "$set"); done
[[ -f "$CURRENT/landmarks.json" ]] && MANIFEST_ARGS+=(--previous-landmarks "$CURRENT/landmarks.json")
set +e
python3 "$SCRIPT_DIR/lib/map_manifest.py" "${MANIFEST_ARGS[@]}"
STATUS=$?
set -e
echo "    Größe: $(du -sh "$TARGET" | cut -f1)  ($(find "$TARGET/tiles" -type f | wc -l | tr -d ' ') Dateien)"

if [[ $STATUS -ne 0 && $FORCE -eq 0 ]]; then
    echo "Prüfung mit Problemen beendet. Version bleibt inaktiv: $TARGET" >&2
    echo "Nach Kontrolle mit --force --activate trotzdem übernehmen." >&2
    exit 1
fi

# KI-Hochrechnung: automatisch, wenn schon eine KI-Version existiert (sonst nur mit --upscale)
if [[ -z "$UPSCALE" ]]; then
    UPSCALE=0
    ls -1d "$MAP_DIR"/versions/*-ki >/dev/null 2>&1 && UPSCALE=1
    [[ $UPSCALE -eq 1 ]] && echo "==> Es gibt bereits eine KI-Version: rechne die neue Version ebenfalls hoch (nur geänderte Kacheln, --no-upscale zum Auslassen)"
fi
LIVE="$VERSION"
if [[ $UPSCALE -eq 1 ]]; then
    "$SCRIPT_DIR/map-upscale.sh" --data-dir "$DATA_DIR" --version "$VERSION"
    LIVE="$VERSION-ki"
fi

if [[ $ACTIVATE -eq 1 ]]; then
    ln -sfn "versions/$LIVE" "$CURRENT"
    echo "==> Aktiv: $LIVE"
    echo "    Auf den Server bringen: scripts/map-upload.sh"
else
    echo "==> Fertig. Aktivieren mit: ln -sfn versions/$LIVE $CURRENT"
fi
