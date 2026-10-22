<script setup lang="ts">
import type { Order } from "~/types/orders";

defineProps<{
  items: Order[];
  selectedId?: string;
  loading: boolean;
  archived: boolean;
  totalOrders: number;
  page: number;
  perPage: number;
  lastPage: number;
  fromItem: number;
  toItem: number;
}>();
const emit = defineEmits<{
  select: [order: Order];
  restore: [order: Order];
  print: [order: Order];
  pay: [order: Order];
  goto: [page: number];
  perPage: [size: number];
}>();

const { t } = useTailorsI18n();
const statusLabelFn = statusLabel;

function onPerPage(event: Event) {
  emit("perPage", Number((event.target as HTMLSelectElement).value));
}
</script>

<template>
  <section class="panel order-list">
    <div class="deck-header">
      <div>
        <span class="deck-eyebrow">{{ t('app.order.order_list') }}</span>
        <h2 class="deck-title">{{ archived ? t('app.order.archived_orders') : t('app.order.active_order_queue') }}</h2>
      </div>
      <span class="badge-count">{{ totalOrders.toLocaleString() }}</span>
    </div>

    <p v-if="loading" class="loading-state">{{ t('app.order.loading_orders') }}</p>
    <p v-else-if="!items.length" class="empty-state">{{ t('app.order.no_matching_orders_found') }}</p>

    <div v-else class="order-records-scroll">
      <div
        v-for="order in items"
        :key="order.id"
        class="record order-select-item"
        :class="{ active: selectedId === order.id }"
        @click="archived ? emit('restore', order) : emit('select', order)"
      >
        <div class="record-meta">
          <div class="record-top-line">
            <strong class="record-customer">{{ order.customer.name }}</strong>
            <span class="order-num-pill">#{{ order.order_number || order.number }}</span>
          </div>
          <div class="record-sub-line">
            <span>{{ order.garment.name }}</span>
            <span v-if="order.karigar" class="karigar-inline">· ✂ {{ order.karigar.name }}</span>
            <span v-if="order.promised_at" class="date-inline">· 📅 {{ new Date(order.promised_at).toLocaleDateString() }}</span>
          </div>
        </div>

        <div class="record-status-col">
          <span class="status" :class="`status--${order.status}`">
            {{ statusLabelFn(order.status) }}
          </span>
          <span v-if="order.total_minor > order.paid_minor" class="due-tag">
            {{ t('app.common.due') }} ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
          </span>
        </div>

        <div class="record-actions-col" @click.stop>
          <button v-if="!archived" class="mini-btn" :title="t('app.order.print_slip')" @click="emit('print', order)">
            <NavIcon name="Printer" />
          </button>
          <button
            v-if="!archived && order.total_minor > order.paid_minor"
            class="mini-btn mini-btn--due"
            :title="t('app.order.pay_due')"
            @click="emit('pay', order)"
          >
            {{ t('app.order.pay') }}
          </button>
        </div>
      </div>
    </div>

    <!-- High-Volume Server-Side Pagination Bar -->
    <div v-if="totalOrders > 0" class="table-pagination-footer">
      <div class="pagination-summary">
        <span><strong>{{ fromItem }}–{{ toItem }}</strong> {{ t('app.order.of') }} <strong>{{ totalOrders.toLocaleString() }}</strong></span>
      </div>

      <div class="pagination-actions">
        <select :value="perPage" class="pagination-perpage" @change="onPerPage">
          <option :value="25">{{ t('app.order.25_page') }}</option>
          <option :value="50">{{ t('app.order.50_page') }}</option>
          <option :value="100">{{ t('app.order.100_page') }}</option>
          <option :value="200">{{ t('app.order.200_page') }}</option>
        </select>

        <div class="pagination-nav-btns">
          <button class="page-nav-btn" :disabled="page <= 1" :title="t('app.order.first_page')" @click="emit('goto', 1)">«</button>
          <button class="page-nav-btn" :disabled="page <= 1" :title="t('app.order.previous_page')" @click="emit('goto', page - 1)">‹</button>
          <span class="page-current-indicator">{{ page }} / {{ lastPage }}</span>
          <button class="page-nav-btn" :disabled="page >= lastPage" :title="t('app.order.next_page')" @click="emit('goto', page + 1)">›</button>
          <button class="page-nav-btn" :disabled="page >= lastPage" :title="t('app.order.last_page')" @click="emit('goto', lastPage)">»</button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>


.view-pill-btn.active {
  background: var(--surface);
  color: var(--primary);
  border-color: var(--line);
  box-shadow: var(--shadow-xs);
}

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

.mini-btn--due {
  color: var(--status-cancelled);
  background: var(--status-cancelled-soft);
  border-color: rgb(220 38 38 / 20%);
}

.order-records-scroll {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: calc(100vh - 18rem);
  overflow-y: auto;
}

.order-select-item {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  align-items: center;
  padding: 1rem 1.15rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 150ms ease;
}

.order-select-item:hover,
.order-select-item.active {
  border-color: var(--primary);
  background: var(--surface);
  transform: translateX(2px);
}

.record-top-line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.record-customer {
  font-size: 0.92rem;
  color: var(--ink);
}

.order-num-pill {
  font-size: 0.725rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.record-sub-line {
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.record-status-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.due-tag {
  font-size: 0.725rem;
  font-weight: 700;
  color: var(--status-cancelled);
}

/* ============================================================
   Server-Side Pagination Footer
   ============================================================ */
.table-pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  background: var(--paper);
  border-top: 1px solid var(--line);
  border-bottom-left-radius: var(--radius-lg);
  border-bottom-right-radius: var(--radius-lg);
  flex-wrap: wrap;
  font-size: 0.85rem;
  margin-top: auto;
}

.pagination-summary {
  color: var(--muted);
}

.pagination-summary strong {
  color: var(--ink);
}

.pagination-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.pagination-perpage {
  height: 2rem;
  padding: 0 0.5rem;
  font-size: 0.8rem;
  border-radius: var(--radius-sm);
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--ink);
}

.pagination-nav-btns {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.15rem;
}

.page-nav-btn {
  width: 1.85rem;
  height: 1.85rem;
  display: grid;
  place-items: center;
  background: transparent;
  border: 0;
  border-radius: var(--radius-xs);
  color: var(--ink);
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 120ms ease;
}

.page-nav-btn:hover:not(:disabled) {
  background: var(--paper);
  color: var(--primary);
}

.page-nav-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.page-current-indicator {
  padding: 0 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ink);
  white-space: nowrap;
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
</style>
