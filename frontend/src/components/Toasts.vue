<script setup lang="ts">
import { useUi } from '../stores/ui'
const ui = useUi()
</script>

<template>
  <div class="toasts" aria-live="polite">
    <div v-for="t in ui.toasts" :key="t.id" class="toast panel" :class="t.kind">{{ t.text }}</div>
  </div>
</template>

<style scoped>
.toasts { position: fixed; bottom: 18px; left: 50%; transform: translateX(-50%); z-index: 3000; display: flex; flex-direction: column; gap: 8px; align-items: center; pointer-events: none; }
.toast { padding: 10px 16px; animation: fade-in 0.2s ease-out; border-left: 3px solid var(--violet); max-width: min(92vw, 520px); }
.toast.error { border-left-color: var(--danger); }
.toast.ok { border-left-color: var(--ok); }
</style>
