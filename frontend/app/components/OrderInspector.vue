<script setup lang="ts">
import type { Employee, Order, Part } from "~/types/orders";

const props = defineProps<{ order: Order; employees: Employee[]; live: boolean }>();
const emit = defineEmits<{
  refresh: [alsoList: boolean];
  advance: [];
  print: [kind: "receipt" | "job" | "barcode"];
  archive: [];
}>();

const { t } = useTailorsI18n();
const api = useTailorsApi();
const toast = useToast();
const statusLabelFn = statusLabel;

// Voice dictation for the saved-order inspector (the wizard has its own instance).
const {
  selectedLang: selectedVoiceLang,
  isListening: isVoiceListening,
  activePartId: voiceActivePartId,
  startListening: startVoiceListening,
  stopListening: stopVoiceListening,
  getLanguagePack,
} = useVoiceMeasurement();

const recentlyUpdatedPartId = ref<string | null>(null);

const measurementValues = reactive<Record<string, number | null>>({});
const assignment = ref("");
const payment = reactive({ amount_minor: 0, method: "cash", reference: "" });

const paymentTaka = computed({
  get: () => payment.amount_minor / 100,
  set: (v: number) => { payment.amount_minor = Math.round(v * 100); },
});

const nextStatus = computed(() =>
  props.order
    ? (
        {
          measuring: "pending_assignment",
          pending_assignment: "in_progress",
          in_progress: "ready",
          ready: "delivered",
        } as Record<string, string>
      )[props.order.status]
    : undefined
);

const nextStatusLabel = computed(() =>
  nextStatus.value ? statusLabelFn(nextStatus.value) : ""
);

// WhatsApp deep link with a short message that names the customer's own workshop.
function getWhatsAppUrl(mobile?: string, name?: string, orderNum?: string) {
  if (!mobile) return "#";
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  const msg = t("app.common.whatsapp_order_update", { p0: name || "", p1: orderNum || "", p2: api.tenantName.value || "" });
  return `https://wa.me/${num}?text=${encodeURIComponent(msg)}`;
}

function triggerInspectorFieldVoice(part: Part) {
  if (isVoiceListening.value && voiceActivePartId.value === part.id) {
    stopVoiceListening();
    return;
  }
  const activeLang = selectedVoiceLang.value || "bn-BD";
  const pack = getLanguagePack(activeLang);

  startVoiceListening({
    lang: activeLang,
    targetPartId: part.id,
    continuous: false,
    onResult: async (pId, val) => {
      measurementValues[pId] = val;
      recentlyUpdatedPartId.value = pId;
      await saveMeasurement(part);
      const confirmText = pack.formatConfirmation(part.name, val, part.unit || "inch");
      toast.success(confirmText);
      setTimeout(() => {
        if (recentlyUpdatedPartId.value === pId) recentlyUpdatedPartId.value = null;
      }, 2500);
    },
    onError: (err) => {
      toast.warning(err);
    },
  });
}

async function saveMeasurement(part: Part) {
  const value = measurementValues[part.id];
  if (!value) return;
  await api.request(`/orders/${props.order.id}/measurements`, {
    method: "POST",
    body: { garment_part_id: part.id, value, unit: part.unit },
  });
  toast.success(t('app.order.p0_saved', { p0: part.name }));
  emit("refresh", false);
}

async function assignKarigar() {
  if (!assignment.value) return;
  await api.request(`/orders/${props.order.id}/assign`, {
    method: "POST",
    body: { karigar_id: assignment.value },
  });
  toast.success(t('app.order.karigar_assigned'));
  emit("refresh", true);
}

async function payRecord() {
  if (payment.amount_minor < 1) return;
  await api.request(`/orders/${props.order.id}/payments`, {
    method: "POST",
    body: payment,
  });
  toast.success(t('app.order.payment_recorded'));
  Object.assign(payment, { amount_minor: 0, method: "cash", reference: "" });
  emit("refresh", true);
}

