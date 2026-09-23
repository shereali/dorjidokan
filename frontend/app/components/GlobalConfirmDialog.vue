<script setup lang="ts">
const { state, handleConfirm, handleCancel } = useConfirmDialog();

function onKeyDown(e: KeyboardEvent) {
  if (!state.isOpen) return;
  if (e.key === "Escape") {
    handleCancel();
  } else if (e.key === "Enter") {
    handleConfirm();
  }
}

onMounted(() => {
  window.addEventListener("keydown", onKeyDown);
});

onUnmounted(() => {
  window.removeEventListener("keydown", onKeyDown);
});
</script>

<template>
  <Teleport to="body">
    <transition name="confirm-modal">
      <div
        v-if="state.isOpen"
        class="confirm-overlay"
        role="alertdialog"
        aria-modal="true"
        :aria-labelledby="'confirm-title'"
        :aria-describedby="'confirm-desc'"
        @click.self="handleCancel"
      >
        <div class="confirm-dialog-panel" :class="`type--${state.type}`">
          <!-- Ambient Glow Glow -->
          <div class="ambient-glow" />

          <!-- Close Mini Button -->
          <button
            type="button"
            class="dialog-close-btn"
            aria-label="Close dialog"
            @click="handleCancel"
          >
            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>

          <!-- Status Icon Medallion -->
          <div class="icon-medallion" :class="`icon--${state.type}`">
            <div class="icon-pulse-ring" />
            <!-- Danger SVG (Trash / Hazard) -->
            <svg
              v-if="state.type === 'danger'"
              class="medallion-svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
              <line x1="10" y1="11" x2="10" y2="17" />
              <line x1="14" y1="11" x2="14" y2="17" />
            </svg>
            <!-- Warning SVG -->
            <svg
              v-else-if="state.type === 'warning'"
              class="medallion-svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
              <line x1="12" y1="9" x2="12" y2="13" />
              <line x1="12" y1="17" x2="12.01" y2="17" />
            </svg>
            <!-- Info SVG -->
            <svg
              v-else
              class="medallion-svg"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              stroke-width="2"
              stroke-linecap="round"
              stroke-linejoin="round"
            >
              <circle cx="12" cy="12" r="10" />
              <line x1="12" y1="16" x2="12" y2="12" />
              <line x1="12" y1="8" x2="12.01" y2="8" />
            </svg>
          </div>

          <!-- Dialog Body -->
          <div class="dialog-content">
            <h3 id="confirm-title" class="dialog-title">
              {{ state.title }}
            </h3>
            
            <p id="confirm-desc" class="dialog-desc">
              {{ state.message }}
            </p>

            <!-- Target Highlight Badge -->
            <div v-if="state.highlight" class="highlight-target-badge">
              <span class="badge-dot" />
              <span class="badge-label">{{ state.highlight }}</span>
            </div>
          </div>

          <!-- Action Buttons -->
          <div class="dialog-actions">
            <button
              type="button"
              class="btn-action-cancel"
              @click="handleCancel"
            >
              {{ state.cancelText }}
            </button>
            <button
              type="button"
              class="btn-action-confirm"
              :class="`btn--${state.type}`"
              @click="handleConfirm"
            >
              {{ state.confirmText }}
            </button>
          </div>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.confirm-overlay {
  position: fixed;
  inset: 0;
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1.25rem;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);
}

.confirm-dialog-panel {
  position: relative;
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border-radius: 20px;
  padding: 2rem 1.75rem 1.6rem;
  box-shadow:
    0 25px 50px -12px rgba(0, 0, 0, 0.25),
    0 0 0 1px rgba(226, 232, 240, 0.8),
    0 1px 3px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  overflow: hidden;
}

/* Ambient Top Light */
.ambient-glow {
  position: absolute;
  top: -80px;
  left: 50%;
  transform: translateX(-50%);
  width: 220px;
  height: 140px;
  border-radius: 50%;
  pointer-events: none;
  opacity: 0.15;
}

