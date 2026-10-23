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
                <NavIcon name="Scissors" /> {{ order.karigar.name }}
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
                <NavIcon name="Calendar" /> {{ order.promised_at ? new Date(order.promised_at).toLocaleDateString() : t('app.order.no_date') }}
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

<style scoped src="../../assets/css/components/_order-board.scss"></style>
