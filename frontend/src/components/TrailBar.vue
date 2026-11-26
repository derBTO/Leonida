<script setup lang="ts">
import { ChevronLeft, ChevronRight, Eye, Maximize2, Puzzle, X } from 'lucide-vue-next'
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { useTrails } from '../stores/trails'
import { useUi } from '../stores/ui'

/** Leiste der aktiven Spur über der Karte: Schritt vor/zurück, abhaken, Übersicht. */
const trails = useTrails()
const ui = useUi()
const trail = computed(() => trails.active!)
const step = computed(() => trail.value.steps[trails.stepIndex])
const hidden = computed(() => (step.value ? trails.isHidden(trail.value, step.value.id) : false))
const label = computed(() => {
  if (!step.value) return ''
  const same = trail.value.steps.filter((s) => s.position === step.value!.position)
  return same.length > 1 ? `${step.value.position}${String.fromCharCode(97 + same.indexOf(step.value))}` : String(step.value.position)
})

function go(delta: number) {
  const next = trails.stepIndex + delta
  if (next < 0 || next >= trail.value.steps.length) return
  trails.stepIndex = next
  trails.focusRequest++
}
async function toggleDone() {
  if (!step.value) return
  try {
    await trails.setDone(trail.value.id, step.value.id, !step.value.done)
  } catch (e) {
    ui.error(e)
  }
}
function close() {
  trails.clear()
}
</script>

<template>
  <div class="trail-bar panel" role="region" aria-label="Aktives Puzzle">
    <div class="head">
      <Puzzle :size="16" class="icon" />
      <RouterLink :to="`/puzzle/${trail.id}`" class="name">{{ trail.name }}</RouterLink>
      <span class="faint progress">{{ trail.steps.filter((s) => s.done).length }}/{{ trail.steps.length }}</span>
      <button class="btn ghost sm icon" type="button" title="Ganzes Puzzle zeigen" aria-label="Ganzes Puzzle zeigen" @click="trails.fitRequest++"><Maximize2 :size="15" /></button>
      <button class="btn ghost sm icon" type="button" title="Puzzle ausblenden" aria-label="Puzzle ausblenden" @click="close"><X :size="16" /></button>
    </div>
    <div v-if="step" class="step">
      <button class="btn sm icon" type="button" aria-label="Vorheriger Schritt" :disabled="trails.stepIndex === 0" @click="go(-1)"><ChevronLeft :size="16" /></button>
      <div class="text">
        <template v-if="hidden">
          <span class="faint">Schritt {{ label }} von {{ trail.step_count }} – verdeckt</span>
          <button class="btn ghost sm" type="button" @click="trails.reveal(step.id)"><Eye :size="14" /> Aufdecken</button>
        </template>
        <template v-else>
          <span class="faint">Schritt {{ label }} von {{ trail.step_count }}<template v-if="!step.pin"> · ohne Ort</template></span>
          <strong>{{ step.title }}</strong>
        </template>
      </div>
      <label v-if="!hidden" class="done" :title="step.done ? 'Als offen markieren' : 'Als erledigt markieren'">
        <input type="checkbox" :checked="step.done" @change="toggleDone" /> erledigt
      </label>
      <button class="btn sm icon" type="button" aria-label="Nächster Schritt" :disabled="trails.stepIndex >= trail.steps.length - 1" @click="go(1)"><ChevronRight :size="16" /></button>
    </div>
    <p v-else class="faint empty-steps">Dieses Puzzle hat noch keine Schritte.</p>
  </div>
</template>

<style scoped>
.trail-bar { position: absolute; z-index: 870; bottom: 24px; left: 50%; transform: translateX(-50%); width: min(520px, calc(100vw - 24px)); padding: 8px 10px; display: flex; flex-direction: column; gap: 6px; }
.head { display: flex; align-items: center; gap: 8px; }
.head .icon { color: var(--pink); flex: none; }
.name { flex: 1; font-family: var(--font-display); font-weight: 700; font-size: 17px; letter-spacing: 0.04em; text-transform: uppercase; text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.progress { font-size: 12px; }
.step { display: flex; align-items: center; gap: 8px; }
.text { flex: 1; display: flex; flex-direction: column; min-width: 0; font-size: 13px; }
.text strong { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-size: 14px; }
.text .btn { align-self: flex-start; }
.done { display: flex; align-items: center; gap: 5px; font-size: 12.5px; color: var(--muted); cursor: pointer; white-space: nowrap; }
.done input { accent-color: #4ade80; }
.empty-steps { margin: 0; font-size: 13px; }
@media (max-width: 760px) { .trail-bar { left: 12px; right: 12px; width: auto; transform: none; bottom: 124px; } }
</style>
