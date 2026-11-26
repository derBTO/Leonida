export type LootType = 'vehicles' | 'weapons' | 'drugs' | 'jewelry' | 'cash'
export type PinStatus = 'unconfirmed' | 'confirmed' | 'gone'
export type RespawnStatus = 'unknown' | 'once' | 'recurring'
export type PinKind = 'loot' | 'poi' | 'hint'
export type PoiType =
  | 'poi'
  | 'photo'
  | 'ammunation'
  | 'bar'
  | 'gas_station'
  | 'groceries'
  | 'clothing'
  | 'tattoo'
  | 'garage'
  | 'tuning'
  | 'barber'
  | 'pay_and_spray'
  | 'workshop'
  | 'airport'
  | 'harbor'
  | 'military'
  | 'restaurant'
  | 'apartment'
  | 'race'
  | 'bus_stop'
  | 'train_stop'
export type LookupCategory =
  | 'weapon_type' | 'fire_mode' | 'weapon_brand' | 'caliber'
  | 'vehicle_manufacturer' | 'vehicle_class' | 'vehicle_style'

export interface User {
  id: number
  username: string
  role: 'admin' | 'editor'
  is_active: boolean
  must_change_password: boolean
  last_login_at: string | null
  created_at: string | null
}

export interface Option { value: string; label: string }

export interface Meta {
  user: User | null
  /** alle Benutzer (nur für Angemeldete), für den Filter „Ersteller“ */
  authors: { id: number; username: string }[]
  enums: Record<'loot_type' | 'poi_type' | 'respawn' | 'pin_status' | 'drivetrain' | 'security' | 'tracker' | 'access' | 'lookup_category', Option[]>
  limits: { upload_max_mb: number; password_min_length: number }
  map: { manifest_url: string; tiles_url: string; version: string | null }
}

/** Kompakter Pin aus /api/pins (Feldnamen kurz gehalten). */
export interface PinIndexItem {
  id: number
  k: PinKind
  p: PoiType | null
  t: string
  x: number
  y: number
  l: LootType[]
  s: PinStatus
  d: string
  w: string[]
  v: string[]
  c: string[]
  /** ID des Erstellers (für den Filter „Ersteller“) */
  u: number | null
  /** Herkunft: 'gtadb' = aus gtadb.org übernommen, sonst null */
  o: 'gtadb' | null
  /** Easteregg (eigenes Symbol, egal welche Art) */
  e: boolean
}

export interface MediaRef { id: string; url: string; thumb_url: string; width: number; height: number }
export interface GalleryImage { id: number; url: string; thumb_url: string; width: number; height: number; caption: string | null }
export interface LookupValue { id: number; category: LookupCategory; name: string; usage?: number }
export interface PinCode { id?: number; label: string | null; code: string; description: string | null }

export type WeaponStats = Record<'damage' | 'accuracy' | 'handling' | 'range', number | null>
export type VehicleStats = Record<'speed' | 'acceleration' | 'handling_on_road' | 'handling_off_road' | 'durability', number | null>

export interface Location { id: number; title: string; kind?: PinKind; poi_type?: PoiType | null; x: number; y: number; status: PinStatus; via_hint?: boolean }
/** Puzzle, das einen Katalogeintrag als Belohnung nennt */
export interface PuzzleRef { id: number; name: string }
/** Schritt eines Hinweises: Text und/oder Bild */
export interface HintStep { id: number; text: string | null; image: MediaRef | null }
/** Zeile im Schritt-Editor eines Hinweises */
export interface HintStepRow { key: number; text: string; image: MediaRef | null }
/** Verknüpfter Ort eines Hinweises */
export interface PlaceRef { id: number; title: string; poi_type: PoiType | null; x: number; y: number }

/** Herkunft übernommener Daten (z. B. NiaDB mit Link zur Quellseite). */
export interface CatalogSource {
  name: string
  url: string | null
}

export interface WeaponSummary {
  id: number
  name: string
  created_by_id?: number | null
  /** wer den Eintrag angelegt oder bearbeitet hat (Übersicht) */
  editor_ids?: number[]
  /** true = legendär/unique, false = nein, null = unbekannt */
  is_legendary: boolean | null
  image: MediaRef | null
  weapon_type: LookupValue | null
  stats: WeaponStats
  location_count?: number
}

