import L from 'leaflet'
import type { PinIndexItem } from '../api/types'
import { LOOT, LOOT_ORDER, PIN_SVG, ringGradient, sortLoot } from '../lib/lootTypes'

/** Marker: dunkler Kreis mit Ring in den Loottyp-Farben (mehrere Typen = Segmente). */
export function pinIcon(p: PinIndexItem, selected = false): L.DivIcon {
  const types = sortLoot(p.l)
  const glyph = types.length ? LOOT[types[0]].svg : PIN_SVG
  const color = types.length ? LOOT[types[0]].color : '#c9c5d6'
  const extra = types.length > 1 ? `<b class="more">${types.length}</b>` : ''
  const cls = ['pin-marker', `s-${p.s}`, selected ? 'selected' : ''].join(' ')
  return L.divIcon({
    className: 'pin-marker-wrap',
    html: `<div class="${cls}" style="--ring:${ringGradient(types)};--glyph:${color}"><span class="glyph">${glyph}</span>${extra}</div>`,
    iconSize: [30, 30],
    iconAnchor: [15, 15],
  })
}

/** Cluster: Anzahl und Ring mit den Farbanteilen der enthaltenen Pins. */
export function clusterIcon(cluster: L.MarkerCluster): L.DivIcon {
  const children = cluster.getAllChildMarkers() as Array<L.Marker & { pin?: PinIndexItem }>
  const counts = new Map(LOOT_ORDER.map((t) => [t, 0]))
  for (const m of children) for (const t of m.pin?.l ?? []) counts.set(t, (counts.get(t) ?? 0) + 1)
  const types = LOOT_ORDER.filter((t) => counts.get(t)! > 0)
  const ring = ringGradient(types, types.map((t) => counts.get(t)!))
  const n = cluster.getChildCount()
  const size = n < 10 ? 34 : n < 100 ? 40 : 48
  return L.divIcon({
    className: 'pin-cluster-wrap',
    html: `<div class="pin-cluster" style="--ring:${ring};width:${size}px;height:${size}px"><span>${n}</span></div>`,
    iconSize: [size, size],
  })
}
