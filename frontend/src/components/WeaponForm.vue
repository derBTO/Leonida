<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { api, ApiError } from '../api/client'
import type { LookupValue, Weapon, WeaponStats } from '../api/types'
import { useCatalog } from '../stores/catalog'
import { useUi } from '../stores/ui'
import EnumSelect from './EnumSelect.vue'
import ImageField from './ImageField.vue'
import LookupSelect from './LookupSelect.vue'
import Modal from './Modal.vue'
import StatEditor from './StatEditor.vue'

const props = defineProps<{ weapon?: Weapon | null; initialName?: string }>()
const emit = defineEmits<{ close: []; saved: [Weapon] }>()
const ui = useUi()
const catalog = useCatalog()
const w = props.weapon
const form = reactive({
  name: w?.name ?? props.initialName ?? '',
  weapon_type: (w?.weapon_type ?? null) as LookupValue | null,
  fire_mode: (w?.fire_mode ?? null) as LookupValue | null,
  brand: (w?.brand ?? null) as LookupValue | null,
  caliber: (w?.caliber ?? null) as LookupValue | null,
  magazine_size: w?.magazine_size?.toString() ?? '',
  legendary: (w?.is_legendary === null || w?.is_legendary === undefined ? null : w.is_legendary ? 'yes' : 'no') as string | null,
  stats: { ...(w?.stats ?? { damage: null, accuracy: null, handling: null, range: null }) } as WeaponStats,
  notes: w?.notes ?? '',
})
const initial = JSON.stringify(form)
const image = ref<File | null>(null)
const errors = ref<Record<string, string[]>>({})
const busy = ref(false)
const dirty = computed(() => JSON.stringify(form) !== initial || image.value !== null)
const err = (k: string) => errors.value[k]?.[0]

async function save() {
  busy.value = true
  errors.value = {}
  const payload = {
    name: form.name,
    weapon_type_id: form.weapon_type?.id ?? null,
    fire_mode_id: form.fire_mode?.id ?? null,
    brand_id: form.brand?.id ?? null,
    caliber_id: form.caliber?.id ?? null,
    magazine_size: form.magazine_size.trim() === '' ? null : Number(form.magazine_size),
    is_legendary: form.legendary === null ? null : form.legendary === 'yes',
    stats: form.stats,
    notes: form.notes,
  }
  try {
    let item = w
      ? (await api.put<{ item: Weapon }>(`/api/weapons/${w.id}`, payload)).item
      : (await api.post<{ item: Weapon }>('/api/weapons', payload)).item
    if (image.value) {
      const data = new FormData()
      data.append('file', image.value)
      item = (await api.post<{ item: Weapon }>(`/api/weapons/${item.id}/image`, data)).item
    }
    catalog.invalidate()
    ui.toast(w ? 'Waffe gespeichert.' : 'Waffe angelegt.', 'ok')
    emit('saved', item)
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.errors
    ui.error(e)
  } finally {
    busy.value = false
  }
}
async function removeImage() {
  if (!w) return
  try {
    emit('saved', (await api.delete<{ item: Weapon }>(`/api/weapons/${w.id}/image`)).item)
  } catch (e) {
    ui.error(e)
  }
}
</script>

<template>
  <Modal :title="w ? 'Waffe bearbeiten' : 'Neue Waffe'" width="720px" :dirty="dirty" @close="emit('close')">
    <form id="weapon-form" @submit.prevent="save">
      <div class="field">
        <label for="wf-name">Name *</label>
        <input id="wf-name" class="input" v-model="form.name" maxlength="120" required />
        <div v-if="err('name')" class="error-text">{{ err('name') }}</div>
      </div>
      <ImageField :media="w?.image ?? null" :pending="image" :busy="busy" @select="image = $event" @remove="removeImage" />
      <div class="grid-2 mt">
        <LookupSelect category="weapon_type" label="Waffentyp" v-model="form.weapon_type" :error="err('weapon_type_id')" />
        <LookupSelect category="fire_mode" label="Art" v-model="form.fire_mode" :error="err('fire_mode_id')" />
        <LookupSelect category="weapon_brand" label="Marke" v-model="form.brand" :error="err('brand_id')" />
        <LookupSelect category="caliber" label="Kaliber" v-model="form.caliber" :error="err('caliber_id')" />
        <div class="field">
          <label for="wf-mag">Magazingröße</label>
          <input id="wf-mag" class="input" inputmode="numeric" pattern="\d*" placeholder="unbekannt" v-model="form.magazine_size" />
          <div v-if="err('magazine_size')" class="error-text">{{ err('magazine_size') }}</div>
        </div>
        <EnumSelect label="Legendär / Unique" :options="[{ value: 'yes', label: 'Ja ★' }, { value: 'no', label: 'Nein' }]" v-model="form.legendary" />
      </div>
      <div class="section-title">Werte (0–10, leer = unbekannt)</div>
      <StatEditor label="Schaden" v-model="form.stats.damage" :error="err('stats.damage')" />
      <StatEditor label="Treffgenauigkeit" v-model="form.stats.accuracy" :error="err('stats.accuracy')" />
      <StatEditor label="Handling" v-model="form.stats.handling" :error="err('stats.handling')" />
      <StatEditor label="Reichweite" v-model="form.stats.range" :error="err('stats.range')" />
      <div class="field mt">
        <label for="wf-notes">Notizen</label>
        <textarea id="wf-notes" class="input" v-model="form.notes" maxlength="5000" />
      </div>
    </form>
    <template #footer>
      <button class="btn" type="button" @click="emit('close')">Abbrechen</button>
      <button class="btn primary" type="submit" form="weapon-form" :disabled="busy">Speichern</button>
    </template>
  </Modal>
</template>

<style scoped>
.mt { margin-top: 12px; }
</style>
