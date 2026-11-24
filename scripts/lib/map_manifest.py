#!/usr/bin/env python3
"""Erzeugt und prüft das Manifest einer importierten gtadb.org-Kartenversion.

Wird von scripts/map-import.sh aufgerufen, kann aber auch einzeln laufen:

    python3 scripts/lib/map_manifest.py build  --source <gtadb-checkout> --version-dir <dir> --set day ...
    python3 scripts/lib/map_manifest.py compare --old <landmarks.json> --new <landmarks.json>

Nur Standardbibliothek, damit es auf macOS und Linux ohne Installation läuft.
"""

from __future__ import annotations

import argparse
import json
import math
import os
import re
import shutil
import sys
from datetime import datetime, timezone
from pathlib import Path

# Kartenparameter, die Leonida voraussetzt (siehe docs/KARTE-AKTUALISIEREN.md).
EXPECTED = {"mapW": 32768, "mapH": 32768, "zeroX": 16384, "zeroY": 16384, "tileSize": 256}
BASE_MAP_SIZE = 1024  # Pixel der ganzen Welt auf Zoom 0
OVERLAY_SUFFIX_ORDER = ("aiwe,1", "martipk,5", "rickrick,3")


class ManifestError(Exception):
    pass


# --------------------------------------------------------------------------- maps.js lesen

def _extract_object(source: str, key: str) -> str:
    """Liefert das JS-Objekt-Literal hinter `key: {` (mit passenden Klammern)."""
    match = re.search(r"\b" + re.escape(key) + r"\s*:\s*\{", source)
    if not match:
        raise ManifestError(f"'{key}' nicht in maps.js gefunden – hat gtadb.org den Viewer umgebaut?")
    start = match.end() - 1
    depth = 0
    for index in range(start, len(source)):
        char = source[index]
        if char == "{":
            depth += 1
        elif char == "}":
            depth -= 1
            if depth == 0:
                return source[start:index + 1]
    raise ManifestError(f"Objekt '{key}' in maps.js ist nicht geschlossen")


def _js_to_json(literal: str) -> object:
    text = re.sub(r"//[^\n]*", "", literal)
    text = re.sub(r"([{,]\s*)(\d+)\s*:", r'\1"\2":', text)
    text = re.sub(r"([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)\s*:", r'\1"\2":', text)
    text = re.sub(r",(\s*[}\]])", r"\1", text)
    return json.loads(text)


def _ranges_from_js(value: dict) -> dict[int, list[list[int]]]:
    return {int(z): [[int(a), int(b)] for a, b in pair] for z, pair in value.items()}


def parse_maps_js(path: Path) -> dict:
    source = path.read_text(encoding="utf-8")
    constants = {}
    for name in EXPECTED:
        match = re.search(r"\b" + name + r"\s*:\s*(-?\d+)", source)
        if not match:
            raise ManifestError(f"Konstante '{name}' nicht in maps.js gefunden")
        constants[name] = int(match.group(1))
    changed = {k: (EXPECTED[k], v) for k, v in constants.items() if v != EXPECTED[k]}
    if changed:
        detail = ", ".join(f"{k}: erwartet {a}, gefunden {b}" for k, (a, b) in changed.items())
        raise ManifestError(
            "Das Koordinatensystem von gtadb.org hat sich geändert (" + detail + "). "
            "Bitte docs/KARTE-AKTUALISIEREN.md, Abschnitt 'Koordinatensystem geändert', befolgen."
        )
    if not re.search(r"1024\s*\*\s*Math\.pow\(2,\s*self\.z\)", source):
        raise ManifestError("Formel 'mapSize = 1024 * 2^z' nicht mehr in maps.js – Koordinatensystem prüfen")
    tile_sets = {k: _ranges_from_js(v) for k, v in _js_to_json(_extract_object(source, "tileSetRanges")).items()}
    overlays = {k: _ranges_from_js(v) for k, v in _js_to_json(_extract_object(source, "tileOverlayRanges")).items()}
    gta6 = re.search(r"gta6\s*:\s*\{[^}]*?tileSets\s*:\s*\[([^\]]*)\]", source, re.S)
    gta6_sets = re.findall(r'"([^"]+)"', gta6.group(1)) if gta6 else []
    return {"constants": constants, "tileSets": tile_sets, "overlays": overlays, "gta6TileSets": gta6_sets}


# --------------------------------------------------------------------------- Geometrie

