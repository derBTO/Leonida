<script setup lang="ts">
import { ChevronLeft, Search, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import type { LootFilter } from '../lib/pinFilter'
import { LOOT, LOOT_ORDER, NO_LOOT_COLOR, ringGradient, sortLoot } from '../lib/lootTypes'
import { usePins } from '../stores/pins'
import { useUi } from '../stores/ui'

defineProps<{ selectedId: number | null }>()
const emit = defineEmits<{ select: [number] }>()
const pins = usePins()
const ui = useUi()
const f = pins.filter

function toggle(t: LootFilter) {
  f.loot = f.loot.includes(t) ? f.loot.filter((x) => x !== t) : [...f.loot, t]
}

// Virtuelle Liste: nur sichtbare Zeilen rendern, damit auch tausende Pins flüssig bleiben.
const ROW = 52
const scroller = ref<HTMLElement | null>(null)
const scrollTop = ref(0)
const viewport = ref(600)
const range = computed(() => {
  const start = Math.max(0, Math.floor(scrollTop.value / ROW) - 6)
  const end = Math.min(pins.listed.length, start + Math.ceil(viewport.value / ROW) + 12)
  return { start, end }
})
const rows = computed(() => pins.listed.slice(range.value.start, range.value.end))
function onScroll() {
  scrollTop.value = scroller.value?.scrollTop ?? 0
  viewport.value = scroller.value?.clientHeight ?? 600
}
const filtersActive = computed(() => f.q !== '' || f.loot.length > 0 || !f.showGone)
</script>

<template>
  <aside class="sidebar panel" :class="{ closed: !ui.sidebarOpen }" aria-label="Pins">
    <div class="top">
      <div class="search">
        <Search :size="16" class="icon" />
        <input class="input" type="search" placeholder="Suche: Titel, Waffe, Fahrzeug, Code …" v-model="f.q" aria-label="Pins durchsuchen" />
        <button v-if="f.q" type="button" class="btn ghost sm icon clear" aria-label="Suche leeren" @click="f.q = ''"><X :size="14" /></button>
      </div>
      <div class="chips">
        <button v-for="t in LOOT_ORDER" :key="t" type="button" class="chip" :class="{ on: f.loot.includes(t) }" :style="{ '--c': LOOT[t].color }" :aria-pressed="f.loot.includes(t)" @click="toggle(t)">
          <span class="dot" />{{ LOOT[t].label }}
        </button>
        <button type="button" class="chip" :class="{ on: f.loot.includes('none') }" :style="{ '--c': NO_LOOT_COLOR }" @click="toggle('none')">
          <span class="dot" />Ohne Loottyp
        </button>
      </div>
      <label class="switch"><input type="checkbox" v-model="f.viewportOnly" /> Nur im Kartenausschnitt</label>
      <label class="switch"><input type="checkbox" v-model="f.showGone" /> Nicht mehr verfügbare zeigen</label>
      <div class="count">
        <span><strong>{{ pins.listed.length }}</strong> {{ pins.listed.length === 1 ? 'Pin' : 'Pins' }}<template v-if="pins.listed.length !== pins.items.length"> von {{ pins.items.length }}</template></span>
        <button v-if="filtersActive" type="button" class="btn ghost sm" @click="pins.resetFilter()">Filter zurücksetzen</button>
      </div>
    </div>
    <div ref="scroller" class="list scroll" @scroll.passive="onScroll">
      <div v-if="!pins.loaded" class="empty pad">Lade Pins …</div>
      <div v-else-if="!pins.listed.length" class="empty pad">Keine Pins gefunden.</div>
      <div :style="{ height: `${pins.listed.length * ROW}px`, position: 'relative' }">
        <button
          v-for="(p, i) in rows" :key="p.id" type="button" class="row" :class="{ active: p.id === selectedId, gone: p.s === 'gone' }"
          :style="{ top: `${(range.start + i) * ROW}px` }" @click="emit('select', p.id)"
        >
          <span class="ring" :style="{ background: ringGradient(sortLoot(p.l)) }"><span /></span>
          <span class="text">
            <span class="name">{{ p.t }}</span>
            <span class="sub">{{ sortLoot(p.l).map((t) => LOOT[t].label).join(' · ') || 'Ohne Loottyp' }}</span>
          </span>
        </button>
      </div>
    </div>
    <button type="button" class="collapse panel" :aria-label="ui.sidebarOpen ? 'Seitenleiste einklappen' : 'Seitenleiste ausklappen'" @click="ui.toggleSidebar()">
      <ChevronLeft :size="18" :class="{ flip: !ui.sidebarOpen }" />
    </button>
  </aside>
</template>

<style scoped>
.sidebar { position: absolute; z-index: 850; top: calc(var(--header-h) + 16px); left: 12px; bottom: 12px; width: var(--sidebar-w); display: flex; flex-direction: column; transition: transform 0.25s ease; }
.sidebar.closed { transform: translateX(calc(-100% - 12px)); }
.top { padding: 12px 12px 8px; border-bottom: 1px solid var(--line); display: flex; flex-direction: column; gap: 9px; }
.search { position: relative; }
.search .icon { position: absolute; left: 10px; top: 11px; color: var(--faint); }
.search .input { padding-left: 32px; padding-right: 34px; }
.search .clear { position: absolute; right: 4px; top: 4px; }
.chips { display: flex; flex-wrap: wrap; gap: 5px; }
.chip { height: 26px; font-size: 12.5px; padding: 0 9px; }
.switch { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; cursor: pointer; }
.switch input { accent-color: var(--pink); }
.count { display: flex; align-items: center; justify-content: space-between; font-size: 13px; color: var(--muted); min-height: 30px; }
.count strong { color: var(--text); }
.list { flex: 1; position: relative; }
.pad { padding: 16px; }
.row { position: absolute; left: 0; right: 0; height: 52px; display: flex; align-items: center; gap: 10px; padding: 0 12px; border: 0; background: none; text-align: left; cursor: pointer; border-bottom: 1px solid rgba(255,255,255,0.04); }
.row:hover { background: var(--panel-hover); }
.row.active { background: linear-gradient(90deg, rgba(255, 61, 139, 0.18), transparent); }
.row.gone { opacity: 0.5; }
.ring { width: 22px; height: 22px; border-radius: 50%; padding: 3px; flex: none; }
.ring span { display: block; width: 100%; height: 100%; border-radius: 50%; background: var(--panel-solid); }
.text { display: flex; flex-direction: column; min-width: 0; }
.name { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
.sub { font-size: 12px; color: var(--faint); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.collapse { position: absolute; top: 12px; right: -44px; width: 34px; height: 34px; border: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text); }
.flip { transform: rotate(180deg); }
@media (max-width: 760px) {
  .sidebar { top: auto; left: 0; right: 0; bottom: 0; width: auto; height: 62vh; border-radius: 16px 16px 0 0; }
  .sidebar.closed { transform: translateY(calc(100% - 64px)); }
  .collapse { top: -44px; right: 12px; }
  .collapse svg { transform: rotate(90deg); }
  .collapse .flip { transform: rotate(-90deg); }
}
</style>
