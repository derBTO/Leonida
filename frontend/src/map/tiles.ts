import type { MapManifest } from '../api/types'

type Range = [[number, number], [number, number]]

/** Welt (Spielmeter) → Pixel auf Zoom z, wie gtadb.org: px = (x + 16384)·2^z/32. */
export function worldToPixel(x: number, y: number, z: number): [number, number] {
  const s = 2 ** z / 32
  return [(x + 16384) * s, (16384 - y) * s]
}

export function pixelToWorld(px: number, py: number, z: number): [number, number] {
  const s = 2 ** z / 32
  return [px / s - 16384, 16384 - py / s]
}

/**
 * Pfad einer Kachel innerhalb eines Tile-Sets. Mit Overlays (nur Community-Karten) gilt die
 * Logik von gtadb.org (maps.js): Kacheln auf dem Rand eines Overlay-Bereichs liegen im
 * kombinierten Ordner, Kacheln innerhalb im Overlay-Ordner selbst.
 */
export function tilePath(set: MapManifest['tileSets'][number], overlayRanges: MapManifest['overlayRanges'], useOverlays: boolean, z: number, x: number, y: number): string | null {
  const range = set.ranges[String(z)] as Range | undefined
  if (!range) return null
  const [[x0, y0], [x1, y1]] = range
  if (x < x0 || x > x1 || y < y0 || y > y1) return null
  let folder = set.id
  if (useOverlays && set.overlay) {
    for (const name of set.overlay.parts) {
      const r = overlayRanges[name]?.[String(z)] as Range | undefined
      if (!r) continue
      const [[ox0, oy0], [ox1, oy1]] = r
      const inside = x >= ox0 && x <= ox1 && y >= oy0 && y <= oy1
      const onEdge = inside && (x === ox0 || x === ox1 || y === oy0 || y === oy1)
      if (onEdge) folder = set.overlay.combined
      else if (x > ox0 && x < ox1 && y > oy0 && y < oy1 && folder === set.id) folder = name
    }
  }
  return `${folder}/${z}/${z},${y},${x}.jpg`
}

