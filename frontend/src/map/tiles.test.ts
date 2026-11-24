import { describe, expect, it } from 'vitest'
import type { MapManifest } from '../api/types'
import { pixelToWorld, tilePath, worldToPixel } from './tiles'

const day: MapManifest['tileSets'][number] = {
  id: 'day', minZoom: 0, maxZoom: 6, overlay: null,
  ranges: { '6': [[34, 34], [167, 206]] },
  bounds: { west: -12032, east: 5120, south: -10112, north: 12032 },
}
const yanis: MapManifest['tileSets'][number] = {
  ...day, id: 'yanis,18', ranges: { '5': [[21, 21], [103, 99]] },
  overlay: { combined: 'yanis,18,aiwe,1,martipk,5,rickrick,3', parts: ['aiwe,1', 'martipk,5', 'rickrick,3'] },
}
const overlays = { 'aiwe,1': { '5': [[37, 50], [41, 55]] as [[number, number], [number, number]] } }

describe('Kachel-Geometrie (wie gtadb.org)', () => {
  it('rechnet Welt ↔ Pixel', () => {
    expect(worldToPixel(-12032, 12032, 6)).toEqual([34 * 256, 34 * 256])
    expect(pixelToWorld(168 * 256, 207 * 256, 6)).toEqual([5120, -10112])
  })

  it('setzt y vor x in den Dateinamen und kennt Bereichsgrenzen', () => {
    expect(tilePath(day, {}, false, 6, 100, 120)).toBe('day/6/6,120,100.jpg')
    expect(tilePath(day, {}, false, 6, 10, 120)).toBeNull()
    expect(tilePath(day, {}, false, 3, 10, 12)).toBeNull()
  })

  it('wählt Overlay-Ordner wie gtadb.org', () => {
    expect(tilePath(yanis, overlays, true, 5, 37, 52)).toBe('yanis,18,aiwe,1,martipk,5,rickrick,3/5/5,52,37.jpg')
    expect(tilePath(yanis, overlays, true, 5, 39, 52)).toBe('aiwe,1/5/5,52,39.jpg')
    expect(tilePath(yanis, overlays, true, 5, 60, 60)).toBe('yanis,18/5/5,60,60.jpg')
    expect(tilePath(yanis, overlays, false, 5, 39, 52)).toBe('yanis,18/5/5,52,39.jpg')
  })
})
