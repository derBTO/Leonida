import L from 'leaflet'
import type { PinIndexItem } from '../api/types'
import { LOOT, LOOT_ORDER, PIN_SVG, ringGradient, sortLoot } from '../lib/lootTypes'
import { isProminent, POI, POI_GREY } from '../lib/poiTypes'

/** Ort (POI): abgerundetes Quadrat in der Kategoriefarbe; graue Orte kleiner. */
export function poiIcon(p: PinIndexItem, selected = false): L.DivIcon {
  const type = p.p ?? 'poi'
  const big = isProminent(type)
  const size = big ? 26 : 22
  return L.divIcon({
    className: 'pin-marker-wrap',
    html: `<div class="poi-marker${big ? ' big' : ''}${selected ? ' selected' : ''}" style="--c:${POI[type].color};width:${size}px;height:${size}px">${POI[type].svg}</div>`,
    iconSize: [size, size],
    iconAnchor: [size / 2, size / 2],
  })
}

export function markerIcon(p: PinIndexItem, selected = false): L.DivIcon {
  return p.k === 'poi' ? poiIcon(p, selected) : pinIcon(p, selected)
}

/** Cluster für Orte: Quadrat mit Anzahl, Rand in der häufigsten Kategoriefarbe. */
export function poiClusterIcon(cluster: L.MarkerCluster): L.DivIcon {
  const children = cluster.getAllChildMarkers() as Array<L.Marker & { pin?: PinIndexItem }>
  const counts = new Map<string, number>()
  for (const m of children) {
    const c = m.pin?.p ? POI[m.pin.p].color : POI_GREY
    counts.set(c, (counts.get(c) ?? 0) + 1)
  }
  const colored = [...counts.entries()].filter(([c]) => c !== POI_GREY).sort((a, b) => b[1] - a[1])
  const color = colored[0]?.[0] ?? POI_GREY
  const n = cluster.getChildCount()
  const size = n < 10 ? 28 : n < 100 ? 32 : 38
  return L.divIcon({
    className: 'pin-cluster-wrap',
    html: `<div class="poi-cluster" style="--c:${color};width:${size}px;height:${size}px"><span>${n}</span></div>`,
    iconSize: [size, size],
  })
}

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
