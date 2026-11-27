import L from 'leaflet'
import { toLatLng } from './crs'
import { LABEL_KINDS, PLACE_LABELS, type PlaceLabel } from './placeLabels'

interface Item {
  label: PlaceLabel
  el: HTMLDivElement
  span: HTMLSpanElement
  ll: L.LatLng
}

/**
 * Ortsnamen-Ebene wie bei Kartendiensten: Je Zoomstufe erscheinen nur die passenden Arten
 * (weit weg Regionen und Städte, nah Stadtteile und Wahrzeichen). Überdecken sich Namen,
 * bleibt der mit dem höheren Vorrang stehen. Reine Anzeige, fängt keine Klicks ab.
 */
export class PlaceLabelLayer extends L.Layer {
  private root?: HTMLDivElement
  private items: Item[] = []

  onAdd(map: L.Map): this {
    if (!map.getPane('labels')) {
      const pane = map.createPane('labels')
      pane.style.zIndex = '450' // über Kacheln und Linien, unter den Pins
      pane.style.pointerEvents = 'none'
    }
    this.root = L.DomUtil.create('div', 'place-labels leaflet-zoom-hide', map.getPane('labels'))
    const sorted = [...PLACE_LABELS].sort((a, b) => LABEL_KINDS[a.k].prio - LABEL_KINDS[b.k].prio)
    this.items = sorted.map((label) => {
      const el = L.DomUtil.create('div', `place-label pl-${label.k}`, this.root)
      const span = document.createElement('span')
      span.textContent = label.n
      if (label.r) span.style.setProperty('--rot', `${label.r}deg`)
      el.appendChild(span)
      return { label, el, span, ll: toLatLng(label.x, label.y) }
    })
    map.on('zoomend viewreset', this.update, this)
    this.update()
    return this
  }

  onRemove(map: L.Map): this {
    map.off('zoomend viewreset', this.update, this)
    this.root?.remove()
    this.root = undefined
    this.items = []
    return this
  }

  /** Position setzen, dann in Vorrang-Reihenfolge Überdeckungen ausblenden (erst alles messen, dann schreiben). */
  private update() {
    const map = this._map
    if (!map || !this.root) return
    const z = map.getZoom()
    // Schrift wächst mit dem Zoom: ganze Karte kleiner, nah normal (Größen in map.css als em)
    this.root.style.fontSize = `${Math.min(1.05, Math.max(0.7, 0.72 + 0.15 * z))}px`
    const active: Item[] = []
    for (const it of this.items) {
      const kind = LABEL_KINDS[it.label.k]
      const on = z >= kind.min && z < kind.max
      it.el.style.display = on ? '' : 'none'
      it.el.style.visibility = ''
      if (!on) continue
      L.DomUtil.setPosition(it.el, map.latLngToLayerPoint(it.ll))
      active.push(it)
    }
    const rects = active.map((it) => it.span.getBoundingClientRect())
    const placed: DOMRect[] = []
    const gap = 6
    active.forEach((it, i) => {
      const r = rects[i]
      const hit = placed.some((p) => r.left < p.right + gap && r.right > p.left - gap && r.top < p.bottom + gap && r.bottom > p.top - gap)
      if (hit) it.el.style.visibility = 'hidden'
      else placed.push(r)
    })
  }
}
