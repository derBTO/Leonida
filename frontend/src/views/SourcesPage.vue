<script setup lang="ts">
import { onMounted, ref } from 'vue'
import type { MapManifest } from '../api/types'
import { useSession } from '../stores/session'

const session = useSession()
const manifest = ref<MapManifest | null>(null)
onMounted(async () => {
  try {
    const res = await fetch(session.meta?.map.manifest_url ?? '/map/current/manifest.json')
    if (res.ok) manifest.value = await res.json()
  } catch {
    /* ohne Karte keine Versionsangabe */
  }
})
</script>

<template>
  <div class="page">
    <article class="panel box">
      <h1>Quellen &amp; Lizenzen</h1>
      <h2>Kartenkacheln</h2>
      <p>
        Die Karte von Leonida stammt von <a href="https://map.gtadb.org" target="_blank" rel="noopener">gtadb.org</a>
        (<a href="https://github.com/rolux/gtadb.org" target="_blank" rel="noopener">GitHub</a>) und steht unter der Lizenz
        <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">Creative Commons Attribution 4.0 International (CC BY 4.0)</a>.
      </p>
      <p class="muted">„GTA VI Map Tiles (CC BY 4.0) — https://map.gtadb.org“ · Copyright © 2025–2026 GTADB.ORG and contributors.</p>
      <p>
        Genutzt wird die Satellitenkarte (Tile-Set <em>day</em>). Die Kacheln werden unverändert und lokal ausgeliefert.
      </p>
      <p>Ein Teil der Orte (POIs) wie Tankstellen, Haltestellen und Geschäfte wurde aus den Landmarken-Daten von gtadb.org übernommen (CC BY 4.0, „GTA VI Landmarks Data — https://map.gtadb.org“). Die Beschreibung jedes übernommenen Orts verlinkt auf den Originaleintrag.</p>
      <p v-if="manifest" class="muted">Aktueller Kartenstand: {{ manifest.version }} (gtadb.org-Commit {{ manifest.source.commit.slice(0, 7) }}).</p>
      <h2>Waffen- und Fahrzeugdaten</h2>
      <p>
        Viele Waffen und Fahrzeuge samt Werten (Schaden, Treffgenauigkeit, Geschwindigkeit, Ratings usw.) und Beschreibungen stammen aus
        <a href="https://meowdb.com/gta6/" target="_blank" rel="noopener">NiaDB (NiaMeowDB)</a>, das die Weiterverwendung mit Quellenlink erlaubt.
        Jeder übernommene Eintrag verlinkt mit „Daten von NiaDB“ auf die Seite, von der seine Daten stammen. Bilder wurden nicht übernommen.
      </p>
      <h2>Spiel</h2>
      <p>Grand Theft Auto VI, Leonida und zugehörige Namen sind Marken von Take-Two Interactive bzw. Rockstar Games. Dies ist ein privates Fanprojekt ohne Verbindung zu diesen Unternehmen.</p>
      <h2>Software</h2>
      <p>Leaflet und Leaflet.markercluster (BSD-2-Clause), Vue, Pinia, Vue Router (MIT), Lucide Icons (ISC), Inter und Barlow Condensed (SIL Open Font License), Laravel (MIT), Intervention Image (MIT).</p>
    </article>
  </div>
</template>

<style scoped>
@import './page.css';
.box { padding: 24px; max-width: 760px; }
h1 { font-size: 36px; margin-bottom: 10px; }
h2 { font-size: 20px; margin: 20px 0 6px; }
a { color: var(--orange); }
</style>
