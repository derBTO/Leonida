import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api/client'
import type { LookupCategory, LookupValue, VehicleSummary, WeaponSummary } from '../api/types'

/** Zwischenspeicher für Auswahllisten und Katalog-Übersichten. */
export const useCatalog = defineStore('catalog', () => {
  const lookups = ref<Partial<Record<LookupCategory, LookupValue[]>>>({})
  const weapons = ref<WeaponSummary[] | null>(null)
  const vehicles = ref<VehicleSummary[] | null>(null)

  async function loadLookups(category: LookupCategory, force = false): Promise<LookupValue[]> {
    if (!lookups.value[category] || force) {
      lookups.value[category] = (await api.get<{ items: LookupValue[] }>(`/api/lookups/${category}`)).items
    }
    return lookups.value[category]!
  }

  /** Legt einen Wert an oder liefert den vorhandenen (Server prüft Dubletten). */
  async function addLookup(category: LookupCategory, name: string): Promise<LookupValue> {
    const res = await api.post<{ item: LookupValue }>(`/api/lookups/${category}`, { name })
    await loadLookups(category, true)
    return res.item
  }

  async function loadWeapons(force = false) {
    if (!weapons.value || force) weapons.value = (await api.get<{ items: WeaponSummary[] }>('/api/weapons')).items
    return weapons.value
  }

  async function loadVehicles(force = false) {
    if (!vehicles.value || force) vehicles.value = (await api.get<{ items: VehicleSummary[] }>('/api/vehicles')).items
    return vehicles.value
  }

  function invalidate() {
    weapons.value = null
    vehicles.value = null
  }

  return { lookups, weapons, vehicles, loadLookups, addLookup, loadWeapons, loadVehicles, invalidate }
})
