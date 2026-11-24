<script setup lang="ts">
import { KeyRound, LogIn, LogOut, Settings, User } from 'lucide-vue-next'
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'

const session = useSession()
const ui = useUi()
const router = useRouter()
const menuOpen = ref(false)

async function logout() {
  menuOpen.value = false
  try {
    await session.logout()
    ui.toast('Abgemeldet.')
    router.push('/')
  } catch (e) {
    ui.error(e)
  }
}
</script>

<template>
  <header class="app-header">
    <nav class="nav panel">
      <RouterLink to="/" class="logo" aria-label="Leonida – Karte"><span class="grad-text">LEONIDA</span></RouterLink>
      <RouterLink to="/" class="link" :class="{ active: $route.name === 'map' || $route.name === 'pin' }">Karte</RouterLink>
      <RouterLink to="/waffen" class="link" active-class="active">Waffen</RouterLink>
      <RouterLink to="/fahrzeuge" class="link" active-class="active">Fahrzeuge</RouterLink>
    </nav>
    <div class="user panel">
      <button v-if="!session.loggedIn" class="btn ghost sm" type="button" @click="ui.loginOpen = true"><LogIn :size="15" /> <span class="hide-sm">Anmelden</span></button>
      <div v-else class="menu-wrap">
        <button class="btn ghost sm" type="button" :aria-expanded="menuOpen" @click="menuOpen = !menuOpen"><User :size="15" /> <span class="hide-sm">{{ session.user!.username }}</span></button>
        <div v-if="menuOpen" class="menu panel" @click="menuOpen = false">
          <RouterLink v-if="session.isAdmin" to="/admin"><Settings :size="15" /> Verwaltung</RouterLink>
          <button type="button" @click="ui.passwordOpen = true"><KeyRound :size="15" /> Passwort ändern</button>
          <RouterLink to="/quellen">Quellen &amp; Lizenzen</RouterLink>
          <button type="button" @click="logout"><LogOut :size="15" /> Abmelden</button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.app-header { position: fixed; z-index: 1000; top: 12px; left: 12px; right: 12px; display: flex; justify-content: space-between; align-items: center; pointer-events: none; }
.nav, .user { pointer-events: auto; display: flex; align-items: center; height: var(--header-h); padding: 0 8px; gap: 2px; }
.logo { font-family: var(--font-display); font-weight: 800; font-size: 26px; letter-spacing: 0.12em; text-decoration: none; padding: 0 12px 0 6px; }
.link { font-family: var(--font-display); font-weight: 600; font-size: 16px; letter-spacing: 0.08em; text-transform: uppercase; text-decoration: none; color: var(--muted); padding: 6px 10px; border-radius: 6px; }
.link:hover { color: var(--text); }
.link.active { color: var(--text); box-shadow: inset 0 -2px 0 var(--pink); }
.menu-wrap { position: relative; }
.menu { position: absolute; right: 0; top: calc(100% + 10px); min-width: 210px; padding: 4px; display: flex; flex-direction: column; }
.menu a, .menu button { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 0; background: none; border-radius: 6px; text-decoration: none; cursor: pointer; text-align: left; }
.menu a:hover, .menu button:hover { background: var(--panel-hover); }
@media (max-width: 560px) {
  .app-header { left: 8px; right: 8px; top: 8px; }
  .logo { font-size: 19px; padding-right: 2px; letter-spacing: 0.08em; }
  .link { font-size: 13.5px; padding: 6px 5px; }
  .hide-sm { display: none; }
  .user { padding: 0 2px; }
}
</style>
