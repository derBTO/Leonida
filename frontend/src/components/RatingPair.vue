<script setup lang="ts">
/** Hervorgehobene Fahrzeug-Ratings On Road / Off Road (0–1000). */
defineProps<{ onRoad: number | null; offRoad: number | null }>()
</script>

<template>
  <div class="ratings">
    <div class="rating on">
      <span class="label">On Road</span>
      <span class="value">{{ onRoad ?? '–' }}</span>
      <div class="bar"><div :style="{ width: `${(onRoad ?? 0) / 10}%` }" /></div>
    </div>
    <div class="rating off">
      <span class="label">Off Road</span>
      <span class="value">{{ offRoad ?? '–' }}</span>
      <div class="bar"><div :style="{ width: `${(offRoad ?? 0) / 10}%` }" /></div>
    </div>
  </div>
</template>

<style scoped>
.ratings { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.rating { position: relative; padding: 12px 14px 14px; border-radius: var(--radius-sm); background: rgba(255, 255, 255, 0.04); border: 1px solid var(--line); overflow: hidden; }
.rating::before { content: ''; position: absolute; inset: 0; opacity: 0.12; background: var(--g); }
.on { --g: var(--grad); }
.off { --g: var(--grad-violet); }
.label { position: relative; display: block; font-family: var(--font-display); font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--muted); font-size: 13px; }
.value { position: relative; display: block; font-family: var(--font-display); font-weight: 700; font-size: 40px; line-height: 1.05; background: var(--g); -webkit-background-clip: text; background-clip: text; color: transparent; font-variant-numeric: tabular-nums; }
.bar { position: relative; height: 3px; background: rgba(255, 255, 255, 0.12); margin-top: 6px; }
.bar div { height: 100%; background: var(--g); }
</style>
