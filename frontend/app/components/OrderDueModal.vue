<script setup lang="ts">
import type { Order } from "~/types/orders";

const props = defineProps<{ order: Order | null }>();
const emit = defineEmits<{ close: []; paid: [orderId: string] }>();

const { t } = useTailorsI18n();
const api = useTailorsApi();
const toast = useToast();

const quickPay = reactive({ amount_minor: 0, method: "cash", reference: "" });

const quickPayTaka = computed({
  get: () => quickPay.amount_minor / 100,
  set: (v: number) => { quickPay.amount_minor = Math.round(v * 100); },
});

// Start every payment with the full outstanding amount, paid in cash.
watch(
  () => props.order,
  (order) => {
    if (!order) return;
    quickPay.amount_minor = order.total_minor - order.paid_minor;
    quickPay.method = "cash";
    quickPay.reference = "";
  },
  { immediate: true },
);

async function recordQuickPayment() {
  const order = props.order;
  if (!order || quickPay.amount_minor <= 0) return;
  try {
    await api.request(`/orders/${order.id}/payments`, {
      method: "POST",
      body: quickPay,
    });
    toast.success(t('app.order.payment_of_p0_recorded', { p0: (quickPay.amount_minor / 100).toFixed(2) }));
    emit("paid", order.id);
  } catch (e: any) {
    toast.error(apiErrorMessage(e, t('app.order.could_not_record_payment')));
  }
}
</script>

<template>
  <div v-if="order" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-window" style="max-width:28rem">
      <div class="modal-header">
        <div>
          <span class="deck-eyebrow">{{ t('app.order.payment') }}</span>
          <h2>{{ t('app.order.collect_due_payment') }}</h2>
        </div>
        <button class="modal-close-btn" :aria-label="t('app.common.close')" @click="emit('close')">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
        </button>
      </div>
      <form class="modal-body" @submit.prevent="recordQuickPayment">
        <div class="quick-due-meta">
          <p><strong>{{ t('app.common.customer') }}</strong> {{ order.customer.name }}</p>
          <p><strong>{{ t('app.order.order') }}</strong> #{{ order.order_number || order.number }}</p>
          <p><strong>{{ t('app.order.total_due') }}</strong> <span style="color:var(--status-cancelled);font-weight:700">৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(2) }}</span></p>
        </div>

        <label>{{ t('app.order.payment_received') }}
          <input
            v-model.number="quickPayTaka"
            type="number"
            min="1"
            :max="(order.total_minor - order.paid_minor) / 100"
            step="1"
            required
          />
        </label>
        <label>{{ t('app.common.payment_method') }}
          <select v-model="quickPay.method">
            <option value="cash">{{ t('app.common.cash') }}</option>
            <option value="mobile_banking">bKash / Nagad</option>
            <option value="card">{{ t('app.common.card') }}</option>
          </select>
        </label>
        <button class="primary" style="margin-top:0.75rem">
          {{ t('app.order.record_payment_issue_receipt') }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>


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
</style>
