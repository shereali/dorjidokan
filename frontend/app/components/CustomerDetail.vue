<script setup lang="ts">
interface Customer {
  public_id: string;
  name: string;
  mobile_number: string;
  address?: string;
  orders_count?: number;
  due_minor?: number;
  last_order_at?: string | null;
}

interface OrderRow {
  id: string;
  order_number: string;
  status: string;
  garment: string | null;
  promised_at: string | null;
  total_minor: number;
  paid_minor: number;
}

const props = defineProps<{ customer: Customer; archived: boolean }>();
const emit = defineEmits<{ close: []; edit: []; ledger: []; points: [] }>();

const api = useTailorsApi();
const { t } = useTailorsI18n();
const { money, date } = usePlatformFormat();

const orders = ref<OrderRow[]>([]);
const loading = ref(false);
const failed = ref(false);

// Every action mirrors the permission the API checks for it.
const canSeeOrders = computed(() => api.can("orders.view"));
const canEdit = computed(() => !props.archived && api.can("customers.manage"));
const canLedger = computed(() => !props.archived && api.can("accounting.view"));

function whatsAppUrl(mobile: string) {
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  return `https://wa.me/${num}?text=${encodeURIComponent("Hello from Tailors OS")}`;
}

onMounted(async () => {
  if (!canSeeOrders.value) return;
  loading.value = true;
  try {
    orders.value = (await api.request<{ items: OrderRow[] }>(`/customers/${props.customer.public_id}/orders`)).data.items;
  } catch {
    failed.value = true;
  } finally {
    loading.value = false;
  }
});
</script>

<template>
  <div class="modal-backdrop" @click.self="emit('close')">
    <div class="modal-window customer-detail" role="dialog" aria-modal="true" :aria-label="customer.name">
      <div class="modal-header">
        <div class="detail-title">
          <div class="detail-avatar">{{ customer.name.slice(0, 1).toUpperCase() }}</div>
          <div>
            <h2>{{ customer.name }}</h2>
            <div class="detail-contact">
              <span>📞 {{ customer.mobile_number }}</span>
              <a :href="whatsAppUrl(customer.mobile_number)" target="_blank" rel="noopener" class="whatsapp-link">
                <NavIcon name="WhatsApp" /> {{ t('app.customer.whatsapp') }}
              </a>
            </div>
            <small v-if="customer.address" class="detail-address">📍 {{ customer.address }}</small>
          </div>
        </div>
        <button class="modal-close-btn" :aria-label="t('app.common.close')" @click="emit('close')">✕</button>
      </div>

      <div class="modal-body">
        <div class="detail-stats">
          <div>
            <small>{{ t('app.customer.col_orders') }}</small>
            <strong>{{ customer.orders_count ?? 0 }}</strong>
          </div>
          <div :class="{ 'is-due': (customer.due_minor ?? 0) > 0 }">
            <small>{{ t('app.customer.outstanding_balance') }}</small>
            <strong>{{ money(customer.due_minor ?? 0) }}</strong>
          </div>
          <div>
            <small>{{ t('app.customer.col_last_order') }}</small>
            <strong>{{ customer.last_order_at ? date(customer.last_order_at) : "—" }}</strong>
          </div>
        </div>

        <template v-if="canSeeOrders">
          <h3>{{ t('app.customer.order_history') }}</h3>
          <p v-if="loading">{{ t('common.loading') }}</p>
          <p v-else-if="failed" class="error">{{ t('app.customer.could_not_load_orders') }}</p>
          <p v-else-if="!orders.length" class="empty">{{ t('app.customer.no_orders_yet') }}</p>
          <div v-else class="history-scroll">
            <table class="history-table">
              <thead>
                <tr>
                  <th>{{ t('app.customer.order_no') }}</th>
                  <th>{{ t('app.customer.garment') }}</th>
                  <th>{{ t('app.common.status') }}</th>
                  <th>{{ t('app.customer.delivery') }}</th>
                  <th class="num">{{ t('app.customer.total') }}</th>
                  <th class="num">{{ t('app.customer.col_due') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="o in orders" :key="o.id">
                  <td>
                    <NuxtLink
                      class="order-link"
                      :to="{ path: '/orders', query: { query: o.order_number } }"
                      :title="t('app.customer.open_order_hint')"
                      @click="emit('close')"
                    >
                      {{ o.order_number }}
                    </NuxtLink>
                  </td>
                  <td>{{ o.garment || "—" }}</td>
                  <td><span class="status" :class="`status--${o.status}`">{{ statusLabel(o.status) }}</span></td>
                  <td>{{ o.promised_at ? date(o.promised_at) : "—" }}</td>
                  <td class="num">{{ money(o.total_minor) }}</td>
                  <td class="num" :class="{ 'is-due': o.total_minor - o.paid_minor > 0 && o.status !== 'cancelled' }">
                    {{ o.status === "cancelled" ? "—" : money(o.total_minor - o.paid_minor) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </div>

      <div class="modal-footer">
        <button v-if="canEdit" class="secondary" @click="emit('edit')">{{ t('app.common.edit') }}</button>
        <button v-if="canLedger" class="secondary" @click="emit('ledger')">
          <NavIcon name="Accounting" /> {{ t('app.customer.ledger') }}
        </button>
        <button v-if="!archived" class="secondary" @click="emit('points')">
          <NavIcon name="Tag" /> {{ t('app.customer.points') }}
        </button>
        <button class="primary" @click="emit('close')">{{ t('app.common.close') }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.customer-detail {
  width: 100%;
  max-width: 52rem;
}

.detail-title {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.detail-title h2 {
  margin: 0;
}

.detail-avatar {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 1.2rem;
  flex-shrink: 0;
}

.detail-contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.85rem;
  color: var(--muted);
}

.detail-address {
  display: block;
  color: var(--muted);
  margin-top: 0.15rem;
}

.whatsapp-link {
  color: #25d366;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  text-decoration: none;
  font-size: 0.8rem;
  background: rgb(37 211 102 / 10%);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.detail-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));
  gap: 0.75rem;
  margin-bottom: 1.25rem;
}

.detail-stats > div {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.7rem 0.9rem;
  display: grid;
  gap: 0.15rem;
}

.detail-stats small {
  color: var(--muted);
}

.detail-stats strong {
  font-size: 1.1rem;
}

.is-due {
  color: var(--color-danger, #b91c1c);
}

.history-scroll {
  overflow-x: auto;
}

.history-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.history-table th,
.history-table td {
  padding: 0.55rem 0.6rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  white-space: nowrap;
}

.history-table th {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.78rem;
}

.history-table .num {
  text-align: right;
}

.order-link {
  font-weight: 700;
  color: var(--primary);
  text-decoration: none;
}

.order-link:hover {
  text-decoration: underline;
}
</style>
