import type { LootType, PinIndexItem } from '../api/types'

/**
 * Such- und Filterregeln für Karte und Sidebar. Identisch mit der Server-Suche
 * (backend/app/Services/PinIndex.php):
 * - Groß-/Kleinschreibung egal, alle Suchwörter müssen vorkommen.
 * - Durchsucht Titel, Beschreibung, Waffen-, Fahrzeugnamen, Codes und Code-Bezeichnungen.
 * - Loottypen: mindestens einer der gewählten (ODER); 'none' = Pins ohne Loottyp.
 */
export type LootFilter = LootType | 'none'

export interface Bounds { west: number; south: number; east: number; north: number }

export interface FilterState {
  q: string
  loot: LootFilter[]
  showGone: boolean
}

export function terms(q: string): string[] {
  return q.toLowerCase().trim().split(/\s+/u).filter(Boolean)
}

const haystacks = new WeakMap<PinIndexItem, string>()
function haystack(p: PinIndexItem): string {
  let h = haystacks.get(p)
  if (h === undefined) {
    h = [p.t, p.d, ...p.w, ...p.v, ...p.c].join('\n').toLowerCase()
    haystacks.set(p, h)
  }
  return h
}

export function matches(p: PinIndexItem, f: FilterState): boolean {
  if (!f.showGone && p.s === 'gone') return false
  if (f.loot.length > 0) {
    const ok = f.loot.some((t) => (t === 'none' ? p.l.length === 0 : p.l.includes(t)))
    if (!ok) return false
  }
  const words = terms(f.q)
  if (words.length === 0) return true
  const h = haystack(p)
  return words.every((w) => h.includes(w))
}

export function inBounds(p: PinIndexItem, b: Bounds): boolean {
  return p.x >= b.west && p.x <= b.east && p.y >= b.south && p.y <= b.north
}

const collator = new Intl.Collator('de', { sensitivity: 'base', numeric: true })
export function sortByTitle(list: PinIndexItem[]): PinIndexItem[] {
  return [...list].sort((a, b) => collator.compare(a.t, b.t) || a.id - b.id)
}
