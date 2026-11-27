<script setup lang="ts">
import { Car, Check, Copy, Crosshair, Link2, Move, Pencil, Puzzle, Trash2 } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import type { Pin } from '../api/types'
import { EGG } from '../lib/easteregg'
import { formatDate, formatUsd } from '../lib/format'
import { LOOT, sortLoot } from '../lib/lootTypes'
import { HINT } from '../lib/hint'
import { POI, poiStyle } from '../lib/poiTypes'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useTrails } from '../stores/trails'
import { useUi } from '../stores/ui'
import Gallery from './Gallery.vue'
import LegendaryStar from './LegendaryStar.vue'
import MediaImage from './MediaImage.vue'
import Modal from './Modal.vue'
import RichText from './RichText.vue'

const props = defineProps<{ pinId: number; reloadKey?: number }>()
const emit = defineEmits<{ close: []; edit: [number]; move: [Pin]; deleted: [] }>()
const session = useSession()
const pins = usePins()
const ui = useUi()
const trails = useTrails()
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
watch(() => [props.pinId, props.reloadKey, trails.version], load, { immediate: true })

/** Spur auf der Karte zeigen, mit diesem Pin als aktuellem Schritt. */
async function showTrail(trailId: number, stepId: number) {
  try {
    await trails.activate(trailId, stepId)
    trails.fitRequest++
  } catch (e) {
    ui.error(e)
  }
}

/** Bild eines Hinweis-Schritts in voller Größe in einem neuen Tab öffnen */
function openImage(url: string) {
  window.open(url, '_blank', 'noopener')
}

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
  if (!pin.value || !window.confirm(`${pin.value.kind === 'poi' ? 'Ort' : pin.value.kind === 'hint' ? 'Hinweis' : 'Pin'} „${pin.value.title}“ wirklich löschen? Bilder werden ebenfalls gelöscht.`)) return
  try {
    await api.delete(`/api/pins/${pin.value.id}`)
    await pins.refresh()
    ui.toast('Gelöscht.', 'ok')
    emit('deleted')
  } catch (e) {
    ui.error(e)
  }
}
const statusClass = { confirmed: 'ok', unconfirmed: 'warn', gone: 'gone' } as const
defineExpose({ reload: load })
</script>

