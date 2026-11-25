<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import type { PinIndexItem } from '../api/types'
import { HINT } from '../lib/hint'
import { LOOT, sortLoot } from '../lib/lootTypes'
import { terms } from '../lib/pinFilter'
import { POI } from '../lib/poiTypes'
import { usePins } from '../stores/pins'

/** Einen Pin per Suche auswählen (Titel, Beschreibung, Codes). Leer = Schritt ohne Ort. */
const model = defineModel<number | null>({ required: true })
const props = defineProps<{ id: string }>()
const pins = usePins()
const q = ref('')
const open = ref(false)
const selected = computed(() => (model.value ? pins.byId.get(model.value) ?? null : null))
const results = computed(() => {
  const words = terms(q.value)
  if (!words.length) return []
  const kindOrder = { hint: 0, loot: 1, poi: 2 } as const
  return pins.items
    .filter((p) => {
      const h = [p.t, p.d, ...p.c].join(' ').toLowerCase()
      return words.every((w) => h.includes(w))
    })
    .sort((a, b) => kindOrder[a.k] - kindOrder[b.k] || a.t.localeCompare(b.t, 'de'))
    .slice(0, 12)
})
const kindLabel = (p: PinIndexItem) =>
  p.k === 'hint' ? HINT.label : p.k === 'poi' ? POI[p.p ?? 'poi'].label : sortLoot(p.l).map((t) => LOOT[t].label).join(' · ') || 'Loot'
const color = (p: PinIndexItem) => (p.k === 'hint' ? HINT.color : p.k === 'poi' ? POI[p.p ?? 'poi'].color : p.l.length ? LOOT[sortLoot(p.l)[0]].color : '#c9c5d6')
function close() {
  window.setTimeout(() => (open.value = false), 150)
}
function pick(p: PinIndexItem) {
  model.value = p.id
  q.value = ''
  open.value = false
}
</script>

<template>
  <div class="picker">
    <div v-if="selected" class="chosen">
      <span class="dot" :style="{ background: color(selected) }" />
      <span class="t">{{ selected.t }}</span>
      <small class="faint">{{ kindLabel(selected) }}</small>
      <button class="btn ghost sm icon" type="button" aria-label="Pin entfernen" @click="model = null"><X :size="14" /></button>
    </div>
    <template v-else>
      <input :id="props.id" class="input" type="search" v-model="q" placeholder="Pin suchen … (leer = ohne Ort)" autocomplete="off"
        @focus="open = true" @blur="close" />
      <div v-if="open && q.trim()" class="results panel">
        <button v-for="p in results" :key="p.id" type="button" class="res" @mousedown.prevent="pick(p)">
          <span class="dot" :style="{ background: color(p) }" />
          <span class="t">{{ p.t }}</span>
          <small class="faint">{{ kindLabel(p) }}</small>
        </button>
        <div v-if="!results.length" class="faint none">Kein Pin gefunden. Neue Hinweise per Rechtsklick auf der Karte anlegen.</div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.picker { position: relative; }
.chosen { display: flex; align-items: center; gap: 8px; height: 40px; padding: 0 4px 0 10px; border: 1px solid var(--line-strong); border-radius: var(--radius-sm); background: rgba(0,0,0,0.2); }
.t { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
.results { position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% + 4px); max-height: 280px; overflow-y: auto; padding: 4px; }
.res { display: flex; align-items: center; gap: 8px; width: 100%; padding: 7px 8px; border: 0; background: none; border-radius: 6px; cursor: pointer; text-align: left; }
.res:hover { background: var(--panel-hover); }
.res small { white-space: nowrap; }
.none { padding: 8px; font-size: 13px; }
</style>
