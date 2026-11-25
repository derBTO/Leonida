<script setup lang="ts">
import { ArrowLeft, Eye, EyeOff, Map as MapIcon, MapPin, Pencil, RotateCcw, Trash2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import type { Trail, TrailStep } from '../api/types'
import RichText from '../components/RichText.vue'
import TrailForm from '../components/TrailForm.vue'
import { formatDate } from '../lib/format'
import { HINT } from '../lib/hint'
import { POI } from '../lib/poiTypes'
import { useSession } from '../stores/session'
import { useTrails } from '../stores/trails'
import { useUi } from '../stores/ui'

/** Eine Spur mit allen Schritten, eigenem Fortschritt und Spoiler-Schutz. */
const props = defineProps<{ id: string }>()
const session = useSession()
const trails = useTrails()
const ui = useUi()
const router = useRouter()
const trail = ref<Trail | null>(null)
const error = ref<string | null>(null)
const editing = ref(false)

async function load() {
  error.value = null
  try {
    trail.value = (await api.get<{ item: Trail }>(`/api/trails/${props.id}`)).item
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}
watch(() => [props.id, session.user?.id], load, { immediate: true })

const labels = computed(() => {
  const steps = trail.value?.steps ?? []
  return steps.map((s, i) => {
    const same = steps.filter((x) => x.position === s.position)
    return same.length > 1 ? `${s.position}${String.fromCharCode(97 + steps.slice(0, i).filter((x) => x.position === s.position).length)}` : String(s.position)
  })
})
const hidden = (s: TrailStep) => (trail.value ? trails.isHidden(trail.value, s.id) : false)
const done = computed(() => trail.value?.steps.filter((s) => s.done).length ?? 0)

async function toggle(s: TrailStep) {
  if (!trail.value) return
  try {
    const next = await trails.setDone(trail.value.id, s.id, !s.done)
    if (next) trail.value = next
  } catch (e) {
    ui.error(e)
  }
}
async function reset() {
  if (!trail.value || !window.confirm('Eigenen Fortschritt dieser Spur zurücksetzen?')) return
  try {
    trail.value = (await api.delete<{ item: Trail }>(`/api/trails/${trail.value.id}/progress`)).item
    if (trails.activeId === trail.value.id) await trails.reload()
    else trails.version++
  } catch (e) {
    ui.error(e)
  }
}
function showOnMap(step?: TrailStep) {
  if (!trail.value) return
  router.push({ path: step?.pin ? `/pin/${step.pin.id}` : '/', query: { spur: String(trail.value.id), ...(step ? { schritt: String(step.id) } : {}) } })
}
async function remove() {
  if (!trail.value || !window.confirm(`Spur „${trail.value.name}“ löschen? Die Pins bleiben erhalten.`)) return
  try {
    await api.delete(`/api/trails/${trail.value.id}`)
    if (trails.activeId === trail.value.id) trails.clear()
    trails.version++
    ui.toast('Spur gelöscht.', 'ok')
    router.push('/spuren')
  } catch (e) {
    ui.error(e)
  }
}
async function saved(t: Trail) {
  editing.value = false
  trail.value = t
  if (trails.activeId === t.id) await trails.reload()
  else trails.version++
}
const pinColor = (s: TrailStep) => (s.pin?.kind === 'hint' ? HINT.color : s.pin?.kind === 'poi' ? POI[s.pin.poi_type ?? 'poi'].color : 'var(--pink)')
const pinKind = (s: TrailStep) => (s.pin?.kind === 'hint' ? 'Hinweis' : s.pin?.kind === 'poi' ? POI[s.pin.poi_type ?? 'poi'].label : 'Loot')
</script>

<template>
  <div class="page">
    <div class="wrap">
      <RouterLink to="/spuren" class="back muted"><ArrowLeft :size="16" /> Alle Spuren</RouterLink>
      <div v-if="error" class="error-text">{{ error }}</div>
      <div v-else-if="!trail" class="muted">Lade …</div>
      <template v-else>
        <div class="panel box">
          <div class="title-row">
            <h1>{{ trail.name }}</h1>
            <button class="btn primary" type="button" :disabled="!trail.steps.some((s) => s.pin)" @click="showOnMap()"><MapIcon :size="16" /> Auf Karte zeigen</button>
          </div>
          <p v-if="trail.description" class="desc"><RichText :text="trail.description" /></p>
          <div class="toolbar">
            <span v-if="session.loggedIn" class="progress"><strong>{{ done }}</strong> von {{ trail.steps.length }} erledigt</span>
            <span v-else class="faint">Anmelden, um den eigenen Fortschritt abzuhaken.</span>
            <label class="toggle" title="Spätere Schritte erst nach dem Abhaken zeigen">
              <input type="checkbox" :checked="trails.spoiler" @change="trails.setSpoiler(($event.target as HTMLInputElement).checked)" />
              <EyeOff :size="15" /> Spoiler-Schutz
            </label>
            <button v-if="session.loggedIn && done" class="btn ghost sm" type="button" @click="reset"><RotateCcw :size="14" /> Fortschritt zurücksetzen</button>
          </div>

          <ol class="steps">
            <li v-for="(s, i) in trail.steps" :key="s.id" class="step" :class="{ done: s.done, parallel: i > 0 && trail.steps[i - 1].position === s.position }">
              <button class="num" type="button" :aria-pressed="s.done" :disabled="hidden(s)" :title="s.done ? 'Als offen markieren' : 'Als erledigt markieren'" @click="toggle(s)">
                {{ s.done ? '✓' : labels[i] }}
              </button>
              <div v-if="hidden(s)" class="body covered">
                <span class="faint">Schritt {{ labels[i] }} ist verdeckt.</span>
                <button class="btn ghost sm" type="button" @click="trails.reveal(s.id)"><Eye :size="14" /> Aufdecken</button>
              </div>
              <div v-else class="body">
                <strong>{{ s.title }}</strong>
                <p v-if="s.description" class="sdesc muted"><RichText :text="s.description" /></p>
                <button v-if="s.pin" class="pin" type="button" @click="showOnMap(s)">
                  <MapPin :size="14" :style="{ color: pinColor(s) }" /> {{ s.pin.title }} <small class="faint">{{ pinKind(s) }}</small>
                </button>
                <span v-else class="faint small">ohne Ort</span>
              </div>
            </li>
          </ol>
          <p v-if="!trail.steps.length" class="empty">Noch keine Schritte.</p>

          <div v-if="session.canEdit" class="actions">
            <button class="btn" type="button" @click="editing = true"><Pencil :size="15" /> Bearbeiten</button>
            <button class="btn danger" type="button" @click="remove"><Trash2 :size="15" /> Löschen</button>
          </div>
          <div class="meta faint">
            Erstellt {{ formatDate(trail.created_at) }}<template v-if="trail.created_by"> von {{ trail.created_by }}</template> ·
            bearbeitet {{ formatDate(trail.updated_at) }}<template v-if="trail.updated_by"> von {{ trail.updated_by }}</template>
          </div>
        </div>
      </template>
    </div>
    <TrailForm v-if="editing && trail" :trail="trail" @close="editing = false" @saved="saved" />
  </div>
</template>

<style scoped>
@import './page.css';
.wrap { max-width: 820px !important; }
.back { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; margin-bottom: 14px; }
.box { padding: 20px; }
.title-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.title-row h1 { margin: 0; }
.desc { white-space: pre-wrap; margin: 10px 0; }
.toolbar { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin: 14px 0 6px; font-size: 14px; }
.progress strong { color: #4ade80; }
.toggle { display: flex; align-items: center; gap: 6px; color: var(--muted); cursor: pointer; }
.toggle input { accent-color: var(--pink); }
.steps { list-style: none; padding: 0; margin: 12px 0; display: flex; flex-direction: column; }
.step { display: flex; gap: 14px; padding: 10px 0; position: relative; }
.step + .step::before { content: ''; position: absolute; left: 16px; top: -10px; height: 20px; border-left: 2px dashed var(--line-strong); }
.step.parallel { margin-left: 30px; }
.step.parallel::before { left: -14px; border-left: 0; border-top: 2px dashed var(--line-strong); width: 26px; height: 0; top: 26px; }
.num { width: 34px; height: 34px; flex: none; border-radius: 50%; border: 2px solid #fff; background: var(--grad); color: #160b12; font-weight: 800; cursor: pointer; }
.num:disabled { background: #3a3648; color: #fff; cursor: default; }
.step.done .num { background: #4ade80; }
.step.done .body strong { text-decoration: line-through; text-decoration-color: rgba(255,255,255,0.35); color: var(--muted); }
.body { display: flex; flex-direction: column; gap: 3px; min-width: 0; padding-top: 6px; }
.covered { flex-direction: row; align-items: center; gap: 10px; }
.sdesc { margin: 0; white-space: pre-wrap; font-size: 14px; }
.pin { display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; padding: 3px 8px; border: 1px solid var(--line); border-radius: 6px; background: none; cursor: pointer; font-size: 13.5px; }
.pin:hover { background: var(--panel-hover); }
.small { font-size: 12.5px; }
.actions { display: flex; gap: 8px; margin-top: 12px; }
.meta { font-size: 12px; margin-top: 10px; }
</style>
