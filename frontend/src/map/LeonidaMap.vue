<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import { Layers, Maximize, Minus, Plus } from 'lucide-vue-next'
import { PlaceLabelLayer } from './PlaceLabelLayer'
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import type { MapManifest, PinIndexItem } from '../api/types'
import { load, save } from '../lib/storage'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useTrails } from '../stores/trails'
import { useUi } from '../stores/ui'
import { fromLatLng, LeonidaCRS, toLatLng } from './crs'
import './map.css'
import { clusterIcon, markerIcon as baseIcon, poiClusterIcon, stepBadgeIcon } from './markers'
import { tilePath } from './tiles'

const props = defineProps<{ selectedId: number | null; movingPin: PinIndexItem | null }>()
const emit = defineEmits<{
  select: [number]
  context: [{ x: number; y: number; clientX: number; clientY: number }]
  moved: [{ x: number; y: number }]
}>()

const pins = usePins()
const session = useSession()
const trails = useTrails()
const ui = useUi()
const el = ref<HTMLElement | null>(null)
const map = shallowRef<L.Map | null>(null)
const manifest = ref<MapManifest | null>(null)
/** Nur die Satellitenkarte „day“ wird genutzt (Community-Karten entfernt, siehe ENTSCHEIDUNGEN.md). */
const TILE_SET = 'day'
const view = load('map', { x: -3500, y: 1000, zoom: 2 })

let tiles: L.TileLayer | null = null
let cluster: L.MarkerClusterGroup | null = null
let poiCluster: L.MarkerClusterGroup | null = null
// Loot in eigenen Clustern, Orte und Hinweise gemeinsam
const groupOf = (p: PinIndexItem) => (p.k === 'loot' ? cluster! : poiCluster!)
let trailLayer: L.LayerGroup | null = null
/** Marker-Symbol; Pins der aktiven Spur bleiben beim Abdunkeln hell. */
function markerIcon(p: PinIndexItem, selected = false): L.DivIcon {
  const icon = baseIcon(p, selected)
  if (trails.pinIds.has(p.id)) icon.options.className += ' trail-pin'
  return icon
}
let moveMarker: L.Marker | null = null
const markers = new Map<number, L.Marker & { pin?: PinIndexItem }>()

function persist() {
  if (!map.value) return
  const c = fromLatLng(map.value.getCenter())
  save('map', { x: c.x, y: c.y, zoom: map.value.getZoom() })
  const b = map.value.getBounds()
  pins.bounds = { west: b.getWest(), east: b.getEast(), south: b.getSouth(), north: b.getNorth() }
}

/** Ortsnamen-Ebene, per Schalter in der Seitenleiste */
const placeLabels = new PlaceLabelLayer()
watch(() => ui.placeNames, (on) => {
  if (!map.value) return
  if (on) placeLabels.addTo(map.value)
  else placeLabels.remove()
})

/** Ebenen-Menü unten rechts: Ortsnamen, KI-Zoomstufen */
const layersOpen = ref(false)
const layersEl = ref<HTMLElement | null>(null)
/** hat die aktive Kartenversion KI-hochgerechnete Stufen? */
const hasHd = ref(false)
watch(() => ui.hdTiles, () => buildTiles())
function onDocClick(e: MouseEvent) {
  if (layersOpen.value && layersEl.value && !layersEl.value.contains(e.target as Node)) layersOpen.value = false
}
function onDocKey(e: KeyboardEvent) {
  if (e.key === 'Escape') layersOpen.value = false
}
document.addEventListener('click', onDocClick, true)
document.addEventListener('keydown', onDocKey)
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick, true)
  document.removeEventListener('keydown', onDocKey)
})

/** Ausdehnung der Kartenkacheln, für „ganze Karte zeigen“ */
let wholeMap: L.LatLngBounds | null = null

