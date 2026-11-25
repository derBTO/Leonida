import { defineStore } from 'pinia'
import { computed, reactive, ref, watch } from 'vue'
import type { PinIndexItem } from '../api/types'
import { inBounds, matches, sortByTitle, type Bounds, type FilterState } from '../lib/pinFilter'
import { load, save } from '../lib/storage'

export const usePins = defineStore('pins', () => {
  const items = ref<PinIndexItem[]>([])
  const loaded = ref(false)
  let etag: string | null = null

  const filter = reactive<FilterState & { viewportOnly: boolean }>(
    load('filter', { q: '', mode: 'both', loot: [], poi: [], showGone: true, viewportOnly: false }),
  )
  watch(filter, () => save('filter', filter), { deep: true })

  const bounds = ref<Bounds | null>(null)

  /** Alle Pins, die Suche und Loottyp-Filter erfüllen – so auf der Karte angezeigt. */
  const visible = computed(() => items.value.filter((p) => matches(p, filter)))
  /** Liste in der Sidebar: zusätzlich optional nur im Kartenausschnitt, alphabetisch. */
  const listed = computed(() => {
    const list = filter.viewportOnly && bounds.value ? visible.value.filter((p) => inBounds(p, bounds.value!)) : visible.value
    return sortByTitle(list)
  })
  const byId = computed(() => new Map(items.value.map((p) => [p.id, p])))

  async function fetchAll() {
    const res = await fetch('/api/pins', { headers: { Accept: 'application/json', ...(etag ? { 'If-None-Match': etag } : {}) }, credentials: 'same-origin' })
    if (res.status === 304) return
    if (!res.ok) throw new Error('Pins konnten nicht geladen werden.')
    etag = res.headers.get('ETag')
    items.value = (await res.json()).pins
    loaded.value = true
  }

  function resetFilter() {
    filter.q = ''
    filter.mode = 'both'
    filter.poi = []
    filter.loot = []
    filter.showGone = true
  }

  async function refresh() {
    await fetchAll()
  }

  return { items, loaded, filter, bounds, visible, listed, byId, fetchAll, refresh, resetFilter }
})