def world_bounds(ranges: dict[int, list[list[int]]], tile_size: int = 256) -> dict:
    """Weltkoordinaten (Meter) der Kachelfläche auf der höchsten Zoomstufe."""
    zoom = max(ranges)
    (x0, y0), (x1, y1) = ranges[zoom]
    meters_per_px = (EXPECTED["mapW"] / BASE_MAP_SIZE) / (2 ** zoom)
    west = x0 * tile_size * meters_per_px - EXPECTED["zeroX"]
    east = (x1 + 1) * tile_size * meters_per_px - EXPECTED["zeroX"]
    north = EXPECTED["zeroY"] - y0 * tile_size * meters_per_px
    south = EXPECTED["zeroY"] - (y1 + 1) * tile_size * meters_per_px
    return {"west": west, "east": east, "south": south, "north": north}


def tile_path(z: int, x: int, y: int) -> str:
    return f"{z}/{z},{y},{x}.jpg"


# --------------------------------------------------------------------------- Prüfung

def check_tiles(tiles_root: Path, name: str, ranges: dict[int, list[list[int]]]) -> dict:
    missing: list[str] = []
    expected = 0
    for z, ((x0, y0), (x1, y1)) in sorted(ranges.items()):
        for y in range(y0, y1 + 1):
            for x in range(x0, x1 + 1):
                expected += 1
                if not (tiles_root / name / tile_path(z, x, y)).is_file():
                    missing.append(tile_path(z, x, y))
    present = 0
    size = 0
    for dirpath, _dirs, files in os.walk(tiles_root / name):
        for file in files:
            if file.endswith(".jpg"):
                present += 1
                size += os.path.getsize(os.path.join(dirpath, file))
    return {"expected": expected, "present": present, "missing": missing, "bytes": size}


def overlay_variant_name(base: str) -> str:
    return ",".join((base,) + OVERLAY_SUFFIX_ORDER)


# --------------------------------------------------------------------------- Landmarken-Vergleich

def load_landmarks(path: Path) -> dict[str, tuple[float, float]]:
    data = json.loads(path.read_text(encoding="utf-8"))
    points = {}
    for key, value in data.items():
        try:
            x, y = value[1]
            points[key] = (float(x), float(y))
        except (TypeError, ValueError, IndexError):
            continue
    return points


def _solve3(m: list[list[float]], v: list[float]) -> list[float]:
    """Löst ein 3x3-Gleichungssystem (Cramer)."""
    def det(a):
        return (a[0][0] * (a[1][1] * a[2][2] - a[1][2] * a[2][1])
                - a[0][1] * (a[1][0] * a[2][2] - a[1][2] * a[2][0])
                + a[0][2] * (a[1][0] * a[2][1] - a[1][1] * a[2][0]))
    d = det(m)
    if abs(d) < 1e-12:
        raise ManifestError("Zu wenige oder ungünstig verteilte Passpunkte für eine Transformation")
    result = []
    for col in range(3):
        mc = [row[:] for row in m]
        for r in range(3):
            mc[r][col] = v[r]
        result.append(det(mc) / d)
    return result


def fit_affine(pairs: list[tuple[tuple[float, float], tuple[float, float]]]) -> list[float]:
    """Kleinste-Quadrate-Fit: new = [a b c; d e f] · [x y 1]."""
    sxx = sxy = sx = syy = sy = n = 0.0
    bx = [0.0, 0.0, 0.0]
    by = [0.0, 0.0, 0.0]
    for (x, y), (nx, ny) in pairs:
        sxx += x * x; sxy += x * y; sx += x; syy += y * y; sy += y; n += 1
        bx[0] += x * nx; bx[1] += y * nx; bx[2] += nx
        by[0] += x * ny; by[1] += y * ny; by[2] += ny
    m = [[sxx, sxy, sx], [sxy, syy, sy], [sx, sy, n]]
    return _solve3(m, bx) + _solve3(m, by)


def apply_affine(t: list[float], x: float, y: float) -> tuple[float, float]:
    return t[0] * x + t[1] * y + t[2], t[3] * x + t[4] * y + t[5]


