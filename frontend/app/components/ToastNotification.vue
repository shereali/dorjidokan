<script setup lang="ts">
const { toasts, dismiss } = useToast();
</script>

<template>
  <div class="toast-container" aria-live="polite" aria-atomic="true">
    <transition-group name="toast">
      <div
        v-for="t in toasts"
        :key="t.id"
        class="toast-item"
        :class="`toast-item--${t.type}`"
        role="status"
        @click="dismiss(t.id)"
      >
        <div class="toast-icon">
          <span v-if="t.type === 'success'">✓</span>
          <span v-else-if="t.type === 'error'">✕</span>
          <span v-else-if="t.type === 'warning'">!</span>
          <span v-else>ℹ</span>
        </div>
        <div class="toast-content">
          <strong v-if="t.title" class="toast-title">{{ t.title }}</strong>
          <span class="toast-message">{{ t.message }}</span>
        </div>
        <button type="button" class="toast-close" :aria-label="`Dismiss notification`" @click.stop="dismiss(t.id)">
          <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-container {
  position: fixed;
  bottom: 1.5rem;
  right: 1.5rem;
  z-index: 9999;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  max-width: 24rem;
  width: calc(100vw - 2rem);
  pointer-events: none;
}

.toast-item {
  pointer-events: auto;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.85rem 1.1rem;
  border-radius: var(--radius);
  background: var(--surface);
  color: var(--ink);
  box-shadow: 0 0.6rem 2rem rgb(0 0 0 / 18%);
  border: var(--border-width) solid var(--line);
  cursor: pointer;
  transition: transform 0.2s ease, opacity 0.2s ease;
}

.toast-item:hover {
  transform: translateY(-2px);
}

.toast-icon {
  width: 1.8rem;
  height: 1.8rem;
  border-radius: 50%;
  display: grid;
  place-items: center;
  font-size: 0.9rem;
  font-weight: 700;
  flex-shrink: 0;
}

.toast-item--success .toast-icon {
  background: var(--status-ready-soft);
  color: var(--status-ready);
}

.toast-item--error .toast-icon {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
}

.toast-item--warning .toast-icon {
  background: var(--status-pending-soft);
  color: var(--status-pending);
}

.toast-item--info .toast-icon {
  background: var(--status-measuring-soft);
  color: var(--status-measuring);
}

.toast-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.toast-title {
  font-size: 0.85rem;
  font-weight: 700;
}

.toast-message {
  font-size: 0.825rem;
  line-height: 1.35;
}

.toast-close {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--muted);
  cursor: pointer;
  padding: 0;
  flex-shrink: 0;
  transition: all 0.15s ease;
}

.toast-close:hover {
  background: rgba(0, 0, 0, 0.06);
  color: var(--ink);
}

.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100%) scale(0.9);
}

.toast-leave-to {
  opacity: 0;
  transform: scale(0.85);
}
</style>
