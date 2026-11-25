<script setup lang="ts">
import { computed } from 'vue'
import { useSession } from '../stores/session'

/** Filter „Ersteller“: alle, eigene oder die eines bestimmten Benutzers (nur für Angemeldete). */
const model = defineModel<number | null>({ required: true })
defineProps<{ compact?: boolean }>()
const session = useSession()
const others = computed(() => (session.meta?.authors ?? []).filter((a) => a.id !== session.user?.id))
const value = computed({
  get: () => (model.value === null ? '' : String(model.value)),
  set: (v: string) => (model.value = v === '' ? null : Number(v)),
})
</script>

<template>
  <label v-if="session.loggedIn" class="author" :class="{ compact }">
    <span>Ersteller</span>
    <select class="input" v-model="value" aria-label="Nach Ersteller filtern">
      <option value="">Alle</option>
      <option v-if="session.user" :value="String(session.user.id)">Meine</option>
      <option v-for="a in others" :key="a.id" :value="String(a.id)">{{ a.username }}</option>
    </select>
  </label>
</template>

<style scoped>
.author { display: flex; align-items: center; gap: 8px; color: var(--muted); font-size: 13.5px; }
.author select { height: 34px; width: auto; min-width: 120px; padding: 0 8px; }
.author.compact select { height: 30px; flex: 1; }
</style>
