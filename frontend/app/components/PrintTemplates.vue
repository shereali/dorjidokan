<script setup lang="ts">
const { t } = useTailorsI18n();
export interface PrintData {
  type: "order_receipt" | "job_ticket" | "pos_invoice" | "barcode_tag";
  shopName?: string;
  shopPhone?: string;
  shopAddress?: string;
  orderNumber?: string;
  barcode?: string;
  customerName?: string;
  customerPhone?: string;
  promisedAt?: string;
  garmentName?: string;
  karigarName?: string;
  urgency?: string;
  items?: Array<{ name: string; quantity: number; price: number; note?: string }>;
  measurements?: Array<{ name: string; value: number | string; unit: string }>;
  totalAmount?: number;
  advancePaid?: number;
  dueAmount?: number;
  discount?: number;
  paymentMethod?: string;
  notes?: string;
  date?: string;
}

const props = defineProps<{
  data: PrintData | null;
}>();

const emit = defineEmits<{
  close: [];
}>();

function printNow() {
  window.print();
}

// While the modal is open, printing shows only the receipt (see the unscoped style at the bottom).
watch(
  () => props.data,
  (value) => {
    if (import.meta.client) document.body.classList.toggle('printing-modal', !!value);
  },
  { immediate: true },
);
onBeforeUnmount(() => {
  if (import.meta.client) document.body.classList.remove('printing-modal');
});
</script>

