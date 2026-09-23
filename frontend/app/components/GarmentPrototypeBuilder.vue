<script setup lang="ts">
import { computed, ref } from "vue"

interface Part {
  id: string
  name: string
  slug?: string
  display_order?: number
  unit?: string
  svg_asset_ref?: string
}

interface Measurement {
  part_id: string
  value: number
  unit: string
}

const props = defineProps<{
  garmentName: string
  parts: Part[]
  measurements: Measurement[]
}>()

const hoveredPartId = ref<string | null>(null)

const completedIds = computed(() => new Set(props.measurements.map(item => item.part_id)))
const orderedParts = computed(() => [...props.parts].sort((a, b) => (a.display_order || 0) - (b.display_order || 0)))
const progress = computed(() => props.parts.length ? Math.round(completedIds.value.size / props.parts.length * 100) : 0)

const measurementMap = computed(() => {
  const map: Record<string, Measurement> = {}
  for (const m of props.measurements) {
    map[m.part_id] = m
  }
  return map
})

const garmentType = computed<'suit' | 'panjabi' | 'shirt' | 'pant' | 'sherwani' | 'waistcoat'>(() => {
  const name = (props.garmentName || "").toLowerCase()
  if (name.includes("suit") || name.includes("coat") || name.includes("blazer")) return "suit"
  if (name.includes("pant") || name.includes("trouser") || name.includes("pajama")) return "pant"
  if (name.includes("shirt")) return "shirt"
  if (name.includes("sherwani")) return "sherwani"
  if (name.includes("waistcoat") || name.includes("mujib") || name.includes("koti")) return "waistcoat"
  return "panjabi"
})

// Check if a part slug or name matches a given key
function isPartActive(keyword: string): boolean {
  return orderedParts.value.some(p => {
    const matched = p.id === keyword ||
      (p.slug && p.slug.toLowerCase().includes(keyword.toLowerCase())) ||
      p.name.toLowerCase().includes(keyword.toLowerCase())
    return matched && (completedIds.value.has(p.id) || hoveredPartId.value === p.id)
  })
}

function getPartVal(keyword: string): string | null {
  const part = orderedParts.value.find(p =>
    p.id === keyword ||
    (p.slug && p.slug.toLowerCase().includes(keyword.toLowerCase())) ||
    p.name.toLowerCase().includes(keyword.toLowerCase())
  )
  if (!part || !measurementMap.value[part.id]) return null
  return `${measurementMap.value[part.id].value} ${measurementMap.value[part.id].unit || 'in'}`
}
</script>

