<script setup lang="ts">
import { Download, Trash2, Upload, UserPlus } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { api, ApiError } from '../api/client'
import type { AuditEntry, LookupCategory, LookupValue, User } from '../api/types'
import { formatDate } from '../lib/format'
import { useCatalog } from '../stores/catalog'
import { usePins } from '../stores/pins'
import { useSession } from '../stores/session'
import { useUi } from '../stores/ui'

const session = useSession()
const ui = useUi()
const catalog = useCatalog()
const pins = usePins()
const tab = ref<'users' | 'lookups' | 'data' | 'audit'>('users')

// ---------------------------------------------------------------- Benutzer
const users = ref<User[]>([])
const newUser = ref({ username: '', password: '', role: 'editor' })
const userErrors = ref<Record<string, string[]>>({})
async function loadUsers() {
  users.value = (await api.get<{ items: User[] }>('/api/users')).items
}
async function createUser() {
  userErrors.value = {}
  try {
    await api.post('/api/users', newUser.value)
    ui.toast(`Benutzer ${newUser.value.username} angelegt. Er muss das Passwort beim ersten Anmelden ändern.`, 'ok', 5000)
    newUser.value = { username: '', password: '', role: 'editor' }
    await loadUsers()
  } catch (e) {
    if (e instanceof ApiError && e.status === 422) userErrors.value = e.errors
    ui.error(e)
  }
}
async function patchUser(u: User, data: Record<string, unknown>) {
  try {
    await api.patch(`/api/users/${u.id}`, data)
    await loadUsers()
  } catch (e) {
    ui.error(e)
    await loadUsers()
  }
}
function resetPassword(u: User) {
  const pw = window.prompt(`Neues Startpasswort für ${u.username} (mind. ${session.meta?.limits.password_min_length} Zeichen):`)
  if (pw) patchUser(u, { password: pw }).then(() => ui.toast('Passwort zurückgesetzt.', 'ok'))
}
async function deleteUser(u: User) {
  if (!window.confirm(`Benutzer ${u.username} löschen? Seine Einträge bleiben erhalten.`)) return
  try {
    await api.delete(`/api/users/${u.id}`)
    await loadUsers()
  } catch (e) {
    ui.error(e)
  }
}

// ---------------------------------------------------------------- Auswahllisten
const category = ref<LookupCategory>('weapon_type')
const values = ref<LookupValue[]>([])
const mergeTarget = ref<Record<number, number | ''>>({})
const categories = computed(() => session.meta?.enums.lookup_category ?? [])
async function loadValues() {
  values.value = (await api.get<{ items: LookupValue[] }>(`/api/lookups/${category.value}?usage=1`)).items
  mergeTarget.value = {}
}
watch(category, () => loadValues().catch(ui.error))
async function rename(v: LookupValue) {
  const name = window.prompt('Neuer Name:', v.name)
  if (!name || name === v.name) return
  try {
    await api.patch(`/api/lookup-values/${v.id}`, { name })
    await afterLookupChange()
  } catch (e) {
    ui.error(e)
  }
}
async function merge(v: LookupValue) {
  const target = values.value.find((x) => x.id === mergeTarget.value[v.id])
  if (!target || !window.confirm(`„${v.name}“ in „${target.name}“ zusammenführen? Alle ${v.usage ?? 0} Verweise werden umgestellt, „${v.name}“ wird gelöscht.`)) return
  try {
    await api.post(`/api/lookup-values/${v.id}/merge`, { target_id: target.id })
    ui.toast('Zusammengeführt.', 'ok')
    await afterLookupChange()
  } catch (e) {
    ui.error(e)
  }
}
async function removeValue(v: LookupValue) {
  if (!window.confirm(`„${v.name}“ löschen?`)) return
  try {
    await api.delete(`/api/lookup-values/${v.id}`)
    await afterLookupChange()
  } catch (e) {
    ui.error(e)
  }
}
async function afterLookupChange() {
  await loadValues()
  await catalog.loadLookups(category.value, true)
  catalog.invalidate()
}

// ---------------------------------------------------------------- Daten
const importFile = ref<File | null>(null)
const importMode = ref<'merge' | 'replace'>('merge')
const report = ref<Record<string, unknown> | null>(null)
const importing = ref(false)
async function runImport(dryRun: boolean) {
  if (!importFile.value) return
  if (!dryRun && importMode.value === 'replace' && !window.confirm('Ersetzen löscht alle vorhandenen Pins, Waffen, Fahrzeuge und Auswahllisten vor dem Import. Fortfahren?')) return
  const data = new FormData()
  data.append('file', importFile.value)
  data.append('mode', importMode.value)
  data.append('dry_run', dryRun ? '1' : '0')
  importing.value = true
  try {
    report.value = (await api.post<{ report: Record<string, unknown> }>('/api/admin/import', data)).report
    if (!dryRun) {
      ui.toast('Import abgeschlossen.', 'ok')
      catalog.invalidate()
      await pins.refresh()
    }
  } catch (e) {
    ui.error(e)
  } finally {
    importing.value = false
  }
}

