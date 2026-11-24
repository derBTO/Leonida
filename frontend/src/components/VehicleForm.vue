<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { LookupValue, Vehicle, VehicleStats } from '../api/types'
import { useCatalog } from '../stores/catalog'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import EnumSelect from './EnumSelect.vue'
import ImageField from './ImageField.vue'
import LookupSelect from './LookupSelect.vue'
import Modal from './Modal.vue'
import StatEditor from './StatEditor.vue'

const props = defineProps<{ vehicle?: Vehicle | null; initialName?: string }>()
const emit = defineEmits<{ close: []; saved: [Vehicle] }>()
const ui = useUi()
const session = useSession()
const catalog = useCatalog()
const v = props.vehicle
const form = reactive({
  name: v?.name ?? props.initialName ?? '',
  manufacturer: (v?.manufacturer ?? null) as LookupValue | null,
  vehicle_class: (v?.vehicle_class ?? null) as LookupValue | null,
  style: (v?.style ?? null) as LookupValue | null,
  drivetrain: v?.drivetrain ?? null as string | null,
  seats: v?.seats?.toString() ?? '',
  security: v?.security ?? null as string | null,
  tracker: v?.tracker ?? null as string | null,
  access: v?.access ?? null as string | null,
  registrable: v?.registrable === null || v?.registrable === undefined ? null : v.registrable ? 'yes' : 'no' as string | null,
  stats: { ...(v?.stats ?? { speed: null, acceleration: null, handling_on_road: null, handling_off_road: null, durability: null }) } as VehicleStats,
  rating_on_road: v?.rating_on_road?.toString() ?? '',
  rating_off_road: v?.rating_off_road?.toString() ?? '',
  notes: v?.notes ?? '',
})
const initial = JSON.stringify(form)
const image = ref<File | null>(null)
const errors = ref<Record<string, string[]>>({})
const busy = ref(false)
const dirty = computed(() => JSON.stringify(form) !== initial || image.value !== null)
const err = (k: string) => errors.value[k]?.[0]
const enums = computed(() => session.meta!.enums)
const intOrNull = (s: string) => (s.trim() === '' ? null : Number(s.trim()))

async function save() {
  busy.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    manufacturer_id: form.manufacturer?.id ?? null,
    class_id: form.vehicle_class?.id ?? null,
    style_id: form.style?.id ?? null,
    drivetrain: form.drivetrain,
    seats: intOrNull(form.seats),
    security: form.security,
    tracker: form.tracker,
    access: form.access,
    registrable: form.registrable === null ? null : form.registrable === 'yes',
    stats: form.stats,
    rating_on_road: intOrNull(form.rating_on_road),
    rating_off_road: intOrNull(form.rating_off_road),
    notes: form.notes,
  }
  try {
    let item = v
      ? (await api.put<{ item: Vehicle }>(`/api/vehicles/${v.id}`, payload)).item
      : (await api.post<{ item: Vehicle }>('/api/vehicles', payload)).item
    if (image.value) {
      const data = new FormData()
      data.append('file', image.value)
      item = (await api.post<{ item: Vehicle }>(`/api/vehicles/${item.id}/image`, data)).item
    }
    catalog.invalidate()
    ui.toast(v ? 'Fahrzeug gespeichert.' : 'Fahrzeug angelegt.', 'ok')
    emit('saved', item)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.errors
    ui.error(e)
  } finally {
    busy.value = false
  }
}
async function removeImage() {
  if (!v) return
  try {
    emit('saved', (await api.delete<{ item: Vehicle }>(`/api/vehicles/${v.id}/image`)).item)
  } catch (e) {
    ui.error(e)
  }
}
</script>

<template>
  <Modal :title="v ? 'Fahrzeug bearbeiten' : 'Neues Fahrzeug'" width="760px" :dirty="dirty" @close="emit('close')">
    <form id="vehicle-form" @submit.prevent="save">
      <div class="field">
        <label for="vf-name">Name *</label>
        <input id="vf-name" class="input" v-model="form.name" maxlength="120" required />
        <div v-if="err('name')" class="error-text">{{ err('name') }}</div>
      </div>
      <ImageField :media="v?.image ?? null" :pending="image" :busy="busy" @select="image = $event" @remove="removeImage" />
      <div class="grid-3 mt">
        <LookupSelect category="vehicle_manufacturer" label="Hersteller" v-model="form.manufacturer" :error="err('manufacturer_id')" />
        <LookupSelect category="vehicle_class" label="Fahrzeugklasse" v-model="form.vehicle_class" :error="err('class_id')" />
        <LookupSelect category="vehicle_style" label="Stil" v-model="form.style" :error="err('style_id')" />
        <EnumSelect label="Antriebsart" :options="enums.drivetrain" v-model="form.drivetrain" />
        <div class="field">
          <label for="vf-seats">Sitze</label>
          <input id="vf-seats" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.seats" />
          <div v-if="err('seats')" class="error-text">{{ err('seats') }}</div>
        </div>
        <EnumSelect label="Sicherheit" :options="enums.security" v-model="form.security" />
        <EnumSelect label="Tracker" :options="enums.tracker" v-model="form.tracker" />
        <EnumSelect label="Zugang" :options="enums.access" v-model="form.access" />
        <EnumSelect label="Registrierbar" :options="[{ value: 'yes', label: 'Ja' }, { value: 'no', label: 'Nein' }]" v-model="form.registrable" />
      </div>
      <div class="section-title">Werte (0–10, leer = unbekannt)</div>
      <StatEditor label="Geschwindigkeit" v-model="form.stats.speed" :error="err('stats.speed')" />
      <StatEditor label="Beschleunigung" v-model="form.stats.acceleration" :error="err('stats.acceleration')" />
      <StatEditor label="On Road Handling" v-model="form.stats.handling_on_road" :error="err('stats.handling_on_road')" />
      <StatEditor label="Off Road Handling" v-model="form.stats.handling_off_road" :error="err('stats.handling_off_road')" />
      <StatEditor label="Belastbarkeit" v-model="form.stats.durability" :error="err('stats.durability')" />
      <div class="section-title">Rating (0–1000, manuell)</div>
      <div class="grid-2">
        <div class="field">
          <label for="vf-on">On Road</label>
          <input id="vf-on" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.rating_on_road" />
          <div v-if="err('rating_on_road')" class="error-text">{{ err('rating_on_road') }}</div>
        </div>
        <div class="field">
          <label for="vf-off">Off Road</label>
          <input id="vf-off" class="input" inputmode="numeric" placeholder="unbekannt" v-model="form.rating_off_road" />
          <div v-if="err('rating_off_road')" class="error-text">{{ err('rating_off_road') }}</div>
        </div>
      </div>
      <div class="field mt">
        <label for="vf-notes">Notizen</label>
        <textarea id="vf-notes" class="input" v-model="form.notes" maxlength="5000" />
      </div>
    </form>
    <template #footer>
      <button class="btn" type="button" @click="emit('close')">Abbrechen</button>
      <button class="btn primary" type="submit" form="vehicle-form" :disabled="busy">Speichern</button>
    </template>
  </Modal>
</template>

<style scoped>
.mt { margin-top: 12px; }
</style>
