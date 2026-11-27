#!/usr/bin/env python3
"""Rechnet die feinste Kachelstufe eines Tile-Sets per KI (Real-ESRGAN ×4) hoch und erzeugt
daraus zwei zusätzliche Zoomstufen (z. B. 7 und 8 aus 6).

Aufruf über scripts/map-upscale.sh (richtet Python-Umgebung und Modell ein). Direkt:

    python3 map_upscale.py --source data/map/versions/A --target data/map/versions/A-ki \
        --model data/cache/models/RealESRGAN_x4plus.pth [--previous data/map/versions/X-ki] \
        [--levels 2] [--area x0,y0,x1,y1] [--device auto|mps|cuda|cpu]

- Die Zielversion bekommt alle vorhandenen Dateien der Quelle per Hardlink (kein Platzbedarf)
  plus die neuen Stufen und ein angepasstes manifest.json.
- Jede Kachel wird mit 16 px Rand aus den Nachbarkacheln hochgerechnet, damit an den
  Kachelgrenzen keine Nähte entstehen.
- Fast einfarbige Kacheln (offenes Meer) werden nur normal vergrößert, ohne KI.
- Abbrechen und erneut starten ist jederzeit möglich: fertige Kacheln werden übersprungen.
- Mit --previous werden Kacheln aus einer früheren KI-Version übernommen, wenn sich die
  Ausgangskachel und ihre Nachbarn nicht geändert haben (z. B. nach einem gtadb-Update).
"""
from __future__ import annotations

import argparse
import datetime as dt
import filecmp
import json
import os
import sys
import time
from pathlib import Path

import numpy as np
from PIL import Image, ImageStat

TILE = 256
MARGIN = 16
SCALE = 4


def tile_file(root: Path, z: int, x: int, y: int) -> Path:
    return root / str(z) / f"{z},{y},{x}.jpg"


def link_tree(source: Path, target: Path) -> int:
    """Alle Dateien der Quelle per Hardlink in die Zielversion übernehmen (manifest.json nicht)."""
    count = 0
    for dirpath, _dirs, files in os.walk(source):
        rel = Path(dirpath).relative_to(source)
        (target / rel).mkdir(parents=True, exist_ok=True)
        for name in files:
            if rel == Path(".") and name == "manifest.json":
                continue
            dst = target / rel / name
            if not dst.exists():
                os.link(Path(dirpath) / name, dst)
                count += 1
    return count


def is_flat(img: Image.Image) -> bool:
    return ImageStat.Stat(img.convert("L").resize((32, 32))).stddev[0] < 4


class Upscaler:
    def __init__(self, model_path: Path, device: str):
        import torch
        import spandrel

        self.torch = torch
        if device == "auto":
            if torch.backends.mps.is_available():
                device = "mps"
            elif torch.cuda.is_available():
                device = "cuda"
            else:
                device = "cpu"
        self.device = device
        model = spandrel.ModelLoader().load_from_file(str(model_path))
        if model.scale != SCALE:
            raise SystemExit(f"Modell skaliert ×{model.scale}, erwartet ×{SCALE}")
        self.model = model.eval().to(device)

    def run(self, img: Image.Image) -> Image.Image:
        torch = self.torch
        a = torch.from_numpy(np.asarray(img, dtype=np.float32) / 255.0).permute(2, 0, 1)[None].to(self.device)
        with torch.no_grad():
            out = self.model(a)
        arr = (out[0].permute(1, 2, 0).clamp(0, 1).cpu().numpy() * 255.0).round().astype(np.uint8)
        return Image.fromarray(arr)


def load_with_margin(src: Path, z: int, x: int, y: int, cache: dict) -> Image.Image:
    """Kachel plus MARGIN px aus den 8 Nachbarn; fehlende Nachbarn werden mit dem Kachelrand gefüllt."""
    def get(xx: int, yy: int) -> Image.Image | None:
        key = (xx, yy)
        if key not in cache:
            f = tile_file(src, z, xx, yy)
            cache[key] = Image.open(f).convert("RGB") if f.exists() else None
        return cache[key]

    center = get(x, y)
    size = TILE + 2 * MARGIN
    canvas = Image.new("RGB", (size, size))
    # Erst die Mitte gestreckt als Füllung für fehlende Nachbarn, dann die echten Nachbarn darüber
    canvas.paste(center.resize((size, size), Image.NEAREST), (0, 0))
    for dy in (-1, 0, 1):
        for dx in (-1, 0, 1):
            t = get(x + dx, y + dy)
            if t is None:
                continue
            canvas.paste(t, (MARGIN + dx * TILE, MARGIN + dy * TILE))
    return canvas