def compare_landmarks(old: dict, new: dict, outlier_m: float = 5.0) -> dict:
    """Robuster Vergleich zweier Landmarken-Stände.

    Einzelne Landmarken werden von der Community laufend verschoben. Eine Änderung des
    Koordinatensystems zeigt sich dagegen als Transformation, die fast alle Punkte betrifft.
    """
    common = sorted(set(old) & set(new))
    pairs = [(old[k], new[k]) for k in common]
    if len(pairs) < 10:
        return {"common": len(pairs), "verdict": "unbekannt", "reason": "zu wenige gemeinsame Landmarken"}
    unchanged = [p for p in pairs if math.dist(p[0], p[1]) < 0.01]
    moved = len(pairs) - len(unchanged)
    current = pairs
    transform = fit_affine(current)
    for _ in range(5):
        kept = [p for p in current if math.dist(apply_affine(transform, *p[0]), p[1]) <= outlier_m]
        if len(kept) < 10 or len(kept) == len(current):
            break
        current = kept
        transform = fit_affine(current)
    identity = [1.0, 0.0, 0.0, 0.0, 1.0, 0.0]
    probe = [(-12000.0, -10000.0), (5000.0, 12000.0), (0.0, 0.0), (-12000.0, 12000.0), (5000.0, -10000.0)]
    max_shift = max(math.dist(apply_affine(transform, *p), apply_affine(identity, *p)) for p in probe)
    verdict = "unverändert" if max_shift < 1.0 else "geändert"
    return {
        "common": len(pairs),
        "unchanged": len(unchanged),
        "moved": moved,
        "inliers": len(current),
        "transform": [round(v, 9) for v in transform],
        "maxShiftMeters": round(max_shift, 3),
        "verdict": verdict,
    }


# --------------------------------------------------------------------------- Kopieren

def _same_file(a: Path, b: Path) -> bool:
    if a.stat().st_size != b.stat().st_size:
        return False
    with a.open("rb") as fa, b.open("rb") as fb:
        while True:
            ca, cb = fa.read(65536), fb.read(65536)
            if ca != cb:
                return False
            if not ca:
                return True


def copy_tree(source: Path, target: Path, previous: Path | None) -> dict:
    """Kopiert source nach target. Dateien, die in previous identisch vorliegen, werden hart verlinkt."""
    stats = {"linked": 0, "copied": 0}
    for dirpath, _dirs, files in os.walk(source):
        rel = Path(dirpath).relative_to(source)
        (target / rel).mkdir(parents=True, exist_ok=True)
        for name in files:
            src = Path(dirpath) / name
            dst = target / rel / name
            if dst.exists():
                dst.unlink()
            old = previous / rel / name if previous else None
            if old is not None and old.is_file() and _same_file(src, old):
                try:
                    os.link(old, dst)
                    stats["linked"] += 1
                    continue
                except OSError:
                    pass
            shutil.copy2(src, dst)
            stats["copied"] += 1
    return stats


# --------------------------------------------------------------------------- Befehle

def cmd_copy(args: argparse.Namespace) -> int:
    previous = Path(args.previous) if args.previous and Path(args.previous).is_dir() else None
    stats = copy_tree(Path(args.source), Path(args.target), previous)
    print(f"    {Path(args.source).name}: {stats['copied']} kopiert, {stats['linked']} unverändert übernommen")
    return 0


