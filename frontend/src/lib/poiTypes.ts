import type { PoiType } from '../api/types'

// Symbole aus Lucide (ISC-Lizenz) als SVG-Strings für Kartenmarker und Listen;
// die Pistole (Ammu-Nation) ist eigens im selben Stil gezeichnet.
const svg = (inner: string) =>
  `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">${inner}</svg>`

/** Standardfarbe für Orte ohne eigene Farbe: Rosé wie die Kartensymbole im Spiel, Symbol schwarz */
export const POI_STANDARD = '#f2ceca'
export const POI_STANDARD_FG = '#141414'

/** Kategorien in der Reihenfolge der Filterliste. Orte in der Standardfarbe werden kleiner dargestellt. */
export const POI: Record<PoiType, { label: string; color: string; svg: string }> = {
  poi: { label: "POI Allgemein", color: '#3b82f6', svg: svg('<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>') },
  photo: { label: "Photo", color: '#e040fb', svg: svg('<path d="M13.997 4a2 2 0 0 1 1.76 1.05l.486.9A2 2 0 0 0 18.003 7H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h1.997a2 2 0 0 0 1.759-1.048l.489-.904A2 2 0 0 1 10.004 4z"/><circle cx="12" cy="13" r="3"/>') },
  ammunation: { label: "Ammu-Nation", color: POI_STANDARD, svg: svg('<path d="M3 8h17v4h-8l-1 2h-2l-1.5 5a1 1 0 0 1-1 .7H4.6a1 1 0 0 1-1-1.3L5.5 12H4a1 1 0 0 1-1-1z"/><path d="M10 12v2"/><path d="M17 8V6"/>') },
  bar: { label: "Bar", color: POI_STANDARD, svg: svg('<path d="M17 11h1a3 3 0 0 1 0 6h-1"/><path d="M9 12v6"/><path d="M13 12v6"/><path d="M14 7.5c-1 0-1.44.5-3 .5s-2-.5-3-.5-1.72.5-2.5.5a2.5 2.5 0 0 1 0-5c.78 0 1.57.5 2.5.5S9.44 2 11 2s2 1.5 3 1.5 1.72-.5 2.5-.5a2.5 2.5 0 0 1 0 5c-.78 0-1.5-.5-2.5-.5Z"/><path d="M5 8v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V8"/>') },
  gas_station: { label: "Tankstelle", color: POI_STANDARD, svg: svg('<path d="M14 13h2a2 2 0 0 1 2 2v2a2 2 0 0 0 4 0v-6.998a2 2 0 0 0-.59-1.42L18 5"/><path d="M14 21V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v16"/><path d="M2 21h13"/><path d="M3 9h11"/>') },
  groceries: { label: "Lebensmittel", color: POI_STANDARD, svg: svg('<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>') },
  clothing: { label: "Kleidung", color: POI_STANDARD, svg: svg('<path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/>') },
  tattoo: { label: "Tattoo", color: POI_STANDARD, svg: svg('<path d="M15.707 21.293a1 1 0 0 1-1.414 0l-1.586-1.586a1 1 0 0 1 0-1.414l5.586-5.586a1 1 0 0 1 1.414 0l1.586 1.586a1 1 0 0 1 0 1.414z"/><path d="m18 13-1.375-6.874a1 1 0 0 0-.746-.776L3.235 2.028a1 1 0 0 0-1.207 1.207L5.35 15.879a1 1 0 0 0 .776.746L13 18"/><path d="m2.3 2.3 7.286 7.286"/><circle cx="11" cy="11" r="2"/>') },
  garage: { label: "Garage", color: '#8b5cf6', svg: svg('<path d="M18 21V10a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v11"/><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V8a2 2 0 0 1 1.132-1.803l7.95-3.974a2 2 0 0 1 1.837 0l7.948 3.974A2 2 0 0 1 22 8z"/><path d="M6 13h12"/><path d="M6 17h12"/>') },
  tuning: { label: "Auto-Tuning", color: POI_STANDARD, svg: svg('<path d="m12 14 4-4"/><path d="M3.34 19a10 10 0 1 1 17.32 0"/>') },
  barber: { label: "Frisör", color: POI_STANDARD, svg: svg('<circle cx="6" cy="6" r="3"/><path d="M8.12 8.12 12 12"/><path d="M20 4 8.12 15.88"/><circle cx="6" cy="18" r="3"/><path d="M14.8 14.8 20 20"/>') },
  pay_and_spray: { label: "Pay & Spray", color: '#facc15', svg: svg('<path d="M3 3h.01"/><path d="M7 5h.01"/><path d="M11 7h.01"/><path d="M3 7h.01"/><path d="M7 9h.01"/><path d="M3 11h.01"/><rect width="4" height="4" x="15" y="5"/><path d="m19 9 2 2v10c0 .6-.4 1-1 1h-6c-.6 0-1-.4-1-1V11l2-2"/><path d="m13 14 8-2"/><path d="m13 19 8-2"/>') },
  workshop: { label: "Autowerkstatt", color: '#f97316', svg: svg('<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.106-3.105c.32-.322.863-.22.983.218a6 6 0 0 1-8.259 7.057l-7.91 7.91a1 1 0 0 1-2.999-3l7.91-7.91a6 6 0 0 1 7.057-8.259c.438.12.54.662.219.984z"/>') },
  airport: { label: "Flughafen", color: POI_STANDARD, svg: svg('<path d="M17.8 19.2 16 11l3.5-3.5C21 6 21.5 4 21 3c-1-.5-3 0-4.5 1.5L13 8 4.8 6.2c-.5-.1-.9.1-1.1.5l-.3.5c-.2.5-.1 1 .3 1.3L9 12l-2 3H4l-1 1 3 2 2 3 1-1v-3l3-2 3.5 5.3c.3.4.8.5 1.3.3l.5-.2c.4-.3.6-.7.5-1.2z"/>') },
  harbor: { label: "Hafen", color: POI_STANDARD, svg: svg('<path d="M12 6v16"/><path d="m19 13 2-1a9 9 0 0 1-18 0l2 1"/><path d="M9 11h6"/><circle cx="12" cy="4" r="2"/>') },
  military: { label: "Militär", color: POI_STANDARD, svg: svg('<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>') },
  restaurant: { label: "Restaurant", color: POI_STANDARD, svg: svg('<path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/>') },
  apartment: { label: "Wohnung", color: '#ef4444', svg: svg('<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-6a2 2 0 0 1 2.582 0l7 6A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>') },
  race: { label: "Rennen", color: '#22c55e', svg: svg('<path d="M4 22V4a1 1 0 0 1 .4-.8A6 6 0 0 1 8 2c3 0 5 2 7.333 2q2 0 3.067-.8A1 1 0 0 1 20 4v10a1 1 0 0 1-.4.8A6 6 0 0 1 16 16c-3 0-5-2-8-2a6 6 0 0 0-4 1.528"/>') },
  bus_stop: { label: "Bushaltestelle", color: POI_STANDARD, svg: svg('<path d="M8 6v6"/><path d="M15 6v6"/><path d="M2 12h19.6"/><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"/><circle cx="7" cy="18" r="2"/><path d="M9 18h5"/><circle cx="16" cy="18" r="2"/>') },
  train_stop: { label: "Bahnhaltestelle", color: POI_STANDARD, svg: svg('<path d="M8 3.1V7a4 4 0 0 0 8 0V3.1"/><path d="m9 15-1-1"/><path d="m15 15 1-1"/><path d="M9 19c-2.8 0-5-2.2-5-5v-4a8 8 0 0 1 16 0v4c0 2.8-2.2 5-5 5Z"/><path d="m8 19-2 3"/><path d="m16 19 2 3"/>') },
}

export const POI_ORDER: PoiType[] = ['poi', 'photo', 'ammunation', 'bar', 'gas_station', 'groceries', 'clothing', 'tattoo', 'garage', 'tuning', 'barber', 'pay_and_spray', 'workshop', 'airport', 'harbor', 'military', 'restaurant', 'apartment', 'race', 'bus_stop', 'train_stop']

export const isProminent = (t: PoiType) => POI[t].color !== POI_STANDARD

/** Symbolfarbe passend zum Hintergrund (schwarz auf Rosé, sonst weiß) */
export const poiFg = (t: PoiType) => (POI[t].color === POI_STANDARD ? POI_STANDARD_FG : '#fff')
/** Style-Variablen für Kategorie-Symbole: --c Hintergrund, --fg Symbol */
export const poiStyle = (t: PoiType) => ({ '--c': POI[t].color, '--fg': poiFg(t) })