function buildTiles() {
  if (!map.value || !manifest.value) return
  const set = manifest.value.tileSets.find((s) => s.id === TILE_SET) ?? manifest.value.tileSets[0]
  const base = session.meta?.map.tiles_url ?? '/map/current/tiles'
  const m = manifest.value
  // Hochauflösende Bildschirme: halb so große Kacheln der nächstfeineren Stufe laden (wie Leaflets
  // detectRetina, aber mit unseren Kachelgrenzen), damit 1 Kachelpixel = 1 Bildschirmpixel ist.
  // KI-hochgerechnete Stufen nur, wenn eingeschaltet; sonst endet die Karte bei der Originalstufe
  const useHd = !!set.upscaled && ui.hdTiles
  const maxNative = set.upscaled && !useHd ? set.upscaled.fromZoom : set.maxZoom
  hasHd.value = !!set.upscaled
  const hidpi = window.devicePixelRatio >= 1.5 && maxNative > set.minZoom
  const offset = hidpi ? 1 : 0
  const Layer = L.TileLayer.extend({
    getTileUrl(coords: L.Coords) {
      const path = tilePath(set, m.overlayRanges, false, coords.z + offset, coords.x, coords.y)
      return path ? `${base}/${path}` : L.Util.emptyImageUrl
    },
  })
  const b = set.bounds
  wholeMap = L.latLngBounds(toLatLng(b.west, b.south), toLatLng(b.east, b.north))
  tiles?.remove()
  tiles = new (Layer as unknown as typeof L.TileLayer)('', {
    tileSize: hidpi ? 128 : 256,
    minZoom: 0,
    maxZoom: 9,
    maxNativeZoom: maxNative - offset,
    minNativeZoom: Math.max(0, set.minZoom - offset),
    bounds: L.latLngBounds(toLatLng(b.west, b.south), toLatLng(b.east, b.north)),
    noWrap: true,
    keepBuffer: 3,
    // CC BY verlangt einen Hinweis auf Änderungen: KI-hochgerechnete Zusatzstufen kennzeichnen
    attribution: '<a href="https://map.gtadb.org" target="_blank" rel="noopener">GTA VI Map Tiles (CC BY 4.0) — map.gtadb.org</a> · gtadb.org and all contributors'
      + (useHd && set.upscaled ? ` · Stufen ${set.upscaled.levels.join('–')} KI-hochgerechnet` : '')
      + ' · <a href="/quellen">Quellen</a> · <a href="https://github.com/derBTO/Leonida" target="_blank" rel="noopener">GitHub</a>',
  })
  tiles.addTo(map.value)
  persist()
}

function syncMarkers(list: PinIndexItem[]) {
  if (!cluster || !poiCluster) return
  const byId = new Map(list.map((p) => [p.id, p]))
  for (const [id, m] of markers) {
    const next = byId.get(id)
    // entfernen, wenn weggefiltert oder die Art gewechselt hat (anderer Cluster)
    if (!next || next.k !== m.pin!.k) {
      groupOf(m.pin!).removeLayer(m)
      markers.delete(id)
    }
  }
  const add: { loot: L.Layer[]; poi: L.Layer[] } = { loot: [], poi: [] }
  for (const p of list) {
    if (props.movingPin?.id === p.id) continue
    const existing = markers.get(p.id)
    if (existing) {
      const prev = existing.pin!
      if (prev.x !== p.x || prev.y !== p.y) existing.setLatLng(toLatLng(p.x, p.y))
      if (prev.s !== p.s || prev.l.join() !== p.l.join() || prev.t !== p.t || prev.p !== p.p) {
        existing.pin = p
        existing.setIcon(markerIcon(p, p.id === props.selectedId))
        existing.options.title = p.t
        groupOf(p).refreshClusters(existing)
      }
      existing.pin = p
      continue
    }
    const marker = L.marker(toLatLng(p.x, p.y), {
      icon: markerIcon(p, p.id === props.selectedId), title: p.t, keyboard: true, riseOnHover: true,
      zIndexOffset: p.k === 'loot' ? 0 : -100,
    }) as L.Marker & { pin?: PinIndexItem }
    marker.pin = p
    marker.on('click', () => emit('select', p.id))
    markers.set(p.id, marker)
    add[p.k === 'loot' ? 'loot' : 'poi'].push(marker)
  }
  cluster.addLayers(add.loot)
  poiCluster.addLayers(add.poi)
}

