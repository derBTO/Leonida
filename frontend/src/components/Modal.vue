<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { onBeforeUnmount, onMounted, ref } from 'vue'

/** fill: Inhalt regelt das Scrollen selbst (z. B. fester Kopf, scrollender Teil) */
const props = withDefaults(defineProps<{ title: string; width?: string; dirty?: boolean; fill?: boolean }>(), { width: '640px', dirty: false, fill: false })
const emit = defineEmits<{ close: [] }>()
const box = ref<HTMLElement | null>(null)

function requestClose() {
  if (props.dirty && !window.confirm('Ungespeicherte Änderungen verwerfen?')) return
  emit('close')
}
function onKey(e: KeyboardEvent) {
  if (e.key === 'Escape') requestClose()
}
onMounted(() => {
  document.addEventListener('keydown', onKey)
  box.value?.querySelector<HTMLElement>('input, select, textarea, button:not(.close)')?.focus()
})
onBeforeUnmount(() => document.removeEventListener('keydown', onKey))
defineExpose({ requestClose })

/** Am Kopf verschiebbar (nicht auf schmalen Bildschirmen, dort füllt der Dialog alles); Doppelklick setzt zurück */
const offset = ref({ x: 0, y: 0 })
let drag: { x: number; y: number; ox: number; oy: number } | null = null
function onDragStart(e: PointerEvent) {
  if (e.button !== 0 || window.innerWidth <= 640 || (e.target as HTMLElement).closest('button')) return
  drag = { x: e.clientX, y: e.clientY, ox: offset.value.x, oy: offset.value.y }
  ;(e.currentTarget as HTMLElement).setPointerCapture(e.pointerId)
  e.preventDefault()
}
function onDragMove(e: PointerEvent) {
  if (!drag || !box.value) return
  const rect = box.value.getBoundingClientRect()
  const nx = drag.ox + e.clientX - drag.x
  const ny = drag.oy + e.clientY - drag.y
  // Kopf bleibt im Fenster: mindestens 80 px seitlich sichtbar, oben nicht darüber hinaus
  const baseLeft = rect.left - offset.value.x
  const baseTop = rect.top - offset.value.y
  offset.value = {
    x: Math.min(Math.max(nx, 80 - baseLeft - rect.width), window.innerWidth - 80 - baseLeft),
    y: Math.min(Math.max(ny, -baseTop), window.innerHeight - 60 - baseTop),
  }
}
function onDragEnd() {
  drag = null
}
</script>

<template>
  <Teleport to="body">
    <div class="backdrop" @mousedown.self="requestClose">
      <div ref="box" class="modal panel" role="dialog" aria-modal="true" :aria-label="title" :style="{ maxWidth: width, transform: offset.x || offset.y ? `translate(${offset.x}px, ${offset.y}px)` : undefined }">
        <header class="drag" @pointerdown="onDragStart" @pointermove="onDragMove" @pointerup="onDragEnd" @pointercancel="onDragEnd" @dblclick="offset = { x: 0, y: 0 }">
          <h2>{{ title }}</h2>
          <button class="btn ghost icon close" type="button" aria-label="Schließen" @click="requestClose"><X :size="18" /></button>
        </header>
        <div class="body" :class="fill ? 'fill' : 'scroll'"><slot /></div>
        <footer v-if="$slots.footer"><slot name="footer" /></footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.backdrop {
  position: fixed; inset: 0; z-index: 2000; background: rgba(5, 4, 10, 0.55);
  display: flex; align-items: center; justify-content: center; padding: 16px; animation: fade-in 0.15s ease-out;
}
.modal { width: 100%; max-height: min(92vh, 1000px); display: flex; flex-direction: column; background: var(--panel-solid); }
header { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px 10px 20px; border-bottom: 1px solid var(--line); }
header h2 { font-size: 22px; }
@media (min-width: 641px) {
  header.drag { cursor: move; user-select: none; touch-action: none; }
}
.body { padding: 16px 20px 20px; flex: 1; }
.body.fill { display: flex; flex-direction: column; min-height: 0; overflow: hidden; padding-bottom: 0; }
footer { display: flex; justify-content: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--line); flex-wrap: wrap; }
@media (max-width: 640px) {
  .backdrop { padding: 0; align-items: stretch; }
  .modal { max-width: none !important; max-height: none; border-radius: 0; }
  .modal:has(.body.fill) { height: 100%; }
}
</style>
