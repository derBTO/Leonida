<script setup lang="ts">
import { computed } from 'vue'

/** Text mit Zeilenumbrüchen; http(s)-Links werden klickbar (ohne v-html, also XSS-sicher). */
const props = defineProps<{ text: string }>()
const parts = computed(() =>
  props.text.split(/(https?:\/\/[^\s<>"]+)/g).map((p, i) => ({ link: i % 2 === 1, text: p })),
)
</script>

<template>
  <span class="rich"><template v-for="(p, i) in parts" :key="i"><a v-if="p.link" :href="p.text" target="_blank" rel="noopener noreferrer">{{ p.text }}</a><template v-else>{{ p.text }}</template></template></span>
</template>

<style scoped>
.rich { white-space: pre-wrap; overflow-wrap: anywhere; }
a { color: var(--orange); }
</style>
