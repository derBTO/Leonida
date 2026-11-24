/** localStorage mit Schutz gegen gesperrten oder vollen Speicher. */
const PREFIX = 'leonida:'

export function load<T>(key: string, fallback: T): T {
  try {
    const raw = window.localStorage.getItem(PREFIX + key)
    return raw === null ? fallback : { ...fallback, ...JSON.parse(raw) }
  } catch {
    return fallback
  }
}

export function save(key: string, value: unknown): void {
  try {
    window.localStorage.setItem(PREFIX + key, JSON.stringify(value))
  } catch {
    /* Speicher nicht verfügbar – Einstellungen gelten dann nur für diese Sitzung */
  }
}
