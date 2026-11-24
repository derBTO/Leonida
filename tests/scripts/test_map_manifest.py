"""Tests für scripts/lib/map_manifest.py – laufen ohne Zusatzpakete:

    python3 -m unittest discover -s tests/scripts
"""

import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / "scripts" / "lib"))

import map_manifest as mm  # noqa: E402

MAPS_JS = """
gtadb.Maps = function(options) {
    let self = {
        mapW: 32768,
        mapH: 32768,
        zeroX: 16384,
        zeroY: 16384,
        tileSize: 256,
        tileSetRanges: {
            "day": {
                0: [[0, 0], [1, 1]],
                1: [[1, 1], [2, 2]],
            },
            "yanis,18": {
                0: [[0, 0], [0, 0]],
            }
        },
        tileOverlayRanges: {
            "aiwe,1": {
                0: [[1, 1], [1, 1]],
            },
        },
        defaults: {
            gta6: {
                tileSet: "day",
                tileSets: [
                    "yanis,18",
                    "day",
                ]
            },
        }
    }
    const mapSize = 1024 * Math.pow(2, self.z)
}
"""


class ParseTests(unittest.TestCase):
    def write(self, text: str) -> Path:
        tmp = Path(tempfile.mkdtemp())
        (tmp / "maps.js").write_text(text, encoding="utf-8")
        return tmp / "maps.js"

    def test_parses_ranges_and_sets(self):
        info = mm.parse_maps_js(self.write(MAPS_JS))
        self.assertEqual(info["tileSets"]["day"][1], [[1, 1], [2, 2]])
        self.assertEqual(info["gta6TileSets"], ["yanis,18", "day"])
        self.assertIn("aiwe,1", info["overlays"])

    def test_detects_changed_coordinate_constants(self):
        with self.assertRaises(mm.ManifestError):
            mm.parse_maps_js(self.write(MAPS_JS.replace("zeroX: 16384", "zeroX: 20000")))

    def test_detects_changed_scale_formula(self):
        with self.assertRaises(mm.ManifestError):
            mm.parse_maps_js(self.write(MAPS_JS.replace("1024 * Math.pow", "2048 * Math.pow")))


class GeometryTests(unittest.TestCase):
    def test_world_bounds_of_real_day_set(self):
        # Werte des Tile-Sets "day" auf Zoom 6 (gtadb.org, Stand 24.11.2026)
        bounds = mm.world_bounds({6: [[34, 34], [167, 206]]})
        self.assertEqual(bounds, {"west": -12032.0, "east": 5120.0, "south": -10112.0, "north": 12032.0})

    def test_tile_path_has_y_before_x(self):
        self.assertEqual(mm.tile_path(3, x=5, y=7), "3/3,7,5.jpg")


class CopyAndCheckTests(unittest.TestCase):
    def test_check_reports_missing_tiles(self):
        root = Path(tempfile.mkdtemp())
        (root / "day" / "0").mkdir(parents=True)
        (root / "day" / "0" / "0,0,0.jpg").write_bytes(b"x")
        result = mm.check_tiles(root, "day", {0: [[0, 0], [1, 0]]})
        self.assertEqual(result["expected"], 2)
        self.assertEqual(result["missing"], ["0/0,0,1.jpg"])

    def test_copy_links_unchanged_files(self):
        base = Path(tempfile.mkdtemp())
        old, src, dst = base / "old", base / "src", base / "dst"
        for folder in (old, src):
            (folder / "0").mkdir(parents=True)
        (old / "0" / "a.jpg").write_bytes(b"same")
        (src / "0" / "a.jpg").write_bytes(b"same")
        (old / "0" / "b.jpg").write_bytes(b"old")
        (src / "0" / "b.jpg").write_bytes(b"new")
        stats = mm.copy_tree(src, dst, old)
        self.assertEqual(stats, {"linked": 1, "copied": 1})
        self.assertEqual((dst / "0" / "a.jpg").stat().st_ino, (old / "0" / "a.jpg").stat().st_ino)
        self.assertEqual((dst / "0" / "b.jpg").read_bytes(), b"new")


class LandmarkComparisonTests(unittest.TestCase):
    @staticmethod
    def grid():
        return {f"L{i}": (float(-12000 + (i % 20) * 850), float(-10000 + (i // 20) * 1100)) for i in range(400)}

    def test_identical_versions_are_unchanged(self):
        result = mm.compare_landmarks(self.grid(), self.grid())
        self.assertEqual(result["verdict"], "unverändert")
        self.assertEqual(result["moved"], 0)

    def test_single_edits_do_not_count_as_system_change(self):
        new = self.grid()
        for key in list(new)[::40]:
            x, y = new[key]
            new[key] = (x + 60, y - 30)
        result = mm.compare_landmarks(self.grid(), new)
        self.assertEqual(result["verdict"], "unverändert")
        self.assertEqual(result["moved"], 10)

    def test_global_shift_is_detected(self):
        new = {k: (x * 1.0005 + 4, y - 3) for k, (x, y) in self.grid().items()}
        result = mm.compare_landmarks(self.grid(), new)
        self.assertEqual(result["verdict"], "geändert")
        transform = result["transform"]
        self.assertAlmostEqual(transform[0], 1.0005, places=6)
        self.assertAlmostEqual(transform[2], 4, places=3)


if __name__ == "__main__":
    unittest.main()