<template>
  <Modal :title="pin?.title ?? (error ? 'Pin' : 'Lade …')" width="760px" :fill="pin?.kind === 'hint'" @close="emit('close')">
    <div class="head-row">
      <span v-if="pin?.is_easteregg" class="type egg" :style="{ '--c': EGG.color }"><span v-html="EGG.svg" />{{ EGG.label }}</span>
      <div v-if="pin && pin.kind === 'poi'" class="types">
        <span class="type" :style="poiStyle(pin.poi_type ?? 'poi')"><span class="sq" v-html="POI[pin.poi_type ?? 'poi'].svg" />{{ POI[pin.poi_type ?? 'poi'].label }}</span>
      </div>
      <div v-else-if="pin && pin.kind === 'hint'" class="types">
        <span class="type" :style="{ '--c': HINT.color }"><span v-html="HINT.svg" />{{ HINT.label }}</span>
      </div>
      <div v-else-if="pin" class="types">
        <span v-for="t in sortLoot(pin.loot_types)" :key="t" class="type" :style="{ '--c': LOOT[t].color }"><span v-html="LOOT[t].svg" />{{ LOOT[t].label }}</span>
      </div>
    </div>
      <div v-if="error" class="error-text">{{ error }}</div>
      <div v-else-if="!pin" class="muted">Lade …</div>
      <!-- Hinweis: Kopf (Titel bis Verknüpfungen) bleibt stehen, nur die Schritte scrollen -->
      <div v-else-if="pin.kind === 'hint'" class="hint-layout">
        <div class="hint-head scroll">
          <div v-if="session.canModify(pin.created_by_id)" class="actions">
            <button class="btn sm" type="button" @click="emit('edit', pin.id)"><Pencil :size="14" /> Bearbeiten</button>
            <button class="btn sm" type="button" @click="emit('move', pin)"><Move :size="14" /> Verschieben</button>
            <button class="btn sm danger" type="button" @click="remove"><Trash2 :size="14" /> Löschen</button>
          </div>
          <div v-if="pin.trails.length" class="trails">
            <div v-for="t in pin.trails" :key="t.step_id" class="trail" :class="{ active: trails.activeId === t.trail_id }">
              <Puzzle :size="16" />
              <span class="t-text">
                <small class="faint">Schritt {{ t.position }} von {{ t.step_count }}</small>
                <RouterLink :to="`/puzzle/${t.trail_id}`">{{ t.trail_name }}</RouterLink>
              </span>
              <button v-if="trails.activeId !== t.trail_id" class="btn sm" type="button" @click="showTrail(t.trail_id, t.step_id)">Auf Karte</button>
            </div>
          </div>
          <p v-if="pin.description" class="desc"><RichText :text="pin.description" /></p>
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
          <div v-if="pin.place || pin.vehicles.length || pin.weapons.length" class="links">
            <RouterLink v-if="pin.place" :to="`/pin/${pin.place.id}`" class="link-chip" :style="poiStyle(pin.place.poi_type ?? 'poi')">
              <span class="sq" v-html="POI[pin.place.poi_type ?? 'poi'].svg" />{{ pin.place.title }}
            </RouterLink>
            <RouterLink v-for="v in pin.vehicles" :key="`v${v.id}`" :to="`/fahrzeuge/${v.id}`" class="link-chip"><Car :size="14" />{{ v.name }}</RouterLink>
            <RouterLink v-for="w in pin.weapons" :key="`w${w.id}`" :to="`/waffen/${w.id}`" class="link-chip" :class="{ 'legendary-name': w.is_legendary }">
              <Crosshair :size="14" />{{ w.name }}<LegendaryStar v-if="w.is_legendary" :size="13" />
            </RouterLink>
          </div>
        </div>
        <div class="hint-steps scroll">
          <section v-for="(s, i) in pin.steps" :key="s.id" class="hstep">
            <div class="hstep-head"><span class="hnum">{{ i + 1 }}</span><hr /></div>
            <img v-if="s.image" :src="s.image.url" :alt="s.text ?? `Bild zu Schritt ${i + 1}`" class="himg" loading="lazy" @click="openImage(s.image.url)" />
            <p v-if="s.text" class="htext"><RichText :text="s.text" /></p>
          </section>
          <p v-if="!pin.steps.length" class="faint">Noch keine Schritte.</p>
          <footer class="meta faint">
            <button class="btn ghost sm" type="button" @click="copy(link(), 'link')"><Check v-if="copied === 'link'" :size="14" /><Link2 v-else :size="14" /> Link kopieren</button>
            <div>Erstellt {{ formatDate(pin.created_at) }}<template v-if="pin.created_by"> von {{ pin.created_by }}</template></div>
            <div>Bearbeitet {{ formatDate(pin.updated_at) }}<template v-if="pin.updated_by"> von {{ pin.updated_by }}</template></div>
          </footer>
        </div>
      </div>
      <template v-else>
        <div v-if="pin.kind === 'loot'" class="badges">
          <span class="badge" :class="statusClass[pin.status]">{{ session.label('pin_status', pin.status) }}</span>
          <span class="badge">Respawn: {{ session.label('respawn', pin.respawn) }}</span>
        </div>

        <div v-if="session.canModify(pin.created_by_id)" class="actions">
          <button class="btn sm" type="button" @click="emit('edit', pin.id)"><Pencil :size="14" /> Bearbeiten</button>
          <button class="btn sm" type="button" @click="emit('move', pin)"><Move :size="14" /> Verschieben</button>
          <button class="btn sm danger" type="button" @click="remove"><Trash2 :size="14" /> Löschen</button>
        </div>

        <div v-if="pin.trails.length" class="trails">
          <div v-for="t in pin.trails" :key="t.step_id" class="trail" :class="{ active: trails.activeId === t.trail_id }">
            <Puzzle :size="16" />
            <span class="t-text">
              <small class="faint">Schritt {{ t.position }} von {{ t.step_count }}</small>
              <RouterLink :to="`/puzzle/${t.trail_id}`">{{ t.trail_name }}</RouterLink>
            </span>
            <button v-if="trails.activeId !== t.trail_id" class="btn sm" type="button" @click="showTrail(t.trail_id, t.step_id)">Auf Karte</button>
          </div>
        </div>

        <p v-if="pin.description" class="desc"><RichText :text="pin.description" /></p>
        <template v-if="pin.hints.length">
          <div class="section-title">Hinweise zu diesem Ort</div>
          <RouterLink v-for="h in pin.hints" :key="h.id" :to="`/pin/${h.id}`" class="link-chip" :style="{ '--c': HINT.color }"><span class="hint-dot" v-html="HINT.svg" />{{ h.title }}</RouterLink>
        </template>
        <div v-if="pin.requirements" class="req"><span class="label">Voraussetzungen / Hinweise</span><p><RichText :text="pin.requirements" /></p></div>

        <div v-if="pin.images.length" class="gallery">
          <Gallery :images="pin.images" />
        </div>

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
          <div class="section-title">{{ pin.kind === 'poi' ? 'Exklusive Waffen' : 'Waffen' }}</div>
          <RouterLink v-for="w in pin.weapons" :key="w.id" :to="`/waffen/${w.id}`" class="item">
            <MediaImage :media="w.image" thumb :alt="w.name" class="thumb" />
            <span><strong :class="{ 'legendary-name': w.is_legendary }">{{ w.name }}<LegendaryStar v-if="w.is_legendary" :size="14" /></strong><small class="muted">{{ w.weapon_type?.name }}</small></span>
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

        <footer class="meta faint">
          <button class="btn ghost sm" type="button" @click="copy(link(), 'link')"><Check v-if="copied === 'link'" :size="14" /><Link2 v-else :size="14" /> Link kopieren</button>
          <div>Erstellt {{ formatDate(pin.created_at) }}<template v-if="pin.created_by"> von {{ pin.created_by }}</template></div>
          <div>Bearbeitet {{ formatDate(pin.updated_at) }}<template v-if="pin.updated_by"> von {{ pin.updated_by }}</template></div>
          <div>x {{ pin.x.toFixed(1) }} · y {{ pin.y.toFixed(1) }}</div>
        </footer>
      </template>
  </Modal>