onMounted(async () => {
  ;(window as unknown as { L: typeof L }).L = L
  await import('leaflet.markercluster')
  const m = L.map(el.value!, {
    crs: LeonidaCRS,
    zoomControl: false,
    attributionControl: true,
    minZoom: 0,
    maxZoom: 9,
    zoomSnap: 0.5,
    wheelPxPerZoomLevel: 90,
    center: toLatLng(view.x, view.y),
    zoom: view.zoom,
    maxBoundsViscosity: 0.7,
  })
  m.attributionControl.setPrefix(false)
  map.value = m
  m.setMaxBounds(L.latLngBounds(toLatLng(-16000, -14000), toLatLng(9000, 16000)))
  cluster = L.markerClusterGroup({
    chunkedLoading: true,
    maxClusterRadius: (z: number) => (z >= 5 ? 30 : 55),
    disableClusteringAtZoom: 7,
    showCoverageOnHover: false,
    spiderfyOnMaxZoom: true,
    iconCreateFunction: clusterIcon,
  })
  m.addLayer(cluster)
  poiCluster = L.markerClusterGroup({
    chunkedLoading: true,
    maxClusterRadius: (z: number) => (z >= 5 ? 24 : 45),
    disableClusteringAtZoom: 6,
    showCoverageOnHover: false,
    spiderfyOnMaxZoom: true,
    iconCreateFunction: poiClusterIcon,
  })
  m.addLayer(poiCluster)
  trailLayer = L.layerGroup().addTo(m)
  if (ui.placeNames) placeLabels.addTo(m)
  m.on('moveend zoomend', persist)
  m.on('contextmenu', (e: L.LeafletMouseEvent) => {
    const c = fromLatLng(e.latlng)
    emit('context', { ...c, clientX: e.originalEvent.clientX, clientY: e.originalEvent.clientY })
  })
  try {
    const url = session.meta?.map.manifest_url ?? '/map/current/manifest.json'
    const res = await fetch(url, { cache: 'no-cache' })
    if (!res.ok) throw new Error()
    manifest.value = await res.json()
    buildTiles()
  } catch {
    ui.toast('Kartendaten nicht gefunden. Bitte scripts/map-import.sh ausführen.', 'error', 8000)
  }
  syncMarkers(pins.visible)
  drawTrail()
  if (fitted !== trails.fitRequest) fitTrail()
  persist()
})

onBeforeUnmount(() => map.value?.remove())

watch(() => pins.visible, syncMarkers)

/** Aktive Spur zeichnen: Linie von Schritt zu Schritt (parallele Schritte verzweigen) und Nummern. */
function drawTrail() {
  if (!map.value || !trailLayer) return
  trailLayer.clearLayers()
  const trail = trails.active
  el.value?.classList.toggle('trail-focus', !!trail)
  if (!trail) return
  const shown = trail.steps.filter((s) => s.pin && !trails.isHidden(trail, s.id))
  const groups = new Map<number, typeof shown>()
  for (const s of shown) groups.set(s.position, [...(groups.get(s.position) ?? []), s])
  const ordered = [...groups.entries()].sort((a, b) => a[0] - b[0]).map(([, g]) => g)
  for (let i = 0; i + 1 < ordered.length; i++) {
    for (const a of ordered[i]) for (const b of ordered[i + 1]) {
      L.polyline([toLatLng(a.pin!.x, a.pin!.y), toLatLng(b.pin!.x, b.pin!.y)], {
        color: a.done && b.done ? '#4ade80' : '#ff3d8b', weight: 3, opacity: 0.9, className: 'trail-line', interactive: false,
      }).addTo(trailLayer)
    }
  }
  const current = trail.steps[trails.stepIndex]?.id
  for (const g of ordered) {
    g.forEach((s, i) => {
      const label = g.length > 1 ? `${s.position}${String.fromCharCode(97 + i)}` : String(s.position)
      const badge = L.marker(toLatLng(s.pin!.x, s.pin!.y), {
        icon: stepBadgeIcon(label, s.id === current ? 'current' : s.done ? 'done' : 'open'),
        zIndexOffset: 2000, keyboard: false, title: `Schritt ${label}: ${s.title}`,
      })
      badge.on('click', () => {
        trails.stepIndex = trail.steps.findIndex((x) => x.id === s.id)
        emit('select', s.pin!.id)
      })
      badge.addTo(trailLayer!)
    })
  }
}
watch(() => [trails.active, trails.stepIndex, trails.spoiler, trails.revealed], drawTrail)
/** Symbole der Spur-Pins auffrischen, wenn sich die Spur ändert (hell vs. abgedunkelt). */
watch(() => trails.pinIds, (now, before) => {
  for (const id of new Set([...now, ...(before ?? [])])) {
    const mk = markers.get(id)
    if (mk?.pin) mk.setIcon(markerIcon(mk.pin, id === props.selectedId))
  }
})

