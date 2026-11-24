<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { RouterView, useRouter } from 'vue-router'
import { setUnauthorizedHandler } from './api/client'
import AppHeader from './components/AppHeader.vue'
import LoginDialog from './components/LoginDialog.vue'
import PasswordDialog from './components/PasswordDialog.vue'
import Toasts from './components/Toasts.vue'
import { useSession } from './stores/session'
import { useUi } from './stores/ui'

const session = useSession()
const ui = useUi()
const router = useRouter()
const ready = ref(false)
const failed = ref<string | null>(null)

setUnauthorizedHandler(() => {
  if (session.user) {
    session.user = null
    ui.toast('Sitzung abgelaufen – bitte erneut anmelden.', 'error')
  }
})

onMounted(async () => {
  try {
    await session.load()
    if (session.user?.must_change_password) ui.passwordOpen = true
    ready.value = true
  } catch (e) {
    failed.value = e instanceof Error ? e.message : String(e)
  }
})

router.beforeEach((to) => {
  if (to.meta.admin && ready.value && !session.isAdmin) return '/'
})
</script>

<template>
  <template v-if="ready">
    <AppHeader />
    <RouterView v-slot="{ Component }">
      <KeepAlive include="MapPage"><component :is="Component" /></KeepAlive>
    </RouterView>
    <LoginDialog v-if="ui.loginOpen" />
    <PasswordDialog v-if="ui.passwordOpen" />
  </template>
  <div v-else class="boot">
    <span class="grad-text logo">LEONIDA</span>
    <p v-if="failed" class="error-text">Server nicht erreichbar: {{ failed }}</p>
  </div>
  <Toasts />
</template>

<style scoped>
.boot { position: fixed; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; }
.logo { font-family: var(--font-display); font-weight: 800; font-size: 48px; letter-spacing: 0.14em; }
</style>
