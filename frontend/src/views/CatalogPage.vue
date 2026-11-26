<script setup lang="ts">
import { MapPin, Plus, Search } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import type { LookupCategory, Vehicle, VehicleSummary, Weapon, WeaponSummary } from '../api/types'
import AuthorSelect from '../components/AuthorSelect.vue'
import LegendaryStar from '../components/LegendaryStar.vue'
import MediaImage from '../components/MediaImage.vue'
import VehicleForm from '../components/VehicleForm.vue'
import WeaponForm from '../components/WeaponForm.vue'
import { formatStat } from '../lib/format'
import { useCatalog } from '../stores/catalog'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'

/** Durchsuchbare Übersicht aller Waffen bzw. Fahrzeuge. */
const props = defineProps<{ kind: 'weapons' | 'vehicles' }>()
const catalog = useCatalog()
const session = useSession()
const ui = useUi()
const router = useRouter()
const q = ref('')
const group = ref<number | ''>('')
const onlyLegendary = ref(false)
const author = ref<number | null>(null)
const creating = ref(false)
const isWeapons = computed(() => props.kind === 'weapons')
const groupCategory = computed<LookupCategory>(() => (isWeapons.value ? 'weapon_type' : 'vehicle_class'))
const base = computed(() => (isWeapons.value ? '/waffen' : '/fahrzeuge'))

async function load() {
  try {
    await (isWeapons.value ? catalog.loadWeapons(true) : catalog.loadVehicles(true))
    await catalog.loadLookups(groupCategory.value)
  } catch (e) {
    ui.error(e)
  }
}
onMounted(load)
watch(() => props.kind, () => { q.value = ''; group.value = ''; onlyLegendary.value = false; load() })

const items = computed<(WeaponSummary | VehicleSummary)[]>(() => {
  const all = ((isWeapons.value ? catalog.weapons : catalog.vehicles) ?? []) as (WeaponSummary | VehicleSummary)[]
  const words = q.value.toLowerCase().split(/\s+/).filter(Boolean)
  return all.filter((i) => {
    const groupId = 'weapon_type' in i ? i.weapon_type?.id : (i as VehicleSummary).vehicle_class?.id
    if (group.value !== '' && groupId !== group.value) return false
    if (onlyLegendary.value && !('weapon_type' in i && i.is_legendary)) return false
    // angelegt oder bearbeitet (editor_ids), sonst nur angelegt
    if (author.value !== null && session.user && !(i.editor_ids ?? [i.created_by_id]).includes(author.value)) return false
    const text = [i.name, 'weapon_type' in i ? i.weapon_type?.name : (i as VehicleSummary).manufacturer?.name, 'vehicle_class' in i ? i.vehicle_class?.name : ''].join(' ').toLowerCase()
    return words.every((w) => text.includes(w))
  })
})
const sub = (i: WeaponSummary | VehicleSummary) =>
  'weapon_type' in i ? i.weapon_type?.name ?? 'Typ unbekannt' : [(i as VehicleSummary).manufacturer?.name, (i as VehicleSummary).vehicle_class?.name].filter(Boolean).join(' · ') || 'Hersteller unbekannt'
const mainStats = (i: WeaponSummary | VehicleSummary): [string, number | null][] =>
  'weapon_type' in i
    ? [['Schaden', i.stats.damage], ['Genauigkeit', i.stats.accuracy], ['Reichweite', i.stats.range]]
    : [['Tempo', (i as VehicleSummary).stats.speed], ['Beschl.', (i as VehicleSummary).stats.acceleration], ['Belastb.', (i as VehicleSummary).stats.durability]]

function created(item: Weapon | Vehicle) {
  creating.value = false
  router.push(`${base.value}/${item.id}`)
}
</script>

