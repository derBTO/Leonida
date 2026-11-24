import L from 'leaflet'

/**
 * Koordinatensystem von gtadb.org in Leaflet: Breitengrad = Spiel-y (Norden), Längengrad = Spiel-x.
 * Zoom z entspricht der gtadb-Zoomstufe: px = (x + 16384)·2^z/32, py = (16384 − y)·2^z/32.
 */
export const LeonidaCRS: L.CRS = L.extend({}, L.CRS.Simple, {
  transformation: new L.Transformation(1 / 32, 512, -1 / 32, 512),
})

export const toLatLng = (x: number, y: number) => L.latLng(y, x)
export const fromLatLng = (ll: L.LatLng) => ({ x: Math.round(ll.lng * 100) / 100, y: Math.round(ll.lat * 100) / 100 })
