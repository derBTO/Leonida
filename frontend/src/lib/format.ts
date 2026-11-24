const usd = new Intl.NumberFormat('de-DE', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 })
const num = new Intl.NumberFormat('de-DE')
const dateTime = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' })

export const formatUsd = (v: number | null | undefined) => (v === null || v === undefined ? '–' : usd.format(v))
export const formatNumber = (v: number | null | undefined) => (v === null || v === undefined ? '–' : num.format(v))
export const formatDate = (v: string | null | undefined) => (v ? dateTime.format(new Date(v)) : '–')
export const formatStat = (v: number | null | undefined) =>
  v === null || v === undefined ? '–' : v.toLocaleString('de-DE', { minimumFractionDigits: 1, maximumFractionDigits: 1 })

/** "12.500" oder "12500" → 12500; leer → null; ungültig → NaN. */
export function parseUsd(text: string): number | null {
  const clean = text.replace(/[\s.$€]/g, '').replace(',', '')
  if (clean === '') return null
  return /^\d+$/.test(clean) ? Number(clean) : NaN
}

/** Statistik-Eingabe mit Komma oder Punkt. */
export function parseStat(text: string): number | null {
  const clean = text.trim().replace(',', '.')
  if (clean === '') return null
  const n = Number(clean)
  return Number.isFinite(n) ? n : NaN
}
