<script setup lang="ts">
import { ArrowDown, ArrowUp, ImagePlus, Plus, Trash2, X } from 'lucide-vue-next'
import { ref } from 'vue'
import { api } from '../api/client'
import type { HintStepRow as StepRow, MediaRef } from '../api/types'
import { useUi } from '../stores/ui'

/**
 * Schritte eines Hinweises bearbeiten: jeder Schritt hat einen Text und optional ein Bild
 * (Bild oben, Text darunter). Bilder werden sofort hochgeladen und beim Speichern zugeordnet.
 */

const steps = defineModel<StepRow[]>({ required: true })
defineProps<{ errors?: Record<string, string[]> }>()
const ui = useUi()
const uploading = ref<number | null>(null)
let seq = Math.max(0, ...steps.value.map((s) => s.key))

function add() {
  steps.value = [...steps.value, { key: ++seq, text: '', image: null }]
}
function remove(i: number) {
  steps.value = steps.value.filter((_, j) => j !== i)
}
function move(i: number, d: number) {
  const j = i + d
  if (j < 0 || j >= steps.value.length) return
  const s = [...steps.value]
  ;[s[i], s[j]] = [s[j], s[i]]
  steps.value = s
}
async function upload(step: StepRow, files: FileList | File[] | null | undefined) {
  const file = files?.[0]
  if (!file) return
  const data = new FormData()
  data.append('file', file)
  uploading.value = step.key
  try {
    step.image = (await api.post<{ media: MediaRef }>('/api/media', data)).media
  } catch (e) {
    ui.error(e)
  } finally {
    uploading.value = null
  }
}
function paste(step: StepRow, e: ClipboardEvent) {
  const files = [...(e.clipboardData?.files ?? [])].filter((f) => f.type.startsWith('image/'))
  if (files.length) {
    e.preventDefault()
    upload(step, files)
  }
}
</script>

<template>
  <ol class="steps">
    <li v-for="(s, i) in steps" :key="s.key" class="step">
      <div class="num">{{ i + 1 }}</div>
      <div class="body">
        <div v-if="s.image" class="img">
          <img :src="s.image.thumb_url" :alt="`Bild zu Schritt ${i + 1}`" />
          <button class="btn ghost sm icon" type="button" aria-label="Bild entfernen" title="Bild entfernen" @click="s.image = null"><X :size="15" /></button>
        </div>
        <label v-else class="add-img" :class="{ busy: uploading === s.key }" @dragover.prevent @drop.prevent="upload(s, $event.dataTransfer?.files)">
          <ImagePlus :size="16" /> {{ uploading === s.key ? 'Lade hoch …' : 'Bild hinzufügen (optional, auch per Ziehen oder Einfügen)' }}
          <input type="file" accept="image/jpeg,image/png,image/webp" hidden @change="upload(s, ($event.target as HTMLInputElement).files); ($event.target as HTMLInputElement).value = ''" />
        </label>
        <textarea class="input" v-model="s.text" maxlength="10000" :placeholder="s.image ? 'Text zum Bild' : 'z. B. Als Erstes müssen wir auf die Insel schwimmen.'" :aria-label="`Schritt ${i + 1}: Text`" @paste="paste(s, $event)" />
        <div v-if="errors?.[`steps.${i}.text`]" class="error-text">{{ errors[`steps.${i}.text`][0] }}</div>
      </div>
      <div class="tools">
        <button class="btn ghost sm icon" type="button" aria-label="Nach oben" :disabled="i === 0" @click="move(i, -1)"><ArrowUp :size="15" /></button>
        <button class="btn ghost sm icon" type="button" aria-label="Nach unten" :disabled="i === steps.length - 1" @click="move(i, 1)"><ArrowDown :size="15" /></button>
        <button class="btn ghost sm icon" type="button" aria-label="Schritt entfernen" @click="remove(i)"><Trash2 :size="15" /></button>
      </div>
    </li>
  </ol>
  <button class="btn" type="button" @click="add"><Plus :size="15" /> Schritt hinzufügen</button>
</template>

<style scoped>
.steps { list-style: none; margin: 0 0 10px; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.step { display: grid; grid-template-columns: 32px 1fr auto; gap: 10px; padding: 10px; border: 1px solid var(--line); border-radius: 10px; background: rgba(255, 255, 255, 0.02); }
.num { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #0d2a28; border: 1.5px solid #2dd4bf; color: #2dd4bf; font-weight: 800; font-size: 13px; }
.body { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.body textarea { min-height: 60px; }
.img { position: relative; align-self: flex-start; }
.img img { display: block; max-width: 100%; max-height: 180px; border-radius: 8px; }
.img .btn { position: absolute; top: 4px; right: 4px; background: rgba(0, 0, 0, 0.6); }
.add-img { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); color: var(--muted); font-size: 13px; cursor: pointer; }
.add-img:hover { border-color: #2dd4bf; color: var(--text); }
.add-img.busy { opacity: 0.6; pointer-events: none; }
.tools { display: flex; flex-direction: column; gap: 2px; }
</style>
