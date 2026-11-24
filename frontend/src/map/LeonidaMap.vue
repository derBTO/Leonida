<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import { Layers, Minus, Plus } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref, shallowRef, watch } from 'vue'
import type { MapManifest, PinIndexItem } from '../api/types'
import { load, save } from '../lib/storage'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import { fromLatLng, LeonidaCRS, toLatLng } from './crs'
import './map.css'
import { clusterIcon, pinIcon } from './markers'
import { TILE_SET_LABELS, tilePath } from './tiles'

const props = defineProps<{ selectedId: number | null; movingPin: PinIndexItem | null }>()
const emit = defineEmits<{
  select: [number]
  context: [{ x: number; y: number; clientX: number; clientY: number }]
  moved: [{ x: number; y: number }]
}>()

const pins = usePins()
const session = useSession()
const ui = useUi()
const el = ref<HTMLElement | null>(null)
const map = shallowRef<L.Map | null>(null)
const manifest = ref<MapManifest | null>(null)
const layersOpen = ref(false)
const view = load('map', { x: -3500, y: 1000, zoom: 2, tileSet: 'day', overlays: false })
const tileSet = ref(view.tileSet)
const overlays = ref(view.overlays)

let tiles: L.TileLayer | null = null
let cluster: L.MarkerClusterGroup | null = null
let moveMarker: L.Marker | null = null
const markers = new Map<number, L.Marker & { pin?: PinIndexItem }>()

function persist() {
  if (!map.value) return
  const c = fromLatLng(map.value.getCenter())
  save('map', { x: c.x, y: c.y, zoom: map.value.getZoom(), tileSet: tileSet.value, overlays: overlays.value })
  const b = map.value.getBounds()
  pins.bounds = { west: b.getWest(), east: b.getEast(), south: b.getSouth(), north: b.getNorth() }
}

function buildTiles() {
  if (!map.value || !manifest.value) return
  const set = manifest.value.tileSets.find((s) => s.id === tileSet.value) ?? manifest.value.tileSets[0]
  tileSet.value = set.id
  const base = session.meta?.map.tiles_url ?? '/map/current/tiles'
  const m = manifest.value
  const Layer = L.TileLayer.extend({
    getTileUrl(coords: L.Coords) {
      const path = tilePath(set, m.overlayRanges, overlays.value, coords.z, coords.x, coords.y)
      return path ? `${base}/${path}` : L.Util.emptyImageUrl
    },
  })
  const b = set.bounds
  tiles?.remove()
  tiles = new (Layer as unknown as typeof L.TileLayer)('', {
    tileSize: 256,
    minZoom: 0,
    maxZoom: 9,
    maxNativeZoom: set.maxZoom,
    minNativeZoom: set.minZoom,
    bounds: L.latLngBounds(toLatLng(b.west, b.south), toLatLng(b.east, b.north)),
    noWrap: true,
    keepBuffer: 3,
    attribution: '<a href="https://map.gtadb.org" target="_blank" rel="noopener">GTA VI Map Tiles (CC BY 4.0) — map.gtadb.org</a> · gtadb.org and all contributors · <a href="/quellen">Quellen</a>',
  })
  tiles.addTo(map.value)
  persist()
}

function syncMarkers(list: PinIndexItem[]) {
  if (!cluster) return
  const wanted = new Set(list.map((p) => p.id))
  const remove: L.Layer[] = []
  for (const [id, m] of markers) {
    if (!wanted.has(id)) {
      remove.push(m)
      markers.delete(id)
    }
  }
  cluster.removeLayers(remove)
  const add: L.Layer[] = []
  for (const p of list) {
    if (props.movingPin?.id === p.id) continue
    const existing = markers.get(p.id)
    if (existing) {
      const prev = existing.pin!
      if (prev.x !== p.x || prev.y !== p.y) existing.setLatLng(toLatLng(p.x, p.y))
      if (prev.s !== p.s || prev.l.join() !== p.l.join() || prev.t !== p.t) {
        existing.pin = p
        existing.setIcon(pinIcon(p, p.id === props.selectedId))
        existing.options.title = p.t
        cluster.refreshClusters(existing)
      }
      existing.pin = p
      continue
    }
    const marker = L.marker(toLatLng(p.x, p.y), { icon: pinIcon(p, p.id === props.selectedId), title: p.t, keyboard: true, riseOnHover: true }) as L.Marker & { pin?: PinIndexItem }
    marker.pin = p
    marker.on('click', () => emit('select', p.id))
    markers.set(p.id, marker)
    add.push(marker)
  }
  cluster.addLayers(add)
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
  persist()
})

