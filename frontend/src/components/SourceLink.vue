<script setup lang="ts">
import { ExternalLink } from 'lucide-vue-next'
import type { CatalogSource } from '../api/types'

/** Quellenangabe übernommener Katalogdaten, z. B. „Daten von NiaDB“. */
defineProps<{ source: CatalogSource }>()
</script>

<template>
  <p class="source faint">
    Daten von
    <a v-if="source.url" :href="source.url" target="_blank" rel="noopener">{{ source.name }} <ExternalLink :size="12" /></a>
    <template v-else>{{ source.name }}</template>
  </p>
</template>

<style scoped>
.source { font-size: 12px; margin-top: 10px; }
.source a { color: var(--pink); text-decoration: none; display: inline-flex; align-items: center; gap: 3px; }
.source a:hover { text-decoration: underline; }
</style>
