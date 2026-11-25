<script setup lang="ts">
import { Check, Crosshair, Landmark, MapPin, Plus, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import type { Pin, PinIndexItem, PinKind } from '../api/types'
import PinDetail from '../components/PinDetail.vue'
import PinEditor from '../components/PinEditor.vue'
import Sidebar from '../components/Sidebar.vue'
import LeonidaMap from '../map/LeonidaMap.vue'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'

const route = useRoute()
const router = useRouter()
const pins = usePins()
const session = useSession()
const ui = useUi()
const mapRef = ref<InstanceType<typeof LeonidaMap> | null>(null)
const selectedId = computed(() => (route.params.id ? Number(route.params.id) : null))
const menu = ref<{ x: number; y: number; clientX: number; clientY: number } | null>(null)
const placing = ref(false)
const moving = ref<PinIndexItem | null>(null)
const moveTarget = ref<{ x: number; y: number } | null>(null)
const detailKey = ref(0)

onMounted(() => pins.fetchAll().catch(ui.error))

/** Pin auswählen: Karte zentrieren und Detailansicht öffnen (eigene URL /pin/<id>). */
function select(id: number) {
  router.push(`/pin/${id}`)
}
watch([selectedId, () => pins.loaded], ([id, loaded]) => {
  if (!id || !loaded) return
  const p = pins.byId.get(id)
  if (p) mapRef.value?.flyTo(p.x, p.y)
  else ui.error('Diesen Pin gibt es nicht (mehr).')
}, { immediate: true })

function closeDetail() {
  router.push('/')
}

function openEditor(pinId: number | null, x = 0, y = 0, kind: PinKind = 'loot') {
  if (!session.canEdit) {
    if (!session.loggedIn) ui.loginOpen = true
    else ui.passwordOpen = true
    return
  }
  menu.value = null
  ui.pinEditor = { pinId, x, y, kind }
}

function onSaved(pin: Pin) {
  ui.pinEditor = null
  detailKey.value++
  if (selectedId.value !== pin.id) router.push(`/pin/${pin.id}`)
}

function startPlacing() {
  if (!session.canEdit) return openEditor(null)
  placing.value = true
}
function confirmPlacing() {
  const c = mapRef.value!.center()
  placing.value = false
  openEditor(null, c.x, c.y)
}

function startMove(pin: Pin) {
  moving.value = pins.byId.get(pin.id) ?? null
  moveTarget.value = null
  ui.toast('Marker an die neue Position ziehen, dann speichern.')
}
async function saveMove() {
  if (!moving.value || !moveTarget.value) {
    moving.value = null
    return
  }
  try {
    await api.patch(`/api/pins/${moving.value.id}/position`, moveTarget.value)
    await pins.refresh()
    detailKey.value++
    ui.toast('Position gespeichert.', 'ok')
  } catch (e) {
    ui.error(e)
  }
  moving.value = null
}

function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    menu.value = null
    placing.value = false
  }
}
document.addEventListener('keydown', onKey)
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))

async function copyCoords() {
  if (!menu.value) return
  await navigator.clipboard?.writeText(`${menu.value.x.toFixed(1)}, ${menu.value.y.toFixed(1)}`).catch(() => {})
  menu.value = null
  ui.toast('Koordinaten kopiert.')
}
watch(() => ui.sidebarOpen, () => window.setTimeout(() => mapRef.value?.invalidate(), 300))
</script>

<template>
  <div class="map-page" :class="{ placing, 'has-detail': selectedId && !moving }">
    <LeonidaMap ref="mapRef" :selected-id="selectedId" :moving-pin="moving" @select="select" @context="menu = $event" @moved="moveTarget = $event" @click="menu = null" />
    <Sidebar :selected-id="selectedId" @select="select" />
    <PinDetail v-if="selectedId && !moving" :pin-id="selectedId" :reload-key="detailKey" @close="closeDetail" @edit="openEditor($event)" @move="startMove" @deleted="closeDetail" />

    <div v-if="menu" class="ctx panel" :style="{ left: `${menu.clientX}px`, top: `${menu.clientY}px` }" @mouseleave="menu = null">
      <button type="button" @click="openEditor(null, menu.x, menu.y)"><MapPin :size="15" /> Neuer Loot-Pin hier</button>
      <button type="button" @click="openEditor(null, menu.x, menu.y, 'poi')"><Landmark :size="15" /> Neuer Ort (POI) hier</button>
      <button type="button" @click="copyCoords"><Crosshair :size="15" /> Koordinaten kopieren</button>
      <div class="faint small">x {{ menu.x.toFixed(1) }} · y {{ menu.y.toFixed(1) }}</div>
    </div>

    <template v-if="placing">
      <div class="crosshair" aria-hidden="true"><Crosshair :size="44" /></div>
      <div class="bar panel">
        <span>Karte unter das Fadenkreuz schieben</span>
        <button class="btn" type="button" @click="placing = false"><X :size="16" /> Abbrechen</button>
        <button class="btn primary" type="button" @click="confirmPlacing"><Check :size="16" /> Hier setzen</button>
      </div>
    </template>
    <div v-else-if="moving" class="bar panel">
      <span>„{{ moving.t }}“ verschieben</span>
      <button class="btn" type="button" @click="moving = null">Abbrechen</button>
      <button class="btn primary" type="button" :disabled="!moveTarget" @click="saveMove">Position speichern</button>
    </div>
    <button v-else-if="session.loggedIn" class="fab" type="button" aria-label="Neuen Pin anlegen" title="Neuer Pin (Touch)" @click="startPlacing"><Plus :size="26" /></button>

    <PinEditor v-if="ui.pinEditor" :key="ui.pinEditor.pinId ?? 'new'" :pin-id="ui.pinEditor.pinId" :x="ui.pinEditor.x" :y="ui.pinEditor.y" :kind="ui.pinEditor.kind" @close="ui.pinEditor = null" @saved="onSaved" />
  </div>
</template>

<style scoped>
.map-page { position: fixed; inset: 0; }
.ctx { position: fixed; z-index: 1500; min-width: 200px; padding: 4px; display: flex; flex-direction: column; transform: translate(4px, 4px); }
.ctx button { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 0; background: none; border-radius: 6px; cursor: pointer; text-align: left; }
.ctx button:hover { background: var(--panel-hover); }
.ctx .small { padding: 4px 10px 6px; font-size: 11.5px; }
.crosshair { position: absolute; z-index: 950; left: 50%; top: 50%; transform: translate(-50%, -50%); color: var(--orange); pointer-events: none; filter: drop-shadow(0 0 6px rgba(0, 0, 0, 0.8)); }
.bar { position: absolute; z-index: 960; left: 50%; bottom: 24px; transform: translateX(-50%); display: flex; align-items: center; gap: 10px; padding: 10px 12px; flex-wrap: wrap; justify-content: center; max-width: calc(100vw - 24px); }
.fab { position: absolute; z-index: 860; right: 64px; bottom: 28px; width: 54px; height: 54px; border-radius: 50%; border: 0; background: var(--grad); color: #160b12; display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 8px 24px rgba(255, 61, 139, 0.45); }
.has-detail :deep(.controls) { right: 424px; }
.has-detail .fab { right: 476px; }
@media (max-width: 760px) {
  .fab { right: 12px; bottom: 140px; }
  .bar { bottom: 80px; }
  .has-detail :deep(.controls), .has-detail .fab { display: none; }
}
</style>
