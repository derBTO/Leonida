<script setup lang="ts">
import { MapPinPlus } from 'lucide-vue-next'
import { computed, onMounted, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { GalleryImage, HintStepRow, LootType, Pin, PinCode, PinKind, PinStatus, PoiType, RespawnStatus, VehicleSummary, WeaponSummary } from '../api/types'
import { EGG } from '../lib/easteregg'
import { formatNumber, parseUsd } from '../lib/format'
import { LOOT, LOOT_ORDER } from '../lib/lootTypes'
import { POI, POI_ORDER, poiStyle } from '../lib/poiTypes'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import CatalogPicker from './CatalogPicker.vue'
import CodesEditor from './CodesEditor.vue'
import EnumSelect from './EnumSelect.vue'
import HintStepsEditor from './HintStepsEditor.vue'
import ImageManager from './ImageManager.vue'
import PinPicker from './PinPicker.vue'
import Modal from './Modal.vue'

/** Anlegen und Bearbeiten eines Pins. Felder je Loottyp werden dynamisch eingeblendet. */
/** lockKind: Art ist vorgegeben (z. B. Hinweis für einen Puzzle-Schritt) */
import type { EditorDraft } from '../stores/ui'
const props = defineProps<{ pinId: number | null; x: number; y: number; kind?: PinKind; title?: string; lockKind?: boolean; draft?: EditorDraft }>()
const emit = defineEmits<{ close: []; saved: [Pin] }>()
const ui = useUi()
const pins = usePins()
const session = useSession()
const loading = ref(props.pinId !== null && !props.draft)
/** Art des gespeicherten Pins und ob Hinweise auf ihn (als Ort) verweisen */
const savedKind = ref<PinKind | null>(null)
const referencedByHints = ref(false)
const placeQuery = ref('')
const busy = ref(false)
const errors = ref<Record<string, string[]>>({})
const images = ref<GalleryImage[]>([])
const manager = ref<InstanceType<typeof ImageManager> | null>(null)

const form = reactive({
  kind: (props.kind ?? 'loot') as PinKind,
  poi_type: null as PoiType | null,
  title: props.title ?? '',
  description: '',
  x: props.x,
  y: props.y,
  loot_types: [] as LootType[],
  weapons: [] as WeaponSummary[],
  vehicles: [] as VehicleSummary[],
  drugs: '',
  jewelry: '',
  cash: '',
  codes: [] as PinCode[],
  requirements: '',
  respawn: 'unknown' as RespawnStatus,
  status: 'unconfirmed' as PinStatus,
  is_easteregg: false,
  place_pin_id: null as number | null,
  steps: [] as HintStepRow[],
})
let initial = ''
const dirty = computed(() => !loading.value && JSON.stringify(form) !== initial)
const money = (v: number | null) => (v === null ? '' : formatNumber(v))

onMounted(async () => {
  if (props.draft) {
    Object.assign(form, JSON.parse(props.draft.form))
    if (props.pinId !== null) savedKind.value = form.kind
    initial = props.draft.initial
    return
  }
  if (props.pinId !== null) {
    try {
      const pin = (await api.get<{ pin: Pin }>(`/api/pins/${props.pinId}`)).pin
      Object.assign(form, {
        kind: pin.kind, poi_type: pin.poi_type,
        title: pin.title, description: pin.description ?? '', x: pin.x, y: pin.y, loot_types: pin.loot_types,
        weapons: pin.weapons, vehicles: pin.vehicles, drugs: money(pin.drugs_value_usd), jewelry: money(pin.jewelry_value_usd),
        cash: money(pin.cash_usd), codes: pin.codes.map((c) => ({ ...c })), requirements: pin.requirements ?? '',
        respawn: pin.respawn, status: pin.status, is_easteregg: pin.is_easteregg,
        place_pin_id: pin.place?.id ?? null,
        steps: pin.steps.map((s, i) => ({ key: i + 1, text: s.text ?? '', image: s.image })),
      })
      images.value = pin.images
      savedKind.value = pin.kind
      referencedByHints.value = (pin.hints?.length ?? 0) > 0
    } catch (e) {
      ui.error(e)
      emit('close')
    }
    loading.value = false
  }
  initial = JSON.stringify(form)
})

const has = (t: LootType) => form.loot_types.includes(t)
function toggle(t: LootType) {
  form.loot_types = has(t) ? form.loot_types.filter((x) => x !== t) : LOOT_ORDER.filter((x) => x === t || form.loot_types.includes(x))
}
const err = (k: string) => errors.value[k]?.[0]
const usdError = (s: string) => (Number.isNaN(parseUsd(s)) ? 'Nur ganze Dollar-Beträge' : undefined)

/** Hinweise bleiben Hinweise (Puzzle zeigen auf sie); Orte, auf die Hinweise verweisen, bleiben Orte */
const kindLocked = computed(() => props.lockKind || savedKind.value === 'hint' || (savedKind.value === 'poi' && referencedByHints.value))

/** Hinweis parken, Ort auf der Karte setzen, danach kommt man mit verknüpftem Ort zurück */
function createPlace() {
  if (!ui.pinEditor) return
  ui.hintDraft = { editor: { ...ui.pinEditor, draft: undefined }, form: JSON.stringify(form), initial, placeTitle: placeQuery.value.trim() }
  ui.pinEditor = null
}

const isPoi = computed(() => form.kind === 'poi')
const isHint = computed(() => form.kind === 'hint')
const noun = computed(() => (isPoi.value ? 'Ort' : isHint.value ? 'Hinweis' : 'Pin'))
const heading = computed(() => (props.pinId ? `${noun.value} bearbeiten` : isPoi.value ? 'Neuer Ort' : isHint.value ? 'Neuer Hinweis' : 'Neuer Pin'))

async function save() {
  if (isPoi.value && !form.poi_type) return ui.error('Bitte eine Kategorie für den Ort wählen.')
  if (form.kind === 'loot') for (const [k, v] of [['drugs', form.drugs], ['jewelry', form.jewelry], ['cash', form.cash]] as const) {
    if (usdError(v)) return ui.error(`Ungültiger Betrag bei ${LOOT[k].label}.`)
  }
  busy.value = true
  errors.value = {}
  const payload = {
    kind: form.kind,
    poi_type: isPoi.value ? form.poi_type : null,
    title: form.title,
    description: form.description,
    x: form.x,
    y: form.y,
    loot_types: form.loot_types,
    weapon_ids: form.weapons.map((w) => w.id),
    vehicle_ids: form.vehicles.map((v) => v.id),
    drugs_value_usd: parseUsd(form.drugs),
    jewelry_value_usd: parseUsd(form.jewelry),
    cash_usd: parseUsd(form.cash),
    codes: form.codes.filter((c) => c.code.trim() !== '' || (c.label ?? '').trim() !== ''),
    requirements: form.requirements,
    respawn: form.respawn,
    status: form.status,
    is_easteregg: form.is_easteregg,
    place_pin_id: isHint.value ? form.place_pin_id : null,
    steps: isHint.value ? form.steps.map((s) => ({ text: s.text, media_id: s.image?.id ?? null })) : [],
  }
  try {
    let pin = props.pinId
      ? (await api.put<{ pin: Pin }>(`/api/pins/${props.pinId}`, payload)).pin
      : (await api.post<{ pin: Pin }>('/api/pins', payload)).pin
    if (!isHint.value && manager.value?.hasPending()) {
      await manager.value.flush(pin.id)
      pin = (await api.get<{ pin: Pin }>(`/api/pins/${pin.id}`)).pin
    }
    await pins.refresh()
    ui.toast(props.pinId ? 'Gespeichert.' : `${noun.value} angelegt.`, 'ok')
    initial = JSON.stringify(form)
    emit('saved', pin)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.errors
    ui.error(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal :title="heading" width="760px" :dirty="dirty" @close="emit('close')">
    <div v-if="loading" class="muted">Lade …</div>
    <form v-else id="pin-form" @submit.prevent="save">
      <div v-if="!kindLocked" class="kinds" role="radiogroup" aria-label="Art">
        <button type="button" role="radio" :aria-checked="form.kind === 'loot'" :class="{ on: form.kind === 'loot' }" @click="form.kind = 'loot'">Loot-Fundort</button>
        <button type="button" role="radio" :aria-checked="isPoi" :class="{ on: isPoi }" @click="form.kind = 'poi'">Ort (POI)</button>
        <button type="button" role="radio" :aria-checked="isHint" :class="{ on: isHint }" @click="form.kind = 'hint'">Hinweis</button>
      </div>
      <p v-else-if="savedKind === 'poi' && referencedByHints" class="faint kind-note">Auf diesen Ort verweisen Hinweise, deshalb bleibt er ein Ort.</p>
      <label class="egg-toggle" :class="{ on: form.is_easteregg }">
        <input type="checkbox" v-model="form.is_easteregg" />
        <span class="egg-icon" :style="{ '--c': EGG.color }" v-html="EGG.svg" />
        Easteregg <small class="faint">– auf der Karte immer mit Ei-Symbol, egal welche Art</small>
      </label>
      <div class="field">
        <label for="pe-title">{{ isPoi ? 'Name *' : 'Titel *' }}</label>
        <input id="pe-title" class="input" v-model="form.title" maxlength="150" required :placeholder="isPoi ? 'z. B. Ammu-Nation Port Gellhorn' : isHint ? 'z. B. Zeitung im Diner' : 'z. B. Safe im Motel'" />
        <div v-if="err('title')" class="error-text">{{ err('title') }}</div>
      </div>
      <div class="coords faint">Position: x {{ form.x.toFixed(1) }}, y {{ form.y.toFixed(1) }}</div>

      <template v-if="isPoi">
        <div class="section-title">Kategorie *</div>
        <div class="poi-grid">
          <button v-for="t in POI_ORDER" :key="t" type="button" class="poi-opt" :class="{ on: form.poi_type === t }" :style="poiStyle(t)" :aria-pressed="form.poi_type === t" @click="form.poi_type = t">
            <span class="sq" v-html="POI[t].svg" />{{ POI[t].label }}
          </button>
        </div>
        <div v-if="err('poi_type')" class="error-text">{{ err('poi_type') }}</div>
        <div class="field block">
          <label for="pe-desc-poi">Beschreibung</label>
          <textarea id="pe-desc-poi" class="input" v-model="form.description" maxlength="20000" />
        </div>
        <div v-if="form.poi_type === 'ammunation'" class="block">
          <div class="label">Waffensortiment</div>
          <CatalogPicker kind="weapons" v-model="form.weapons" />
          <p class="faint hint-info">Der Laden erscheint bei diesen Waffen als Fundort.</p>
        </div>
      </template>
      <template v-else-if="isHint">
        <div class="field block">
          <label for="pe-desc-hint">Beschreibung</label>
          <textarea id="pe-desc-hint" class="input" v-model="form.description" maxlength="20000" placeholder="Worum geht es bei diesem Hinweis?" />
        </div>
        <div class="section-title">Codes</div>
        <CodesEditor v-model="form.codes" :errors="errors" />

        <div class="section-title">Verknüpfungen</div>
        <div class="field">
          <label for="pe-place">Ort</label>
          <PinPicker id="pe-place" v-model="form.place_pin_id" v-model:query="placeQuery" :kinds="['poi']" placeholder="Vorhandenen Ort suchen … (optional)">
            <template #extra="{ query }">
              <button type="button" class="res new-res" @mousedown.prevent="createPlace"><MapPinPlus :size="15" /> „{{ query }}“ als neuen Ort auf der Karte anlegen</button>
            </template>
          </PinPicker>
          <button v-if="!form.place_pin_id" class="btn sm new-place" type="button" @click="createPlace"><MapPinPlus :size="15" /> Neuen Ort auf der Karte anlegen</button>
          <div v-if="err('place_pin_id')" class="error-text">{{ err('place_pin_id') }}</div>
        </div>
        <p class="faint hint-info">Ist ein Ort gewählt, erscheint er bei den verknüpften Fahrzeugen und Waffen als Fundort.</p>
        <div class="block">
          <div class="label">Fahrzeuge</div>
          <CatalogPicker kind="vehicles" v-model="form.vehicles" />
        </div>
        <div class="block">
          <div class="label">Waffen</div>
          <CatalogPicker kind="weapons" v-model="form.weapons" />
        </div>

        <div class="section-title">Schritte</div>
        <HintStepsEditor v-model="form.steps" :errors="errors" />
      </template>
      <template v-else>
      <div class="section-title">Loottypen</div>
      <div class="chips">
        <button v-for="t in LOOT_ORDER" :key="t" type="button" class="chip" :class="{ on: has(t) }" :style="{ '--c': LOOT[t].color }" :aria-pressed="has(t)" @click="toggle(t)">
          <span class="dot" /> {{ LOOT[t].label }}
        </button>
      </div>

      <div v-if="has('vehicles')" class="block">
        <div class="label">Fahrzeuge</div>
        <CatalogPicker kind="vehicles" v-model="form.vehicles" />
      </div>
      <div v-if="has('weapons')" class="block">
        <div class="label">Waffen</div>
        <CatalogPicker kind="weapons" v-model="form.weapons" />
      </div>
      <div v-if="has('drugs') || has('jewelry') || has('cash')" class="grid-3 block">
        <div v-if="has('drugs')" class="field">
          <label for="pe-drugs">Wert Drogen (US$)</label>
          <input id="pe-drugs" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.drugs" />
          <div v-if="usdError(form.drugs) || err('drugs_value_usd')" class="error-text">{{ usdError(form.drugs) ?? err('drugs_value_usd') }}</div>
        </div>
        <div v-if="has('jewelry')" class="field">
          <label for="pe-jewelry">Wert Schmuck (US$)</label>
          <input id="pe-jewelry" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.jewelry" />
          <div v-if="usdError(form.jewelry) || err('jewelry_value_usd')" class="error-text">{{ usdError(form.jewelry) ?? err('jewelry_value_usd') }}</div>
        </div>
        <div v-if="has('cash')" class="field">
          <label for="pe-cash">Geldbetrag (US$)</label>
          <input id="pe-cash" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.cash" />
          <div v-if="usdError(form.cash) || err('cash_usd')" class="error-text">{{ usdError(form.cash) ?? err('cash_usd') }}</div>
        </div>
      </div>

      <div class="field block">
        <label for="pe-desc">Beschreibung</label>
        <textarea id="pe-desc" class="input" v-model="form.description" maxlength="20000" />
      </div>
      <div class="field block">
        <label for="pe-req">Voraussetzungen / Hinweise</label>
        <textarea id="pe-req" class="input small" v-model="form.requirements" maxlength="5000" placeholder="z. B. nur nachts, Brecheisen nötig" />
      </div>
      <div class="grid-2 block">
        <EnumSelect label="Status" :options="session.meta!.enums.pin_status" v-model="form.status as any" required />
        <EnumSelect label="Respawn" :options="session.meta!.enums.respawn" v-model="form.respawn as any" required />
      </div>

      <div class="section-title">Codes</div>
      <CodesEditor v-model="form.codes" :errors="errors" />
      </template>

      <template v-if="!isHint">
        <div class="section-title">Bilder</div>
        <ImageManager ref="manager" :pin-id="pinId" :images="images" @changed="images = $event" />
      </template>
    </form>
    <template #footer>
      <button class="btn" type="button" @click="emit('close')">Abbrechen</button>
      <button class="btn primary" type="submit" form="pin-form" :disabled="busy || loading">{{ busy ? 'Speichere …' : 'Speichern' }}</button>
    </template>
  </Modal>
</template>

<style scoped>
.coords { font-size: 12px; margin-top: 4px; }
.chips { display: flex; flex-wrap: wrap; gap: 6px; }
.block { margin-top: 14px; }
.block > .label { margin-bottom: 6px; display: block; }
textarea.small { min-height: 60px; }
.egg-toggle { display: flex; align-items: center; gap: 8px; margin-top: 10px; padding: 7px 10px; border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); cursor: pointer; font-size: 14px; }
.egg-toggle.on { border-style: solid; border-color: #a3e635; background: rgba(163, 230, 53, 0.08); }
.egg-toggle input { accent-color: #a3e635; }
.egg-icon { display: inline-flex; color: var(--c); }
.egg-icon :deep(svg) { width: 18px; height: 18px; }
.new-place { margin-top: 6px; }
.new-res { display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 8px; margin-top: 2px; border: 0; border-top: 1px solid var(--line); background: none; cursor: pointer; text-align: left; color: var(--orange); }
.new-res:hover { background: var(--panel-hover); }
.kind-note { font-size: 13px; margin: 0 0 10px; }
.hint-info { font-size: 13px; margin: 10px 0 0; }
.kinds { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 4px; padding: 3px; margin-bottom: 14px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); background: rgba(0,0,0,0.25); }
.kinds button { height: 34px; border: 0; border-radius: 6px; background: none; cursor: pointer; font-family: var(--font-display); font-weight: 600; font-size: 15px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); }
.kinds button.on { background: rgba(255,255,255,0.1); color: var(--text); box-shadow: inset 0 -2px 0 var(--pink); }
.poi-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 6px; }
.poi-opt { display: flex; align-items: center; gap: 8px; height: 34px; padding: 0 8px; border-radius: 8px; border: 1px solid var(--line-strong); background: rgba(255,255,255,0.03); cursor: pointer; text-align: left; font-size: 13px; }
.poi-opt.on { border-color: var(--c); background: color-mix(in srgb, var(--c) 20%, transparent); }
.sq { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 5px; background: var(--c); color: var(--fg, #fff); flex: none; }
.sq :deep(svg) { width: 14px; height: 14px; }
</style>