// ---------------------------------------------------------------- Protokoll
const audit = ref<AuditEntry[]>([])
const actionLabel: Record<string, string> = { create: 'angelegt', update: 'geändert', delete: 'gelöscht', move: 'verschoben', merge: 'zusammengeführt', login: 'angemeldet', password: 'Passwort geändert', image: 'Bild geändert', import: 'Import', export: 'Export', migrate: 'umgerechnet' }
const entityLabel: Record<string, string> = { pin: 'Pin', weapon: 'Waffe', vehicle: 'Fahrzeug', lookup: 'Listenwert', user: 'Benutzer', pin_image: 'Pin-Bild', data: 'Daten', pins: 'Pins' }

watch(tab, async (t) => {
  try {
    if (t === 'users') await loadUsers()
    if (t === 'lookups') await loadValues()
    if (t === 'audit') audit.value = (await api.get<{ items: AuditEntry[] }>('/api/audit')).items
  } catch (e) {
    ui.error(e)
  }
})
onMounted(() => loadUsers().catch(ui.error))
</script>

<template>
  <div class="page">
    <div>
      <h1>Verwaltung</h1>
      <div class="tabs" role="tablist">
        <button v-for="[k, l] in [['users', 'Benutzer'], ['lookups', 'Auswahllisten'], ['data', 'Export & Import'], ['audit', 'Protokoll']]" :key="k"
          type="button" role="tab" :aria-selected="tab === k" :class="{ on: tab === k }" @click="tab = k as typeof tab">{{ l }}</button>
      </div>

      <section v-if="tab === 'users'" class="panel box">
        <table class="table">
          <thead><tr><th>Benutzer</th><th>Rolle</th><th>Aktiv</th><th>Letzte Anmeldung</th><th /></tr></thead>
          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>{{ u.username }} <span v-if="u.must_change_password" class="badge warn">Passwort ändern</span></td>
              <td><select class="input sm" :value="u.role" :disabled="u.id === session.user?.id" @change="patchUser(u, { role: ($event.target as HTMLSelectElement).value })"><option value="admin">Administrator</option><option value="editor">Editor</option></select></td>
              <td><input type="checkbox" :checked="u.is_active" :disabled="u.id === session.user?.id" @change="patchUser(u, { is_active: ($event.target as HTMLInputElement).checked })" /></td>
              <td class="muted">{{ formatDate(u.last_login_at) }}</td>
              <td class="right">
                <button class="btn sm" type="button" @click="resetPassword(u)">Passwort zurücksetzen</button>
                <button class="btn sm danger icon" type="button" :disabled="u.id === session.user?.id" :aria-label="`${u.username} löschen`" @click="deleteUser(u)"><Trash2 :size="14" /></button>
              </td>
            </tr>
          </tbody>
        </table>
        <form class="new" @submit.prevent="createUser">
          <div class="field"><label>Benutzername</label><input class="input" v-model="newUser.username" required />
            <div v-if="userErrors.username" class="error-text">{{ userErrors.username[0] }}</div></div>
          <div class="field"><label>Startpasswort</label><input class="input" type="text" v-model="newUser.password" required autocomplete="off" />
            <div v-if="userErrors.password" class="error-text">{{ userErrors.password[0] }}</div></div>
          <div class="field"><label>Rolle</label><select class="input" v-model="newUser.role"><option value="editor">Editor</option><option value="admin">Administrator</option></select></div>
          <button class="btn primary" type="submit"><UserPlus :size="16" /> Anlegen</button>
        </form>
      </section>

      <section v-if="tab === 'lookups'" class="panel box">
        <div class="cats">
          <button v-for="c in categories" :key="c.value" type="button" class="chip" :class="{ on: category === c.value }" @click="category = c.value as LookupCategory">{{ c.label }}</button>
        </div>
        <p class="muted">Neue Werte entstehen direkt in den Formularen. Hier lassen sich Werte umbenennen, Dubletten zusammenführen und unbenutzte löschen.</p>
        <table class="table">
          <thead><tr><th>Wert</th><th>Verwendet</th><th>Zusammenführen in</th><th /></tr></thead>
          <tbody>
            <tr v-for="v in values" :key="v.id">
              <td>{{ v.name }}</td>
              <td>{{ v.usage }}</td>
              <td class="merge">
                <select class="input sm" v-model="mergeTarget[v.id]"><option value="">–</option><option v-for="t in values.filter((x) => x.id !== v.id)" :key="t.id" :value="t.id">{{ t.name }}</option></select>
                <button class="btn sm" type="button" :disabled="!mergeTarget[v.id]" @click="merge(v)">Zusammenführen</button>
              </td>
              <td class="right">
                <button class="btn sm" type="button" @click="rename(v)">Umbenennen</button>
                <button class="btn sm danger icon" type="button" :disabled="(v.usage ?? 0) > 0" title="Nur unbenutzte Werte können gelöscht werden" @click="removeValue(v)"><Trash2 :size="14" /></button>
              </td>
            </tr>
          </tbody>
        </table>
        <p v-if="!values.length" class="empty">Noch keine Werte.</p>
      </section>

      <section v-if="tab === 'data'" class="panel box">
        <h2>Export</h2>
        <p class="muted">ZIP mit allen Pins, Waffen, Fahrzeugen, Auswahllisten und Bildern. Benutzerkonten sind nicht enthalten.</p>
        <a class="btn primary" href="/api/admin/export" download><Download :size="16" /> Export herunterladen</a>
        <h2 class="mt">Import</h2>
        <div class="import">
          <input type="file" accept=".zip,application/zip" @change="importFile = ($event.target as HTMLInputElement).files?.[0] ?? null; report = null" />
          <label><input type="radio" value="merge" v-model="importMode" /> Zusammenführen (vorhandene Einträge aktualisieren, neue hinzufügen)</label>
          <label><input type="radio" value="replace" v-model="importMode" /> Ersetzen (vorher alles löschen)</label>
          <div class="row">
            <button class="btn" type="button" :disabled="!importFile || importing" @click="runImport(true)">Probelauf</button>
            <button class="btn primary" type="button" :disabled="!importFile || importing" @click="runImport(false)"><Upload :size="16" /> Importieren</button>
          </div>
        </div>
        <pre v-if="report" class="report">{{ JSON.stringify(report, null, 2) }}</pre>
      </section>

      <section v-if="tab === 'audit'" class="panel box">
        <table class="table">
          <thead><tr><th>Zeit</th><th>Benutzer</th><th>Aktion</th><th>Eintrag</th></tr></thead>
          <tbody>
            <tr v-for="a in audit" :key="a.id">
              <td class="muted nowrap">{{ formatDate(a.created_at) }}</td>
              <td>{{ a.user ?? '–' }}</td>
              <td>{{ entityLabel[a.entity] ?? a.entity }} {{ actionLabel[a.action] ?? a.action }}</td>
              <td>{{ a.summary }}</td>
            </tr>
          </tbody>
        </table>
      </section>
    </div>
  </div>
