export type LootType = 'vehicles' | 'weapons' | 'drugs' | 'jewelry' | 'cash'
export type PinStatus = 'unconfirmed' | 'confirmed' | 'gone'
export type RespawnStatus = 'unknown' | 'once' | 'recurring'
export type PinKind = 'loot' | 'poi'
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
}

export interface MediaRef { id: string; url: string; thumb_url: string; width: number; height: number }
export interface GalleryImage { id: number; url: string; thumb_url: string; width: number; height: number; caption: string | null }
export interface LookupValue { id: number; category: LookupCategory; name: string; usage?: number }
export interface PinCode { id?: number; label: string | null; code: string; description: string | null }

export type WeaponStats = Record<'damage' | 'accuracy' | 'handling' | 'range', number | null>
export type VehicleStats = Record<'speed' | 'acceleration' | 'handling_on_road' | 'handling_off_road' | 'durability', number | null>

export interface Location { id: number; title: string; x: number; y: number; status: PinStatus }

export interface WeaponSummary {
  id: number
  name: string
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
  created_at: string | null
  updated_at: string | null
  created_by: string | null
  updated_by: string | null
  locations: Location[]
}

export interface VehicleSummary {
  id: number
  name: string
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
  access: 'key' | 'keyless' | null
  registrable: boolean | null
  notes: string | null
  created_at: string | null
  updated_at: string | null
  created_by: string | null
  updated_by: string | null
  locations: Location[]
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
  updated_by: string | null
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
