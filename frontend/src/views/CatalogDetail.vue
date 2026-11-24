<script setup lang="ts">
import { ArrowLeft, MapPin, Pencil, Trash2 } from 'lucide-vue-next'
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api, ApiError } from '../api/client'
import type { Location, Vehicle, Weapon } from '../api/types'
import VehicleCard from '../components/VehicleCard.vue'
import VehicleForm from '../components/VehicleForm.vue'
import WeaponCard from '../components/WeaponCard.vue'
import WeaponForm from '../components/WeaponForm.vue'
import { formatDate } from '../lib/format'
import { useCatalog } from '../stores/catalog'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'

/** Detailansicht einer Waffe bzw. eines Fahrzeugs mit allen Fundorten. */
const props = defineProps<{ kind: 'weapons' | 'vehicles'; id: string }>()
const session = useSession()
const ui = useUi()
const pins = usePins()
const catalog = useCatalog()
const router = useRouter()
const item = ref<Weapon | Vehicle | null>(null)
const error = ref<string | null>(null)
const editing = ref(false)
const isWeapons = computed(() => props.kind === 'weapons')
const base = computed(() => (isWeapons.value ? '/waffen' : '/fahrzeuge'))

async function load() {
  error.value = null
  try {
    item.value = (await api.get<{ item: Weapon | Vehicle }>(`/api/${props.kind}/${props.id}`)).item
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  }
}
watch(() => [props.kind, props.id], load, { immediate: true })

async function remove() {
  if (!item.value) return
  const n = item.value.locations.length
  const text = n
    ? `„${item.value.name}“ ist ${n} Fundort(en) zugeordnet. Trotzdem löschen? Die Zuordnungen werden entfernt.`
    : `„${item.value.name}“ wirklich löschen?`
  if (!window.confirm(text)) return
  try {
    await api.delete(`/api/${props.kind}/${item.value.id}?force=1`)
    catalog.invalidate()
    pins.refresh().catch(() => {})
    ui.toast('Gelöscht.', 'ok')
    router.push(base.value)
  } catch (e) {
    ui.error(e instanceof ApiError ? e.message : e)
  }
}
function saved(next: Weapon | Vehicle) {
  editing.value = false
  item.value = next
  pins.refresh().catch(() => {})
}
const statusLabel = (l: Location) => session.label('pin_status', l.status)
</script>

<template>
  <div class="page">
    <div class="wrap">
      <RouterLink :to="base" class="back muted"><ArrowLeft :size="16" /> {{ isWeapons ? 'Alle Waffen' : 'Alle Fahrzeuge' }}</RouterLink>
      <div v-if="error" class="error-text">{{ error }}</div>
      <div v-else-if="!item" class="muted">Lade …</div>
      <div v-else class="layout">
        <div class="panel card-box">
          <WeaponCard v-if="isWeapons" :weapon="item as Weapon" />
          <VehicleCard v-else :vehicle="item as Vehicle" />
          <div v-if="session.canEdit" class="actions">
            <button class="btn" type="button" @click="editing = true"><Pencil :size="15" /> Bearbeiten</button>
            <button class="btn danger" type="button" @click="remove"><Trash2 :size="15" /> Löschen</button>
          </div>
          <div class="meta faint">
            Erstellt {{ formatDate(item.created_at) }}<template v-if="item.created_by"> von {{ item.created_by }}</template> ·
            bearbeitet {{ formatDate(item.updated_at) }}<template v-if="item.updated_by"> von {{ item.updated_by }}</template>
          </div>
        </div>
        <aside class="panel locations">
          <h2>Fundorte <span class="muted">({{ item.locations.length }})</span></h2>
          <p v-if="!item.locations.length" class="empty">Noch keinem Pin zugeordnet.</p>
          <RouterLink v-for="l in item.locations" :key="l.id" :to="`/pin/${l.id}`" class="loc">
            <MapPin :size="16" />
            <span><strong>{{ l.title }}</strong><small class="faint">{{ statusLabel(l) }} · x {{ l.x.toFixed(0) }}, y {{ l.y.toFixed(0) }}</small></span>
          </RouterLink>
        </aside>
      </div>
    </div>
    <WeaponForm v-if="editing && isWeapons" :weapon="item as Weapon" @close="editing = false" @saved="saved" />
    <VehicleForm v-if="editing && !isWeapons" :vehicle="item as Vehicle" @close="editing = false" @saved="saved" />
  </div>
</template>

<style scoped>
@import './page.css';
.back { display: inline-flex; align-items: center; gap: 6px; text-decoration: none; margin-bottom: 14px; }
.layout { display: grid; grid-template-columns: minmax(0, 600px) minmax(260px, 1fr); gap: 16px; align-items: start; }
.card-box { padding: 20px; }
.actions { display: flex; gap: 8px; margin-top: 16px; }
.meta { font-size: 12px; margin-top: 10px; }
.locations { padding: 16px; }
.locations h2 { font-size: 22px; margin-bottom: 8px; }
.loc { display: flex; gap: 10px; align-items: flex-start; padding: 8px; border-radius: 8px; text-decoration: none; color: var(--pink); }
.loc:hover { background: var(--panel-hover); }
.loc span { display: flex; flex-direction: column; color: var(--text); }
@media (max-width: 860px) { .layout { grid-template-columns: 1fr; } }
</style>
