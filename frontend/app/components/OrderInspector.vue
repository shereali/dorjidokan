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
        <span v-if="order.customer.mobile_number" class="phone-inline">· <NavIcon name="Phone" /> {{ order.customer.mobile_number }}</span>
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
        <span class="measurement-part-title">{{ part.name }}</span>
        <div class="measurement-input-group">
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
          <button class="save-measurement-btn">{{ t('app.order.save') }}</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Karigar Assignment -->
  <div v-if="api.can('orders.assign')" class="assignment-section">
    <h3 class="section-title">{{ t('app.order.karigar_assignment') }}</h3>
    <form class="assignment-form" @submit.prevent="assignKarigar">
      <select v-model="assignment" class="assignment-select">
        <option value="">{{ t('app.order.unassigned') }}</option>
        <option v-for="e in employees" :key="e.id" :value="e.id">
          {{ e.name }}
        </option>
      </select>
      <button class="primary assign-btn">{{ t('app.order.assign_karigar') }}</button>
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
      <div class="payment-field-group payment-field-group--amount">
        <label class="payment-field-label">{{ t('app.order.collect_due') }}</label>
        <div class="payment-currency-input">
          <span class="payment-currency-prefix">৳</span>
          <input
            v-model.number="paymentTaka"
            type="number"
            min="0.01"
            step="0.01"
            :max="(order.total_minor - order.paid_minor) / 100"
            :placeholder="t('app.common.amount_2')"
          />
        </div>
      </div>
      <div class="payment-field-group payment-field-group--method">
        <label class="payment-field-label">{{ t('app.common.payment_method') }}</label>
        <select v-model="payment.method" class="payment-method-select">
          <option value="cash">{{ t('app.common.cash') }}</option>
          <option value="mobile_banking">bKash / Nagad</option>
          <option value="card">{{ t('app.common.card') }}</option>
          <option value="bank_transfer">{{ t('app.common.bank') }}</option>
        </select>
      </div>
      <div class="payment-field-group payment-field-group--action">
        <label class="payment-field-label payment-label-spacer">&nbsp;</label>
        <button class="primary record-payment-btn">{{ t('app.common.record_payment') }}</button>
      </div>
    </form>
  </div>

  <!-- Action Buttons Deck -->
  <div class="order-actions-bar">
    <button v-if="nextStatus && api.can('orders.status')" class="primary advance-action-btn" @click="emit('advance')">
      <span>{{ t('app.order.move_to') }} {{ nextStatusLabel }}</span>
      <span class="btn-arrow">➔</span>
    </button>
    <button class="secondary action-btn-secondary" @click="emit('print', 'receipt')">
      <NavIcon name="Printer" /> {{ t('app.order.print_receipt') }}
    </button>
    <button class="secondary action-btn-secondary" @click="emit('print', 'job')">
      <NavIcon name="Scissors" /> {{ t('app.order.job_card') }}
    </button>
    <button class="secondary action-btn-secondary" @click="emit('print', 'barcode')">
      <NavIcon name="Barcode" /> {{ t('app.order.barcode') }}
    </button>
    <button v-if="!['ready', 'delivered'].includes(order.status) && api.can('orders.delete')" class="danger-btn action-btn-danger" @click="emit('archive')">
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

<style scoped src="../../assets/css/components/_order-inspector.scss"></style>
