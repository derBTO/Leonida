import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api/client'
import type { Trail, VehicleSummary, WeaponSummary } from '../api/types'
import { load, save } from '../lib/storage'
import { useSession } from './session'
import { useUi } from './ui'

/** Schritt im Spur-Editor (auch Entwurf beim Pin-Setzen) */
export interface TrailDraftStep { key: number; id: number | null; title: string; description: string; pin_id: number | null; parallel: boolean }

/**
 * Entwurf einer Spur, während für einen Schritt ein neuer Pin auf der Karte gesetzt wird.
 * resume = false: Karte zeigt das Fadenkreuz; true: zurück im Editor, dort wieder öffnen.
 */
export interface TrailDraft {
  trailId: number | null
  name: string
  description: string
  weapons: WeaponSummary[]
  vehicles: VehicleSummary[]
  steps: TrailDraftStep[]
  stepKey: number
  returnPath: string
  resume: boolean
}

/**
 * Aktive Spur auf der Karte: nummerierte Schritte, Verbindungslinie und Schritt-Navigation.
 * Die Auswahl bleibt im Browser gespeichert; der Fortschritt liegt auf dem Server (pro Person).
 */
export const useTrails = defineStore('trails', () => {
  const session = useSession()
  const ui = useUi()
  const stored = load('trail', { id: null as number | null, spoiler: false })
  const active = ref<Trail | null>(null)
  const activeId = ref<number | null>(stored.id)
  /** Index in active.steps des gerade gezeigten Schritts */
  const stepIndex = ref(0)
  /** Spoiler-Schutz: spätere Schritte erst nach dem Abhaken bzw. Aufdecken zeigen */
  const spoiler = ref(stored.spoiler)
  const revealed = ref(new Set<number>())
  /** Version zum Neuladen anderer Ansichten nach Änderungen */
  const version = ref(0)
  /** Wunsch an die Karte, den Ausschnitt auf die Spur zu setzen (Zähler) */
  const fitRequest = ref(0)
  /** Wunsch an die Karte, zum aktuellen Schritt zu fliegen (Zähler) */
  const focusRequest = ref(0)
  /** Spur-Entwurf beim Setzen eines neuen Pins für einen Schritt */
  const draft = ref<TrailDraft | null>(null)
  const draftStep = computed(() => draft.value?.steps.find((s) => s.key === draft.value!.stepKey) ?? null)

  const persist = () => save('trail', { id: activeId.value, spoiler: spoiler.value })

  /** Pins der aktiven Spur, die gezeigt werden dürfen (verdeckte Schritte nicht). */
  const pinIds = computed(() => {
    const t = active.value
    if (!t) return new Set<number>()
    return new Set(t.steps.filter((s) => s.pin && !isHidden(t, s.id)).map((s) => s.pin!.id))
  })

  /** Höchste Schrittnummer, die ohne Spoiler sichtbar ist: alle erledigten plus die nächste offene. */
  const openUpTo = computed(() => {
    const steps = active.value?.steps ?? []
    const firstOpen = steps.find((s) => !s.done)
    return firstOpen ? firstOpen.position : Infinity
  })
  function isHidden(trail: Trail, stepId: number): boolean {
    if (!spoiler.value || revealed.value.has(stepId)) return false
    const firstOpen = trail.steps.find((s) => !s.done)
    const step = trail.steps.find((s) => s.id === stepId)
    return !!firstOpen && !!step && !step.done && step.position > firstOpen.position
  }

  async function activate(id: number, stepId?: number) {
    const trail = (await api.get<{ item: Trail }>(`/api/trails/${id}`)).item
    if (activeId.value !== id) revealed.value = new Set()
    active.value = trail
    activeId.value = id
    const byStep = stepId ? trail.steps.findIndex((s) => s.id === stepId) : -1
    const firstOpen = trail.steps.findIndex((s) => !s.done)
    stepIndex.value = byStep >= 0 ? byStep : Math.max(0, firstOpen)
    persist()
    return trail
  }

  async function restore() {
    if (activeId.value && !active.value) {
      try {
        await activate(activeId.value)
      } catch {
        clear()
      }
    }
  }

  function clear() {
    active.value = null
    activeId.value = null
    stepIndex.value = 0
    persist()
  }

  function setSpoiler(on: boolean) {
    spoiler.value = on
    revealed.value = new Set()
    persist()
  }
  function reveal(stepId: number) {
    revealed.value = new Set([...revealed.value, stepId])
  }

  /** Erledigt-Haken setzen; ohne Anmeldung öffnet sich der Login. */
  async function setDone(trailId: number, stepId: number, done: boolean): Promise<Trail | null> {
    if (!session.canEdit) {
      if (!session.loggedIn) ui.loginOpen = true
      else ui.passwordOpen = true
      return null
    }
    const trail = (await api.put<{ item: Trail }>(`/api/trails/${trailId}/steps/${stepId}/progress`, { done })).item
    if (active.value?.id === trailId) active.value = trail
    version.value++
    return trail
  }

  async function reload() {
    if (activeId.value) await activate(activeId.value, active.value?.steps[stepIndex.value]?.id)
    version.value++
  }

  return { draft, draftStep, fitRequest, focusRequest, active, activeId, stepIndex, spoiler, revealed, version, pinIds, openUpTo, isHidden, activate, restore, clear, setSpoiler, reveal, setDone, reload }
})
