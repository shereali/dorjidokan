<script setup lang="ts">
const { tr } = usePlatformFormat();
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
    toast.error(tr("Could not load the report.", "রিপোর্ট লোড করা যায়নি।"));
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
    toast.success(tr("Export queued — see the history below.", "এক্সপোর্ট শুরু হয়েছে — নিচের ইতিহাসে দেখুন।"));
    await load();
  } catch (e: any) {
    toast.error(tr("Could not create the export.", "এক্সপোর্ট তৈরি করা যায়নি।"));
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
    toast.error(tr("Could not download the file.", "ফাইল ডাউনলোড করা যায়নি।"));
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
        <p class="eyebrow">{{ tr("WORKSHOP REPORTS", "ওয়ার্কশপ রিপোর্ট") }}</p>
        <h1>{{ tr("Financial & operations reports", "আর্থিক ও কার্যক্রম রিপোর্ট") }}</h1>
        <p>{{ tr("Revenue, labour cost, net profit and stock health at a glance.", "আয়, মজুরি খরচ, নিট মুনাফা ও স্টকের অবস্থা এক নজরে।") }}</p>
      </div>
    </header>

    <!-- Date Range Filter & Quick Presets -->
    <form class="panel report-filter-panel" @submit.prevent="load">
      <div class="filter-controls">
        <label>{{ tr("From", "শুরু") }}
          <input v-model="from" type="date" />
        </label>
        <label>{{ tr("To", "শেষ") }}
          <input v-model="to" type="date" />
        </label>
        <button class="primary" style="margin-top:auto">{{ tr("Run report", "রিপোর্ট দেখুন") }}</button>
      </div>

      <div class="preset-buttons">
        <button type="button" class="mini-btn" @click="preset(1)">{{ tr("Today", "আজ") }}</button>
        <button type="button" class="mini-btn" @click="preset(7)">{{ tr("Last 7 days", "গত ৭ দিন") }}</button>
        <button type="button" class="mini-btn" @click="preset(30)">{{ tr("Last 30 days", "গত ৩০ দিন") }}</button>
      </div>
    </form>

    <p v-if="loading">{{ tr("Calculating…", "হিসাব হচ্ছে…") }}</p>

    <template v-else-if="report">
      <!-- High Level Operational Cards -->
      <div class="metrics">
        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ tr("ORDER REVENUE", "অর্ডার থেকে আয়") }}</span>
          <strong>{{ money(report.orders.value_minor) }}</strong>
          <small>{{ tr(`${report.orders.count} orders booked`, `${report.orders.count}টি অর্ডার বুক হয়েছে`) }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ tr("FABRIC SALES", "কাপড় বিক্রয়") }}</span>
          <strong>{{ money(report.sales.value_minor) }}</strong>
          <small>{{ tr(`${report.sales.count} POS invoices`, `${report.sales.count}টি POS ইনভয়েস`) }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ tr("EXPENSES", "খরচ") }}</span>
          <strong>{{ money(report.expenses_minor) }}</strong>
          <small>{{ tr("Overheads and supplies", "পরিচালন খরচ ও সরঞ্জাম") }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ tr("UNPAID KARIGAR WAGES", "কারিগরের বকেয়া মজুরি") }}</span>
          <strong style="color:var(--status-cancelled)">{{ money(report.wages_unpaid_minor) }}</strong>
          <small>{{ tr("Piece-rate wages owed", "পিস-রেটে জমে থাকা মজুরি") }}</small>
        </article>
      </div>

      <!-- Decision Dashboard Summary -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>{{ tr("Business summary", "ব্যবসার সারসংক্ষেপ") }}</h2>
        <div v-if="report.decision" class="metrics" style="margin-bottom:0">
          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ tr("ESTIMATED NET PROFIT", "আনুমানিক নিট মুনাফা") }}</span>
            <strong style="color:var(--status-ready)">{{ money(report.decision.net_profit_minor) }}</strong>
            <small>{{ tr("Revenue minus costs and wages", "আয় থেকে খরচ ও মজুরি বাদ") }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ tr("CUSTOMER DUES", "গ্রাহকের কাছে পাওনা") }}</span>
            <strong style="color:var(--status-inprogress)">{{ money(report.decision.receivables_minor) }}</strong>
            <small>{{ tr("Total unpaid by customers", "গ্রাহকদের মোট বকেয়া") }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ tr("OVERDUE DELIVERIES", "দেরি হওয়া ডেলিভারি") }}</span>
            <strong :style="{ color: report.decision.overdue_orders > 0 ? 'var(--status-cancelled)' : 'var(--status-ready)' }">
              {{ report.decision.overdue_orders }}
            </strong>
            <small>{{ tr("Orders past their promised date", "প্রতিশ্রুত তারিখ পেরোনো অর্ডার") }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ tr("MAKING COST", "মজুরি খরচ") }}</span>
            <strong>{{ money(report.decision.labor_cost_minor) }}</strong>
            <small>{{ tr("Karigar production cost", "কারিগরের উৎপাদন খরচ") }}</small>
          </article>
        </div>
      </section>

      <!-- Low Stock Alert Box -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>{{ tr("Low stock — reorder list", "কম স্টক — পুনরায় কেনার তালিকা") }}</h2>
        <p v-if="!report.low_stock?.length" class="empty">{{ tr("All items are above their reorder level.", "সব পণ্যের স্টক নিরাপদ সীমার ওপরে আছে।") }}</p>
        <div class="low-stock-grid">
          <div v-for="item in report.low_stock || []" :key="item.id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block;color:var(--status-cancelled)">{{ tr("Low stock!", "স্টক কম!") }}</small>
            </div>
            <strong style="color:var(--status-cancelled)">{{ item.balance || 0 }} {{ item.unit }}</strong>
          </div>
        </div>
      </section>
    </template>

    <!-- Exports Section -->
    <div class="workspace-grid">
      <form class="panel form" @submit.prevent="createExport('summary')">
        <h2>{{ tr("Export reports", "রিপোর্ট ডাউনলোড") }}</h2>
        <label>{{ tr("Send to email (optional)", "ইমেইলে পাঠান (ঐচ্ছিক)") }}
          <input v-model="exportEmail" type="email" placeholder="owner@tailors.com" />
        </label>
        <button class="primary">{{ tr("Export summary (CSV)", "সারসংক্ষেপ ডাউনলোড (CSV)") }}</button>
        <button type="button" class="secondary" @click="createExport('tenant_data')">
          {{ tr("Export full backup (JSON)", "সম্পূর্ণ ব্যাকআপ ডাউনলোড (JSON)") }}
        </button>
      </form>

      <section class="panel">
        <h2>{{ tr("Export history", "এক্সপোর্টের ইতিহাস") }}</h2>
        <p v-if="!exports.length" class="empty">{{ tr("No exports yet.", "এখনো কোনো এক্সপোর্ট নেই।") }}</p>
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
              {{ tr("Download", "ডাউনলোড") }}
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
