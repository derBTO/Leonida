<script setup lang="ts">
import { Plus, Trash2 } from 'lucide-vue-next'
import type { PinCode } from '../api/types'

/** Codes eines Pins. Werte bleiben Text, damit führende Nullen erhalten bleiben. */
const props = defineProps<{ modelValue: PinCode[]; errors?: Record<string, string[]> }>()
const emit = defineEmits<{ 'update:modelValue': [PinCode[]] }>()
const set = (i: number, key: keyof PinCode, value: string) =>
  emit('update:modelValue', props.modelValue.map((c, j) => (j === i ? { ...c, [key]: value } : c)))
</script>

<template>
  <div class="codes">
    <div v-for="(c, i) in modelValue" :key="i" class="code">
      <input class="input" placeholder="Bezeichnung, z. B. Safe" :value="c.label ?? ''" maxlength="100" @input="set(i, 'label', ($event.target as HTMLInputElement).value)" />
      <input class="input mono" placeholder="Code *" :value="c.code" maxlength="100" inputmode="text" @input="set(i, 'code', ($event.target as HTMLInputElement).value)" />
      <input class="input desc" placeholder="Beschreibung (optional)" :value="c.description ?? ''" maxlength="2000" @input="set(i, 'description', ($event.target as HTMLInputElement).value)" />
      <button type="button" class="btn ghost sm icon" aria-label="Code entfernen" @click="emit('update:modelValue', modelValue.filter((_, j) => j !== i))"><Trash2 :size="15" /></button>
      <div v-if="errors?.[`codes.${i}.code`]" class="error-text full">{{ errors[`codes.${i}.code`][0] }}</div>
    </div>
    <button type="button" class="btn sm" @click="emit('update:modelValue', [...modelValue, { label: '', code: '', description: '' }])"><Plus :size="14" /> Code hinzufügen</button>
  </div>
</template>

<style scoped>
.codes { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.code { display: grid; grid-template-columns: 1fr 120px 1.4fr 30px; gap: 6px; width: 100%; align-items: center; }
.mono { font-family: ui-monospace, Menlo, monospace; letter-spacing: 0.08em; }
.full { grid-column: 1 / -1; }
@media (max-width: 560px) { .code { grid-template-columns: 1fr 100px 30px; } .desc { grid-column: 1 / 3; grid-row: 2; } }
</style>