// Fill the editable measurement boxes from the order whenever it is loaded or refreshed; reset the karigar picker only when another order opens.
watch(
  () => props.order,
  (order) => {
    for (const measurement of order.measurements || []) measurementValues[measurement.part_id] = measurement.value;
  },
  { immediate: true },
);
watch(
  () => props.order.id,
  () => {
    assignment.value = props.order.karigar?.id || "";
  },
  { immediate: true },
);
</script>

<template>
  <div class="order-detail-header">
    <div>
      <span class="eyebrow">{{ t('app.order.order_details') }}</span>
      <h2>{{ order.customer.name }}</h2>
      <div class="detail-subtitle">
        <span>#{{ order.order_number || order.number }}</span>
        <span>· {{ order.garment.name }}</span>
        <span v-if="order.customer.mobile_number">· 📞 {{ order.customer.mobile_number }}</span>
      </div>
    </div>

    <div class="order-detail-badges">
      <span class="status" :class="`status--${order.status}`">{{ statusLabelFn(order.status) }}</span>
      <span v-if="live" class="live-pill"><span class="live-dot"></span> Live</span>
      <a
        v-if="order.customer.mobile_number"
        :href="getWhatsAppUrl(order.customer.mobile_number, order.customer.name, order.order_number || order.number)"
        target="_blank"
        class="whatsapp-action-btn"
        :title="t('app.common.send_whatsapp_message')"
      >
        <NavIcon name="WhatsApp" />
      </a>
    </div>
  </div>

  <!-- Garment Visual Prototype -->
  <div class="detail-blueprint-box">
    <GarmentPrototypeBuilder
      :garment-name="order.garment.name"
      :parts="order.garment.parts || []"
      :measurements="order.measurements || []"
    />
  </div>

  <!-- Measurement inputs -->
  <div class="measurements-section">
    <h3 class="section-title">{{ t('app.order.measurements') }}</h3>
    <div class="measurement-form-grid">
      <form
        v-for="part in order.garment.parts || []"
        :key="part.id"
        class="measurement-input-row"
        @submit.prevent="saveMeasurement(part)"
      >
        <label>{{ part.name }}
          <div class="input-with-unit-voice">
            <input
              v-model.number="measurementValues[part.id]"
              type="number"
              step="0.01"
              min="0.01"
              :placeholder="part.unit"
              :aria-label="part.name"
              :class="{ 'flash-success': recentlyUpdatedPartId === part.id }"
            />
            <span class="unit-badge">{{ part.unit }}</span>
            <button
              type="button"
              class="btn-field-mic"
              :class="{ 'is-field-listening': isVoiceListening && voiceActivePartId === part.id }"
              :title="t('app.order.speak_p0_measurement', { p0: part.name })"
              @click="triggerInspectorFieldVoice(part)"
            >
              <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                <line x1="12" y1="19" x2="12" y2="23" />
                <line x1="8" y1="23" x2="16" y2="23" />
              </svg>
            </button>
          </div>
        </label>
        <button class="mini-btn">{{ t('app.order.save') }}</button>
      </form>
    </div>
  </div>

  <!-- Karigar Assignment -->
  <div v-if="api.can('orders.assign')" class="assignment-section">
    <h3 class="section-title">{{ t('app.order.karigar_assignment') }}</h3>
    <form class="assignment-form" @submit.prevent="assignKarigar">
      <select v-model="assignment">
        <option value="">{{ t('app.order.unassigned') }}</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">
          {{ e.name }}
        </option>
      </select>
      <button class="primary">{{ t('app.order.assign_karigar') }}</button>
    </form>
  </div>

  <!-- Payments & Dues Summary -->
  <div class="payment-card">
    <div class="payment-card-row">
      <div class="payment-cell">
        <small>{{ t('app.order.total_making_cost') }}</small>
        <strong>৳ {{ (order.total_minor / 100).toFixed(2) }}</strong>
      </div>
      <div class="payment-cell">
        <small>{{ t('app.order.advance_received') }}</small>
        <strong style="color:var(--status-ready)">৳ {{ (order.paid_minor / 100).toFixed(2) }}</strong>
      </div>
      <div class="payment-cell">
        <small>{{ t('app.order.balance_due') }}</small>
        <strong style="color:var(--status-cancelled)">৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(2) }}</strong>
      </div>
    </div>

    <form v-if="order.total_minor > order.paid_minor && api.can('orders.payments')" class="payment-inline-form" @submit.prevent="payRecord">
      <label>{{ t('app.order.collect_due') }}
        <input
          v-model.number="paymentTaka"
          type="number"
          min="0.01"
          step="0.01"
          :max="(order.total_minor - order.paid_minor) / 100"
          :placeholder="t('app.common.amount_2')"
        />
      </label>
      <select v-model="payment.method">
        <option value="cash">{{ t('app.common.cash') }}</option>
        <option value="mobile_banking">bKash / Nagad</option>
        <option value="card">{{ t('app.common.card') }}</option>
        <option value="bank_transfer">{{ t('app.common.bank') }}</option>
      </select>
      <button class="primary">{{ t('app.common.record_payment') }}</button>
    </form>
  </div>

  <!-- Action Buttons Deck -->
  <div class="order-actions-bar">
    <button v-if="nextStatus && api.can('orders.status')" class="primary advance-action-btn" @click="emit('advance')">
      <span>{{ t('app.order.move_to') }} {{ nextStatusLabel }}</span>
      <span class="btn-arrow">➔</span>
    </button>
    <button class="secondary" @click="emit('print', 'receipt')">
      <NavIcon name="Printer" /> {{ t('app.order.print_receipt') }}
    </button>
    <button class="secondary" @click="emit('print', 'job')">
      <NavIcon name="Scissors" /> {{ t('app.order.job_card') }}
    </button>
    <button class="secondary" @click="emit('print', 'barcode')">
      <NavIcon name="Barcode" /> {{ t('app.order.barcode') }}
    </button>
    <button v-if="!['ready', 'delivered'].includes(order.status) && api.can('orders.delete')" class="danger-btn" @click="emit('archive')">
      {{ t('app.order.archive') }}
    </button>
  </div>

  <!-- Order Timeline History -->
  <div class="timeline-box">
    <h4 class="timeline-title">{{ t('app.order.order_history') }}</h4>
    <ol class="timeline-list">
      <li v-for="event in order.timeline || []" :key="event.created_at" class="timeline-item">
        <div class="timeline-dot"></div>
        <div class="timeline-content">
          <div class="timeline-top">
            <strong>{{ statusLabelFn(event.status) }}</strong>
            <small>{{ new Date(event.created_at).toLocaleString() }}</small>
          </div>
          <span v-if="event.note" class="timeline-note">{{ event.note }}</span>
        </div>
      </li>
    </ol>
  </div>