def cmd_build(args: argparse.Namespace) -> int:
    source = Path(args.source)
    version_dir = Path(args.version_dir)
    info = parse_maps_js(source / "maps" / "maps.js")
    tiles_root = version_dir / "tiles"
    manifest_sets = []
    problems = []
    for name in args.sets:
        if name not in info["tileSets"]:
            raise ManifestError(f"Tile-Set '{name}' ist in maps.js nicht definiert")
        if info["gta6TileSets"] and name not in info["gta6TileSets"]:
            problems.append(f"Tile-Set '{name}' wird von gtadb.org nicht mehr für GTA VI angeboten")
        ranges = info["tileSets"][name]
        check = check_tiles(tiles_root, name, ranges)
        if check["missing"]:
            problems.append(f"{name}: {len(check['missing'])} von {check['expected']} Kacheln fehlen, z. B. {check['missing'][:3]}")
        entry = {
            "id": name,
            "minZoom": min(ranges),
            "maxZoom": max(ranges),
            "ranges": {str(z): r for z, r in sorted(ranges.items())},
            "bounds": world_bounds(ranges),
            "tiles": check["present"],
            "bytes": check["bytes"],
            "overlay": None,
        }
        variant = overlay_variant_name(name)
        if name != "day" and (tiles_root / variant).is_dir():
            entry["overlay"] = {"combined": variant, "parts": list(OVERLAY_SUFFIX_ORDER)}
            for part in OVERLAY_SUFFIX_ORDER:
                if not (tiles_root / part).is_dir():
                    problems.append(f"Overlay-Verzeichnis '{part}' fehlt")
        manifest_sets.append(entry)
    manifest = {
        "format": 1,
        "version": args.version,
        "source": {
            "repository": "https://github.com/rolux/gtadb.org",
            "commit": args.commit,
            "commitDate": args.commit_date,
        },
        "importedAt": datetime.now(timezone.utc).isoformat(timespec="seconds"),
        "crs": {
            "description": "px = (x + 16384) * 2^z / 32, py = (16384 - y) * 2^z / 32 (Spielmeter, y nach Norden)",
            "transformation": [1 / 32, 512, -1 / 32, 512],
            "tileSize": EXPECTED["tileSize"],
            "tileUrl": "{z}/{z},{y},{x}.jpg",
        },
        "tileSets": manifest_sets,
        "overlayRanges": {k: {str(z): r for z, r in sorted(v.items())} for k, v in info["overlays"].items()},
        "attribution": [
            "GTA VI Map Tiles (CC BY 4.0) — https://map.gtadb.org",
            "gtadb.org and all contributors",
        ],
        "license": "CC BY 4.0 — https://creativecommons.org/licenses/by/4.0/",
    }
    if args.previous_landmarks and Path(args.previous_landmarks).is_file() and (version_dir / "landmarks.json").is_file():
        manifest["landmarkComparison"] = compare_landmarks(
            load_landmarks(Path(args.previous_landmarks)), load_landmarks(version_dir / "landmarks.json")
        )
        if manifest["landmarkComparison"].get("verdict") == "geändert":
            problems.append(
                "Landmarken deuten auf ein geändertes Koordinatensystem hin "
                f"(max. Verschiebung {manifest['landmarkComparison']['maxShiftMeters']} m). "
                "Pins müssen mit scripts/migrate-coords umgerechnet werden."
            )
    manifest["problems"] = problems
    (version_dir / "manifest.json").write_text(json.dumps(manifest, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    for entry in manifest_sets:
        print(f"  {entry['id']:<12} Zoom {entry['minZoom']}-{entry['maxZoom']}  {entry['tiles']:>6} Kacheln  {entry['bytes'] / 1e6:7.1f} MB"
              + ("  + Overlays" if entry["overlay"] else ""))
    if "landmarkComparison" in manifest:
        c = manifest["landmarkComparison"]
        print(f"  Landmarken: {c.get('common')} gemeinsam, {c.get('moved', '?')} verschoben, Koordinatensystem {c['verdict']}")
    for problem in problems:
        print(f"  PROBLEM: {problem}", file=sys.stderr)
    return 1 if problems else 0


def cmd_compare(args: argparse.Namespace) -> int:
    result = compare_landmarks(load_landmarks(Path(args.old)), load_landmarks(Path(args.new)))
    print(json.dumps(result, indent=2, ensure_ascii=False))
    return 0 if result["verdict"] != "geändert" else 2


def main(argv: list[str] | None = None) -> int:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="command", required=True)
    build = sub.add_parser("build", help="Manifest für eine importierte Version schreiben und prüfen")
    build.add_argument("--source", required=True, help="Checkout von gtadb.org")
    build.add_argument("--version-dir", required=True)
    build.add_argument("--version", required=True)
    build.add_argument("--commit", required=True)
    build.add_argument("--commit-date", required=True)
    build.add_argument("--set", dest="sets", action="append", required=True)
    build.add_argument("--previous-landmarks")
    build.set_defaults(func=cmd_build)
    copy = sub.add_parser("copy", help="Kachelordner kopieren, unveränderte Dateien hart verlinken")
    copy.add_argument("--source", required=True)
    copy.add_argument("--target", required=True)
    copy.add_argument("--previous")
    copy.set_defaults(func=cmd_copy)
    compare = sub.add_parser("compare", help="Zwei landmarks.json vergleichen")
    compare.add_argument("--old", required=True)
    compare.add_argument("--new", required=True)
    compare.set_defaults(func=cmd_compare)
    args = parser.parse_args(argv)
    try:
        return args.func(args)
    except ManifestError as error:
        print(f"FEHLER: {error}", file=sys.stderr)
        return 3


if __name__ == "__main__":
    sys.exit(main())
