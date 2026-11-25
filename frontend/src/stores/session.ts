import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api/client'
import type { Meta, User } from '../api/types'

export const useSession = defineStore('session', () => {
  const meta = ref<Meta | null>(null)
  const user = ref<User | null>(null)

  const loggedIn = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin')
  /** Schreiben ist erst nach Änderung des Initialpassworts erlaubt (serverseitig ebenso). */
  const canEdit = computed(() => !!user.value && !user.value.must_change_password)
  /** Ändern/Löschen: Admin alles, alle anderen nur selbst Angelegtes (serverseitig ebenso). */
  function canModify(createdById: number | null | undefined): boolean {
    return canEdit.value && (isAdmin.value || (createdById != null && createdById === user.value?.id))
  }
  /** Waffen, Fahrzeuge und Auswahllisten pflegt nur der Administrator. */
  const canEditCatalog = computed(() => canEdit.value && isAdmin.value)

  async function load() {
    meta.value = await api.get<Meta>('/api/meta')
    user.value = meta.value.user
  }

  async function login(username: string, password: string) {
    const res = await api.post<{ user: User }>('/api/auth/login', { username, password })
    user.value = res.user
  }

  async function logout() {
    await api.post('/api/auth/logout')
    user.value = null
  }

  async function changePassword(current_password: string, password: string, password_confirmation: string) {
    const res = await api.post<{ user: User }>('/api/auth/password', { current_password, password, password_confirmation })
    user.value = res.user
  }

  function label(group: keyof Meta['enums'], value: string | null | undefined): string {
    if (value === null || value === undefined) return '–'
    return meta.value?.enums[group].find((o) => o.value === value)?.label ?? value
  }

  return { meta, user, loggedIn, isAdmin, canEdit, canModify, canEditCatalog, load, login, logout, changePassword, label }
})
