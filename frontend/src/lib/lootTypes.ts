import type { LootType } from '../api/types'

// Symbole für Kartenmarker als SVG-Strings (Pfade aus Lucide, ISC-Lizenz), damit sie in
// Leaflet-DivIcons ohne Vue gerendert werden können.
const svg = (inner: string) =>
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

export const LOOT: Record<LootType, { label: string; color: string; svg: string; icon: string }> = {
  vehicles: {
    label: 'Fahrzeuge',
    color: '#22d3ee',
    icon: 'Car',
    svg: svg('<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/>'),
  },
  weapons: {
    label: 'Waffen',
    color: '#ff4d8d',
    icon: 'Crosshair',
    svg: svg('<circle cx="12" cy="12" r="10"/><line x1="22" x2="18" y1="12" y2="12"/><line x1="6" x2="2" y1="12" y2="12"/><line x1="12" x2="12" y1="6" y2="2"/><line x1="12" x2="12" y1="22" y2="18"/>'),
  },
  drugs: {
    label: 'Drogen',
    color: '#4ade80',
    icon: 'Leaf',
    svg: svg('<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/>'),
  },
  jewelry: {
    label: 'Schmuck',
    color: '#a78bfa',
    icon: 'Gem',
    svg: svg('<path d="M10.5 3 8 9l4 13 4-13-2.5-6"/><path d="M17 3a2 2 0 0 1 1.6.8l3 4a2 2 0 0 1 .013 2.382l-7.99 10.986a2 2 0 0 1-3.247 0l-7.99-10.986A2 2 0 0 1 2.4 7.8l2.998-3.997A2 2 0 0 1 7 3z"/><path d="M2 9h20"/>'),
  },
  cash: {
    label: 'Geld',
    color: '#fbbf24',
    icon: 'Banknote',
    svg: svg('<rect width="20" height="12" x="2" y="6" rx="2"/><circle cx="12" cy="12" r="2"/><path d="M6 12h.01M18 12h.01"/>'),
  },
}

export const LOOT_ORDER: LootType[] = ['vehicles', 'weapons', 'drugs', 'jewelry', 'cash']
export const NO_LOOT_COLOR = '#8d89a0'
export const PIN_SVG = svg('<circle cx="12" cy="12" r="3"/>')

/** conic-gradient aus gleich großen Segmenten je Loottyp. */
export function ringGradient(types: LootType[], weights?: number[]): string {
  if (types.length === 0) return NO_LOOT_COLOR
  if (types.length === 1) return LOOT[types[0]].color
  const total = weights ? weights.reduce((a, b) => a + b, 0) : types.length
  let start = 0
  const stops = types.map((t, i) => {
    const share = ((weights ? weights[i] : 1) / total) * 360
    const seg = `${LOOT[t].color} ${start.toFixed(1)}deg ${(start + share).toFixed(1)}deg`
    start += share
    return seg
  })
  return `conic-gradient(${stops.join(', ')})`
}

export function sortLoot(types: LootType[]): LootType[] {
  return LOOT_ORDER.filter((t) => types.includes(t))
}