<template>
  <div class="garment-blueprint-container">
    <div class="blueprint-stage">
      <svg
        viewBox="0 0 300 360"
        class="blueprint-svg"
        :aria-label="`${garmentName} ${progress}% complete`"
      >
        <defs>
          <linearGradient id="fabricGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="var(--paper)" stop-opacity="0.8" />
            <stop offset="100%" stop-color="var(--surface)" stop-opacity="0.95" />
          </linearGradient>
          <linearGradient id="activeGrad" x1="0%" y1="0%" x2="100%" y2="0%">
            <stop offset="0%" stop-color="#10b981" />
            <stop offset="100%" stop-color="#059669" />
          </linearGradient>
          <filter id="blueprintGlow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#10b981" flood-opacity="0.3" />
          </filter>
        </defs>

        <!-- GRID BACKGROUND -->
        <g class="blueprint-grid" opacity="0.12" stroke="currentColor" stroke-width="0.5">
          <line v1 v-for="x in [30, 60, 90, 120, 150, 180, 210, 240, 270]" :key="`gx-${x}`" :x1="x" y1="20" :x2="x" y2="340" />
          <line h1 v-for="y in [40, 80, 120, 160, 200, 240, 280, 320]" :key="`gy-${y}`" x1="20" :y1="y" x2="280" :y2="y" />
        </g>

        <!-- ==================== SUIT 2-PIECE BLUEPRINT ==================== -->
        <g v-if="garmentType === 'suit'" class="garment-vector-group">
          <!-- Coat Silhouette & Fabric -->
          <path
            class="silhouette-path"
            d="M80 44 L32 94 L54 165 L84 150 L84 278 L216 278 L216 150 L246 165 L268 94 L220 44 L185 64 L150 78 L115 64 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="2"
          />
          <!-- Lapels & Collar -->
          <path d="M115 64 L138 140 L110 148 L80 44" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <path d="M185 64 L162 140 L190 148 L220 44" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <path d="M138 140 L150 178 L162 140" fill="none" stroke="var(--ink)" stroke-width="1.5" />
          <!-- Chest Pocket -->
          <path d="M96 125 L120 125" stroke="var(--ink)" stroke-width="1.5" stroke-linecap="round" />
          <!-- Flap Pockets -->
          <rect x="88" y="210" width="36" height="10" rx="2" fill="none" stroke="var(--ink)" stroke-width="1.25" />
          <rect x="176" y="210" width="36" height="10" rx="2" fill="none" stroke="var(--ink)" stroke-width="1.25" />
          <!-- Coat Buttons -->
          <circle cx="150" cy="184" r="2.5" fill="var(--ink)" />
          <circle cx="150" cy="214" r="2.5" fill="var(--ink)" />
          
          <!-- Trouser preview silhouette -->
          <path
            d="M106 284 L90 348 L124 348 L150 300 L176 348 L210 348 L194 284 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="1.5"
            stroke-dasharray="3 3"
          />

          <!-- Active Measurement Highlights -->
          <g v-if="isPartActive('coat-length') || isPartActive('length')" class="guide-active">
            <line x1="150" y1="44" x2="150" y2="278" stroke="#10b981" stroke-width="2.5" stroke-dasharray="4 3" filter="url(#blueprintGlow)" />
            <circle cx="150" cy="44" r="3" fill="#10b981" />
            <circle cx="150" cy="278" r="3" fill="#10b981" />
          </g>
          <g v-if="isPartActive('chest')" class="guide-active">
            <line x1="72" y1="130" x2="228" y2="130" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <circle cx="72" cy="130" r="3.5" fill="#10b981" /><circle cx="228" cy="130" r="3.5" fill="#10b981" />
          </g>
          <g v-if="isPartActive('waist')" class="guide-active">
            <line x1="84" y1="190" x2="216" y2="190" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <circle cx="84" cy="190" r="3.5" fill="#10b981" /><circle cx="216" cy="190" r="3.5" fill="#10b981" />
          </g>
          <g v-if="isPartActive('shoulder')" class="guide-active">
            <path d="M80 44 L115 64 M185 64 L220 44" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('sleeve')" class="guide-active">
            <path d="M80 44 L32 94 L54 165" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
            <path d="M220 44 L268 94 L246 165" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('pant-length')" class="guide-active">
            <line x1="90" y1="284" x2="90" y2="348" stroke="#10b981" stroke-width="2.5" stroke-dasharray="3 3" />
            <line x1="210" y1="284" x2="210" y2="348" stroke="#10b981" stroke-width="2.5" stroke-dasharray="3 3" />
          </g>
        </g>

        <!-- ==================== PANJABI BLUEPRINT ==================== -->
        <g v-else-if="garmentType === 'panjabi'" class="garment-vector-group">
          <!-- Panjabi Body & Flared Gher -->
          <path
            class="silhouette-path"
            d="M92 48 L35 98 L65 174 L92 158 L86 332 L214 332 L208 158 L235 174 L265 98 L208 48 L170 68 L130 68 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="2"
          />
          <!-- Band Collar & Placket -->
          <path d="M130 68 Q150 92 170 68 V48 Q150 28 130 48 Z" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <line x1="150" y1="74" x2="150" y2="185" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" />
          <circle cx="150" cy="98" r="2" fill="var(--ink)" />
          <circle cx="150" cy="124" r="2" fill="var(--ink)" />
          <circle cx="150" cy="150" r="2" fill="var(--ink)" />
          <circle cx="150" cy="176" r="2" fill="var(--ink)" />
          <!-- Side Slits (Phara) -->
          <line x1="88" y1="230" x2="86" y2="332" stroke="var(--ink)" stroke-width="1.5" />
          <line x1="212" y1="230" x2="214" y2="332" stroke="var(--ink)" stroke-width="1.5" />

          <!-- Active Highlights -->
          <g v-if="isPartActive('length') || isPartActive('body')" class="guide-active">
            <line x1="150" y1="48" x2="150" y2="332" stroke="#10b981" stroke-width="2.5" stroke-dasharray="4 3" filter="url(#blueprintGlow)" />
            <circle cx="150" cy="48" r="3" fill="#10b981" /><circle cx="150" cy="332" r="3" fill="#10b981" />
          </g>
          <g v-if="isPartActive('chest')" class="guide-active">
            <line x1="86" y1="135" x2="214" y2="135" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <circle cx="86" cy="135" r="3.5" fill="#10b981" /><circle cx="214" cy="135" r="3.5" fill="#10b981" />
          </g>
          <g v-if="isPartActive('waist')" class="guide-active">
            <line x1="94" y1="205" x2="206" y2="205" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <circle cx="94" cy="205" r="3.5" fill="#10b981" /><circle cx="206" cy="205" r="3.5" fill="#10b981" />
          </g>
          <g v-if="isPartActive('shoulder') || isPartActive('teera')" class="guide-active">
            <path d="M92 48 L130 68 M170 68 L208 48" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('sleeve')" class="guide-active">
            <path d="M92 48 L35 98 L65 174" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
            <path d="M208 48 L265 98 L235 174" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('cuff')" class="guide-active">
            <line x1="45" y1="165" x2="68" y2="175" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" />
            <line x1="232" y1="175" x2="255" y2="165" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" />
          </g>
          <g v-if="isPartActive('bottom') || isPartActive('gher')" class="guide-active">
            <line x1="86" y1="332" x2="214" y2="332" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
        </g>

        <!-- ==================== EXECUTIVE SHIRT BLUEPRINT ==================== -->
        <g v-else-if="garmentType === 'shirt'" class="garment-vector-group">
          <!-- Shirt Body with curved bottom -->
          <path
            class="silhouette-path"
            d="M94 48 L40 100 L68 174 L98 158 L98 290 Q150 315 202 290 L202 158 L232 174 L260 100 L206 48 L170 70 L130 70 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="2"
          />
          <!-- Spread Collar -->
          <path d="M130 70 L115 105 L150 92 L185 105 L170 70 Z" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <!-- Button Placket -->
          <line x1="150" y1="92" x2="150" y2="298" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" />
          <circle cx="150" cy="115" r="2" fill="var(--ink)" />
          <circle cx="150" cy="150" r="2" fill="var(--ink)" />
          <circle cx="150" cy="185" r="2" fill="var(--ink)" />
          <circle cx="150" cy="220" r="2" fill="var(--ink)" />
          <circle cx="150" cy="255" r="2" fill="var(--ink)" />
          <!-- Chest Pocket -->
          <rect x="108" y="132" width="26" height="28" rx="2" fill="none" stroke="var(--ink)" stroke-width="1.25" />

          <!-- Active Highlights -->
          <g v-if="isPartActive('length')" class="guide-active">
            <line x1="150" y1="48" x2="150" y2="305" stroke="#10b981" stroke-width="2.5" stroke-dasharray="4 3" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('chest')" class="guide-active">
            <line x1="90" y1="142" x2="210" y2="142" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('waist')" class="guide-active">
            <line x1="98" y1="210" x2="202" y2="210" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('shoulder')" class="guide-active">
            <path d="M94 48 L130 70 M170 70 L206 48" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('sleeve')" class="guide-active">
            <path d="M94 48 L40 100 L68 174" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
            <path d="M206 48 L260 100 L232 174" stroke="#10b981" stroke-width="3" stroke-linecap="round" fill="none" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('cuff')" class="guide-active">
            <line x1="48" y1="165" x2="72" y2="175" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" />
            <line x1="228" y1="175" x2="252" y2="165" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" />
          </g>
        </g>

        <!-- ==================== FORMAL TROUSER / PANT BLUEPRINT ==================== -->
        <g v-else-if="garmentType === 'pant'" class="garment-vector-group">
          <!-- Trouser Silhouette -->
          <path
            class="silhouette-path"
            d="M95 45 L205 45 L218 115 L224 335 L174 335 L150 145 L126 335 L76 335 L82 115 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="2"
          />
          <!-- Waistband & Belt Loops -->
          <rect x="93" y="45" width="114" height="16" rx="2" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <line x1="120" y1="45" x2="120" y2="61" stroke="var(--ink)" stroke-width="1.5" />
          <line x1="150" y1="45" x2="150" y2="61" stroke="var(--ink)" stroke-width="1.5" />
          <line x1="180" y1="45" x2="180" y2="61" stroke="var(--ink)" stroke-width="1.5" />
          <!-- Front Fly & Crease Lines -->
          <path d="M150 61 L150 120 Q142 135 150 145" fill="none" stroke="var(--ink)" stroke-width="1.5" />
          <line x1="102" y1="75" x2="100" y2="335" stroke="var(--line)" stroke-width="1" stroke-dasharray="2 2" />
          <line x1="198" y1="75" x2="200" y2="335" stroke="var(--line)" stroke-width="1" stroke-dasharray="2 2" />
          <!-- Slash Pockets -->
          <line x1="96" y1="61" x2="114" y2="95" stroke="var(--ink)" stroke-width="1.5" />
          <line x1="204" y1="61" x2="186" y2="95" stroke="var(--ink)" stroke-width="1.5" />

          <!-- Active Highlights -->
          <g v-if="isPartActive('length')" class="guide-active">
            <line x1="76" y1="45" x2="76" y2="335" stroke="#10b981" stroke-width="2.5" stroke-dasharray="4 3" filter="url(#blueprintGlow)" />
            <circle cx="76" cy="45" r="3" fill="#10b981" /><circle cx="76" cy="335" r="3" fill="#10b981" />
          </g>
          <g v-if="isPartActive('waist')" class="guide-active">
            <line x1="93" y1="53" x2="207" y2="53" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('hip')" class="guide-active">
            <line x1="86" y1="110" x2="214" y2="110" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('thigh')" class="guide-active">
            <line x1="84" y1="165" x2="148" y2="165" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <line x1="152" y1="165" x2="216" y2="165" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('knee')" class="guide-active">
            <line x1="80" y1="235" x2="138" y2="235" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <line x1="162" y1="235" x2="220" y2="235" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('bottom') || isPartActive('hem')" class="guide-active">
            <line x1="76" y1="335" x2="126" y2="335" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
            <line x1="174" y1="335" x2="224" y2="335" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('high') || isPartActive('fly')" class="guide-active">
            <line x1="150" y1="61" x2="150" y2="145" stroke="#10b981" stroke-width="3" stroke-dasharray="3 3" />
          </g>
        </g>

        <!-- ==================== ROYAL SHERWANI / WAISTCOAT ==================== -->
        <g v-else class="garment-vector-group">
          <!-- Royal Silhouette -->
          <path
            class="silhouette-path"
            d="M92 48 L40 100 L68 174 L96 158 L90 326 L210 326 L204 158 L232 174 L260 100 L208 48 L170 68 L130 68 Z"
            fill="url(#fabricGradient)"
            stroke="var(--line)"
            stroke-width="2"
          />
          <!-- Mandarin Collar -->
          <path d="M130 68 Q150 90 170 68 V48 Q150 28 130 48 Z" fill="none" stroke="var(--ink)" stroke-width="1.75" />
          <!-- Center Royal Placket -->
          <line x1="150" y1="72" x2="150" y2="280" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" />
          <circle cx="150" cy="95" r="2.5" fill="var(--ink)" />
          <circle cx="150" cy="120" r="2.5" fill="var(--ink)" />
          <circle cx="150" cy="145" r="2.5" fill="var(--ink)" />
          <circle cx="150" cy="170" r="2.5" fill="var(--ink)" />
          <circle cx="150" cy="195" r="2.5" fill="var(--ink)" />

          <!-- Active Highlights -->
          <g v-if="isPartActive('length')" class="guide-active">
            <line x1="150" y1="48" x2="150" y2="326" stroke="#10b981" stroke-width="2.5" stroke-dasharray="4 3" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('chest')" class="guide-active">
            <line x1="90" y1="140" x2="210" y2="140" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('waist')" class="guide-active">
            <line x1="94" y1="205" x2="206" y2="205" stroke="#10b981" stroke-width="3" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
          <g v-if="isPartActive('shoulder')" class="guide-active">
            <path d="M92 48 L130 68 M170 68 L208 48" stroke="#10b981" stroke-width="3.5" stroke-linecap="round" filter="url(#blueprintGlow)" />
          </g>
        </g>
      </svg>

      <!-- Completion Badge Overlay -->
      <div class="blueprint-overlay-meta">
        <span class="blueprint-garment-title">{{ garmentName }}</span>
        <span class="blueprint-pct-badge" :class="{ 'blueprint-pct-badge--complete': progress === 100 }">
          <b>{{ progress }}%</b> Complete
        </span>
      </div>
    </div>

    <!-- ANATOMICAL PARTS CHECKLIST & MEASUREMENT PILLS -->
    <div class="blueprint-parts-list">
      <div
        v-for="part in orderedParts"
        :key="part.id"
        class="blueprint-part-pill"
        :class="{
          'blueprint-part-pill--done': completedIds.has(part.id),
          'blueprint-part-pill--hover': hoveredPartId === part.id,
        }"
        @mouseenter="hoveredPartId = part.id"
        @mouseleave="hoveredPartId = null"
      >
        <span class="part-status-icon">
          {{ completedIds.has(part.id) ? '✓' : '○' }}
        </span>
        <span class="part-name">{{ part.name }}</span>
        <strong v-if="measurements.find(item => item.part_id === part.id)?.value" class="part-value">
          {{ measurements.find(item => item.part_id === part.id)?.value }} {{ measurements.find(item => item.part_id === part.id)?.unit || '' }}
        </strong>
        <span v-else class="part-pending-dot">—</span>
      </div>
    </div>
  </div>
