<script setup lang="ts">
import { ImagePlus, Trash2 } from 'lucide-vue-next'
import { ref } from 'vue'
import type { MediaRef } from '../api/types'
import MediaImage from './MediaImage.vue'

/** Hauptbild (Waffe/Fahrzeug): Auswahl, Drag-and-drop oder Einfügen aus der Zwischenablage. */
defineProps<{ media: MediaRef | null; pending: File | null; busy?: boolean }>()
const emit = defineEmits<{ select: [File]; remove: [] }>()
const over = ref(false)
const input = ref<HTMLInputElement | null>(null)
const preview = ref<string | null>(null)

function take(file: File | undefined | null) {
  if (!file || !file.type.startsWith('image/')) return
  if (preview.value) URL.revokeObjectURL(preview.value)
  preview.value = URL.createObjectURL(file)
  emit('select', file)
}
function onDrop(e: DragEvent) {
  over.value = false
  take(e.dataTransfer?.files?.[0])
}
function onPaste(e: ClipboardEvent) {
  const item = [...(e.clipboardData?.items ?? [])].find((i) => i.type.startsWith('image/'))
  if (item) {
    take(item.getAsFile())
    e.preventDefault()
  }
}
</script>

<template>
  <div class="field">
    <span class="label">Bild</span>
    <div class="drop" :class="{ over }" tabindex="0" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="onDrop" @paste="onPaste"
      @keydown.enter.prevent="input?.click()">
      <img v-if="pending && preview" :src="preview" alt="Vorschau" class="img" />
      <MediaImage v-else-if="media" :media="media" alt="Bild" class="img" />
      <div class="hint">
        <ImagePlus :size="18" />
        <span>Bild ablegen, mit Strg/⌘+V einfügen oder</span>
        <button type="button" class="btn sm" :disabled="busy" @click="input?.click()">Datei wählen</button>
        <button v-if="media && !pending" type="button" class="btn sm danger" :disabled="busy" @click="emit('remove')"><Trash2 :size="14" /> Entfernen</button>
      </div>
      <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" hidden @change="take(($event.target as HTMLInputElement).files?.[0])" />
    </div>
  </div>
</template>

<style scoped>
.drop { border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); padding: 10px; display: flex; gap: 12px; align-items: center; }
.drop.over { border-color: var(--orange); background: rgba(255, 138, 61, 0.08); }
.img { width: 140px; height: 80px; object-fit: contain; border-radius: 6px; background: rgba(0, 0, 0, 0.3); flex: none; }
.hint { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; color: var(--muted); font-size: 13px; }
</style>
