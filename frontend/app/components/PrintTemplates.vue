<script setup lang="ts">
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
</script>

<template>
  <div v-if="data" class="print-modal-backdrop" @click.self="emit('close')">
    <div class="print-modal-window">
      <div class="print-toolbar no-print">
        <div class="print-toolbar-info">
          <strong>
            <span v-if="data.type === 'order_receipt'">Customer Order Receipt</span>
            <span v-else-if="data.type === 'job_ticket'">Workshop Job Ticket (কাটিং ও সেলাই স্লিপ)</span>
            <span v-else-if="data.type === 'pos_invoice'">Point of Sale Invoice</span>
            <span v-else>Barcode Tag</span>
          </strong>
          <small>Thermal 80mm / A4 Printer Compatible</small>
        </div>
        <div class="print-toolbar-actions">
          <button class="primary-btn" @click="printNow">
            <NavIcon name="Printer" /> Print Now
          </button>
          <button class="secondary-btn" @click="emit('close')">
            Close
          </button>
        </div>
      </div>

      <div class="printable-paper" :class="`printable--${data.type}`">
        <!-- Header -->
        <div class="print-header">
          <h2 class="shop-title">{{ data.shopName || "সুতো TAILORS" }}</h2>
          <p v-if="data.shopPhone" class="shop-sub">Phone: {{ data.shopPhone }}</p>
          <p v-if="data.shopAddress" class="shop-sub">{{ data.shopAddress }}</p>
          <div class="divider-dashed"></div>
        </div>

        <!-- Receipt / Invoice View -->
        <template v-if="data.type === 'order_receipt' || data.type === 'pos_invoice'">
          <div class="doc-badge-row">
            <span class="doc-type-label">{{ data.type === 'order_receipt' ? 'ORDER MEMO' : 'CASH RECEIPT' }}</span>
            <span class="doc-num">#{{ data.orderNumber }}</span>
          </div>

          <div class="info-grid">
            <div><strong>Customer:</strong> {{ data.customerName || "Walk-in" }}</div>
            <div v-if="data.customerPhone"><strong>Mobile:</strong> {{ data.customerPhone }}</div>
            <div><strong>Date:</strong> {{ data.date || new Date().toLocaleDateString() }}</div>
            <div v-if="data.promisedAt" class="delivery-highlight">
              <strong>Delivery Date:</strong> {{ new Date(data.promisedAt).toLocaleDateString() }}
            </div>
            <div v-if="data.urgency === 'urgent'" class="urgent-tag">★ URGENT ORDER ★</div>
          </div>

          <div class="divider-dashed"></div>

          <table class="receipt-table">
            <thead>
              <tr>
                <th style="text-align:left">Item / Description</th>
                <th style="text-align:center">Qty</th>
                <th style="text-align:right">Amount (৳)</th>
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
            <div class="total-row"><span>Total Amount:</span> <strong>৳ {{ (data.totalAmount || 0).toFixed(2) }}</strong></div>
            <div v-if="data.discount" class="total-row"><span>Discount:</span> <span>- ৳ {{ (data.discount || 0).toFixed(2) }}</span></div>
            <div class="total-row"><span>Advance Paid:</span> <span>৳ {{ (data.advancePaid || 0).toFixed(2) }}</span></div>
            <div class="total-row due-row">
              <span>Balance Due (বকেয়া):</span>
              <strong>৳ {{ (data.dueAmount || 0).toFixed(2) }}</strong>
            </div>
          </div>

          <div class="divider-dashed"></div>

          <div v-if="data.barcode" class="barcode-container">
            <div class="barcode-text">*{{ data.barcode }}*</div>
            <small>{{ data.barcode }}</small>
          </div>

          <div class="print-footer">
            <p>দয়া করে ডেলিভারির সময় এই স্লিপটি সাথে আনুন।</p>
            <p>Thank you for your custom!</p>
          </div>
        </template>

        <!-- Workshop Job Ticket (Job Card for Master/Karigar) -->
        <template v-else-if="data.type === 'job_ticket'">
          <div class="job-card-header">
            <div class="badge-large">JOB TICKET / কাটিং স্লিপ</div>
            <div class="order-big">#{{ data.orderNumber }}</div>
          </div>

          <div class="info-grid">
            <div><strong>Customer:</strong> {{ data.customerName }} ({{ data.customerPhone }})</div>
            <div><strong>Garment:</strong> <span class="garment-name-badge">{{ data.garmentName }}</span></div>
            <div v-if="data.karigarName"><strong>Assigned Karigar:</strong> {{ data.karigarName }}</div>
            <div class="delivery-highlight">
              <strong>Target Delivery:</strong> {{ data.promisedAt ? new Date(data.promisedAt).toLocaleString() : 'Not set' }}
            </div>
            <div v-if="data.urgency === 'urgent'" class="urgent-tag">★ জরুরি ডেলিভারি (EMERGENCY) ★</div>
          </div>

          <div class="divider-solid"></div>

          <h3 class="section-title">Measurements (মাপের তালিকা)</h3>
          <div class="measurement-print-grid">
            <div v-for="m in data.measurements || []" :key="m.name" class="measure-box">
              <span class="measure-name">{{ m.name }}</span>
              <strong class="measure-val">{{ m.value }} <small>{{ m.unit }}</small></strong>
            </div>
          </div>

          <div v-if="data.notes" class="workshop-notes-box">
            <strong>কাটিং ও সেলাই বিশেষ নির্দেশিকা:</strong>
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
              Due: {{ new Date(data.promisedAt).toLocaleDateString() }}
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
  body * {
    visibility: hidden;
  }
  .no-print {
    display: none !important;
  }
  .print-modal-backdrop {
    position: absolute;
    inset: 0;
    background: transparent !important;
    padding: 0 !important;
  }
  .print-modal-window {
    box-shadow: none !important;
    border: none !important;
    max-width: 100% !important;
  }
  .printable-paper,
  .printable-paper * {
    visibility: visible;
  }
  .printable-paper {
    position: absolute;
    left: 0;
    top: 0;
    width: 100%;
    margin: 0;
    padding: 10px;
    box-shadow: none;
  }
}
</style>
