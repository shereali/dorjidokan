<script setup lang="ts">
const { t } = useTailorsI18n();
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
    toast.error(t('app.report.could_not_load_the_report'));
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
    toast.success(t('app.report.export_queued_see_the_history_below'));
    await load();
  } catch (e: any) {
    toast.error(t('app.report.could_not_create_the_export'));
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
    toast.error(t('app.report.could_not_download_the_file'));
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
        <p class="eyebrow">{{ t('app.report.workshop_reports') }}</p>
        <h1>{{ t('app.report.financial_operations_reports') }}</h1>
        <p>{{ t('app.report.revenue_labour_cost_net_profit_and') }}</p>
      </div>
    </header>

    <!-- Date Range Filter & Quick Presets -->
    <form class="panel report-filter-panel" @submit.prevent="load">
      <div class="filter-controls">
        <label>{{ t('app.report.from') }}
          <input v-model="from" type="date" />
        </label>
        <label>{{ t('app.report.to') }}
          <input v-model="to" type="date" />
        </label>
        <button class="primary" style="margin-top:auto">{{ t('app.report.run_report') }}</button>
      </div>

      <div class="preset-buttons">
        <button type="button" class="mini-btn" @click="preset(1)">{{ t('app.common.today') }}</button>
        <button type="button" class="mini-btn" @click="preset(7)">{{ t('app.report.last_7_days') }}</button>
        <button type="button" class="mini-btn" @click="preset(30)">{{ t('app.report.last_30_days') }}</button>
      </div>
    </form>

    <p v-if="loading">{{ t('app.report.calculating') }}</p>

    <template v-else-if="report">
      <!-- High Level Operational Cards -->
      <div class="metrics">
        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.order_revenue') }}</span>
          <strong>{{ money(report.orders.value_minor) }}</strong>
          <small>{{ t('app.report.p0_orders_booked', { p0: report.orders.count }) }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.fabric_sales') }}</span>
          <strong>{{ money(report.sales.value_minor) }}</strong>
          <small>{{ t('app.report.p0_pos_invoices', { p0: report.sales.count }) }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.expenses') }}</span>
          <strong>{{ money(report.expenses_minor) }}</strong>
          <small>{{ t('app.report.overheads_and_supplies') }}</small>
        </article>

        <article>
          <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.unpaid_karigar_wages') }}</span>
          <strong style="color:var(--status-cancelled)">{{ money(report.wages_unpaid_minor) }}</strong>
          <small>{{ t('app.report.piece_rate_wages_owed') }}</small>
        </article>
      </div>

      <!-- Decision Dashboard Summary -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>{{ t('app.report.business_summary') }}</h2>
        <div v-if="report.decision" class="metrics" style="margin-bottom:0">
          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.estimated_net_profit') }}</span>
            <strong style="color:var(--status-ready)">{{ money(report.decision.net_profit_minor) }}</strong>
            <small>{{ t('app.report.revenue_minus_costs_and_wages') }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.customer_dues') }}</span>
            <strong style="color:var(--status-inprogress)">{{ money(report.decision.receivables_minor) }}</strong>
            <small>{{ t('app.report.total_unpaid_by_customers') }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.overdue_deliveries') }}</span>
            <strong :style="{ color: report.decision.overdue_orders > 0 ? 'var(--status-cancelled)' : 'var(--status-ready)' }">
              {{ report.decision.overdue_orders }}
            </strong>
            <small>{{ t('app.report.orders_past_their_promised_date') }}</small>
          </article>

          <article>
            <span class="eyebrow" style="font-size:0.68rem">{{ t('app.report.making_cost') }}</span>
            <strong>{{ money(report.decision.labor_cost_minor) }}</strong>
            <small>{{ t('app.report.karigar_production_cost') }}</small>
          </article>
        </div>
      </section>

      <!-- Low Stock Alert Box -->
      <section class="panel" style="margin-bottom:1.5rem">
        <h2>{{ t('app.report.low_stock_reorder_list') }}</h2>
        <p v-if="!report.low_stock?.length" class="empty">{{ t('app.report.all_items_are_above_their_reorder') }}</p>
        <div class="low-stock-grid">
          <div v-for="item in report.low_stock || []" :key="item.id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block;color:var(--status-cancelled)">{{ t('app.report.low_stock') }}</small>
            </div>
            <strong style="color:var(--status-cancelled)">{{ item.balance || 0 }} {{ item.unit }}</strong>
          </div>
        </div>
      </section>
    </template>

    <!-- Exports Section -->
    <div class="workspace-grid">
      <form class="panel form" @submit.prevent="createExport('summary')">
        <h2>{{ t('app.report.export_reports') }}</h2>
        <label>{{ t('app.report.send_to_email_optional') }}
          <input v-model="exportEmail" type="email" placeholder="owner@tailors.com" />
        </label>
        <button class="primary">{{ t('app.report.export_summary_csv') }}</button>
        <button type="button" class="secondary" @click="createExport('tenant_data')">
          {{ t('app.report.export_full_backup_json') }}
        </button>
      </form>

      <section class="panel">
        <h2>{{ t('app.report.export_history') }}</h2>
        <p v-if="!exports.length" class="empty">{{ t('app.report.no_exports_yet') }}</p>
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
              {{ t('app.report.download') }}
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
