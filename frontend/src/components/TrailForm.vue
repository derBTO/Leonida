<script setup lang="ts">
import { ArrowDown, ArrowUp, MapPinPlus, Plus, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { Trail, VehicleSummary, WeaponSummary } from '../api/types'
import { useRoute, useRouter } from 'vue-router'
import { usePins } from '../stores/pins'
import { useTrails, type TrailDraft, type TrailDraftStep } from '../stores/trails'
import { useUi } from '../stores/ui'
import Modal from './Modal.vue'
import CatalogPicker from './CatalogPicker.vue'
import PinPicker from './PinPicker.vue'

/** Spur anlegen/bearbeiten: Name, Beschreibung und geordnete Schritte (optional parallel). */
const props = defineProps<{ trail?: Trail | null; draft?: TrailDraft | null }>()
const emit = defineEmits<{ close: []; saved: [Trail] }>()
const ui = useUi()
const pins = usePins()
const trails = useTrails()
const route = useRoute()
const router = useRouter()
const busy = ref(false)
const errors = ref<Record<string, string[]>>({})

type StepRow = TrailDraftStep
let seq = Math.max(0, ...(props.draft?.steps.map((s) => s.key) ?? []))
const row = (o: Partial<StepRow> = {}): StepRow => ({ key: ++seq, id: null, title: '', description: '', pin_id: null, parallel: false, ...o })

const fromTrail = () => ({
  name: props.trail?.name ?? '',
  description: props.trail?.description ?? '',
  weapons: (props.trail?.weapons ?? []) as WeaponSummary[],
  vehicles: (props.trail?.vehicles ?? []) as VehicleSummary[],
  steps: props.trail
    ? props.trail.steps.map((s, i, all) => row({ id: s.id, title: s.title, description: s.description ?? '', pin_id: s.pin?.id ?? null, parallel: i > 0 && all[i - 1].position === s.position }))
    : [row(), row()],
})
const form = reactive(props.draft
  ? { name: props.draft.name, description: props.draft.description, weapons: props.draft.weapons, vehicles: props.draft.vehicles, steps: props.draft.steps }
  : fromTrail())
// Nach dem Pin-Setzen gilt der Entwurf als ungespeicherte Änderung
const initial = props.draft ? '' : JSON.stringify(form)
const dirty = computed(() => JSON.stringify(form) !== initial)
onMounted(() => { if (!pins.loaded) pins.fetchAll().catch(ui.error) })

/** Schrittnummern wie auf dem Server: parallel = gleiche Nummer wie der vorige. */
const numbers = computed(() => {
  let n = 0
  const raw = form.steps.map((s, i) => (n = s.parallel && i > 0 ? n : n + 1))
  return raw.map((num, i) => {
    const same = raw.filter((x) => x === num).length
    return same > 1 ? `${num}${String.fromCharCode(97 + raw.slice(0, i).filter((x) => x === num).length)}` : String(num)
  })
})

function move(i: number, d: number) {
  const j = i + d
  if (j < 0 || j >= form.steps.length) return
  const s = [...form.steps]
  ;[s[i], s[j]] = [s[j], s[i]]
  form.steps = s
  form.steps[0].parallel = false
}
function remove(i: number) {
  form.steps.splice(i, 1)
  if (form.steps[0]) form.steps[0].parallel = false
}
const err = (k: string) => errors.value[k]?.[0]

function close() {
  trails.draft = null
  emit('close')
}

/** Für diesen Schritt einen neuen Pin auf der Karte setzen; der Editor öffnet sich danach wieder. */
function placeOnMap(s: StepRow) {
  if (!s.title.trim()) return ui.error('Bitte zuerst beschreiben, was in diesem Schritt passiert.')
  trails.draft = {
    trailId: props.trail?.id ?? null,
    name: form.name,
    description: form.description,
    weapons: [...form.weapons],
    vehicles: [...form.vehicles],
    steps: form.steps.map((x) => ({ ...x })),
    stepKey: s.key,
    returnPath: route.path,
    resume: false,
  }
  router.push('/')
}

async function save() {
  busy.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    description: form.description,
    weapon_ids: form.weapons.map((w) => w.id),
    vehicle_ids: form.vehicles.map((v) => v.id),
    steps: form.steps.map((s, i) => ({ id: s.id, title: s.title, description: s.description, pin_id: s.pin_id, parallel: i > 0 && s.parallel })),
  }
  try {
    const trail = props.trail
      ? (await api.put<{ item: Trail }>(`/api/trails/${props.trail.id}`, payload)).item
      : (await api.post<{ item: Trail }>('/api/trails', payload)).item
    ui.toast(props.trail ? 'Puzzle gespeichert.' : 'Puzzle angelegt.', 'ok')
    trails.draft = null
    emit('saved', trail)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.errors
    ui.error(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal :title="trail ? 'Puzzle bearbeiten' : 'Neues Puzzle'" width="820px" :dirty="dirty" @close="close">
    <form id="trail-form" @submit.prevent="save">
      <div class="field">
        <label for="tf-name">Name *</label>
        <input id="tf-name" class="input" v-model="form.name" maxlength="150" required placeholder="z. B. Das Erbe von Lyle" />
        <div v-if="err('name')" class="error-text">{{ err('name') }}</div>
      </div>
      <div class="field block">
        <label for="tf-desc">Beschreibung</label>
        <textarea id="tf-desc" class="input small" v-model="form.description" maxlength="20000" placeholder="Worum geht es, wie wird das Puzzle ausgelöst?" />
      </div>

      <div class="section-title">Belohnung</div>
      <div class="field">
        <div class="label">Fahrzeuge</div>
        <CatalogPicker kind="vehicles" v-model="form.vehicles" />
      </div>
      <div class="field block">
        <div class="label">Waffen</div>
        <CatalogPicker kind="weapons" v-model="form.weapons" />
      </div>

      <div class="section-title">Schritte</div>
      <p class="faint info">Jeder Schritt zeigt auf einen Hinweis. Fehlt er noch, legt „Hinweis auf Karte setzen“ ihn an; danach geht es hier weiter. „Parallel“ = gleiche Nummer wie der Schritt darüber (Reihenfolge egal). Ein Schritt ohne Hinweis ist erlaubt, z. B. „Auf Anruf warten“.</p>
      <ol class="steps">
        <li v-for="(s, i) in form.steps" :key="s.key" class="step" :class="{ parallel: s.parallel && i > 0 }">
          <div class="num">{{ numbers[i] }}</div>
          <div class="fields">
            <input class="input" v-model="s.title" maxlength="200" required :placeholder="i === form.steps.length - 1 ? 'z. B. Loot im Versteck holen' : 'Was passiert hier? z. B. Moses treffen'" :aria-label="`Schritt ${numbers[i]}: Name`" />
            <div v-if="err(`steps.${i}.title`)" class="error-text">{{ err(`steps.${i}.title`) }}</div>
            <textarea class="input small" v-model="s.description" maxlength="5000" placeholder="Beschreibung (optional)" :aria-label="`Schritt ${numbers[i]}: Beschreibung`" />
            <div class="pin-row">
              <PinPicker :id="`tf-pin-${s.key}`" v-model="s.pin_id" :kinds="['hint']" placeholder="Hinweis suchen … (leer = ohne Hinweis)" class="grow" />
              <button v-if="!s.pin_id" class="btn" type="button" title="Neuen Hinweis auf der Karte setzen und hier eintragen" @click="placeOnMap(s)"><MapPinPlus :size="15" /> Hinweis auf Karte setzen</button>
            </div>
            <div v-if="err(`steps.${i}.pin_id`)" class="error-text">{{ err(`steps.${i}.pin_id`) }}</div>
            <label v-if="i > 0" class="par"><input type="checkbox" v-model="s.parallel" /> parallel zu Schritt {{ numbers[i - 1] }}</label>
          </div>
          <div class="tools">
            <button class="btn ghost sm icon" type="button" aria-label="Nach oben" :disabled="i === 0" @click="move(i, -1)"><ArrowUp :size="15" /></button>
            <button class="btn ghost sm icon" type="button" aria-label="Nach unten" :disabled="i === form.steps.length - 1" @click="move(i, 1)"><ArrowDown :size="15" /></button>
            <button class="btn ghost sm icon" type="button" aria-label="Schritt entfernen" @click="remove(i)"><Trash2 :size="15" /></button>
          </div>
        </li>
      </ol>
      <button class="btn" type="button" @click="form.steps.push(row())"><Plus :size="15" /> Schritt hinzufügen</button>
    </form>
    <template #footer>
      <button class="btn" type="button" @click="close">Abbrechen</button>
      <button class="btn primary" type="submit" form="trail-form" :disabled="busy">{{ busy ? 'Speichere …' : 'Speichern' }}</button>
    </template>
  </Modal>
</template>

<style scoped>
.block { margin-top: 14px; }
textarea.small { min-height: 40px; }
.info { font-size: 13px; margin: 0 0 10px; }
.label { font-family: var(--font-display); font-weight: 600; font-size: 12.5px; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); margin-bottom: 4px; }
.steps { list-style: none; margin: 0 0 12px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.step { display: grid; grid-template-columns: 38px 1fr auto; gap: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; background: rgba(255,255,255,0.02); }
.step.parallel { margin-left: 28px; border-style: dashed; }
.num { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--grad); color: #160b12; font-weight: 800; font-size: 13px; }
.fields { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.tools { display: flex; flex-direction: column; gap: 2px; }
.pin-row { display: flex; gap: 6px; align-items: flex-start; }
.pin-row .grow { flex: 1; min-width: 0; }
.par { display: flex; align-items: center; gap: 6px; font-size: 13px; color: var(--muted); cursor: pointer; }
.par input { accent-color: var(--pink); }
</style>
