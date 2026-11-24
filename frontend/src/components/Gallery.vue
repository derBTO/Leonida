<script setup lang="ts">
import { ChevronLeft, ChevronRight, Maximize2, X } from 'lucide-vue-next'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import type { GalleryImage } from '../api/types'

/** Bildergalerie mit Pfeilen, Zähler, Beschreibung und Lightbox. */
const props = defineProps<{ images: GalleryImage[] }>()
const index = ref(0)
const lightbox = ref(false)
const current = computed(() => props.images[index.value])
watch(() => props.images, () => (index.value = Math.min(index.value, Math.max(props.images.length - 1, 0))))

function step(d: number) {
  const n = props.images.length
  index.value = (index.value + d + n) % n
}
function onKey(e: KeyboardEvent) {
  if (!lightbox.value) return
  if (e.key === 'ArrowLeft') step(-1)
  else if (e.key === 'ArrowRight') step(1)
  else if (e.key === 'Escape') { lightbox.value = false; e.stopPropagation() }
}
watch(lightbox, (on) => (on ? document.addEventListener('keydown', onKey, true) : document.removeEventListener('keydown', onKey, true)))
onBeforeUnmount(() => document.removeEventListener('keydown', onKey, true))

let touchX = 0
function touchEnd(e: TouchEvent) {
  const dx = e.changedTouches[0].clientX - touchX
  if (Math.abs(dx) > 40) step(dx < 0 ? 1 : -1)
}
</script>

<template>
  <div v-if="images.length" class="gallery">
    <div class="stage" @touchstart.passive="touchX = $event.touches[0].clientX" @touchend="touchEnd">
      <img :src="current.url" :alt="current.caption ?? `Bild ${index + 1}`" loading="lazy" @click="lightbox = true" />
      <template v-if="images.length > 1">
        <button class="nav prev" type="button" aria-label="Vorheriges Bild" @click="step(-1)"><ChevronLeft :size="20" /></button>
        <button class="nav next" type="button" aria-label="Nächstes Bild" @click="step(1)"><ChevronRight :size="20" /></button>
      </template>
      <span class="counter">{{ index + 1 }} / {{ images.length }}</span>
      <button class="zoom" type="button" aria-label="Vergrößern" @click="lightbox = true"><Maximize2 :size="15" /></button>
    </div>
    <p v-if="current.caption" class="caption">{{ current.caption }}</p>
    <div v-if="images.length > 1" class="thumbs">
      <button v-for="(img, i) in images" :key="img.id" type="button" :class="{ on: i === index }" :aria-label="`Bild ${i + 1}`" @click="index = i">
        <img :src="img.thumb_url" alt="" loading="lazy" />
      </button>
    </div>

    <Teleport to="body">
      <div v-if="lightbox" class="lightbox" @click.self="lightbox = false">
        <img :src="current.url" :alt="current.caption ?? ''" />
        <button class="close btn icon" type="button" aria-label="Schließen" @click="lightbox = false"><X :size="20" /></button>
        <template v-if="images.length > 1">
          <button class="nav prev big" type="button" aria-label="Vorheriges Bild" @click="step(-1)"><ChevronLeft :size="28" /></button>
          <button class="nav next big" type="button" aria-label="Nächstes Bild" @click="step(1)"><ChevronRight :size="28" /></button>
        </template>
        <div class="lb-caption"><span class="counter-inline">{{ index + 1 }} / {{ images.length }}</span> {{ current.caption }}</div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.stage { position: relative; aspect-ratio: 16 / 10; background: rgba(0, 0, 0, 0.35); border-radius: var(--radius-sm); overflow: hidden; }
.stage img { width: 100%; height: 100%; object-fit: contain; cursor: zoom-in; }
.nav { position: absolute; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; border-radius: 50%; border: 0; background: rgba(10, 8, 18, 0.7); display: flex; align-items: center; justify-content: center; cursor: pointer; }
.nav:hover { background: rgba(10, 8, 18, 0.9); }
.prev { left: 8px; }
.next { right: 8px; }
.counter { position: absolute; left: 8px; bottom: 8px; padding: 2px 8px; border-radius: 6px; background: rgba(10, 8, 18, 0.75); font-size: 12px; font-variant-numeric: tabular-nums; }
.zoom { position: absolute; right: 8px; bottom: 8px; border: 0; border-radius: 6px; background: rgba(10, 8, 18, 0.75); padding: 5px; cursor: pointer; display: flex; }
.caption { margin: 6px 0 0; color: var(--muted); white-space: pre-wrap; }
.thumbs { display: flex; gap: 6px; margin-top: 8px; overflow-x: auto; padding-bottom: 2px; }
.thumbs button { flex: none; width: 56px; height: 38px; padding: 0; border: 2px solid transparent; border-radius: 6px; overflow: hidden; background: none; cursor: pointer; opacity: 0.65; }
.thumbs button.on { border-color: var(--orange); opacity: 1; }
.thumbs img { width: 100%; height: 100%; object-fit: cover; }
.lightbox { position: fixed; inset: 0; z-index: 4000; background: rgba(3, 2, 8, 0.94); display: flex; align-items: center; justify-content: center; animation: fade-in 0.15s; }
.lightbox img { max-width: 94vw; max-height: 84vh; object-fit: contain; }
.lightbox .close { position: absolute; top: 14px; right: 14px; }
.nav.big { width: 48px; height: 48px; }
.lb-caption { position: absolute; bottom: 18px; left: 50%; transform: translateX(-50%); max-width: 90vw; text-align: center; color: var(--text); }
.counter-inline { color: var(--muted); margin-right: 8px; font-variant-numeric: tabular-nums; }
</style>
