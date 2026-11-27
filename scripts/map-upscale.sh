#!/usr/bin/env bash
# Rechnet die aktive Kartenversion per KI (Real-ESRGAN ×4) hoch: aus der feinsten Stufe (6)
# werden zwei schärfere Zusatzstufen (7 und 8). Läuft auf dem Mac (Apple-Grafik über PyTorch/MPS).
#
#   scripts/map-upscale.sh [--test] [--levels 1|2] [--area x0,y0,x1,y1] [--activate] [--data-dir DIR]
#
#   --test       nur ein Ausschnitt (Downtown Vice City), dauert wenige Minuten
#   --levels 1   nur Stufe 7 (Standard 2: Stufen 7 und 8)
#   --area       eigener Ausschnitt in Spielmetern
#   --activate   die neue Version danach aktiv schalten (data/map/current)
#
# Das Ergebnis ist eine neue Kartenversion <version>-ki unter data/map/versions/. Die bisherige
# Version bleibt unverändert; zurück mit: ln -sfn versions/<alte-version> data/map/current
# Abbrechen (Ctrl+C) und erneut starten setzt dort fort, wo es aufgehört hat.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
DATA_DIR="${LEONIDA_DATA_DIR:-$PROJECT_DIR/data}"
LEVELS=2
AREA=""
ACTIVATE=0
SUFFIX="-ki"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --data-dir) DATA_DIR="$2"; shift 2 ;;
        --levels) LEVELS="$2"; shift 2 ;;
        --area) AREA="$2"; SUFFIX="-ki-test"; shift 2 ;;
        --test) AREA="-1500,-1200,400,800"; SUFFIX="-ki-test"; shift ;;
        --activate) ACTIVATE=1; shift ;;
        -h|--help) sed -n '2,15p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unbekannte Option: $1" >&2; exit 2 ;;
    esac
done

MAP_DIR="$DATA_DIR/map"
CURRENT="$MAP_DIR/current"
[[ -L "$CURRENT" ]] || { echo "FEHLER: Keine aktive Kartenversion. Zuerst scripts/map-import.sh --activate." >&2; exit 1; }
ACTIVE="$(basename "$(readlink "$CURRENT")")"
# Quelle ist immer die gtadb-Version, auch wenn gerade eine KI-Version aktiv ist
BASE="${ACTIVE%-ki-test}"; BASE="${BASE%-ki}"
SOURCE="$MAP_DIR/versions/$BASE"
TARGET="$MAP_DIR/versions/$BASE$SUFFIX"
[[ -f "$SOURCE/manifest.json" ]] || { echo "FEHLER: $SOURCE/manifest.json fehlt." >&2; exit 1; }

# Frühere KI-Version (anderer gtadb-Stand) zum Wiederverwenden unveränderter Kacheln
PREVIOUS=""
if [[ -z "$AREA" ]]; then
    for dir in $(ls -1d "$MAP_DIR"/versions/*-ki 2>/dev/null | sort -r); do
        [[ "$dir" != "$TARGET" && -f "$dir/manifest.json" ]] && { PREVIOUS="$dir"; break; }
    done
fi

# Python-Umgebung (einmalig, ca. 1 GB) und Modell (64 MB)
VENV="$DATA_DIR/cache/upscale-venv"
if [[ ! -x "$VENV/bin/python" ]]; then
    # neuestes vorhandenes Python nehmen (aktuelles PyTorch braucht 3.10+, das System-Python ist oft 3.9)
    PY=""
    for cand in python3.13 python3.12 python3.11 python3.10 /opt/homebrew/bin/python3 python3; do
        command -v "$cand" >/dev/null 2>&1 || continue
        "$cand" -c 'import sys; sys.exit(0 if sys.version_info >= (3, 10) else 1)' && { PY="$cand"; break; }
        [[ -z "$PY" ]] && PY="$cand"
    done
    [[ -n "$PY" ]] || { echo "FEHLER: Kein python3 gefunden (z. B. brew install python@3.12)." >&2; exit 1; }
    echo "==> Richte Python-Umgebung ein mit $($PY --version) unter $VENV (einmalig, einige Minuten, ca. 1 GB) …"
    "$PY" -m venv "$VENV"
    "$VENV/bin/pip" install --quiet --upgrade pip
    "$VENV/bin/pip" install --quiet torch torchvision "spandrel>=0.4,<0.5" pillow numpy
fi
MODEL="$DATA_DIR/cache/models/RealESRGAN_x4plus.pth"
MODEL_SHA="4fa0d38905f75ac06eb49a7951b426670021be3018265fd191d2125df9d682f1"
if [[ ! -f "$MODEL" ]]; then
    echo "==> Lade KI-Modell Real-ESRGAN x4plus (64 MB) …"
    mkdir -p "$(dirname "$MODEL")"
    curl -fL --progress-bar -o "$MODEL.part" https://github.com/xinntao/Real-ESRGAN/releases/download/v0.1.0/RealESRGAN_x4plus.pth
    mv "$MODEL.part" "$MODEL"
fi
echo "$MODEL_SHA  $MODEL" | shasum -a 256 -c --quiet - || { echo "FEHLER: Prüfsumme des Modells stimmt nicht." >&2; rm -f "$MODEL"; exit 1; }

echo "==> Hochrechnen: $BASE → $(basename "$TARGET")${AREA:+ (nur Ausschnitt $AREA)}"
ARGS=(--source "$SOURCE" --target "$TARGET" --model "$MODEL" --levels "$LEVELS")
[[ -n "$AREA" ]] && ARGS+=("--area=$AREA")
[[ -n "$PREVIOUS" ]] && ARGS+=(--previous "$PREVIOUS")
# MPS-Fallback für einzelne Operationen, die Apple-Grafik noch nicht kann
PYTORCH_ENABLE_MPS_FALLBACK=1 "$VENV/bin/python" "$SCRIPT_DIR/lib/map_upscale.py" "${ARGS[@]}"

if [[ $ACTIVATE -eq 1 ]]; then
    ln -sfn "versions/$(basename "$TARGET")" "$CURRENT"
    echo "==> Aktiv: $(basename "$TARGET"). Zurück: ln -sfn versions/$BASE $CURRENT"
else
    echo "==> Ansehen: ln -sfn versions/$(basename "$TARGET") $CURRENT  (oder erneut mit --activate)"
    echo "    Zurück:   ln -sfn versions/$BASE $CURRENT"
fi
