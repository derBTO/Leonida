<script setup lang="ts">
import { ChevronDown, Plus, X } from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'
import type { LookupCategory, LookupValue } from '../api/types'
import { useCatalog } from '../stores/catalog'
import { useUi } from '../stores/ui'

/**
 * Durchsuchbares Dropdown für dynamische Auswahllisten. Fehlt ein Wert, kann er direkt
 * angelegt werden; Dubletten (auch in anderer Schreibweise) erkennt der Server.
 */
const props = defineProps<{ category: LookupCategory; label: string; modelValue: LookupValue | null; error?: string }>()
const emit = defineEmits<{ 'update:modelValue': [LookupValue | null] }>()
const catalog = useCatalog()
const ui = useUi()
const open = ref(false)
const query = ref('')
const active = ref(0)
const busy = ref(false)
const id = `lk-${Math.random().toString(36).slice(2, 8)}`

onMounted(() => catalog.loadLookups(props.category).catch(ui.error))

const key = (s: string) => s.trim().replace(/\s+/g, ' ').toLowerCase()
const options = computed(() => {
  const all = catalog.lookups[props.category] ?? []
  const q = key(query.value)
  return q ? all.filter((v) => v.name.toLowerCase().includes(q)) : all
})
const exact = computed(() => (catalog.lookups[props.category] ?? []).find((v) => key(v.name) === key(query.value)))
const canCreate = computed(() => query.value.trim() !== '' && !exact.value)

function pick(v: LookupValue | null) {
  emit('update:modelValue', v)
  open.value = false
  query.value = ''
}
async function create() {
  busy.value = true
  try {
    pick(await catalog.addLookup(props.category, query.value))
  } catch (e) {
    ui.error(e)
  } finally {
    busy.value = false
  }
}
function onKey(e: KeyboardEvent) {
  const count = options.value.length + (canCreate.value ? 1 : 0)
  if (e.key === 'ArrowDown') { open.value = true; active.value = Math.min(active.value + 1, count - 1); e.preventDefault() }
  else if (e.key === 'ArrowUp') { active.value = Math.max(active.value - 1, 0); e.preventDefault() }
  else if (e.key === 'Enter' && open.value) {
    e.preventDefault()
    if (active.value < options.value.length) pick(options.value[active.value])
    else if (canCreate.value) create()
  } else if (e.key === 'Escape' && open.value) { open.value = false; e.stopPropagation() }
}
function onBlur() {
  window.setTimeout(() => (open.value = false), 150)
}
</script>

<template>
  <div class="field lookup">
    <label :for="id">{{ label }}</label>
    <div class="box" :class="{ err: error }">
      <input
        :id="id" class="input" autocomplete="off" role="combobox" :aria-expanded="open"
        :placeholder="modelValue ? modelValue.name : 'unbekannt'" v-model="query"
        :class="{ chosen: modelValue && !query }"
        @focus="open = true; active = 0" @input="open = true; active = 0" @keydown="onKey" @blur="onBlur"
      />
      <button v-if="modelValue" type="button" class="btn ghost sm icon clear" title="Leeren (unbekannt)" @click="pick(null)"><X :size="14" /></button>
      <ChevronDown v-else :size="16" class="chev" />
      <ul v-if="open && (options.length || canCreate)" class="menu panel scroll" role="listbox">
        <li v-for="(v, i) in options" :key="v.id" role="option" :aria-selected="modelValue?.id === v.id"
          :class="{ active: i === active, selected: modelValue?.id === v.id }" @mousedown.prevent="pick(v)">{{ v.name }}</li>
        <li v-if="canCreate" class="create" :class="{ active: active === options.length }" @mousedown.prevent="create">
          <Plus :size="14" /> „{{ query.trim() }}“ hinzufügen
          <span v-if="busy" class="faint">…</span>
        </li>
      </ul>
    </div>
    <div v-if="error" class="error-text">{{ error }}</div>
  </div>
</template>

<style scoped>
.box { position: relative; }
.box.err .input { border-color: var(--danger); }
.input { padding-right: 34px; }
.input.chosen::placeholder { color: var(--text); }
.chev { position: absolute; right: 10px; top: 11px; color: var(--faint); pointer-events: none; }
.clear { position: absolute; right: 4px; top: 4px; }
.menu { position: absolute; z-index: 50; left: 0; right: 0; top: calc(100% + 4px); max-height: 240px; margin: 0; padding: 4px; list-style: none; background: var(--panel-solid); }
.menu li { padding: 7px 10px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.menu li.active { background: rgba(255, 255, 255, 0.08); }
.menu li.selected { color: var(--orange); }
.menu li.create { color: var(--pink); border-top: 1px solid var(--line); margin-top: 2px; }
</style>