<template>
  <div class="page">
    <div class="head">
      <h1>{{ isWeapons ? 'Waffen' : 'Fahrzeuge' }}</h1>
      <div class="tools">
        <div class="search"><Search :size="16" class="icon" /><input class="input" type="search" placeholder="Suchen …" v-model="q" /></div>
        <select class="input sel" v-model="group" :aria-label="isWeapons ? 'Waffentyp' : 'Fahrzeugklasse'">
          <option value="">{{ isWeapons ? 'Alle Waffentypen' : 'Alle Klassen' }}</option>
          <option v-for="v in catalog.lookups[groupCategory] ?? []" :key="v.id" :value="v.id">{{ v.name }}</option>
        </select>
        <AuthorSelect v-model="author" />
        <label v-if="isWeapons" class="legend-toggle"><input type="checkbox" v-model="onlyLegendary" /> Nur legendäre <LegendaryStar :size="14" /></label>
        <button v-if="session.canEditCatalog" class="btn primary" type="button" @click="creating = true"><Plus :size="16" /> {{ isWeapons ? 'Neue Waffe' : 'Neues Fahrzeug' }}</button>
      </div>
    </div>
    <div class="muted count">{{ items.length }} Einträge</div>
    <div class="grid">
      <RouterLink v-for="i in items" :key="i.id" :to="`${base}/${i.id}`" class="tile panel">
        <div class="img"><MediaImage :media="i.image" thumb :alt="i.name" /></div>
        <div class="info">
          <h3 :class="{ 'legendary-name': 'is_legendary' in i && i.is_legendary }">{{ i.name }}<LegendaryStar v-if="'is_legendary' in i && i.is_legendary" :size="16" /></h3>
          <div class="sub">{{ sub(i) }}</div>
          <div class="mini">
            <div v-for="[label, v] in mainStats(i)" :key="label"><span>{{ label }}</span><b>{{ formatStat(v) }}</b><i><em :style="{ width: `${(v ?? 0) * 10}%` }" /></i></div>
          </div>
          <div class="loc faint"><MapPin :size="13" /> {{ i.location_count ?? 0 }} Fundorte</div>
        </div>
      </RouterLink>
    </div>
    <div v-if="(isWeapons ? catalog.weapons : catalog.vehicles) && !items.length" class="empty">Nichts gefunden.</div>
    <WeaponForm v-if="creating && isWeapons" :initial-name="q" @close="creating = false" @saved="created" />
    <VehicleForm v-if="creating && !isWeapons" :initial-name="q" @close="creating = false" @saved="created" />
  </div>
</template>

<style scoped>
@import './page.css';
.head { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.head h1 { font-size: 40px; }
.tools { display: flex; gap: 8px; flex-wrap: wrap; }
.search { position: relative; }
.search .icon { position: absolute; left: 10px; top: 11px; color: var(--faint); }
.search .input { padding-left: 32px; width: 240px; }
.sel { width: 200px; }
.legend-toggle { display: flex; align-items: center; gap: 6px; color: var(--muted); cursor: pointer; padding: 0 4px; }
.legend-toggle input { accent-color: #facc15; }
.count { margin: 8px 0 14px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 14px; }
.tile { text-decoration: none; overflow: hidden; display: flex; flex-direction: column; transition: transform 0.15s, box-shadow 0.15s; }
.tile:hover { transform: translateY(-2px); box-shadow: 0 18px 48px rgba(0,0,0,0.55), 0 0 0 1px var(--pink); }
.img { aspect-ratio: 16 / 9; background: radial-gradient(ellipse at center, rgba(139, 92, 246, 0.18), rgba(0, 0, 0, 0.3) 70%); }
.info { padding: 10px 12px 12px; }
.info h3 { font-size: 21px; line-height: 1.1; }
.sub { color: var(--muted); font-size: 13px; margin-bottom: 8px; }
.mini { display: flex; flex-direction: column; gap: 4px; }
.mini div { display: grid; grid-template-columns: 82px 30px 1fr; align-items: center; gap: 6px; font-size: 11.5px; }
.mini span { font-family: var(--font-display); font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--faint); }
.mini b { text-align: right; font-variant-numeric: tabular-nums; }
.mini i { height: 3px; background: rgba(255,255,255,0.12); }
.mini em { display: block; height: 100%; background: #eee; }
.loc { margin-top: 8px; font-size: 12px; display: flex; align-items: center; gap: 4px; }
</style>