<template>
  <Teleport to="body">
    <div v-if="data" class="print-modal-backdrop" @click.self="emit('close')">
      <div class="print-modal-window">
        <div class="print-toolbar no-print">
          <div class="print-toolbar-info">
            <strong>
              <span v-if="data.type === 'order_receipt'">{{ t('app.print_templates.order_receipt') }}</span>
              <span v-else-if="data.type === 'job_ticket'">{{ t('app.print_templates.job_ticket_cutting_sewing_slip') }}</span>
              <span v-else-if="data.type === 'pos_invoice'">{{ t('app.print_templates.sale_invoice') }}</span>
              <span v-else>{{ t('app.print_templates.barcode_tag') }}</span>
            </strong>
            <small>{{ t('app.print_templates.works_with_80mm_thermal_and_a4') }}</small>
          </div>
          <div class="print-toolbar-actions">
            <button class="primary-btn" @click="printNow">
              <NavIcon name="Printer" /> {{ t('app.print_templates.print') }}
            </button>
            <button class="secondary-btn" @click="emit('close')">
              {{ t('app.common.close') }}
            </button>
          </div>
        </div>

        <div class="printable-paper" :class="`printable--${data.type}`">
          <!-- Header -->
          <div class="print-header">
            <h2 class="shop-title">{{ data.shopName || t('app.print_templates.tailor_shop') }}</h2>
            <p v-if="data.shopPhone" class="shop-sub">{{ t('app.print_templates.phone') }}: {{ data.shopPhone }}</p>
            <p v-if="data.shopAddress" class="shop-sub">{{ data.shopAddress }}</p>
            <div class="divider-dashed"></div>
          </div>

          <!-- Receipt / Invoice View -->
          <template v-if="data.type === 'order_receipt' || data.type === 'pos_invoice'">
            <div class="doc-badge-row">
              <span class="doc-type-label">{{ data.type === 'order_receipt' ? t('app.print_templates.order_memo') : t('app.print_templates.cash_receipt') }}</span>
              <span class="doc-num">#{{ data.orderNumber }}</span>
            </div>

            <div class="info-grid">
              <div><strong>{{ t('app.common.customer') }}</strong> {{ data.customerName || t('app.print_templates.walk_in') }}</div>
              <div v-if="data.customerPhone"><strong>{{ t('app.print_templates.mobile') }}</strong> {{ data.customerPhone }}</div>
              <div><strong>{{ t('app.print_templates.date') }}</strong> {{ data.date || new Date().toLocaleDateString() }}</div>
              <div v-if="data.promisedAt" class="delivery-highlight">
                <strong>{{ t('app.print_templates.delivery_date') }}</strong> {{ new Date(data.promisedAt).toLocaleDateString() }}
              </div>
              <div v-if="data.urgency === 'urgent'" class="urgent-tag">{{ t('app.print_templates.urgent_order') }}</div>
            </div>

            <div class="divider-dashed"></div>

            <table class="receipt-table">
              <thead>
                <tr>
                  <th style="text-align:left">{{ t('app.print_templates.item') }}</th>
                  <th style="text-align:center">{{ t('app.print_templates.qty') }}</th>
                  <th style="text-align:right">{{ t('app.print_templates.amount') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(item, idx) in data.items || []" :key="idx">
                  <td>
                    <strong>{{ item.name }}</strong>
                    <small v-if="item.note" class="block-note">{{ item.note }}</small>
                  </td>
                  <td style="text-align:center">{{ item.quantity }}</td>
                  <td style="text-align:right">{{ (item.price).toFixed(2) }}</td>
                </tr>
              </tbody>
            </table>

            <div class="divider-dashed"></div>

            <div class="totals-block">
              <div class="total-row"><span>{{ t('app.print_templates.total') }}</span> <strong>৳ {{ (data.totalAmount || 0).toFixed(2) }}</strong></div>
              <div v-if="data.discount" class="total-row"><span>{{ t('app.print_templates.discount') }}</span> <span>- ৳ {{ (data.discount || 0).toFixed(2) }}</span></div>
              <div class="total-row"><span>{{ t('app.print_templates.paid') }}</span> <span>৳ {{ (data.advancePaid || 0).toFixed(2) }}</span></div>
              <div class="total-row due-row">
                <span>{{ t('app.print_templates.balance_due') }}</span>
                <strong>৳ {{ (data.dueAmount || 0).toFixed(2) }}</strong>
              </div>
            </div>

            <div class="divider-dashed"></div>

            <div v-if="data.barcode" class="barcode-container">
              <div class="barcode-text">*{{ data.barcode }}*</div>
              <small>{{ data.barcode }}</small>
            </div>

            <div class="print-footer">
              <p>{{ t('app.print_templates.please_bring_this_slip_when_collecting') }}</p>
              <p>{{ t('app.print_templates.thank_you_for_choosing_us') }}</p>
            </div>
          </template>

          <!-- Workshop Job Ticket (Job Card for Master/Karigar) -->
          <template v-else-if="data.type === 'job_ticket'">
            <div class="job-card-header">
              <div class="badge-large">{{ t('app.print_templates.job_ticket') }}</div>
              <div class="order-big">#{{ data.orderNumber }}</div>
            </div>

            <div class="info-grid">
              <div><strong>{{ t('app.common.customer') }}</strong> {{ data.customerName }} ({{ data.customerPhone }})</div>
              <div><strong>{{ t('app.print_templates.garment') }}</strong> <span class="garment-name-badge">{{ data.garmentName }}</span></div>
              <div v-if="data.karigarName"><strong>{{ t('app.print_templates.karigar') }}</strong> {{ data.karigarName }}</div>
              <div class="delivery-highlight">
                <strong>{{ t('app.print_templates.delivery') }}</strong> {{ data.promisedAt ? new Date(data.promisedAt).toLocaleString() : t('app.print_templates.not_set') }}
              </div>
              <div v-if="data.urgency === 'urgent'" class="urgent-tag">{{ t('app.print_templates.urgent_delivery') }}</div>
            </div>

            <div class="divider-solid"></div>

            <h3 class="section-title">{{ t('app.print_templates.measurements') }}</h3>
            <div class="measurement-print-grid">
              <div v-for="m in data.measurements || []" :key="m.name" class="measure-box">
                <span class="measure-name">{{ m.name }}</span>
                <strong class="measure-val">{{ m.value }} <small>{{ m.unit }}</small></strong>
              </div>
            </div>

            <div v-if="data.notes" class="workshop-notes-box">
              <strong>{{ t('app.print_templates.cutting_stitching_notes') }}</strong>
              <p>{{ data.notes }}</p>
            </div>

            <div class="divider-dashed"></div>

            <div v-if="data.barcode" class="barcode-container">
              <div class="barcode-text">*{{ data.barcode }}*</div>
              <small>{{ data.orderNumber }}</small>
            </div>
          </template>

          <!-- Barcode Tag -->
          <template v-else>
            <div class="barcode-tag-content">
              <h3>{{ data.orderNumber }}</h3>
              <p><strong>{{ data.customerName }}</strong> · {{ data.garmentName }}</p>
              <div v-if="data.promisedAt" class="delivery-highlight">
                {{ t('app.print_templates.due') }}: {{ new Date(data.promisedAt).toLocaleDateString() }}
              </div>
              <div v-if="data.barcode" class="barcode-container">
                <div class="barcode-text">*{{ data.barcode }}*</div>
                <small>{{ data.barcode }}</small>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.print-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 60%);
  backdrop-filter: blur(4px);
  z-index: 10000;
  display: grid;
  place-items: center;
  padding: 1.5rem;
  overflow-y: auto;
}

.print-modal-window {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: 0 1.5rem 4rem rgb(0 0 0 / 30%);
  max-width: 32rem;
  width: 100%;
  border: var(--border-width) solid var(--line);
  overflow: hidden;
}

.print-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem 1.5rem;
  background: var(--paper);
  border-bottom: var(--border-width) solid var(--line);
}

.print-toolbar-info strong {
  display: block;
  font-size: 0.95rem;
  color: var(--ink);
}

.print-toolbar-info small {
  color: var(--muted);
  font-size: 0.8rem;
}

.print-toolbar-actions {
  display: flex;
  gap: 0.5rem;
}

.primary-btn {
  background: var(--primary);
  color: #fff;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: var(--radius-sm);
  font-weight: 600;
  font-size: 0.85rem;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  cursor: pointer;
}

.secondary-btn {
  background: transparent;
  color: var(--ink);
  border: var(--border-width) solid var(--line);
  padding: 0.5rem 0.85rem;
  border-radius: var(--radius-sm);
  font-size: 0.85rem;
  cursor: pointer;
}