export interface Weapon extends WeaponSummary {
  uuid: string
  fire_mode: LookupValue | null
  brand: LookupValue | null
  caliber: LookupValue | null
  magazine_size: number | null
  notes: string | null
  source: CatalogSource | null
  created_at: string | null
  updated_at: string | null
  created_by: string | null
  updated_by: string | null
  locations: Location[]
  puzzles: PuzzleRef[]
}

export interface VehicleSummary {
  id: number
  name: string
  created_by_id?: number | null
  /** wer den Eintrag angelegt oder bearbeitet hat (Übersicht) */
  editor_ids?: number[]
  image: MediaRef | null
  manufacturer: LookupValue | null
  vehicle_class: LookupValue | null
  stats: VehicleStats
  rating_on_road: number | null
  rating_off_road: number | null
  location_count?: number
}

export interface Vehicle extends VehicleSummary {
  uuid: string
  style: LookupValue | null
  drivetrain: 'FWD' | 'RWD' | 'AWD' | null
  seats: number | null
  security: 'not_specified' | 'basic' | 'modern' | 'advanced' | null
  tracker: 'none' | 'standard' | 'advanced' | 'military' | null
  access: 'none' | 'key' | 'keyless' | null
  registrable: boolean | null
  notes: string | null
  source: CatalogSource | null
  created_at: string | null
  updated_at: string | null
  created_by: string | null
  updated_by: string | null
  locations: Location[]
  puzzles: PuzzleRef[]
}

export interface Pin {
  id: number
  uuid: string
  kind: PinKind
  poi_type: PoiType | null
  title: string
  description: string | null
  x: number
  y: number
  loot_types: LootType[]
  requirements: string | null
  drugs_value_usd: number | null
  jewelry_value_usd: number | null
  cash_usd: number | null
  respawn: RespawnStatus
  status: PinStatus
  weapons: WeaponSummary[]
  vehicles: VehicleSummary[]
  codes: PinCode[]
  images: GalleryImage[]
  created_at: string | null
  updated_at: string | null
  created_by: string | null
  created_by_id: number | null
  source: 'gtadb' | null
  is_easteregg: boolean
  updated_by: string | null
  trails: PinTrailRef[]
  /** Hinweis: verknüpfter Ort */
  place: PlaceRef | null
  /** Hinweis: Schritte */
  steps: HintStep[]
  /** Ort: Hinweise, die auf ihn verweisen */
  hints: { id: number; title: string }[]
}

/** Spur, in der ein Pin als Schritt vorkommt */
export interface PinTrailRef { trail_id: number; trail_name: string; step_id: number; position: number; step_count: number }

export interface TrailSummary {
  id: number
  name: string
  description: string | null
  /** höchste Schrittnummer (parallele Schritte zählen einmal) */
  step_count: number
  steps_total: number
  /** erledigte Schritte der angemeldeten Person */
  steps_done: number
  /** Namen der Belohnungen (Waffen, Fahrzeuge) */
  rewards: string[]
  pin_ids: number[]
  created_by_id: number | null
  created_by: string | null
  updated_at: string | null
}

export interface TrailStepPin { id: number; title: string; kind: PinKind; poi_type: PoiType | null; x: number; y: number; place_id?: number | null }

export interface TrailStep {
  id: number
  position: number
  title: string
  description: string | null
  pin: TrailStepPin | null
  done: boolean
}

export interface Trail extends TrailSummary {
  uuid: string
  updated_by: string | null
  created_at: string | null
  weapons: WeaponSummary[]
  vehicles: VehicleSummary[]
  steps: TrailStep[]
}

export interface AuditEntry {
  id: number
  user: string | null
  action: string
  entity: string
  entity_id: string | null
  summary: string | null
  changes: Record<string, unknown> | null
  created_at: string
}

export interface MapManifest {
  version: string
  source: { repository: string; commit: string; commitDate: string }
  crs: { transformation: [number, number, number, number]; tileSize: number; tileUrl: string }
  tileSets: Array<{
    id: string
    minZoom: number
    maxZoom: number
    ranges: Record<string, [[number, number], [number, number]]>
    bounds: { west: number; east: number; south: number; north: number }
    overlay: { combined: string; parts: string[] } | null
  }>
  overlayRanges: Record<string, Record<string, [[number, number], [number, number]]>>
  attribution: string[]
}