/** Ausschnitt auf alle sichtbaren Schritte der aktiven Spur setzen. */
let fitted = 0
watch(() => trails.fitRequest, () => fitTrail())
/** Schritt-Navigation der Spurleiste: zum Pin des aktuellen Schritts fliegen (ohne Detaildialog). */
watch(() => trails.focusRequest, () => {
  const t = trails.active
  const step = t?.steps[trails.stepIndex]
  if (t && step?.pin && !trails.isHidden(t, step.id)) flyTo(step.pin.x, step.pin.y)
})
/** Ganze Karte zentriert zeigen, ohne dass die Seitenleiste etwas verdeckt */
function fitWhole() {
  if (!map.value || !wholeMap) return
  const wide = window.innerWidth > 760
  const side = wide && ui.sidebarOpen ? (document.querySelector('.sidebar')?.getBoundingClientRect().right ?? 0) + 10 : 10
  map.value.flyToBounds(wholeMap, { paddingTopLeft: [side, 70], paddingBottomRight: [70, 10], duration: 0.6 })
}

function fitTrail() {
  const trail = trails.active
  if (!map.value || !trail) return
  fitted = trails.fitRequest
  const pts = trail.steps.filter((s) => s.pin && !trails.isHidden(trail, s.id)).map((s) => toLatLng(s.pin!.x, s.pin!.y))
  if (pts.length === 1) map.value.flyTo(pts[0], Math.max(map.value.getZoom(), 5), { duration: 0.6 })
  else if (pts.length > 1) {
    // Sidebar, Detailansicht und Puzzle-Leiste nicht überdecken
    const wide = window.innerWidth > 760
    const side = wide && ui.sidebarOpen ? (document.querySelector('.sidebar')?.getBoundingClientRect().right ?? 0) + 40 : 40
    const right = 60
    map.value.flyToBounds(L.latLngBounds(pts), { paddingTopLeft: [side, 110], paddingBottomRight: [right, wide ? 150 : 200], maxZoom: 6, duration: 0.6 })
  }
}

watch(() => props.selectedId, (id, old) => {
  for (const pid of [old, id]) {
    const mk = pid ? markers.get(pid) : undefined
    if (mk?.pin) mk.setIcon(markerIcon(mk.pin, pid === id))
  }
})

/** Verschieben: Marker aus dem Cluster lösen und ziehbar machen. */
watch(() => props.movingPin, (pin, old) => {
  if (!map.value || !cluster || !poiCluster) return
  moveMarker?.remove()
  moveMarker = null
  if (old) {
    const mk = markers.get(old.id)
    if (mk) groupOf(mk.pin!).addLayer(mk)
  }
  if (pin) {
    const mk = markers.get(pin.id)
    if (mk) groupOf(mk.pin!).removeLayer(mk)
    moveMarker = L.marker(toLatLng(pin.x, pin.y), { icon: markerIcon(pin, true), draggable: true, autoPan: true, zIndexOffset: 1000 })
    moveMarker.on('dragend', () => emit('moved', fromLatLng(moveMarker!.getLatLng())))
    moveMarker.addTo(map.value)
    map.value.panTo(moveMarker.getLatLng())
  }
})

