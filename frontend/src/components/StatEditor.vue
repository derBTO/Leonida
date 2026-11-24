<script setup lang="ts">
import { X } from 'lucide-vue-next'
import { ref, watch } from 'vue'
import { formatStat, parseStat } from '../lib/format'

/** Slider und Zahlenfeld, synchron. Leer = unbekannt (null), getrennt von 0. */
const props = defineProps<{ label: string; modelValue: number | null; error?: string }>()
const emit = defineEmits<{ 'update:modelValue': [number | null] }>()
const text = ref(props.modelValue === null ? '' : formatStat(props.modelValue))
const invalid = ref(false)

watch(() => props.modelValue, (v) => {
  if (parseStat(text.value) !== v) text.value = v === null ? '' : formatStat(v)
})

function fromSlider(e: Event) {
  const v = Math.round(Number((e.target as HTMLInputElement).value) * 10) / 10
  invalid.value = false
  text.value = formatStat(v)
  emit('update:modelValue', v)
}
function fromText() {
  const v = parseStat(text.value)
  invalid.value = v !== null && (Number.isNaN(v) || v < 0 || v > 10 || Math.abs(v * 10 - Math.round(v * 10)) > 1e-9)
  if (!invalid.value) emit('update:modelValue', v)
}
function clear() {
  text.value = ''
  invalid.value = false
  emit('update:modelValue', null)
}
</script>

<template>
  <div class="stat-editor" :class="{ unknown: modelValue === null }">
    <label class="label">{{ label }}</label>
    <input type="range" min="0" max="10" step="0.1" :value="modelValue ?? 0" :aria-label="`${label} (Schieberegler)`" @input="fromSlider" />
    <input class="input num" :class="{ bad: invalid || error }" inputmode="decimal" placeholder="–" v-model="text" :aria-label="label" @input="fromText" @blur="fromText" />
    <button class="btn ghost sm icon" type="button" title="Auf „unbekannt“ setzen" :disabled="modelValue === null" @click="clear"><X :size="14" /></button>
    <div v-if="invalid || error" class="error-text msg">{{ error ?? '0 bis 10, eine Nachkommastelle' }}</div>
  </div>
</template>

<style scoped>
.stat-editor { display: grid; grid-template-columns: 150px 1fr 70px 30px; align-items: center; gap: 10px; padding: 4px 0; }
.label { font-size: 14px; }
.num { height: 32px; text-align: right; font-variant-numeric: tabular-nums; }
.num.bad { border-color: var(--danger); }
.msg { grid-column: 2 / -1; }
input[type='range'] { width: 100%; accent-color: var(--pink); }
.unknown input[type='range'] { opacity: 0.35; }
@media (max-width: 560px) { .stat-editor { grid-template-columns: 1fr 64px 30px; } .stat-editor .label { grid-column: 1 / -1; } }
</style>
