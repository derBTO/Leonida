<script setup lang="ts">
import type { Vehicle } from '../api/types'
import { useSession } from '../stores/session'
import MediaImage from './MediaImage.vue'
import SourceLink from './SourceLink.vue'
import RatingPair from './RatingPair.vue'
import StatBar from './StatBar.vue'

/** Fahrzeugkarte: Name, Bild, Eigenschaften, Werte, Ratings. */
defineProps<{ vehicle: Vehicle }>()
const session = useSession()
const name = (v: { name: string } | null) => v?.name ?? '–'
const yesNo = (v: boolean | null) => (v === null ? '–' : v ? 'Ja' : 'Nein')
</script>

<template>
  <article class="card">
    <h1 class="title">{{ vehicle.name }}</h1>
    <div class="subtitle">{{ [vehicle.manufacturer?.name, vehicle.vehicle_class?.name].filter(Boolean).join(' · ') || 'Hersteller unbekannt' }}</div>
    <div class="image"><MediaImage :media="vehicle.image" :alt="vehicle.name" /></div>
    <dl class="props">
      <div><dt>Hersteller</dt><dd>{{ name(vehicle.manufacturer) }}</dd></div>
      <div><dt>Klasse</dt><dd>{{ name(vehicle.vehicle_class) }}</dd></div>
      <div><dt>Stil</dt><dd>{{ name(vehicle.style) }}</dd></div>
      <div><dt>Antrieb</dt><dd>{{ vehicle.drivetrain ?? '–' }}</dd></div>
      <div><dt>Sitze</dt><dd>{{ vehicle.seats ?? '–' }}</dd></div>
      <div><dt>Sicherheit</dt><dd>{{ session.label('security', vehicle.security) }}</dd></div>
      <div><dt>Tracker</dt><dd>{{ session.label('tracker', vehicle.tracker) }}</dd></div>
      <div><dt>Zugang</dt><dd>{{ session.label('access', vehicle.access) }}</dd></div>
      <div><dt>Registrierbar</dt><dd>{{ yesNo(vehicle.registrable) }}</dd></div>
    </dl>
    <div class="stats">
      <StatBar label="Geschwindigkeit" :value="vehicle.stats.speed" />
      <StatBar label="Beschleunigung" :value="vehicle.stats.acceleration" />
      <StatBar label="On Road Handling" :value="vehicle.stats.handling_on_road" />
      <StatBar label="Off Road Handling" :value="vehicle.stats.handling_off_road" />
      <StatBar label="Belastbarkeit" :value="vehicle.stats.durability" />
    </div>
    <RatingPair :on-road="vehicle.rating_on_road" :off-road="vehicle.rating_off_road" />
    <p v-if="vehicle.notes" class="notes">{{ vehicle.notes }}</p>
    <SourceLink v-if="vehicle.source" :source="vehicle.source" />
  </article>
</template>

<style scoped>
@import './card.css';
</style>
