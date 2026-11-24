<script setup lang="ts">
import { Check, Copy, Link2, Move, Pencil, Trash2, X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import type { Pin } from '../api/types'
import { formatDate, formatUsd } from '../lib/format'
import { LOOT, sortLoot } from '../lib/lootTypes'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import Gallery from './Gallery.vue'
import MediaImage from './MediaImage.vue'

const props = defineProps<{ pinId: number; reloadKey?: number }>()
const emit = defineEmits<{ close: []; edit: [number]; move: [Pin]; deleted: [] }>()
const session = useSession()
const pins = usePins()
const ui = useUi()
const pin = ref<Pin | null>(null)
const error = ref<string | null>(null)
const copied = ref<string | null>(null)

async function load() {
  error.value = null
  try {
    pin.value = (await api.get<{ pin: Pin }>(`/api/pins/${props.pinId}`)).pin
  } catch (e) {
    pin.value = null
    error.value = e instanceof Error ? e.message : String(e)
  }
}
watch(() => [props.pinId, props.reloadKey], load, { immediate: true })

async function copy(text: string, key: string) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    window.setTimeout(() => (copied.value = null), 1500)
  } catch {
    ui.error('Kopieren nicht möglich – bitte manuell markieren.')
  }
}
const link = () => `${window.location.origin}/pin/${props.pinId}`

async function remove() {
  if (!pin.value || !window.confirm(`Pin „${pin.value.title}“ wirklich löschen? Bilder werden ebenfalls gelöscht.`)) return
  try {
    await api.delete(`/api/pins/${pin.value.id}`)
    await pins.refresh()
    ui.toast('Pin gelöscht.', 'ok')
    emit('deleted')
  } catch (e) {
    ui.error(e)
  }
}
const statusClass = { confirmed: 'ok', unconfirmed: 'warn', gone: 'gone' } as const
defineExpose({ reload: load })
</script>

<template>
  <aside class="detail panel" aria-label="Pin-Details">
    <header>
      <div v-if="pin" class="types">
        <span v-for="t in sortLoot(pin.loot_types)" :key="t" class="type" :style="{ '--c': LOOT[t].color }"><span v-html="LOOT[t].svg" />{{ LOOT[t].label }}</span>
      </div>
      <button class="btn ghost icon" type="button" aria-label="Schließen" @click="emit('close')"><X :size="18" /></button>
    </header>
    <div class="body scroll">
      <div v-if="error" class="error-text">{{ error }}</div>
      <div v-else-if="!pin" class="muted">Lade …</div>
      <template v-else>
        <h2 class="title">{{ pin.title }}</h2>
        <div class="badges">
          <span class="badge" :class="statusClass[pin.status]">{{ session.label('pin_status', pin.status) }}</span>
          <span class="badge">Respawn: {{ session.label('respawn', pin.respawn) }}</span>
        </div>

        <div v-if="session.canEdit" class="actions">
          <button class="btn sm" type="button" @click="emit('edit', pin.id)"><Pencil :size="14" /> Bearbeiten</button>
          <button class="btn sm" type="button" @click="emit('move', pin)"><Move :size="14" /> Verschieben</button>
          <button class="btn sm danger" type="button" @click="remove"><Trash2 :size="14" /> Löschen</button>
        </div>

        <p v-if="pin.description" class="desc">{{ pin.description }}</p>
        <div v-if="pin.requirements" class="req"><span class="label">Voraussetzungen / Hinweise</span><p>{{ pin.requirements }}</p></div>

        <div v-if="pin.drugs_value_usd !== null || pin.jewelry_value_usd !== null || pin.cash_usd !== null" class="values">
          <div v-if="pin.loot_types.includes('drugs')"><span class="label">Drogen</span><strong>{{ formatUsd(pin.drugs_value_usd) }}</strong></div>
          <div v-if="pin.loot_types.includes('jewelry')"><span class="label">Schmuck</span><strong>{{ formatUsd(pin.jewelry_value_usd) }}</strong></div>
          <div v-if="pin.loot_types.includes('cash')"><span class="label">Geld</span><strong>{{ formatUsd(pin.cash_usd) }}</strong></div>
        </div>

        <template v-if="pin.vehicles.length">
          <div class="section-title">Fahrzeuge</div>
          <RouterLink v-for="v in pin.vehicles" :key="v.id" :to="`/fahrzeuge/${v.id}`" class="item">
            <MediaImage :media="v.image" thumb :alt="v.name" class="thumb" />
            <span><strong>{{ v.name }}</strong><small class="muted">{{ [v.manufacturer?.name, v.vehicle_class?.name].filter(Boolean).join(' · ') }}</small></span>
          </RouterLink>
        </template>
        <template v-if="pin.weapons.length">
          <div class="section-title">Waffen</div>
          <RouterLink v-for="w in pin.weapons" :key="w.id" :to="`/waffen/${w.id}`" class="item">
            <MediaImage :media="w.image" thumb :alt="w.name" class="thumb" />
            <span><strong>{{ w.name }}</strong><small class="muted">{{ w.weapon_type?.name }}</small></span>
          </RouterLink>
        </template>

        <template v-if="pin.codes.length">
          <div class="section-title">Codes</div>
          <div v-for="(c, i) in pin.codes" :key="i" class="code">
            <div>
              <span class="label">{{ c.label || 'Code' }}</span>
              <code>{{ c.code }}</code>
              <small v-if="c.description" class="muted">{{ c.description }}</small>
            </div>
            <button class="btn sm icon" type="button" :aria-label="`Code ${c.code} kopieren`" @click="copy(c.code, `c${i}`)">
              <Check v-if="copied === `c${i}`" :size="15" /><Copy v-else :size="15" />
            </button>
          </div>
        </template>

        <template v-if="pin.images.length">
          <div class="section-title">Bilder</div>
          <Gallery :images="pin.images" />
        </template>

        <footer class="meta faint">
          <button class="btn ghost sm" type="button" @click="copy(link(), 'link')"><Check v-if="copied === 'link'" :size="14" /><Link2 v-else :size="14" /> Link kopieren</button>
          <div>Erstellt {{ formatDate(pin.created_at) }}<template v-if="pin.created_by"> von {{ pin.created_by }}</template></div>
          <div>Bearbeitet {{ formatDate(pin.updated_at) }}<template v-if="pin.updated_by"> von {{ pin.updated_by }}</template></div>
          <div>x {{ pin.x.toFixed(1) }} · y {{ pin.y.toFixed(1) }}</div>
        </footer>
      </template>
    </div>
  </aside>