/** Zum Punkt fliegen; auf Smartphones so, dass der Marker oberhalb des Detail-Sheets liegt. */
function flyTo(x: number, y: number, minZoom = 5) {
  const m = map.value
  if (!m) return
  const zoom = Math.max(m.getZoom(), minZoom)
  m.flyTo(toLatLng(x, y), zoom, { duration: 0.6 })
}
function center() {
  return map.value ? fromLatLng(map.value.getCenter()) : { x: 0, y: 0 }
}
function invalidate() {
  map.value?.invalidateSize()
}
defineExpose({ flyTo, center, invalidate, fitTrail })
</script>

<template>
  <div class="map-wrap">
    <div ref="el" class="map" />
    <a class="brand" href="https://github.com/derBTO/Leonida" target="_blank" rel="noopener" title="Leonida auf GitHub">
      <img src="/brand/bto-logo.webp" alt="BTO – Leonida auf GitHub" width="171" height="216" />
    </a>
    <div class="controls">
      <div ref="layersEl" class="layers-wrap">
        <div v-if="layersOpen" class="layers-menu panel" role="dialog" aria-label="Kartenebenen">
          <div class="lm-title">Kartenebenen</div>
          <label class="switch"><input type="checkbox" v-model="ui.placeNames" /> Ortsnamen</label>
          <template v-if="hasHd">
            <label class="switch"><input type="checkbox" v-model="ui.hdTiles" /> Schärfere Zoomstufen 7–8</label>
            <p class="lm-note">Experimentell: per KI aus den Originalkacheln hochgerechnet. Aus = Originalbilder, bei starkem Zoom unschärfer.</p>
          </template>
        </div>
        <div class="zoom panel">
          <button type="button" aria-label="Kartenebenen" title="Kartenebenen" :aria-expanded="layersOpen" :class="{ on: layersOpen }" @click="layersOpen = !layersOpen"><Layers :size="17" /></button>
        </div>
      </div>
      <div class="zoom panel">
        <button type="button" aria-label="Hineinzoomen" @click="map?.zoomIn()"><Plus :size="18" /></button>
        <button type="button" aria-label="Ganze Karte zeigen" title="Ganze Karte zeigen" @click="fitWhole"><Maximize :size="16" /></button>
        <button type="button" aria-label="Herauszoomen" @click="map?.zoomOut()"><Minus :size="18" /></button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.map-wrap, .map { position: absolute; inset: 0; }
.controls { position: absolute; right: 12px; bottom: 28px; z-index: 800; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.zoom { display: flex; flex-direction: column; overflow: hidden; }
.zoom button { width: 38px; height: 38px; border: 0; background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text); }
.zoom button:hover { background: var(--panel-hover); }
.zoom button + button { border-top: 1px solid var(--line); }
.zoom button.on { color: var(--pink); }
.layers-wrap { position: relative; }
.layers-menu { position: absolute; right: 48px; bottom: 0; width: 250px; padding: 12px 14px; display: flex; flex-direction: column; gap: 8px; }
.lm-title { font-family: var(--font-display); font-weight: 700; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
.layers-menu .switch { display: flex; align-items: center; gap: 8px; color: var(--text); font-size: 13.5px; cursor: pointer; }
.layers-menu .switch input { accent-color: var(--pink); width: 16px; height: 16px; margin: 0; }
.lm-note { margin: -4px 0 0 24px; font-size: 12px; color: var(--faint); line-height: 1.35; }
/* Logo links neben den Zoom-Knöpfen, unten bündig */
.brand { position: absolute; right: 62px; bottom: 28px; z-index: 790; display: block; line-height: 0; opacity: 0.9; transition: opacity 0.15s, transform 0.15s; }
.brand img { height: 72px; width: auto; filter: drop-shadow(0 2px 6px rgba(0, 0, 0, 0.6)); }
.brand:hover { opacity: 1; transform: scale(1.04); }
@media (max-width: 760px) {
  .controls { bottom: 96px; }
  .brand { bottom: 96px; right: 58px; }
  .brand img { height: 50px; }
}
</style>
