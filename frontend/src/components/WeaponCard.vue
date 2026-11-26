<script setup lang="ts">
import type { Weapon } from '../api/types'
import LegendaryStar from './LegendaryStar.vue'
import MediaImage from './MediaImage.vue'
import SourceLink from './SourceLink.vue'
import StatBar from './StatBar.vue'

/** Waffenkarte: 1. Name, 2. großes Bild, 3. Eigenschaften, 4. Werte. */
defineProps<{ weapon: Weapon }>()
const name = (v: { name: string } | null) => v?.name ?? '–'
</script>

<template>
  <article class="card">
    <h1 class="title" :class="{ 'legendary-name': weapon.is_legendary }">{{ weapon.name }}<LegendaryStar v-if="weapon.is_legendary" :size="26" /></h1>
    <div class="subtitle">{{ [weapon.weapon_type?.name, weapon.fire_mode?.name].filter(Boolean).join(' · ') || 'Typ unbekannt' }}</div>
    <div class="image"><MediaImage :media="weapon.image" :alt="weapon.name" /></div>
    <dl class="props">
      <div><dt>Waffentyp</dt><dd>{{ name(weapon.weapon_type) }}</dd></div>
      <div><dt>Art</dt><dd>{{ name(weapon.fire_mode) }}</dd></div>
      <div><dt>Marke</dt><dd>{{ name(weapon.brand) }}</dd></div>
      <div><dt>Kaliber</dt><dd>{{ name(weapon.caliber) }}</dd></div>
      <div><dt>Magazin</dt><dd>{{ weapon.magazine_size ?? '–' }}</dd></div>
      <div><dt>Legendär / Unique</dt><dd>{{ weapon.is_legendary === null ? '–' : weapon.is_legendary ? 'Ja' : 'Nein' }}</dd></div>
    </dl>
    <div class="stats">
      <StatBar label="Schaden" :value="weapon.stats.damage" />
      <StatBar label="Treffgenauigkeit" :value="weapon.stats.accuracy" />
      <StatBar label="Handling" :value="weapon.stats.handling" />
      <StatBar label="Reichweite" :value="weapon.stats.range" />
    </div>
    <p v-if="weapon.notes" class="notes">{{ weapon.notes }}</p>
    <SourceLink v-if="weapon.source" :source="weapon.source" />
  </article>
</template>

<style scoped>
@import './card.css';
</style>