</template>

<style scoped>
.head-row { display: flex; flex-wrap: wrap; align-items: center; gap: 12px; margin-bottom: 4px; }
.head-row:empty { display: none; }
.types { display: flex; flex-wrap: wrap; gap: 10px; }
.type.egg { padding: 2px 8px 2px 4px; border: 1px solid var(--c); border-radius: 999px; background: rgba(163, 230, 53, 0.08); }
.gallery { margin: 14px 0; }
.hint-layout { display: flex; flex-direction: column; min-height: 0; flex: 1; }
.hint-head { flex: none; max-height: 45%; padding-bottom: 10px; border-bottom: 1px solid var(--line); }
.hint-steps { flex: 1; min-height: 0; padding: 6px 2px 16px; }
.links { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
.link-chip { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px 4px 6px; border: 1px solid var(--line-strong); border-radius: 999px; text-decoration: none; font-size: 13.5px; margin: 0 6px 6px 0; }
.link-chip:hover { background: var(--panel-hover); }
.link-chip .sq { width: 18px; height: 18px; }
.hint-dot { display: inline-flex; color: var(--c); }
.hint-dot :deep(svg) { width: 14px; height: 14px; }
.hstep { margin-top: 14px; }
.hstep-head { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
.hstep-head hr { flex: 1; border: 0; border-top: 1px solid var(--line-strong); margin: 0; }
.hnum { font-family: var(--font-display); font-weight: 800; font-size: 22px; color: #2dd4bf; min-width: 18px; }
.himg { display: block; max-width: 100%; max-height: 60vh; margin: 0 auto 8px; border-radius: 8px; cursor: zoom-in; }
.htext { white-space: pre-wrap; margin: 0; }
.type { display: inline-flex; align-items: center; gap: 5px; color: var(--c); font-family: var(--font-display); font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; font-size: 13px; }
.type :deep(svg) { width: 15px; height: 15px; }
.sq { display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px; border-radius: 5px; background: var(--c); color: var(--fg, #fff); }
.sq :deep(svg) { width: 13px; height: 13px; }
.badges { display: flex; gap: 6px; margin: 8px 0; flex-wrap: wrap; }
.actions { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.desc { white-space: pre-wrap; margin: 10px 0; }
.trails { display: flex; flex-direction: column; gap: 4px; margin: 10px 0; }
.trail { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; border: 1px solid var(--line); color: var(--pink); }
.trail.active { border-color: var(--pink); background: rgba(255, 61, 139, 0.08); }
.t-text { flex: 1; display: flex; flex-direction: column; min-width: 0; color: var(--text); }
.t-text a { text-decoration: none; font-weight: 600; }
.req { border-left: 3px solid var(--orange); padding: 6px 10px; background: rgba(255, 138, 61, 0.07); border-radius: 0 6px 6px 0; margin: 10px 0; }
.req p { margin: 2px 0 0; white-space: pre-wrap; }
.label { font-family: var(--font-display); font-weight: 600; font-size: 12.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); }
.values { display: flex; gap: 18px; flex-wrap: wrap; margin: 12px 0; }
.values div { display: flex; flex-direction: column; }
.values strong { font-family: var(--font-display); font-size: 24px; }
.item { display: flex; align-items: center; gap: 10px; padding: 6px; border-radius: 8px; text-decoration: none; }
.item:hover { background: var(--panel-hover); }
.item span { display: flex; flex-direction: column; }
.item strong { display: inline-flex; align-items: center; }
.thumb { width: 72px !important; height: 40px !important; border-radius: 5px; flex: none; background: rgba(0,0,0,0.3); }
.code { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 6px 0; border-bottom: 1px solid var(--line); }
.code > div { display: flex; flex-direction: column; }
.code code { font-family: ui-monospace, Menlo, monospace; font-size: 20px; letter-spacing: 0.12em; }
.meta { margin-top: 18px; font-size: 12px; display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
</style>