</template>

<style scoped>
@import './page.css';
h1 { font-size: 40px; margin-bottom: 12px; }
h2 { font-size: 22px; margin-bottom: 6px; }
.mt { margin-top: 22px; }
.tabs { display: flex; gap: 4px; margin-bottom: 14px; flex-wrap: wrap; }
.tabs button { border: 0; background: none; padding: 8px 14px; border-radius: 8px; cursor: pointer; font-family: var(--font-display); font-weight: 600; font-size: 16px; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); }
.tabs button.on { color: var(--text); background: rgba(255, 255, 255, 0.06); box-shadow: inset 0 -2px 0 var(--pink); }
.box { padding: 16px; overflow-x: auto; }
.table { width: 100%; border-collapse: collapse; }
.table th { text-align: left; font-family: var(--font-display); font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--faint); font-size: 13px; padding: 6px 8px; border-bottom: 1px solid var(--line); }
.table td { padding: 7px 8px; border-bottom: 1px solid var(--line); vertical-align: middle; }
.right { text-align: right; white-space: nowrap; }
.right .btn { margin-left: 4px; }
.nowrap { white-space: nowrap; }
.input.sm { height: 30px; width: auto; min-width: 130px; }
.merge { display: flex; gap: 6px; }
.new { display: grid; grid-template-columns: 1fr 1fr 160px auto; gap: 10px; align-items: end; margin-top: 16px; }
.cats { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 8px; }
.import { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.import .row { display: flex; gap: 8px; }
.report { background: rgba(0, 0, 0, 0.35); padding: 12px; border-radius: 8px; overflow: auto; font-size: 12px; }
@media (max-width: 760px) { .new { grid-template-columns: 1fr; } }
</style>
