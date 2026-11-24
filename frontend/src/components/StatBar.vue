<script setup lang="ts">
import { formatStat } from '../lib/format'
/** Statistikbalken nach dem Referenzbild: Label, Wert rechts, dünner Balken mit Teilstrichen. */
defineProps<{ label: string; value: number | null }>()
</script>

<template>
  <div class="stat" :class="{ unknown: value === null }">
    <div class="row">
      <span class="label">{{ label }}</span>
      <span class="value">{{ formatStat(value) }}</span>
    </div>
    <div class="track" role="meter" :aria-label="label" aria-valuemin="0" aria-valuemax="10" :aria-valuenow="value ?? undefined">
      <div class="fill" :style="{ width: `${(value ?? 0) * 10}%` }" />
      <i v-for="n in 9" :key="n" :style="{ left: `${n * 10}%` }" />
    </div>
  </div>
</template>

<style scoped>
.stat { padding: 7px 0 9px; border-bottom: 1px solid var(--line); }
.row { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 6px; }
.label, .value { font-family: var(--font-display); font-weight: 700; font-size: 16px; letter-spacing: 0.06em; text-transform: uppercase; }
.value { font-variant-numeric: tabular-nums; }
.track { position: relative; height: 5px; background: rgba(255, 255, 255, 0.16); }
.fill { position: absolute; inset: 0 auto 0 0; background: #f4f2f8; box-shadow: 0 0 10px rgba(255, 255, 255, 0.25); transition: width 0.3s ease; }
.track i { position: absolute; top: -2px; width: 1px; height: 9px; background: rgba(11, 10, 18, 0.85); }
.unknown .label, .unknown .value { color: var(--faint); }
.unknown .track { background: repeating-linear-gradient(90deg, rgba(255,255,255,0.12) 0 6px, transparent 6px 10px); }
</style>