def neighbours_unchanged(src: Path, prev_src: Path, z: int, x: int, y: int) -> bool:
    for dy in (-1, 0, 1):
        for dx in (-1, 0, 1):
            a, b = tile_file(src, z, x + dx, y + dy), tile_file(prev_src, z, x + dx, y + dy)
            if a.exists() != b.exists():
                return False
            if a.exists() and not (os.path.samefile(a, b) or filecmp.cmp(a, b, shallow=False)):
                return False
    return True


def main(argv: list[str] | None = None) -> int:
    p = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--source", required=True, type=Path, help="Kartenversion (Ordner mit manifest.json)")
    p.add_argument("--target", required=True, type=Path, help="neue Kartenversion")
    p.add_argument("--model", required=True, type=Path)
    p.add_argument("--set", default="day")
    p.add_argument("--levels", type=int, default=2, choices=(1, 2), help="1 = nur +1 Stufe, 2 = +2 Stufen (Standard)")
    p.add_argument("--previous", type=Path, help="frühere KI-Version zum Wiederverwenden unveränderter Kacheln")
    p.add_argument("--area", help="nur Ausschnitt in Spielmetern x0,y0,x1,y1 (zum Testen)")
    p.add_argument("--device", default="auto")
    p.add_argument("--quality", type=int, default=85)
    args = p.parse_args(argv)

    manifest = json.loads((args.source / "manifest.json").read_text())
    tset = next((s for s in manifest["tileSets"] if s["id"] == args.set), None)
    if not tset:
        raise SystemExit(f"Tile-Set {args.set} fehlt im Manifest")
    if tset.get("upscaled"):
        raise SystemExit("Die Quellversion ist schon hochgerechnet. Bitte die gtadb-Version als Quelle nehmen.")
    z = int(tset["maxZoom"])
    (x0, y0), (x1, y1) = tset["ranges"][str(z)]
    if args.area:
        ax0, ay0, ax1, ay1 = (float(v) for v in args.area.split(","))
        px = lambda gx: int((gx + 16384) * 2**z / 32) // TILE  # noqa: E731
        py = lambda gy: int((16384 - gy) * 2**z / 32) // TILE  # noqa: E731
        x0, x1 = max(x0, px(min(ax0, ax1))), min(x1, px(max(ax0, ax1)))
        y0, y1 = max(y0, py(max(ay0, ay1))), min(y1, py(min(ay0, ay1)))

    args.target.mkdir(parents=True, exist_ok=True)
    linked = link_tree(args.source, args.target)
    print(f"==> Zielversion {args.target.name}: {linked} Dateien per Hardlink übernommen", flush=True)

    src = args.source / "tiles" / args.set
    dst = args.target / "tiles" / args.set
    prev_src = prev_dst = None
    if args.previous and (args.previous / "manifest.json").exists():
        prev_src = args.previous / "tiles" / args.set
        prev_dst = prev_src
        print(f"    unveränderte Kacheln werden aus {args.previous.name} übernommen", flush=True)
    levels = list(range(z + 1, z + 1 + args.levels))
    for lz in levels:
        (dst / str(lz)).mkdir(parents=True, exist_ok=True)

    up: Upscaler | None = None
    todo = [(x, y) for y in range(y0, y1 + 1) for x in range(x0, x1 + 1) if tile_file(src, z, x, y).exists()]
    total = len(todo)
    stats = {"ki": 0, "flach": 0, "übernommen": 0, "vorhanden": 0}
    started = time.time()
    last_print = 0.0
    cache: dict = {}
    current_row = None
    for i, (x, y) in enumerate(todo, 1):
        if y != current_row:  # Cache auf drei Zeilen begrenzen
            cache = {k: v for k, v in cache.items() if abs(k[1] - y) <= 1}
            current_row = y
        top = levels[-1]
        f = 2 ** (top - z)
        last = tile_file(dst, top, x * f + f - 1, y * f + f - 1)
        if last.exists() and all(tile_file(dst, lz, x * 2 ** (lz - z), y * 2 ** (lz - z)).exists() for lz in levels):
            stats["vorhanden"] += 1
        elif prev_src is not None and neighbours_unchanged(src, prev_src, z, x, y) and all(
            tile_file(prev_dst, lz, x * 2 ** (lz - z) + 2 ** (lz - z) - 1, y * 2 ** (lz - z) + 2 ** (lz - z) - 1).exists() for lz in levels
        ):
            for lz in levels:
                n = 2 ** (lz - z)
                for j in range(n):
                    for k in range(n):
                        a, b = tile_file(prev_dst, lz, x * n + k, y * n + j), tile_file(dst, lz, x * n + k, y * n + j)
                        if not b.exists():
                            os.link(a, b)
            stats["übernommen"] += 1
        else:
            center = Image.open(tile_file(src, z, x, y)).convert("RGB")
            if is_flat(center):
                big = center.resize((TILE * SCALE, TILE * SCALE), Image.LANCZOS)
                stats["flach"] += 1
            else:
                if up is None:
                    print("    lade KI-Modell …", flush=True)
                    up = Upscaler(args.model, args.device)
                    print(f"    Rechenwerk: {up.device}", flush=True)
                out = up.run(load_with_margin(src, z, x, y, cache))
                m = MARGIN * SCALE
                big = out.crop((m, m, m + TILE * SCALE, m + TILE * SCALE))
                stats["ki"] += 1
            # Stufe z+2 direkt aus dem ×4-Bild, Stufe z+1 aus dem auf die Hälfte verkleinerten Bild
            for lz in levels:
                n = 2 ** (lz - z)
                img = big if n == SCALE else big.resize((TILE * n, TILE * n), Image.LANCZOS)
                for j in range(n):
                    for k in range(n):
                        part = img.crop((k * TILE, j * TILE, (k + 1) * TILE, (j + 1) * TILE))
                        out_file = tile_file(dst, lz, x * n + k, y * n + j)
                        tmp = out_file.with_suffix(".tmp")
                        part.save(tmp, "JPEG", quality=args.quality, optimize=True)
                        os.replace(tmp, out_file)
        now = time.time()
        if now - last_print > 10 or i == total:
            last_print = now
            done_work = stats["ki"] + stats["flach"]
            rate = (now - started) / max(1, done_work)
            remaining = (total - i) * rate if done_work else 0
            eta = str(dt.timedelta(seconds=int(remaining)))
            print(f"    {i}/{total} Kacheln · KI {stats['ki']} · Meer {stats['flach']} · übernommen {stats['übernommen']} · schon fertig {stats['vorhanden']} · Rest ca. {eta}", flush=True)

    # Manifest: neue Stufen eintragen
    new = json.loads(json.dumps(manifest))
    new["version"] = args.target.name
    for s in new["tileSets"]:
        if s["id"] != args.set:
            continue
        (rx0, ry0), (rx1, ry1) = s["ranges"][str(z)]
        for lz in levels:
            n = 2 ** (lz - z)
            s["ranges"][str(lz)] = [[rx0 * n, ry0 * n], [rx1 * n + n - 1, ry1 * n + n - 1]]
        s["maxZoom"] = levels[-1]
        files = [f for lz in range(s["minZoom"], levels[-1] + 1) for f in (dst / str(lz)).glob("*.jpg")]
        s["tiles"] = len(files)
        s["bytes"] = sum(f.stat().st_size for f in files)
        s["upscaled"] = {
            "fromZoom": z,
            "levels": levels,
            "model": args.model.stem,
            "area": args.area,
            "createdAt": dt.datetime.now(dt.timezone.utc).isoformat(timespec="seconds"),
        }
    new.setdefault("problems", [])
    (args.target / "manifest.json").write_text(json.dumps(new, indent=2, ensure_ascii=False) + "\n")
    print(f"==> Fertig in {dt.timedelta(seconds=int(time.time() - started))}. Manifest geschrieben: {args.target / 'manifest.json'}", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
