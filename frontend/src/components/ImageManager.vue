<script setup lang="ts">
import { GripVertical, ImagePlus, Trash2 } from 'lucide-vue-next'
import { onBeforeUnmount, ref } from 'vue'
import { api } from '../api/client'
import type { GalleryImage } from '../api/types'
import { useUi } from '../stores/ui'

/**
 * Bilder eines Pins bearbeiten: hinzufügen (Datei, Drag-and-drop, Zwischenablage),
 * Reihenfolge per Ziehen, Beschreibung, Löschen. Bei neuen Pins (pinId = null) werden
 * Bilder gesammelt und nach dem ersten Speichern mit flush(pinId) hochgeladen.
 */
const props = defineProps<{ pinId: number | null; images: GalleryImage[] }>()
const emit = defineEmits<{ changed: [GalleryImage[]] }>()
const ui = useUi()
const list = ref<GalleryImage[]>([...props.images])
const pending = ref<{ key: string; file: File; url: string; caption: string }[]>([])
const uploading = ref(0)
const over = ref(false)
const input = ref<HTMLInputElement | null>(null)
let dragIndex: number | null = null

function update(next: GalleryImage[]) {
  list.value = next
  emit('changed', next)
}

async function upload(file: File, caption = '', pinId = props.pinId!) {
  const data = new FormData()
  data.append('file', file)
  if (caption) data.append('caption', caption)
  uploading.value++
  try {
    const res = await api.post<{ image: GalleryImage }>(`/api/pins/${pinId}/images`, data)
    update([...list.value, res.image])
  } catch (e) {
    ui.error(e)
  } finally {
    uploading.value--
  }
}

function add(files: Iterable<File>) {
  for (const file of files) {
    if (!file.type.startsWith('image/')) continue
    if (props.pinId) upload(file)
    else pending.value.push({ key: Math.random().toString(36), file, url: URL.createObjectURL(file), caption: '' })
  }
}

function onPaste(e: ClipboardEvent) {
  const files = [...(e.clipboardData?.items ?? [])].filter((i) => i.type.startsWith('image/')).map((i) => i.getAsFile()).filter((f): f is File => !!f)
  if (files.length) {
    e.preventDefault()
    add(files)
    ui.toast(files.length === 1 ? 'Screenshot eingefügt.' : `${files.length} Bilder eingefügt.`, 'ok')
  }
}
document.addEventListener('paste', onPaste)
onBeforeUnmount(() => {
  document.removeEventListener('paste', onPaste)
  pending.value.forEach((p) => URL.revokeObjectURL(p.url))
})

async function saveCaption(img: GalleryImage) {
  try {
    await api.patch(`/api/pin-images/${img.id}`, { caption: img.caption })
  } catch (e) {
    ui.error(e)
  }
}

async function remove(img: GalleryImage) {
  if (!window.confirm('Bild löschen?')) return
  try {
    await api.delete(`/api/pin-images/${img.id}`)
    update(list.value.filter((i) => i.id !== img.id))
  } catch (e) {
    ui.error(e)
  }
}

async function drop(target: number) {
  if (dragIndex === null || dragIndex === target) return
  const next = [...list.value]
  const [moved] = next.splice(dragIndex, 1)
  next.splice(target, 0, moved)
  dragIndex = null
  update(next)
  try {
    await api.put(`/api/pins/${props.pinId}/images/order`, { ids: next.map((i) => i.id) })
  } catch (e) {
    ui.error(e)
  }
}

function move(i: number, d: number) {
  dragIndex = i
  drop(i + d)
}

/** Nach dem Anlegen eines neuen Pins: gesammelte Bilder hochladen. */
async function flush(pinId: number) {
  for (const p of pending.value) await upload(p.file, p.caption, pinId)
  pending.value = []
}
defineExpose({ flush, hasPending: () => pending.value.length > 0 })
</script>

<template>
  <div class="images">
    <div class="drop" :class="{ over }" @dragover.prevent="over = true" @dragleave="over = false" @drop.prevent="over = false; add($event.dataTransfer?.files ?? [])">
      <ImagePlus :size="18" />
      <span>Bilder hierher ziehen, Screenshot mit Strg/⌘+V einfügen oder</span>
      <button type="button" class="btn sm" @click="input?.click()">Dateien wählen</button>
      <span v-if="uploading" class="muted">Lade hoch … ({{ uploading }})</span>
      <input ref="input" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden @change="add(($event.target as HTMLInputElement).files ?? []); ($event.target as HTMLInputElement).value = ''" />
    </div>
    <ul class="list">
      <li v-for="(img, i) in list" :key="img.id" draggable="true" @dragstart="dragIndex = i" @dragover.prevent @drop.prevent="drop(i)">
        <span class="grip" title="Ziehen zum Sortieren"><GripVertical :size="16" /></span>
        <img :src="img.thumb_url" alt="" />
        <input class="input" placeholder="Beschreibung / Hinweis (optional)" v-model="img.caption" maxlength="2000" @change="saveCaption(img)" />
        <div class="order">
          <button type="button" class="btn ghost sm icon" :disabled="i === 0" aria-label="Nach vorne" @click="move(i, -1)">↑</button>
          <button type="button" class="btn ghost sm icon" :disabled="i === list.length - 1" aria-label="Nach hinten" @click="move(i, 1)">↓</button>
        </div>
        <button type="button" class="btn ghost sm icon" aria-label="Bild löschen" @click="remove(img)"><Trash2 :size="15" /></button>
      </li>
      <li v-for="p in pending" :key="p.key" class="pending">
        <span class="grip" />
        <img :src="p.url" alt="" />
        <input class="input" placeholder="Beschreibung / Hinweis (optional)" v-model="p.caption" />
        <span class="faint small">wird beim Speichern hochgeladen</span>
        <button type="button" class="btn ghost sm icon" aria-label="Entfernen" @click="pending = pending.filter((x) => x !== p)"><Trash2 :size="15" /></button>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.drop { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 12px; border: 1px dashed var(--line-strong); border-radius: var(--radius-sm); color: var(--muted); font-size: 13px; }
.drop.over { border-color: var(--orange); background: rgba(255, 138, 61, 0.08); }
.list { list-style: none; margin: 8px 0 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
.list li { display: flex; align-items: center; gap: 8px; padding: 4px; border-radius: 8px; background: rgba(255, 255, 255, 0.03); }
.list img { width: 64px; height: 42px; object-fit: cover; border-radius: 5px; flex: none; }
.grip { color: var(--faint); cursor: grab; display: flex; width: 16px; }
.order { display: flex; }
.small { font-size: 12px; white-space: nowrap; }
.pending { opacity: 0.85; }
@media (max-width: 560px) { .order { display: none; } }
</style>