</template>

<style scoped>
.detail { position: absolute; z-index: 900; top: calc(var(--header-h) + 16px); right: 12px; bottom: 12px; width: 400px; display: flex; flex-direction: column; animation: fade-in 0.18s ease-out; }
header { display: flex; align-items: center; justify-content: space-between; padding: 8px 8px 0 16px; gap: 8px; }
.types { display: flex; flex-wrap: wrap; gap: 10px; }
.type { display: inline-flex; align-items: center; gap: 5px; color: var(--c); font-family: var(--font-display); font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-size: 13px; }
.type :deep(svg) { width: 15px; height: 15px; }
.body { padding: 4px 16px 16px; flex: 1; }
.title { font-size: 30px; line-height: 1.05; }
.badges { display: flex; gap: 6px; margin: 8px 0; flex-wrap: wrap; }
.actions { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.desc { white-space: pre-wrap; margin: 10px 0; }
.req { border-left: 3px solid var(--orange); padding: 6px 10px; background: rgba(255, 138, 61, 0.07); border-radius: 0 6px 6px 0; margin: 10px 0; }
.req p { margin: 2px 0 0; white-space: pre-wrap; }
.label { font-family: var(--font-display); font-weight: 600; font-size: 12.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
.values { display: flex; gap: 18px; flex-wrap: wrap; margin: 12px 0; }
.values div { display: flex; flex-direction: column; }
.values strong { font-family: var(--font-display); font-size: 24px; }
.item { display: flex; align-items: center; gap: 10px; padding: 6px; border-radius: 8px; text-decoration: none; }
.item:hover { background: var(--panel-hover); }
.item span { display: flex; flex-direction: column; }
.thumb { width: 72px !important; height: 40px !important; border-radius: 5px; flex: none; background: rgba(0,0,0,0.3); }
.code { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--line); }
.code > div { display: flex; flex-direction: column; }
.code code { font-family: ui-monospace, Menlo, monospace; font-size: 20px; letter-spacing: 0.12em; }
.meta { margin-top: 18px; font-size: 12px; display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
@media (max-width: 760px) {
  .detail { top: auto; left: 0; right: 0; bottom: 0; width: auto; max-height: 78vh; border-radius: 16px 16px 0 0; }
}
</style>