.type--danger .ambient-glow {
  background: radial-gradient(circle, #ef4444 0%, transparent 70%);
}

.type--warning .ambient-glow {
  background: radial-gradient(circle, #c48a28 0%, transparent 70%);
}

.type--info .ambient-glow {
  background: radial-gradient(circle, #0a3d31 0%, transparent 70%);
}

/* Mini Close Button */
.dialog-close-btn {
  position: absolute;
  top: 1.1rem;
  right: 1.1rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #64748b;
  cursor: pointer;
  padding: 0;
  transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

.dialog-close-btn:hover {
  background: #f1f5f9;
  color: #0f172a;
  border-color: #cbd5e1;
  transform: scale(1.06);
}

.dialog-close-btn:active {
  transform: scale(0.96);
}

/* Icon Medallion */
.icon-medallion {
  position: relative;
  width: 60px;
  height: 60px;
  border-radius: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1.25rem;
}

.icon-pulse-ring {
  position: absolute;
  inset: -4px;
  border-radius: 22px;
  opacity: 0.5;
}

.icon--danger {
  background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
  color: #dc2626;
  box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.25);
}

.icon--danger .icon-pulse-ring {
  border: 2px solid #fee2e2;
}

.icon--warning {
  background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
  color: #c48a28;
  box-shadow: 0 8px 16px -4px rgba(196, 138, 40, 0.25);
}

.icon--warning .icon-pulse-ring {
  border: 2px solid #fef3c7;
}

.icon--info {
  background: linear-gradient(135deg, #e2f0eb 0%, #bacdc4 100%);
  color: #0a3d31;
  box-shadow: 0 8px 16px -4px rgba(10, 61, 49, 0.25);
}

.icon--info .icon-pulse-ring {
  border: 2px solid #e2f0eb;
}

.medallion-svg {
  width: 28px;
  height: 28px;
}

/* Content */
.dialog-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
}

.dialog-title {
  font-size: 1.25rem;
  font-weight: 800;
  color: #0f172a;
  margin: 0 0 0.5rem 0;
  line-height: 1.3;
}

.dialog-desc {
  font-size: 0.9rem;
  color: #64748b;
  line-height: 1.5;
  margin: 0 0 1rem 0;
  max-width: 340px;
}

/* Target Highlight */
.highlight-target-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 0.45rem 0.85rem;
  max-width: 100%;
  margin-bottom: 1.5rem;
}

.badge-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

.type--danger .badge-dot {
  background: #ef4444;
}

.type--warning .badge-dot {
  background: #c48a28;
}

.type--info .badge-dot {
  background: #0a3d31;
}

.badge-label {
  font-size: 0.875rem;
  font-weight: 700;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* Action Buttons */
.dialog-actions {
  display: flex;
  gap: 0.75rem;
  width: 100%;
}

.btn-action-cancel {
  flex: 1;
  background: #ffffff;
  border: 1.5px solid #cbd5e1;
  color: #475569;
  border-radius: 10px;
  padding: 0.75rem 1rem;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-action-cancel:hover {
  background: #f8fafc;
  color: #0f172a;
  border-color: #94a3b8;
  transform: translateY(-1px);
}

.btn-action-cancel:active {
  transform: translateY(0);
}

.btn-action-confirm {
  flex: 1.25;
  border: none;
  border-radius: 10px;
  padding: 0.75rem 1.15rem;
  font-size: 0.9rem;
  font-weight: 700;
  cursor: pointer;
  color: #ffffff;
  transition: all 0.18s cubic-bezier(0.16, 1, 0.3, 1);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
}

.btn-action-confirm.btn--danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  box-shadow: 0 4px 14px rgba(239, 68, 68, 0.35);
}

.btn-action-confirm.btn--danger:hover {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  transform: translateY(-1.5px);
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.45);
}

.btn-action-confirm.btn--warning {
  background: linear-gradient(135deg, #c48a28 0%, #a16e1a 100%);
  box-shadow: 0 4px 14px rgba(196, 138, 40, 0.35);
}

.btn-action-confirm.btn--warning:hover {
  background: linear-gradient(135deg, #a16e1a 0%, #825611 100%);
  transform: translateY(-1.5px);
  box-shadow: 0 6px 20px rgba(196, 138, 40, 0.45);
}

.btn-action-confirm.btn--info {
  background: linear-gradient(135deg, #0a3d31 0%, #072e25 100%);
  box-shadow: 0 4px 14px rgba(10, 61, 49, 0.35);
}

.btn-action-confirm.btn--info:hover {
  background: linear-gradient(135deg, #072e25 0%, #041c17 100%);
  transform: translateY(-1.5px);
  box-shadow: 0 6px 20px rgba(10, 61, 49, 0.45);
}

.btn-action-confirm:active {
  transform: translateY(0);
}

/* Animations */
.confirm-modal-enter-active,
.confirm-modal-leave-active {
  transition: opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.confirm-modal-enter-active .confirm-dialog-panel,
.confirm-modal-leave-active .confirm-dialog-panel {
  transition: transform 0.22s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.22s cubic-bezier(0.16, 1, 0.3, 1);
}

.confirm-modal-enter-from {
  opacity: 0;
}

.confirm-modal-enter-from .confirm-dialog-panel {
  opacity: 0;
  transform: scale(0.92) translateY(12px);
}

.confirm-modal-leave-to {
  opacity: 0;
}

.confirm-modal-leave-to .confirm-dialog-panel {
  opacity: 0;
  transform: scale(0.95) translateY(6px);
}

@media (max-width: 480px) {
  .confirm-dialog-panel {
    padding: 1.5rem 1.25rem 1.25rem;
    border-radius: 16px;
  }

  .dialog-actions {
    flex-direction: column-reverse;
    gap: 0.5rem;
  }

  .btn-action-cancel,
  .btn-action-confirm {
    width: 100%;
    padding: 0.7rem;
  }
}
</style>