onBeforeUnmount(() => map.value?.remove())

watch(() => pins.visible, syncMarkers)
watch([tileSet, overlays], buildTiles)

watch(() => props.selectedId, (id, old) => {
  for (const pid of [old, id]) {
    const mk = pid ? markers.get(pid) : undefined
    if (mk?.pin) mk.setIcon(pinIcon(mk.pin, pid === id))
  }
})

/** Verschieben: Marker aus dem Cluster lösen und ziehbar machen. */
watch(() => props.movingPin, (pin, old) => {
  if (!map.value || !cluster) return
  moveMarker?.remove()
  moveMarker = null
  if (old) {
    const mk = markers.get(old.id)
    if (mk) cluster.addLayer(mk)
  }
  if (pin) {
    const mk = markers.get(pin.id)
    if (mk) cluster.removeLayer(mk)
    moveMarker = L.marker(toLatLng(pin.x, pin.y), { icon: pinIcon(pin, true), draggable: true, autoPan: true, zIndexOffset: 1000 })
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
  let target = toLatLng(x, y)
  if (window.innerWidth <= 760) {
    const point = m.project(target, zoom).add([0, m.getSize().y * 0.28])
    target = m.unproject(point, zoom)
  }
  m.flyTo(target, zoom, { duration: 0.6 })
}
function center() {
  return map.value ? fromLatLng(map.value.getCenter()) : { x: 0, y: 0 }
}
function invalidate() {
  map.value?.invalidateSize()
}
defineExpose({ flyTo, center, invalidate })
</script>

<template>
  <div class="map-wrap">
    <div ref="el" class="map" />
    <div class="controls">
      <div class="zoom panel">
        <button type="button" aria-label="Hineinzoomen" @click="map?.zoomIn()"><Plus :size="18" /></button>
        <button type="button" aria-label="Herauszoomen" @click="map?.zoomOut()"><Minus :size="18" /></button>
      </div>
      <div class="layers">
        <button class="panel layer-btn" type="button" aria-label="Kartenebene wählen" :aria-expanded="layersOpen" @click="layersOpen = !layersOpen"><Layers :size="18" /></button>
        <div v-if="layersOpen && manifest" class="layer-menu panel">
          <div class="label">Kartenebene</div>
          <label v-for="s in manifest.tileSets" :key="s.id" class="opt">
            <input type="radio" :value="s.id" v-model="tileSet" /> {{ TILE_SET_LABELS[s.id] ?? s.id }}
          </label>
          <label v-if="manifest.tileSets.find((s) => s.id === tileSet)?.overlay" class="opt sep">
            <input type="checkbox" v-model="overlays" /> Detail-Overlays
          </label>
          <div class="faint small">Stand {{ manifest.version }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.map-wrap, .map { position: absolute; inset: 0; }
.controls { position: absolute; right: 12px; bottom: 28px; z-index: 800; display: flex; flex-direction: column; align-items: flex-end; gap: 8px; }
.zoom { display: flex; flex-direction: column; overflow: hidden; }
.zoom button, .layer-btn { width: 38px; height: 38px; border: 0; background: transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text); }
.zoom button:hover, .layer-btn:hover { background: var(--panel-hover); }
.zoom button + button { border-top: 1px solid var(--line); }
.layer-btn { background: var(--panel); }
.layers { position: relative; }
.layer-menu { position: absolute; right: 46px; bottom: 0; width: 250px; padding: 10px 12px; display: flex; flex-direction: column; gap: 6px; }
.layer-menu .label { font-family: var(--font-display); font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
.opt { display: flex; gap: 8px; align-items: center; cursor: pointer; }
.opt input { accent-color: var(--pink); }
.sep { border-top: 1px solid var(--line); padding-top: 6px; }
.small { font-size: 11.5px; }
@media (max-width: 760px) { .controls { bottom: 96px; } }
</style>
