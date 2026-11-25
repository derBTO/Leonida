<script setup lang="ts">
import { Plus, Route, Search } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import type { Trail, TrailSummary } from '../api/types'
import TrailForm from '../components/TrailForm.vue'
import { terms } from '../lib/pinFilter'
import { load as loadPref, save as savePref } from '../lib/storage'
import { useSession } from '../stores/session'
import { useTrails } from '../stores/trails'
import { useUi } from '../stores/ui'

/** Übersicht aller Spuren mit eigenem Fortschritt. */
const session = useSession()
const trails = useTrails()
const ui = useUi()
const router = useRouter()
const items = ref<TrailSummary[] | null>(null)
const q = ref('')
const prefs = loadPref('trails-page', { mine: false, open: false })
const onlyMine = ref(prefs.mine)
const onlyOpen = ref(prefs.open)
watch([onlyMine, onlyOpen], () => savePref('trails-page', { mine: onlyMine.value, open: onlyOpen.value }))
const creating = ref(false)
// Zurück vom Pin-Setzen: Editor mit dem Entwurf wieder öffnen
const draft = trails.draft?.resume && trails.draft.trailId === null ? trails.draft : null
if (draft) creating.value = true

async function load() {
  try {
    items.value = (await api.get<{ items: TrailSummary[] }>('/api/trails')).items
  } catch (e) {
    ui.error(e)
  }
}
onMounted(load)
watch(() => [trails.version, session.user?.id], load)

const shown = computed(() => {
  const words = terms(q.value)
  return (items.value ?? []).filter((t) => {
    if (onlyMine.value && t.created_by_id !== session.user?.id) return false
    if (onlyOpen.value && t.steps_total > 0 && t.steps_done >= t.steps_total) return false
    const h = `${t.name} ${t.description ?? ''}`.toLowerCase()
    return words.every((w) => h.includes(w))
  })
})
const pct = (t: TrailSummary) => (t.steps_total ? Math.round((t.steps_done / t.steps_total) * 100) : 0)
function created(t: Trail) {
  creating.value = false
  router.push(`/spuren/${t.id}`)
}
</script>

<template>
  <div class="page">
    <div class="head">
      <h1>Spuren</h1>
      <div class="tools">
        <div class="search"><Search :size="16" class="icon" /><input class="input" type="search" placeholder="Suchen …" v-model="q" /></div>
        <label v-if="session.loggedIn" class="toggle"><input type="checkbox" v-model="onlyMine" /> Nur meine</label>
        <label v-if="session.loggedIn" class="toggle"><input type="checkbox" v-model="onlyOpen" /> Nur offene</label>
        <button v-if="session.canEdit" class="btn primary" type="button" @click="creating = true"><Plus :size="16" /> Neue Spur</button>
      </div>
    </div>
    <p class="muted intro">Eine Spur verbindet Stationen in fester Reihenfolge: Treffpunkt → Hinweis → Code → Loot. Den Fortschritt hakt jede Person für sich ab.</p>
    <div v-if="!items" class="muted">Lade …</div>
    <div v-else-if="!shown.length" class="empty">{{ items.length ? 'Keine Spur passt zum Filter.' : 'Noch keine Spuren angelegt.' }}</div>
    <div class="grid">
      <RouterLink v-for="t in shown" :key="t.id" :to="`/spuren/${t.id}`" class="tile panel">
        <div class="top"><Route :size="18" /><h3>{{ t.name }}</h3></div>
        <p v-if="t.description" class="desc muted">{{ t.description }}</p>
        <div class="meta faint">{{ t.step_count }} Schritt{{ t.step_count === 1 ? '' : 'e' }}<template v-if="t.created_by"> · von {{ t.created_by }}</template></div>
        <div v-if="session.loggedIn && t.steps_total" class="bar" :title="`${t.steps_done} von ${t.steps_total} erledigt`">
          <span :style="{ width: `${pct(t)}%` }" />
        </div>
        <div v-if="session.loggedIn && t.steps_total" class="faint small">{{ t.steps_done === t.steps_total ? 'Erledigt' : `${t.steps_done} von ${t.steps_total} erledigt` }}</div>
      </RouterLink>
    </div>
    <TrailForm v-if="creating" :draft="draft" @close="creating = false" @saved="created" />
  </div>
</template>

<style scoped>
@import './page.css';
.head { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.tools { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.search { position: relative; }
.search .icon { position: absolute; left: 10px; top: 12px; color: var(--faint); }
.search .input { padding-left: 32px; width: 240px; }
.toggle { display: flex; align-items: center; gap: 6px; color: var(--muted); font-size: 14px; cursor: pointer; }
.toggle input { accent-color: var(--pink); }
.intro { margin: 8px 0 18px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; }
.tile { display: flex; flex-direction: column; gap: 6px; padding: 14px 16px; text-decoration: none; transition: transform 0.12s, border-color 0.12s; }
.tile:hover { transform: translateY(-2px); border-color: var(--pink); }
.top { display: flex; align-items: center; gap: 8px; color: var(--pink); }
.top h3 { color: var(--text); font-size: 20px; margin: 0; }
.desc { margin: 0; font-size: 13.5px; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.meta, .small { font-size: 12.5px; }
.bar { height: 6px; border-radius: 3px; background: rgba(255,255,255,0.08); overflow: hidden; margin-top: 4px; }
.bar span { display: block; height: 100%; background: linear-gradient(90deg, #4ade80, #2dd4bf); }
</style>
