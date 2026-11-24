<script setup lang="ts">
import type { Option } from '../api/types'

/** Feste Auswahlliste. Leere Auswahl = unbekannt (null), getrennt von expliziten Werten. */
defineProps<{ label: string; options: Option[]; modelValue: string | null; unknownLabel?: string; required?: boolean }>()
const emit = defineEmits<{ 'update:modelValue': [string | null] }>()
const id = `en-${Math.random().toString(36).slice(2, 8)}`
</script>

<template>
  <div class="field">
    <label :for="id">{{ label }}</label>
    <select :id="id" class="input" :value="modelValue ?? ''" @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value || null)">
      <option v-if="!required" value="">{{ unknownLabel ?? '– unbekannt –' }}</option>
      <option v-for="o in options" :key="o.value" :value="o.value">{{ o.label }}</option>
    </select>
  </div>
</template>