.printable-paper {
  background: #fff;
  color: #111;
  padding: 1.5rem;
  font-family: 'Courier New', Courier, monospace, system-ui;
  font-size: 0.85rem;
  line-height: 1.4;
  margin: 1rem auto;
  box-shadow: 0 0.25rem 1rem rgb(0 0 0 / 5%);
  border-radius: 4px;
}

.print-header {
  text-align: center;
  margin-bottom: 0.75rem;
}

.shop-title {
  font-size: 1.3rem;
  font-weight: 800;
  margin: 0 0 0.2rem;
  letter-spacing: 0.05em;
  color: #000;
}

.shop-sub {
  margin: 0;
  font-size: 0.8rem;
  color: #444;
}

.divider-dashed {
  border-top: 1px dashed #999;
  margin: 0.75rem 0;
}

.divider-solid {
  border-top: 1.5px solid #333;
  margin: 0.75rem 0;
}

.doc-badge-row {
  display: flex;
  justify-content: space-between;
  font-weight: 800;
  font-size: 0.95rem;
  margin-bottom: 0.5rem;
}

.info-grid {
  display: grid;
  gap: 0.25rem;
  font-size: 0.825rem;
}

.delivery-highlight {
  background: #f0f0f0;
  padding: 0.25rem 0.5rem;
  font-weight: 700;
  border-radius: 3px;
  margin-top: 0.2rem;
}

.urgent-tag {
  color: #c00;
  font-weight: 800;
  text-align: center;
  padding: 0.2rem 0;
  letter-spacing: 0.05em;
}

.receipt-table {
  width: 100%;
  border-collapse: collapse;
  margin: 0.5rem 0;
}

.receipt-table th {
  border-bottom: 1px dashed #777;
  padding: 0.3rem 0;
  font-size: 0.8rem;
}

.receipt-table td {
  padding: 0.4rem 0;
  vertical-align: top;
}

.block-note {
  display: block;
  font-size: 0.75rem;
  color: #555;
}

.totals-block {
  display: grid;
  gap: 0.25rem;
  margin: 0.5rem 0;
}

.total-row {
  display: flex;
  justify-content: space-between;
}

.due-row {
  font-size: 1rem;
  font-weight: 800;
  border-top: 1px solid #333;
  padding-top: 0.3rem;
  margin-top: 0.2rem;
}

.barcode-container {
  text-align: center;
  margin: 0.75rem 0 0.25rem;
}

.barcode-text {
  font-family: monospace;
  font-size: 1.5rem;
  letter-spacing: 3px;
  font-weight: 700;
}

.print-footer {
  text-align: center;
  font-size: 0.75rem;
  color: #555;
  margin-top: 0.75rem;
  line-height: 1.3;
}

/* Workshop Job ticket styles */
.job-card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.badge-large {
  background: #222;
  color: #fff;
  padding: 0.25rem 0.6rem;
  border-radius: 3px;
  font-weight: 700;
  font-size: 0.85rem;
}

.order-big {
  font-size: 1.25rem;
  font-weight: 800;
}

.garment-name-badge {
  background: #e8e8e8;
  padding: 0.15rem 0.5rem;
  border-radius: 3px;
  font-weight: 700;
}

.section-title {
  font-size: 0.9rem;
  margin: 0.6rem 0 0.4rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.measurement-print-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.measure-box {
  display: flex;
  justify-content: space-between;
  border: 1px solid #ccc;
  padding: 0.4rem 0.6rem;
  border-radius: 3px;
  background: #fafafa;
}

.measure-name {
  color: #333;
}

.measure-val {
  font-size: 1rem;
}

.workshop-notes-box {
  background: #fff9e6;
  border: 1px solid #ecdca0;
  padding: 0.5rem;
  border-radius: 4px;
  margin-top: 0.5rem;
}

.workshop-notes-box p {
  margin: 0.2rem 0 0;
  white-space: pre-wrap;
}

/* Screen Print Media */
@media print {
  .no-print {
    display: none !important;
  }

  .print-modal-backdrop {
    position: static;
    display: block;
    overflow: visible;
    padding: 0 !important;
    background: transparent !important;
    backdrop-filter: none;
  }

  .print-modal-window {
    box-shadow: none !important;
    border: none !important;
    border-radius: 0;
    max-width: 100% !important;
    overflow: visible;
  }

  .printable-paper {
    margin: 0;
    padding: 10px;
    box-shadow: none;
  }
}
</style>

<style>
/*
 * Unscoped on purpose: scoped CSS rewrites `body` selectors to body[data-v-…], which matches nothing.
 * The modal is teleported to <body>, so while it is open every other child of <body> can be dropped from the printout.
 */
@media print {
  body.printing-modal > *:not(.print-modal-backdrop) {
    display: none !important;
  }

  body.printing-modal {
    height: auto !important;
    overflow: visible !important;
    background: #fff !important;
  }
}
</style>
