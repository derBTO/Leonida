<script setup lang="ts">
import { ref } from 'vue'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'
import Modal from './Modal.vue'

const session = useSession()
const ui = useUi()
const username = ref('')
const password = ref('')
const error = ref<string | null>(null)
const busy = ref(false)

async function submit() {
  busy.value = true
  error.value = null
  try {
    await session.login(username.value, password.value)
    ui.loginOpen = false
    if (session.user?.must_change_password) ui.passwordOpen = true
    else ui.toast(`Angemeldet als ${session.user!.username}.`, 'ok')
  } catch (e) {
    error.value = e instanceof Error ? e.message : String(e)
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <Modal title="Anmelden" width="380px" @close="ui.loginOpen = false">
    <form id="login" class="form" @submit.prevent="submit">
      <div class="field"><label for="lg-user">Benutzername</label><input id="lg-user" class="input" autocomplete="username" v-model="username" required /></div>
      <div class="field"><label for="lg-pw">Passwort</label><input id="lg-pw" class="input" type="password" autocomplete="current-password" v-model="password" required /></div>
      <div v-if="error" class="error-text">{{ error }}</div>
    </form>
    <template #footer><button class="btn primary" type="submit" form="login" :disabled="busy">Anmelden</button></template>
  </Modal>
</template>

<style scoped>
.form { display: flex; flex-direction: column; gap: 12px; }
</style>
