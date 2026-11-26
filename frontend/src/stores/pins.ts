import { defineStore } from 'pinia'
import { computed, reactive, ref, watch } from 'vue'
import type { PinIndexItem } from '../api/types'
import { inBounds, matches, sortByTitle, type Bounds, type FilterState } from '../lib/pinFilter'
import { load, save } from '../lib/storage'
import { useSession } from './session'
import { useTrails } from './trails'

export const usePins = defineStore('pins', () => {
  const session = useSession()
  const trails = useTrails()
  const items = ref<PinIndexItem[]>([])
  const loaded = ref(false)
  let etag: string | null = null

  const filter = reactive<FilterState & { viewportOnly: boolean; author: number | null }>(
    load('filter', { q: '', mode: 'both', loot: [], poi: [], showGone: true, showHints: true, showGtadb: false, eggs: 'show' as 'show' | 'hide' | 'only', author: null as number | null, viewportOnly: false }),
  )
  watch(filter, () => save('filter', filter), { deep: true })

  const bounds = ref<Bounds | null>(null)

  /** Alle Pins, die Suche und Filter erfüllen – so auf der Karte angezeigt.
   *  Ist ein Puzzle aktiv, nur dessen Hinweise und Orte (unabhängig vom Filter). */
  const visible = computed(() => {
    if (trails.active) return items.value.filter((p) => trails.pinIds.has(p.id))
    // Ersteller-Filter nur für Angemeldete (nur sie sehen die Benutzerliste)
    const f = session.user ? filter : { ...filter, author: null }
    const pinned = trails.pinIds
    return items.value.filter((p) => pinned.has(p.id) || matches(p, f))
  })
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
    filter.showHints = true
    filter.author = null
    filter.eggs = 'show'
  }

  async function refresh() {
    await fetchAll()
  }

  return { items, loaded, filter, bounds, visible, listed, byId, fetchAll, refresh, resetFilter }
})
