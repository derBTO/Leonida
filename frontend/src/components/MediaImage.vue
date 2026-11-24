<script setup lang="ts">
import { ImageOff } from 'lucide-vue-next'
import type { MediaRef } from '../api/types'
/** Bild mit Lazy Loading und Platzhalter. */
defineProps<{ media: MediaRef | null; thumb?: boolean; alt: string }>()
</script>

<template>
  <img v-if="media" class="media" :src="thumb ? media.thumb_url : media.url" :alt="alt" loading="lazy" decoding="async" />
  <div v-else class="media placeholder"><ImageOff :size="28" /></div>
</template>

<style scoped>
.media { display: block; width: 100%; height: 100%; object-fit: contain; }
.placeholder { display: flex; align-items: center; justify-content: center; color: var(--faint); background: rgba(255, 255, 255, 0.03); }
</style>
