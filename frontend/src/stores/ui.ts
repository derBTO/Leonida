import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { load, save } from '../lib/storage'

export interface Toast { id: number; text: string; kind: 'info' | 'error' | 'ok' }

/** draft: zwischengespeichertes Formular (JSON), z. B. nach dem Anlegen eines Orts aus dem Hinweis heraus */
export interface EditorDraft { form: string; initial: string }
export interface PinEditorState { pinId: number | null; x: number; y: number; kind?: 'loot' | 'poi' | 'hint'; title?: string; lockKind?: boolean; draft?: EditorDraft }
/** Hinweis-Editor ist geparkt, während ein neuer Ort auf der Karte gesetzt wird */
export interface HintDraft extends EditorDraft { editor: PinEditorState; placeTitle: string }

export const useUi = defineStore('ui', () => {
  const stored = load('ui', { sidebarOpen: true, placeNames: true })
  const sidebarOpen = ref(stored.sidebarOpen && window.innerWidth > 760)
  /** Ortsnamen (Regionen, Städte, Stadtteile …) als Text-Ebene auf der Karte */
  const placeNames = ref(stored.placeNames !== false)
  const toasts = ref<Toast[]>([])
  const loginOpen = ref(false)
  const passwordOpen = ref(false)
  const pinEditor = ref<PinEditorState | null>(null)
  const hintDraft = ref<HintDraft | null>(null)
  let seq = 0

  function toast(text: string, kind: Toast['kind'] = 'info', ms = 3500) {
    const id = ++seq
    toasts.value.push({ id, text, kind })
    window.setTimeout(() => (toasts.value = toasts.value.filter((t) => t.id !== id)), ms)
  }

  function error(e: unknown) {
    toast(e instanceof Error ? e.message : String(e), 'error', 6000)
  }

  function toggleSidebar(open?: boolean) {
    sidebarOpen.value = open ?? !sidebarOpen.value
    persist()
  }

  function persist() {
    save('ui', { sidebarOpen: sidebarOpen.value, placeNames: placeNames.value })
  }
  watch(placeNames, persist)

  return { sidebarOpen, placeNames, toasts, loginOpen, passwordOpen, pinEditor, hintDraft, toast, error, toggleSidebar }
})
