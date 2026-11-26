<script setup lang="ts">
import { useSession } from '../stores/session'
import { Plus, X } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import type { Vehicle, VehicleSummary, Weapon, WeaponSummary } from '../api/types'
import { useCatalog } from '../stores/catalog'
import { useUi } from '../stores/ui'
import LegendaryStar from './LegendaryStar.vue'
import MediaImage from './MediaImage.vue'
import VehicleForm from './VehicleForm.vue'
import WeaponForm from './WeaponForm.vue'

type Item = WeaponSummary | VehicleSummary
/** Mehrfachauswahl aus dem Waffen- oder Fahrzeugkatalog, mit Neuanlage direkt im Dialog. */
const session = useSession()
const props = defineProps<{ kind: 'weapons' | 'vehicles'; modelValue: Item[] }>()
const emit = defineEmits<{ 'update:modelValue': [Item[]] }>()
const catalog = useCatalog()
const ui = useUi()
const query = ref('')
const open = ref(false)
const creating = ref<string | null>(null)
const label = computed(() => (props.kind === 'weapons' ? 'Waffe' : 'Fahrzeug'))

onMounted(() => (props.kind === 'weapons' ? catalog.loadWeapons() : catalog.loadVehicles()).catch(ui.error))

const all = computed<Item[]>(() => (props.kind === 'weapons' ? catalog.weapons : catalog.vehicles) ?? [])
const chosen = computed(() => new Set(props.modelValue.map((i) => i.id)))
const options = computed(() => {
  const q = query.value.trim().toLowerCase()
  return all.value.filter((i) => !chosen.value.has(i.id) && (!q || i.name.toLowerCase().includes(q))).slice(0, 50)
})
const exact = computed(() => all.value.some((i) => i.name.toLowerCase() === query.value.trim().toLowerCase()))

function closeLater() {
  window.setTimeout(() => (open.value = false), 150)
}
function add(item: Item) {
  emit('update:modelValue', [...props.modelValue, item])
  query.value = ''
}
function remove(id: number) {
  emit('update:modelValue', props.modelValue.filter((i) => i.id !== id))
}
async function created(item: Weapon | Vehicle) {
  creating.value = null
  await (props.kind === 'weapons' ? catalog.loadWeapons(true) : catalog.loadVehicles(true))
  add(item)
}
</script>

<template>
  <div class="picker">
    <ul v-if="modelValue.length" class="chosen">
      <li v-for="item in modelValue" :key="item.id">
        <MediaImage :media="item.image" thumb :alt="item.name" class="thumb" />
        <span class="name" :class="{ 'legendary-name': 'is_legendary' in item && item.is_legendary }">{{ item.name }}<LegendaryStar v-if="'is_legendary' in item && item.is_legendary" :size="13" /></span>
        <button type="button" class="btn ghost sm icon" :aria-label="`${item.name} entfernen`" @click="remove(item.id)"><X :size="14" /></button>
      </li>
    </ul>
    <div class="search">
      <input class="input" :placeholder="session.canEditCatalog ? `${label} suchen oder neu anlegen …` : `${label} suchen …`" v-model="query" @focus="open = true" @blur="closeLater" />
      <ul v-if="open && (options.length || query.trim())" class="menu panel scroll">
        <li v-for="item in options" :key="item.id" @mousedown.prevent="add(item)">
          <MediaImage :media="item.image" thumb :alt="item.name" class="thumb" /> <span :class="{ 'legendary-name': 'is_legendary' in item && item.is_legendary }">{{ item.name }}</span><LegendaryStar v-if="'is_legendary' in item && item.is_legendary" :size="13" />
        </li>
        <li v-if="query.trim() && !exact && session.canEditCatalog" class="create" @mousedown.prevent="creating = query.trim()">
          <Plus :size="14" /> „{{ query.trim() }}“ als neue {{ kind === 'weapons' ? 'Waffe' : 'Fahrzeug' }} anlegen
        </li>
      </ul>
    </div>
    <WeaponForm v-if="creating !== null && kind === 'weapons'" :initial-name="creating" @close="creating = null" @saved="created" />
    <VehicleForm v-if="creating !== null && kind === 'vehicles'" :initial-name="creating" @close="creating = null" @saved="created" />
  </div>
</template>

<style scoped>
.chosen { list-style: none; margin: 0 0 8px; padding: 0; display: flex; flex-direction: column; gap: 4px; }
.chosen li { display: flex; align-items: center; gap: 10px; padding: 4px 4px 4px 6px; border-radius: 8px; background: rgba(255, 255, 255, 0.04); }
.thumb { width: 48px !important; height: 28px !important; border-radius: 4px; flex: none; background: rgba(0,0,0,0.3); }
.name { flex: 1; }
.search { position: relative; }
.menu { position: absolute; z-index: 60; left: 0; right: 0; top: calc(100% + 4px); max-height: 260px; margin: 0; padding: 4px; list-style: none; background: var(--panel-solid); }
.menu li { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
.menu li:hover { background: rgba(255, 255, 255, 0.08); }
.menu li.create { color: var(--pink); border-top: 1px solid var(--line); }
</style>