</template>

<style scoped>
.garment-blueprint-container {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  width: 100%;
}

.blueprint-stage {
  position: relative;
  width: 100%;
  max-width: 22rem;
  margin: 0 auto;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: inset 0 0 20px rgba(0, 0, 0, 0.03);
}

.blueprint-svg {
  width: 100%;
  height: auto;
  max-height: 22rem;
  display: block;
}

.silhouette-path {
  transition: all 200ms ease;
}

.guide-active line,
.guide-active path {
  animation: pulseGuide 2s infinite ease-in-out;
}

@keyframes pulseGuide {
  0%, 100% { opacity: 0.95; }
  50% { opacity: 0.65; }
}

.blueprint-overlay-meta {
  position: absolute;
  top: 0.75rem;
  left: 0.75rem;
  right: 0.75rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  pointer-events: none;
}

.blueprint-garment-title {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.blueprint-pct-badge {
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.2rem 0.55rem;
  border-radius: 9999px;
  background: var(--paper);
  border: 1px solid var(--line);
  color: var(--ink);
}

.blueprint-pct-badge--complete {
  background: var(--status-ready-soft);
  border-color: #10b981;
  color: #059669;
}

.blueprint-parts-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(10.5rem, 1fr));
  gap: 0.5rem;
  width: 100%;
}

.blueprint-part-pill {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  padding: 0.45rem 0.75rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  font-size: 0.8rem;
  color: var(--muted);
  cursor: pointer;
  transition: all 120ms ease;
}

.blueprint-part-pill:hover,
.blueprint-part-pill--hover {
  border-color: var(--primary);
  background: var(--paper);
  color: var(--ink);
}

.blueprint-part-pill--done {
  border-color: rgba(16, 185, 129, 0.4);
  background: rgba(16, 185, 129, 0.05);
  color: var(--ink);
}

.part-status-icon {
  font-weight: 700;
  color: var(--muted);
  font-size: 0.85rem;
}

.blueprint-part-pill--done .part-status-icon {
  color: #10b981;
}

.part-name {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.part-value {
  font-size: 0.78rem;
  color: var(--primary);
  font-weight: 700;
}

.part-pending-dot {
  color: var(--line);
  font-weight: bold;
}
</style>
