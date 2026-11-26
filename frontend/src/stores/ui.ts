import { defineStore } from 'pinia'
import { ref } from 'vue'
import { load, save } from '../lib/storage'

export interface Toast { id: number; text: string; kind: 'info' | 'error' | 'ok' }

export interface PinEditorState { pinId: number | null; x: number; y: number; kind?: 'loot' | 'poi' | 'hint'; title?: string; lockKind?: boolean }

export const useUi = defineStore('ui', () => {
  const stored = load('ui', { sidebarOpen: true })
  const sidebarOpen = ref(stored.sidebarOpen && window.innerWidth > 760)
  const toasts = ref<Toast[]>([])
  const loginOpen = ref(false)
  const passwordOpen = ref(false)
  const pinEditor = ref<PinEditorState | null>(null)
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
    save('ui', { sidebarOpen: sidebarOpen.value })
  }

  return { sidebarOpen, toasts, loginOpen, passwordOpen, pinEditor, toast, error, toggleSidebar }
})
