<script setup lang="ts">
import { ref } from 'vue'
import { ApiError } from '../api/client'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import Modal from './Modal.vue'

const session = useSession()
const ui = useUi()
const current = ref('')
const next = ref('')
const confirm = ref('')
const errors = ref<Record<string, string[]>>({})
const busy = ref(false)
const forced = session.user?.must_change_password

async function submit() {
  busy.value = true
  errors.value = {}
  try {
    await session.changePassword(current.value, next.value, confirm.value)
    ui.passwordOpen = false
    ui.toast('Passwort geändert.', 'ok')
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) errors.value = e.errors
    else ui.error(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal title="Passwort ändern" width="420px" @close="ui.passwordOpen = false">
    <p v-if="forced" class="muted">Bitte vergib ein eigenes Passwort. Bis dahin sind keine Änderungen möglich.</p>
    <form id="pw" class="form" @submit.prevent="submit">
      <div class="field"><label for="pw-cur">Aktuelles Passwort</label><input id="pw-cur" class="input" type="password" autocomplete="current-password" v-model="current" required />
        <div v-if="errors.current_password" class="error-text">{{ errors.current_password[0] }}</div></div>
      <div class="field"><label for="pw-new">Neues Passwort (mind. {{ session.meta?.limits.password_min_length }} Zeichen)</label><input id="pw-new" class="input" type="password" autocomplete="new-password" v-model="next" required />
        <div v-if="errors.password" class="error-text">{{ errors.password[0] }}</div></div>
      <div class="field"><label for="pw-rep">Neues Passwort wiederholen</label><input id="pw-rep" class="input" type="password" autocomplete="new-password" v-model="confirm" required /></div>
    </form>
    <template #footer><button class="btn primary" type="submit" form="pw" :disabled="busy">Speichern</button></template>
  </Modal>
</template>

<style scoped>
.form { display: flex; flex-direction: column; gap: 12px; }
</style>
