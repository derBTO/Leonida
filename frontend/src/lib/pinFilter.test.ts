import { describe, expect, it } from 'vitest'
import type { PinIndexItem } from '../api/types'
import { inBounds, matches, sortByTitle, type FilterState } from './pinFilter'

const pin = (o: Partial<PinIndexItem>): PinIndexItem => ({ id: 1, t: '', x: 0, y: 0, l: [], s: 'confirmed', d: '', w: [], v: [], c: [], ...o })
const f = (o: Partial<FilterState> = {}): FilterState => ({ q: '', loot: [], showGone: true, ...o })

describe('Pin-Filter', () => {
  it('sucht ohne Rücksicht auf Groß-/Kleinschreibung in allen Feldern', () => {
    const p = pin({ t: 'Motel', d: 'Tresor im KELLER', w: ['Pump Shotgun'], v: ['Vapid Dominator'], c: ['Safe 0042'] })
    for (const q of ['motel', 'keller', 'SHOTGUN', 'dominator', '0042', 'safe']) expect(matches(p, f({ q }))).toBe(true)
    expect(matches(p, f({ q: 'bank' }))).toBe(false)
  })

  it('verlangt alle Suchwörter', () => {
    const p = pin({ t: 'Motel am Strand' })
    expect(matches(p, f({ q: 'motel strand' }))).toBe(true)
    expect(matches(p, f({ q: 'motel stadt' }))).toBe(false)
  })

  it('filtert Loottypen mit ODER und kennt "ohne Loottyp"', () => {
    const a = pin({ l: ['weapons'] })
    const b = pin({ l: ['cash', 'drugs'] })
    const c = pin({ l: [] })
    const filter = f({ loot: ['weapons', 'drugs'] })
    expect([a, b, c].map((p) => matches(p, filter))).toEqual([true, true, false])
    expect([a, b, c].map((p) => matches(p, f({ loot: ['none'] })))).toEqual([false, false, true])
  })

  it('blendet nicht mehr verfügbare Pins optional aus', () => {
    expect(matches(pin({ s: 'gone' }), f({ showGone: false }))).toBe(false)
    expect(matches(pin({ s: 'gone' }), f({ showGone: true }))).toBe(true)
  })

  it('prüft den Kartenausschnitt und sortiert alphabetisch', () => {
    expect(inBounds(pin({ x: 5, y: 5 }), { west: 0, south: 0, east: 10, north: 10 })).toBe(true)
    expect(inBounds(pin({ x: 15, y: 5 }), { west: 0, south: 0, east: 10, north: 10 })).toBe(false)
    const sorted = sortByTitle([pin({ id: 1, t: 'Zoo' }), pin({ id: 2, t: 'ärztehaus' }), pin({ id: 3, t: 'Bank 10' }), pin({ id: 4, t: 'Bank 9' })])
    expect(sorted.map((p) => p.t)).toEqual(['ärztehaus', 'Bank 9', 'Bank 10', 'Zoo'])
  })
})
