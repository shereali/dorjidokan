<script setup lang="ts">
interface ExportItem {
  id: string;
  type: string;
  status: string;
  email?: string;
  failure_message?: string;
  download_path?: string;
  created_at?: string;
}

const api = useTailorsApi();
const toast = useToast();
const config = useRuntimeConfig();

const from = ref(new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10));
const to = ref(new Date().toISOString().slice(0, 10));
const report = ref<any>(null);
const loading = ref(false);
const exports = ref<ExportItem[]>([]);
const exportEmail = ref("");

const money = (v: number) => `৳ ${(v / 100).toLocaleString()}`;

async function load() {
  loading.value = true;
  try {
    const [summary, history] = await Promise.all([
      api.request<any>(`/reports/summary?from=${from.value}&to=${to.value}`),
      api.request<{ items: ExportItem[] }>("/exports"),
    ]);
    report.value = summary.data;
    exports.value = history.data.items;
  } catch (e: any) {
    toast.error("Could not load report data.");
  } finally {
    loading.value = false;
  }
}

async function createExport(type = "summary") {
  try {
    await api.request("/exports", {
      method: "POST",
      body: { type, from: from.value, to: to.value, email: exportEmail.value || null },
    });
    toast.success("Export job queued. Check export history below.");
    await load();
  } catch (e: any) {
    toast.error("Could not generate export.");
  }
}

async function download(item: ExportItem) {
  if (!item.download_path) return;
  try {
    const response = await $fetch.raw(`${config.public.apiBase}${item.download_path}`, {
      credentials: "include",
      responseType: "blob",
      headers: { "X-Tenant": api.tenant.value },
    });
    const url = URL.createObjectURL(response._data as Blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `tailors-${item.type}.csv`;
    link.click();
    URL.revokeObjectURL(url);
  } catch {
    toast.error("Could not download file.");
  }
}

function preset(days: number) {
  const end = new Date();
  const start = new Date();
  if (days === 1) {
    start.setDate(end.getDate());
  } else {
    start.setDate(end.getDate() - days + 1);
  }
  from.value = start.toISOString().slice(0, 10);
  to.value = end.toISOString().slice(0, 10);
  load();
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKSHOP INTELLIGENCE</p>
        <h1>Financial & Operations Reports</h1>
        <p>Real-time analytics on revenue, labor expenses, net profitability and inventory health.</p>
      </div>
    </header>

    <!-- Date Range Filter & Quick Presets -->
    <form class="panel report-filter-panel" @submit.prevent="load">
      <div class="filter-controls">
        <label>From Date
          <input v-model="from" type="date" />
        </label>
        <label>To Date
          <input v-model="to" type="date" />
        </label>
        <button class="primary" style="margin-top:auto">Run Report</button>
      </div>

      <div class="preset-buttons">
        <button type="button" class="mini-btn" @click="preset(1)">Today (আজ)</button>
        <button type="button" class="mini-btn" @click="preset(7)">Last 7 Days (৭ দিন)</button>
        <button type="button" class="mini-btn" @click="preset(30)">Last 30 Days (৩০ দিন)</button>
      </div>
    </form>

    <p v-if="loading">Calculating workshop financials…</p>

    <template v-else-if="report">
      <!-- High Level Operational Cards -->
      <div class="metrics">
        <article>
          <span class="eyebrow" style="font-size:0.68rem">ORDER REVENUE</span>
          <strong>{{ money(report.orders.value_minor) }}</strong>
          <small>{{ report.orders.count }} tailoring orders booked</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">FABRIC SALES</span>
          <strong>{{ money(report.sales.value_minor) }}</strong>
          <small>{{ report.sales.count }} point-of-sale invoices</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">DAILY EXPENSES</span>
          <strong>{{ money(report.expenses_minor) }}</strong>
          <small>Operating overhead & supplies</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">UNPAID KARIGAR WAGES</span>
          <strong style="color:var(--status-cancelled)">{{ money(report.wages_unpaid_minor) }}</strong>
          <small>Accumulated piece-rate liability</small>
        </article>
      </div>

      <!-- Decision Dashboard Summary -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>Decision Dashboard (ব্যবসায়িক মূল্যায়ন)</h2>
        <div v-if="report.decision" class="metrics" style="margin-bottom:0">
          <article>
            <span class="eyebrow" style="font-size:0.68rem">ESTIMATED NET PROFIT</span>
            <strong style="color:var(--status-ready)">{{ money(report.decision.net_profit_minor) }}</strong>
            <small>Gross revenue minus costs & wages</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">CUSTOMER RECEIVABLES</span>
            <strong style="color:var(--status-inprogress)">{{ money(report.decision.receivables_minor) }}</strong>
            <small>Total outstanding customer dues</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">OVERDUE DELIVERIES</span>
            <strong :style="{ color: report.decision.overdue_orders > 0 ? 'var(--status-cancelled)' : 'var(--status-ready)' }">
              {{ report.decision.overdue_orders }}
            </strong>
            <small>Orders past promised delivery date</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">LABOR / MAKING COST</span>
            <strong>{{ money(report.decision.labor_cost_minor) }}</strong>
            <small>Craftsman production expenses</small>
          </article>
        </div>
      </section>

      <!-- Low Stock Alert Box -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>Low Stock Alert (পুনরায় ক্রয়ের তালিকা)</h2>
        <p v-if="!report.low_stock?.length" class="empty">All inventory items are currently above safety reorder level.</p>
        <div class="low-stock-grid">
          <div v-for="item in report.low_stock || []" :key="item.id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block;color:var(--status-cancelled)">Low balance alert!</small>
            </div>
            <strong style="color:var(--status-cancelled)">{{ item.balance || 0 }} {{ item.unit }}</strong>
          </div>
        </div>
      </section>
    </template>

    <!-- Exports Section -->
    <div class="workspace-grid">
      <form class="panel form" @submit.prevent="createExport('summary')">
        <h2>Export Reports (রিপোর্ট ডাউনলোড)</h2>
        <label>Deliver to Email (Optional)
          <input v-model="exportEmail" type="email" placeholder="owner@tailors.com" />
        </label>
        <button class="primary">Queue Summary CSV Export</button>
        <button type="button" class="secondary" @click="createExport('tenant_data')">
          Export Full Tenant Backup (JSON)
        </button>
      </form>

      <section class="panel">
        <h2>Export File History</h2>
        <p v-if="!exports.length" class="empty">No generated exports yet.</p>
        <div class="export-history-list">
          <article v-for="item in exports" :key="item.id" class="record record--stock">
            <div>
              <strong>{{ item.type.toUpperCase() }}</strong>
              <small style="display:block">{{ item.status }} {{ item.email ? `· ${item.email}` : '' }}</small>
              <small v-if="item.failure_message" class="error">{{ item.failure_message }}</small>
            </div>
            <span class="status" :class="item.status === 'completed' ? 'status--ready' : 'status--pending'">
              {{ item.status }}
            </span>
            <button v-if="item.status === 'completed'" class="mini-btn" @click="download(item)">
              Download File
            </button>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.report-filter-panel {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.filter-controls {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.preset-buttons {
  display: flex;
  gap: 0.5rem;
}

.low-stock-grid,
.export-history-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}
</style>
