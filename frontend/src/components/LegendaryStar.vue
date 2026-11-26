<script setup lang="ts">
import { Star } from 'lucide-vue-next'
/** Gelber Stern hinter dem Namen legendärer / einzigartiger Waffen. */
withDefaults(defineProps<{ size?: number }>(), { size: 16 })
</script>

<template>
  <span class="legendary" title="Legendär / Unique" aria-label="Legendär / Unique" role="img"><Star :size="size" fill="currentColor" /></span>
</template>

<style scoped>
.legendary { display: inline-flex; vertical-align: 0.05em; margin-left: 0.3em; color: var(--legendary); filter: drop-shadow(0 0 6px rgba(250, 204, 21, 0.55)); flex: none; }
</style>
