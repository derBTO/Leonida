/** Schlanker Fetch-Wrapper: Session-Cookie, CSRF-Header, einheitliche Fehler. */

export class ApiError extends Error {
  status: number
  errors: Record<string, string[]>
  data: Record<string, unknown>

  constructor(message: string, status: number, errors: Record<string, string[]> = {}, data: Record<string, unknown> = {}) {
    super(message)
    this.status = status
    this.errors = errors
    this.data = data
  }

  /** Erste Fehlermeldung zu einem Feld (auch verschachtelt, z. B. "stats.damage"). */
  field(name: string): string | undefined {
    return this.errors[name]?.[0]
  }
}

function xsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

type Body = Record<string, unknown> | unknown[] | FormData | undefined

let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(fn: () => void) {
  onUnauthorized = fn
}

export async function request<T>(method: string, url: string, body?: Body, init: RequestInit = {}): Promise<T> {
  const headers: Record<string, string> = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
  const token = xsrfToken()
  if (token) headers['X-XSRF-TOKEN'] = token
  let payload: BodyInit | undefined
  if (body instanceof FormData) payload = body
  else if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
    payload = JSON.stringify(body)
  }
  let response: Response
  try {
    response = await fetch(url, { method, credentials: 'same-origin', ...init, headers: { ...headers, ...(init.headers as Record<string, string>) }, body: payload })
  } catch {
    throw new ApiError('Keine Verbindung zum Server.', 0)
  }
  if (response.status === 204 || response.status === 304) return undefined as T
  const isJson = response.headers.get('Content-Type')?.includes('application/json')
  const data = isJson ? await response.json().catch(() => ({})) : {}
  if (!response.ok) {
    if (response.status === 401 && onUnauthorized && !url.endsWith('/auth/login')) onUnauthorized()
    const message =
      (data as { message?: string }).message && response.status !== 422
        ? (data as { message: string }).message
        : Object.values((data as { errors?: Record<string, string[]> }).errors ?? {})[0]?.[0] ??
          (data as { message?: string }).message ??
          `Fehler ${response.status}`
    throw new ApiError(message, response.status, (data as { errors?: Record<string, string[]> }).errors ?? {}, data as Record<string, unknown>)
  }
  return data as T
}

export const api = {
  get: <T>(url: string, init?: RequestInit) => request<T>('GET', url, undefined, init),
  post: <T>(url: string, body?: Body) => request<T>('POST', url, body ?? {}),
  put: <T>(url: string, body?: Body) => request<T>('PUT', url, body ?? {}),
  patch: <T>(url: string, body?: Body) => request<T>('PATCH', url, body ?? {}),
  delete: <T>(url: string) => request<T>('DELETE', url),
}

/** Sorgt dafür, dass das XSRF-TOKEN-Cookie existiert (wird bei jeder Laravel-Antwort gesetzt). */
export async function ensureCsrf(): Promise<void> {
  if (!xsrfToken()) await api.get('/api/auth/me')
}
