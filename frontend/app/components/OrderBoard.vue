<script setup lang="ts">
import type { Order } from "~/types/orders";

const props = defineProps<{ items: Order[]; selectedId?: string; loading: boolean }>();
const emit = defineEmits<{
  select: [order: Order];
  pay: [order: Order];
  advance: [order: Order];
  print: [order: Order];
}>();

const { t } = useTailorsI18n();

function ordersInStatus(status: string) {
  return props.items.filter((order) => order.status === status);
}
</script>

<template>
  <div class="kanban-board-container">
    <div class="kanban-board">
      <div v-for="col in kanbanColumns" :key="col.key" class="kanban-column">
        <!-- Column Header -->
        <div class="kanban-header">
          <div class="kanban-title-group">
            <span class="status-indicator-dot" :style="{ background: col.color }"></span>
            <div>
              <strong>{{ t(`app.orders.stage_${col.key}`) }}</strong>
            </div>
          </div>
          <span class="kanban-count">{{ ordersInStatus(col.key).length }}</span>
        </div>

        <!-- Cards in Column -->
        <div class="kanban-cards">
          <div
            v-for="order in ordersInStatus(col.key)"
            :key="order.id"
            class="kanban-card"
            :class="{ 'kanban-card--active': selectedId === order.id }"
            @click="emit('select', order)"
          >
            <div class="kanban-card-top">
              <div class="customer-block">
                <strong class="customer-name">{{ order.customer.name }}</strong>
                <small v-if="order.customer.mobile_number" class="customer-mobile">
                  {{ order.customer.mobile_number }}
                </small>
              </div>
              <span class="kanban-order-num">#{{ order.order_number || order.number }}</span>
            </div>

            <div class="kanban-meta-row">
              <span class="garment-tag">{{ order.garment.name }}</span>
              <span v-if="order.karigar" class="karigar-tag">
                ✂ {{ order.karigar.name }}
              </span>
            </div>

            <!-- Card Delivery & Due Bar -->
            <div class="kanban-footer">
              <span
                class="due-date-pill"
                :class="{
                  'due-date-pill--today': order.promised_at && new Date(order.promised_at).toDateString() === new Date().toDateString(),
                  'due-date-pill--urgent': order.promised_at && new Date(order.promised_at) < new Date(),
                }"
                :title="order.promised_at ? new Date(order.promised_at).toLocaleString() : ''"
              >
                📅 {{ order.promised_at ? new Date(order.promised_at).toLocaleDateString() : t('app.order.no_date') }}
              </span>

              <div class="card-mini-actions" @click.stop>
                <button
                  v-if="order.total_minor > order.paid_minor"
                  class="mini-btn mini-btn--due"
                  :title="t('app.order.collect_due_payment')"
                  @click="emit('pay', order)"
                >
                  {{ t('app.common.due') }} ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
                </button>

                <button
                  v-if="col.key !== 'delivered'"
                  class="mini-btn mini-btn--forward"
                  :title="t('app.order.move_to_the_next_stage')"
                  @click="emit('advance', order)"
                >
                  {{ t('app.order.next') }}
                </button>

                <button
                  class="mini-btn"
                  :title="t('app.order.print_receipt')"
                  @click="emit('print', order)"
                >
                  <NavIcon name="Printer" />
                </button>
              </div>
            </div>
          </div>

          <div v-if="!ordersInStatus(col.key).length" class="empty-kanban-slot">
            <span>{{ loading ? t('app.common.loading') : t('app.order.no_orders_in_this_stage') }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Order board */
.kanban-board-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 0.5rem;
}

.kanban-board {
  display: grid;
  grid-template-columns: repeat(5, minmax(17.5rem, 1fr));
  gap: 1.15rem;
  align-items: start;
  min-width: 87.5rem;
}

.kanban-column {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 18rem);
  box-shadow: var(--shadow-xs);
}

.kanban-header {
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--line);
  background: var(--paper);
  border-top-left-radius: var(--radius-lg);
  border-top-right-radius: var(--radius-lg);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kanban-title-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.status-indicator-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
}

.kanban-title-group strong {
  display: block;
  font-size: 0.88rem;
  color: var(--ink);
}

.kanban-title-group small {
  display: block;
  font-size: 0.72rem;
  color: var(--muted);
}

.kanban-count {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-pill);
  padding: 0.15rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
}

.kanban-cards {
  padding: 0.85rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.kanban-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

.kanban-card:hover {
  border-color: var(--primary);
  background: var(--surface);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.kanban-card--active {
  border-color: var(--primary) !important;
  background: var(--surface) !important;
  box-shadow: 0 0 0 2px var(--primary-soft), var(--shadow-sm) !important;
}

.kanban-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.customer-name {
  display: block;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
}

.customer-mobile {
  display: block;
  font-size: 0.75rem;
  color: var(--muted);
  margin-top: 0.1rem;
}

.kanban-order-num {
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  color: var(--ink);
}

.kanban-meta-row {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.garment-tag {
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  color: var(--ink);
}

.karigar-tag {
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary);
  border: 1px solid rgb(20 92 75 / 15%);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.kanban-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.25rem;
  padding-top: 0.65rem;
  border-top: 1px dashed var(--line);
}

.due-date-pill {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--muted);
}

.due-date-pill--today {
  color: var(--accent);
  font-weight: 700;
}

.due-date-pill--urgent {
  color: var(--status-cancelled);
  font-weight: 700;
}

.card-mini-actions {
  display: flex;
  gap: 0.35rem;
  align-items: center;
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

.mini-btn--forward {
  background: var(--primary-soft);
  color: var(--primary);
  border-color: rgb(20 92 75 / 20%);
}

.empty-kanban-slot {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--muted);
  font-size: 0.8rem;
  font-style: italic;
}
</style>