</template>

<style scoped>


.mini-btn {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 0.3rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 140ms ease;
}

.mini-btn:hover {
  background: var(--paper);
  border-color: var(--primary);
  color: var(--primary);
}

/* Detail Panel */
.order-detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--line);
  margin-bottom: 1.25rem;
}

.detail-subtitle {
  display: flex;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.order-detail-badges {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.whatsapp-action-btn {
  color: #25d366;
  padding: 0.4rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-xs);
  background: rgb(37 211 102 / 12%);
  transition: all 150ms ease;
}

.whatsapp-action-btn:hover {
  background: #25d366;
  color: #fff;
}

.detail-blueprint-box {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.section-title {
  font: 700 0.95rem var(--sans);
  color: var(--ink);
  margin: 0 0 0.85rem;
}

.measurement-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.85rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 40rem) {
  .measurement-form-grid {
    grid-template-columns: 1fr;
  }
}

.measurement-input-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.65rem 0.85rem;
}

.measurement-input-row label {
  flex: 1;
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.unit-badge {
  position: absolute;
  right: 0.5rem;
  font-size: 0.725rem;
  color: var(--muted);
  font-weight: 600;
  pointer-events: none;
}

.assignment-section {
  margin-bottom: 1.5rem;
}

.assignment-form {
  display: flex;
  gap: 0.75rem;
}

.assignment-form select {
  flex: 1;
}

.payment-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.payment-card-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.payment-cell small {
  display: block;
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 600;
  text-transform: uppercase;
}

.payment-cell strong {
  display: block;
  font-size: 1.15rem;
  margin-top: 0.2rem;
  color: var(--ink);
}

.payment-inline-form {
  display: flex;
  gap: 0.75rem;
  align-items: flex-end;
  padding-top: 1rem;
  border-top: 1px dashed var(--line);
}

.order-actions-bar {
  display: flex;
  gap: 0.65rem;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 1.5rem;
}

.advance-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-arrow {
  transition: transform 150ms ease;
}

.advance-action-btn:hover .btn-arrow {
  transform: translateX(3px);
}

.danger-btn {
  background: transparent;
  border: 1px solid var(--color-danger);
  color: var(--color-danger);
  padding: 0.65rem 1.15rem;
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 150ms ease;
}

.danger-btn:hover {
  background: var(--color-danger-soft);
}

.timeline-box {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
}

.timeline-title {
  margin: 0 0 1rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.timeline-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.timeline-item {
  display: flex;
  gap: 0.85rem;
  position: relative;
}

.timeline-dot {
  width: 0.65rem;
  height: 0.65rem;
  border-radius: 50%;
  background: var(--primary);
  margin-top: 0.35rem;
  flex-shrink: 0;
}

.timeline-content {
  flex: 1;
}

.timeline-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.timeline-top strong {
  font-size: 0.88rem;
  color: var(--ink);
}

.timeline-top small {
  font-size: 0.75rem;
  color: var(--muted);
}

.timeline-note {
  display: block;
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

/* Input with integrated unit & voice mic button */
.input-with-unit-voice {
  display: flex;
  align-items: stretch;
  min-height: 3rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.input-with-unit-voice:focus-within {
  border-color: var(--primary);
  box-shadow: var(--focus-ring);
}

.form .input-with-unit-voice input {
  flex: 1;
  min-width: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  padding: 0.55rem 0.65rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  width: 100%;
}

.form .input-with-unit-voice input:focus {
  outline: none;
  box-shadow: none;
}

.input-with-unit-voice .unit-badge {
  position: static;
  flex-shrink: 0;
  background: var(--paper);
  border-left: 1px solid var(--line);
  border-right: 1px solid var(--line);
  color: var(--muted);
  font-size: 0.725rem;
  font-weight: 700;
  padding: 0 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
}

.btn-field-mic {
  background: transparent;
  border: none;
  color: var(--muted);
  width: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: all 0.15s ease;
}

.btn-field-mic:hover {
  background: var(--paper);
  color: var(--primary);
}

.btn-field-mic.is-field-listening {
  background: #ef4444;
  color: #ffffff;
  animation: pulse-mic 1s infinite;
}

.flash-success {
  background-color: #dcfce7 !important;
  transition: background-color 0.3s ease;
}

@media (max-width: 48rem) {
  .voice-assistant-panel {
    flex-direction: column;
    align-items: stretch;
  }
  .modal-steps-bar {
    overflow-x: auto;
  }
  .step-item {
    font-size: 0.725rem;
    padding: 0.65rem 0.25rem;
  }
  .payment-card-row {
    flex-direction: column;
    gap: 0.5rem;
  }
  .table-pagination-footer {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }
}

@keyframes pulse-mic {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
  70% { transform: scale(1.06); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}
</style>
