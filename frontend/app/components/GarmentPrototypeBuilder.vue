<script setup lang="ts">
import { computed } from "vue"

interface Part { id: string; name: string; display_order?: number; svg_asset_ref?: string }
interface Measurement { part_id: string; value: number; unit: string }
const props = defineProps<{ garmentName: string; parts: Part[]; measurements: Measurement[] }>()
const completedIds = computed(() => new Set(props.measurements.map(item => item.part_id)))
const orderedParts = computed(() => [...props.parts].sort((a, b) => (a.display_order || 0) - (b.display_order || 0)))
const progress = computed(() => props.parts.length ? Math.round(completedIds.value.size / props.parts.length * 100) : 0)
</script>
<template>
  <div class="builder">
    <div class="stage">
      <svg viewBox="0 0 300 360" :aria-label="`${garmentName} ${progress}% complete`">
        <path class="ghost" d="M92 50 35 100l30 78 28-18v165h114V160l28 18 30-78-57-50-28 25h-60z" />
        <image v-for="part in orderedParts.filter(item => completedIds.has(item.id) && item.svg_asset_ref)" :key="part.id" :href="part.svg_asset_ref" width="300" height="360" :aria-label="part.name" />
      </svg><b>{{ progress }}%</b>
    </div><ol>
      <li v-for="part in orderedParts" :key="part.id" :class="{ done: completedIds.has(part.id) }">
        <span>{{ completedIds.has(part.id) ? '✓' : '○' }}</span>{{ part.name }}<small>{{ measurements.find(item => item.part_id === part.id)?.value || '—' }} {{ measurements.find(item => item.part_id === part.id)?.unit || '' }}</small>
      </li>
    </ol>
  </div>
</template>
