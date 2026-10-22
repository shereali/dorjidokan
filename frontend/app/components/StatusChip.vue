<script setup lang="ts">
const props = defineProps<{ status: string }>();
const { statusLabel, tone } = usePlatformFormat();
const label = computed(() => statusLabel(props.status));
const kind = computed(() => tone(props.status));
</script>

<template>
  <span class="chip" :class="`chip--${kind}`">{{ label }}</span>
</template>

<style scoped>
.chip {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.6rem;
  border-radius: 99px;
  font-size: 0.75rem;
  font-weight: 700;
  white-space: nowrap;
  border: 1px solid currentcolor;
}

.chip--good { color: var(--status-ready, #2e7d4f); background: color-mix(in srgb, currentcolor 10%, transparent); }
.chip--info { color: var(--primary, #1b5e4a); background: color-mix(in srgb, currentcolor 10%, transparent); }
.chip--warn { color: #a8650a; background: color-mix(in srgb, currentcolor 12%, transparent); }
.chip--bad { color: var(--color-danger, #8b2e2e); background: color-mix(in srgb, currentcolor 10%, transparent); }
.chip--muted { color: var(--muted, #6b7280); background: color-mix(in srgb, currentcolor 8%, transparent); }
</style>
