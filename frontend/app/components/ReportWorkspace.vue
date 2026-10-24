<script setup lang="ts">
interface ExportItem {
  id: string;
  type: string;
  status: string;
  email?: string;
  failure_message?: string;
  download_path?: string;
  completed_at?: string;
  created_at?: string;
}

interface LowStockItem {
  id: string;
  sku: string;
  name: string;
  unit: string;
  balance: number;
  reorder_level: number;
}

interface ReportData {
  period: { from: string; to: string };
  orders: { count: number; value_minor: number; paid_minor: number };
  sales: { count: number; value_minor: number; paid_minor: number };
  expenses_minor: number;
  wages_unpaid_minor: number;
  decision: {
    gross_income_minor: number;
    labor_cost_minor: number;
    net_profit_minor: number;
    receivables_minor: number;
    overdue_orders: number;
  };
  low_stock: LowStockItem[];
}

const { t, locale } = useTailorsI18n();
const api = useTailorsApi();
const toast = useToast();
const config = useRuntimeConfig();
const router = useRouter();

// Default: 1st of current month to today
const pad = (n: number) => String(n).padStart(2, "0");
const now = new Date();
const from = ref(new Date(now.getFullYear(), now.getMonth(), 1).toISOString().slice(0, 10));
const to = ref(now.toISOString().slice(0, 10));
const activePreset = ref<string>("this_month");

const report = ref<ReportData | null>(null);
const loading = ref(false);
const exports = ref<ExportItem[]>([]);
const exportEmail = ref("");
const exportingType = ref<string | null>(null);

// Active Tab navigation
const activeTab = ref<"overview" | "wages" | "stock" | "exports">("overview");

// Stock Search & Filter
const stockQuery = ref("");
const stockFilter = ref<"all" | "critical">("all");

// Format helpers
const money = (v: number | null | undefined) => {
  const val = (v ?? 0) / 100;
  return `৳ ${val.toLocaleString(locale.value === "bn" ? "bn-BD" : "en-BD", { maximumFractionDigits: 2 })}`;
};

const formatDate = (iso?: string) => {
  if (!iso) return "—";
  return new Date(iso).toLocaleDateString(locale.value === "bn" ? "bn-BD" : "en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  });
};

const formatDateTime = (iso?: string) => {
  if (!iso) return "—";
  return new Date(iso).toLocaleString(locale.value === "bn" ? "bn-BD" : "en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
};

// Computed Financial Metrics
const grossIncome = computed(() => {
  if (report.value?.decision?.gross_income_minor !== undefined) {
    return report.value.decision.gross_income_minor;
  }
  return (report.value?.orders?.value_minor || 0) + (report.value?.sales?.value_minor || 0);
});

const netProfit = computed(() => report.value?.decision?.net_profit_minor ?? 0);

const profitMargin = computed(() => {
  if (!grossIncome.value || grossIncome.value <= 0) return 0;
  return Math.round((netProfit.value / grossIncome.value) * 100);
});

const totalBilled = computed(() => grossIncome.value);
const totalPaid = computed(() => (report.value?.orders?.paid_minor || 0) + (report.value?.sales?.paid_minor || 0));

const collectionRate = computed(() => {
  if (!totalBilled.value || totalBilled.value <= 0) return 100;
  return Math.min(100, Math.round((totalPaid.value / totalBilled.value) * 100));
});

// Segment Breakdown
const laborCost = computed(() => report.value?.decision?.labor_cost_minor || 0);
const overheadExpenses = computed(() => report.value?.expenses_minor || 0);

const laborPercent = computed(() => {
  if (!grossIncome.value || grossIncome.value <= 0) return 0;
  return Math.min(100, Math.round((laborCost.value / grossIncome.value) * 100));
});

const overheadPercent = computed(() => {
  if (!grossIncome.value || grossIncome.value <= 0) return 0;
  return Math.min(100, Math.round((overheadExpenses.value / grossIncome.value) * 100));
});

const profitPercent = computed(() => {
  if (netProfit.value <= 0) return 0;
  return Math.max(0, 100 - laborPercent.value - overheadPercent.value);
});

// Formatted Period Summary
const periodDays = computed(() => {
  if (!from.value || !to.value) return 1;
  const start = new Date(from.value).getTime();
  const end = new Date(to.value).getTime();
  return Math.max(1, Math.round((end - start) / (1000 * 60 * 60 * 24)) + 1);
});

const formattedPeriod = computed(() => {
  if (!from.value || !to.value) return "";
  const d1 = formatDate(from.value);
  const d2 = formatDate(to.value);
  return t("app.report.selected_period_summary", { p0: d1, p1: d2, p2: periodDays.value });
});

const generatedAtTime = computed(() => {
  return new Date().toLocaleString(locale.value === "bn" ? "bn-BD" : "en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
});

// Low Stock Items Filtered
const filteredLowStock = computed(() => {
  const list = report.value?.low_stock || [];
  return list.filter((item) => {
    const query = stockQuery.value.trim().toLowerCase();
    const matchText = !query || item.name.toLowerCase().includes(query) || (item.sku && item.sku.toLowerCase().includes(query));
    if (!matchText) return false;
    if (stockFilter.value === "critical") return Number(item.balance) <= 0;
    return true;
  });
});

const criticalStockCount = computed(() => {
  return (report.value?.low_stock || []).filter((i) => Number(i.balance) <= 0).length;
});

// Data Loading
async function load() {
  loading.value = true;
  try {
    const [summary, history] = await Promise.all([
      api.request<ReportData>(`/reports/summary?from=${from.value}&to=${to.value}`),
      api.request<{ items: ExportItem[] }>("/exports"),
    ]);
    report.value = summary.data;
    exports.value = history.data.items || [];
  } catch {
    toast.error(t("app.report.could_not_load_the_report"));
  } finally {
    loading.value = false;
  }
}

function preset(type: "today" | "7d" | "this_month" | "last_month" | "30d" | "this_year") {
  activePreset.value = type;
  const d = new Date();
  const fmt = (dateObj: Date) => `${dateObj.getFullYear()}-${pad(dateObj.getMonth() + 1)}-${pad(dateObj.getDate())}`;

  if (type === "today") {
    from.value = fmt(d);
    to.value = fmt(d);
  } else if (type === "7d") {
    const start = new Date(d);
    start.setDate(d.getDate() - 6);
    from.value = fmt(start);
    to.value = fmt(d);
  } else if (type === "this_month") {
    const start = new Date(d.getFullYear(), d.getMonth(), 1);
    from.value = fmt(start);
    to.value = fmt(d);
  } else if (type === "last_month") {
    const start = new Date(d.getFullYear(), d.getMonth() - 1, 1);
    const end = new Date(d.getFullYear(), d.getMonth(), 0);
    from.value = fmt(start);
    to.value = fmt(end);
  } else if (type === "30d") {
    const start = new Date(d);
    start.setDate(d.getDate() - 29);
    from.value = fmt(start);
    to.value = fmt(d);
  } else if (type === "this_year") {
    const start = new Date(d.getFullYear(), 0, 1);
    from.value = fmt(start);
    to.value = fmt(d);
  }
  load();
}

function onCustomDateChange() {
  activePreset.value = "custom";
}

async function createExport(type = "summary") {
  exportingType.value = type;
  try {
    await api.request("/exports", {
      method: "POST",
      body: { type, from: from.value, to: to.value, email: exportEmail.value || null },
    });
    toast.success(t("app.report.export_queued_see_the_history_below"));
    activeTab.value = "exports";
    await load();
  } catch {
    toast.error(t("app.report.could_not_create_the_export"));
  } finally {
    exportingType.value = null;
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
    toast.error(t("app.report.could_not_download_the_file"));
  }
}

function printReport() {
  window.print();
}

onMounted(load);
</script>

<template>
  <div class="report-workspace-root">
    <!-- ATELIER REPORT HEADER -->
    <header class="report-header no-print">
      <div class="report-header__brand">
        <div class="report-header__tag">
          <span class="pulse-indicator"></span>
          <span class="tag-title">{{ t('app.report.workshop_reports') }}</span>
          <span class="tag-period">{{ formattedPeriod }}</span>
        </div>
        <h1 class="report-header__heading">{{ t('app.report.financial_operations_reports') }}</h1>
        <p class="report-header__subtitle">{{ t('app.report.revenue_labour_cost_net_profit_and') }}</p>
      </div>

      <div class="report-header__actions">
        <button type="button" class="action-btn action-btn--secondary" :title="t('app.report.print_statement')" @click="printReport">
          <NavIcon name="Printer" />
          <span>{{ t('app.report.print_statement') }}</span>
        </button>
        <button
          type="button"
          class="action-btn action-btn--primary"
          :disabled="exportingType === 'summary'"
          @click="createExport('summary')"
        >
          <NavIcon name="Download" />
          <span>{{ exportingType === 'summary' ? t('app.common.loading') : t('app.report.export_summary_csv') }}</span>
        </button>
        <button type="button" class="action-btn action-btn--icon" :class="{ 'is-spinning': loading }" :title="t('app.report.refresh')" @click="load">
          <NavIcon name="Refresh" />
        </button>
      </div>
    </header>

    <!-- INTERACTIVE DATE CONTROLS & TIMEFRAME PRESETS -->
    <section class="filter-card no-print">
      <div class="filter-card__presets">
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === 'today' }"
          @click="preset('today')"
        >
          {{ t('app.common.today') }}
        </button>
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === '7d' }"
          @click="preset('7d')"
        >
          {{ t('app.report.last_7_days') }}
        </button>
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === 'this_month' }"
          @click="preset('this_month')"
        >
          {{ t('app.report.this_month') }}
        </button>
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === 'last_month' }"
          @click="preset('last_month')"
        >
          {{ t('app.report.last_month') }}
        </button>
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === '30d' }"
          @click="preset('30d')"
        >
          {{ t('app.report.last_30_days') }}
        </button>
        <button
          type="button"
          class="preset-pill"
          :class="{ 'is-active': activePreset === 'this_year' }"
          @click="preset('this_year')"
        >
          {{ t('app.report.this_year') }}
        </button>
      </div>

      <form class="filter-card__inputs" @submit.prevent="load">
        <div class="date-field">
          <label for="report-from-input">{{ t('app.report.from') }}</label>
          <div class="input-with-icon">
            <NavIcon name="Calendar" />
            <input id="report-from-input" v-model="from" type="date" @change="onCustomDateChange" />
          </div>
        </div>

        <div class="date-field">
          <label for="report-to-input">{{ t('app.report.to') }}</label>
          <div class="input-with-icon">
            <NavIcon name="Calendar" />
            <input id="report-to-input" v-model="to" type="date" @change="onCustomDateChange" />
          </div>
        </div>

        <button type="submit" class="run-btn" :disabled="loading">
          <NavIcon name="Check" />
          <span>{{ loading ? t('app.report.calculating') : t('app.report.run_report') }}</span>
        </button>
      </form>
    </section>

    <!-- NAVIGATION TABS -->
    <nav class="report-tabs no-print">
      <button
        type="button"
        class="tab-btn"
        :class="{ 'is-active': activeTab === 'overview' }"
        @click="activeTab = 'overview'"
      >
        <NavIcon name="Dashboard" />
        <span>{{ t('app.report.tab_overview') }}</span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ 'is-active': activeTab === 'wages' }"
        @click="activeTab = 'wages'"
      >
        <NavIcon name="Karigars" />
        <span>{{ t('app.report.tab_wages') }}</span>
        <span v-if="report && report.wages_unpaid_minor > 0" class="tab-badge tab-badge--danger">
          {{ money(report.wages_unpaid_minor) }}
        </span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ 'is-active': activeTab === 'stock' }"
        @click="activeTab = 'stock'"
      >
        <NavIcon name="Package" />
        <span>{{ t('app.report.tab_stock') }}</span>
        <span v-if="report?.low_stock?.length" class="tab-badge tab-badge--warning">
          {{ report.low_stock.length }}
        </span>
      </button>

      <button
        type="button"
        class="tab-btn"
        :class="{ 'is-active': activeTab === 'exports' }"
        @click="activeTab = 'exports'"
      >
        <NavIcon name="Download" />
        <span>{{ t('app.report.tab_exports') }}</span>
        <span v-if="exports.length" class="tab-badge tab-badge--neutral">
          {{ exports.length }}
        </span>
      </button>
    </nav>

    <!-- LOADING SKELETON -->
    <div v-if="loading && !report" class="skeleton-grid no-print">
      <div v-for="i in 4" :key="i" class="skeleton-card"></div>
    </div>

    <!-- MAIN REPORT VIEWS -->
    <div v-else-if="report" class="report-content">
      <!-- TAB 1: EXECUTIVE OVERVIEW -->
      <section v-show="activeTab === 'overview'" class="tab-pane">
        <!-- TOP ROW KPI CARDS -->
        <div class="kpi-grid">
          <!-- CROWNING CARD: ESTIMATED NET PROFIT -->
          <article class="kpi-card kpi-card--hero">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.estimated_net_profit') }}</span>
              <span v-if="grossIncome > 0" class="kpi-pill kpi-pill--profit">
                {{ profitMargin >= 0 ? `+${profitMargin}%` : `${profitMargin}%` }} {{ t('app.report.profit_margin') }}
              </span>
            </div>
            <div class="kpi-card__value kpi-card__value--profit">
              {{ money(netProfit) }}
            </div>
            <p class="kpi-card__meta">{{ t('app.report.revenue_minus_costs_and_wages') }}</p>
          </article>

          <!-- GROSS REVENUE -->
          <article class="kpi-card">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.gross_revenue') }}</span>
              <span class="kpi-icon-wrap"><NavIcon name="Cash" /></span>
            </div>
            <div class="kpi-card__value">
              {{ money(grossIncome) }}
            </div>
            <p class="kpi-card__meta">
              {{ t('app.report.p0_orders_booked', { p0: report.orders.count }) }} · {{ t('app.report.p0_pos_invoices', { p0: report.sales.count }) }}
            </p>
          </article>

          <!-- KARIGAR LABOR COSTS -->
          <article class="kpi-card">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.making_cost') }}</span>
              <span class="kpi-icon-wrap"><NavIcon name="Karigars" /></span>
            </div>
            <div class="kpi-card__value">
              {{ money(laborCost) }}
            </div>
            <p class="kpi-card__meta">
              <span v-if="report.wages_unpaid_minor > 0" class="text-danger font-medium">
                {{ money(report.wages_unpaid_minor) }} {{ t('app.report.unpaid_karigar_wages') }}
              </span>
              <span v-else>{{ t('app.report.karigar_production_cost') }}</span>
            </p>
          </article>

          <!-- OVERHEAD EXPENSES -->
          <article class="kpi-card">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.expenses') }}</span>
              <span class="kpi-icon-wrap"><NavIcon name="Coins" /></span>
            </div>
            <div class="kpi-card__value">
              {{ money(report.expenses_minor) }}
            </div>
            <p class="kpi-card__meta">{{ t('app.report.overheads_and_supplies') }}</p>
          </article>

          <!-- CUSTOMER DUES -->
          <article class="kpi-card">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.customer_dues') }}</span>
              <span class="kpi-pill kpi-pill--warning">
                {{ collectionRate }}% {{ t('app.report.collection_rate') }}
              </span>
            </div>
            <div class="kpi-card__value text-warning">
              {{ money(report.decision.receivables_minor) }}
            </div>
            <p class="kpi-card__meta">{{ t('app.report.total_unpaid_by_customers') }}</p>
          </article>

          <!-- WORKSHOP RISK ALERTS -->
          <article class="kpi-card">
            <div class="kpi-card__header">
              <span class="kpi-label">{{ t('app.report.overdue_deliveries') }}</span>
              <span
                class="kpi-pill"
                :class="report.decision.overdue_orders > 0 ? 'kpi-pill--danger' : 'kpi-pill--success'"
              >
                {{ report.decision.overdue_orders > 0 ? t('app.report.orders_past_their_promised_date') : t('app.report.all_on_schedule') }}
              </span>
            </div>
            <div
              class="kpi-card__value"
              :class="report.decision.overdue_orders > 0 ? 'text-danger' : 'text-success'"
            >
              {{ report.decision.overdue_orders }}
            </div>
            <p class="kpi-card__meta">{{ t('app.report.orders_past_their_promised_date') }}</p>
          </article>
        </div>

        <!-- REVENUE & COST BREAKDOWN VISUALIZATION -->
        <div class="breakdown-section">
          <!-- SEGMENT PROGRESS BAR -->
          <div class="segment-card">
            <div class="segment-card__header">
              <div>
                <h2 class="card-heading">{{ t('app.report.cost_distribution') }}</h2>
                <p class="card-caption">{{ t('app.report.revenue_labour_cost_net_profit_and') }}</p>
              </div>
              <div class="segment-total">
                <span class="segment-total__label">{{ t('app.report.gross_revenue') }}</span>
                <span class="segment-total__num">{{ money(grossIncome) }}</span>
              </div>
            </div>

            <!-- MULTI-SEGMENT PROPORTION BAR -->
            <div class="stacked-bar">
              <div
                class="stacked-bar__segment stacked-bar__segment--profit"
                :style="{ width: `${profitPercent}%` }"
                :title="`${t('app.report.net_margin_share')}: ${profitPercent}%`"
              ></div>
              <div
                class="stacked-bar__segment stacked-bar__segment--labor"
                :style="{ width: `${laborPercent}%` }"
                :title="`${t('app.report.labor_share')}: ${laborPercent}%`"
              ></div>
              <div
                class="stacked-bar__segment stacked-bar__segment--overhead"
                :style="{ width: `${overheadPercent}%` }"
                :title="`${t('app.report.overhead_share')}: ${overheadPercent}%`"
              ></div>
            </div>

            <!-- LEGEND METRICS -->
            <div class="stacked-legend">
              <div class="legend-item">
                <span class="legend-dot legend-dot--profit"></span>
                <div class="legend-text">
                  <strong>{{ t('app.report.net_margin_share') }}</strong>
                  <span class="legend-val">{{ money(netProfit) }} ({{ profitPercent }}%)</span>
                </div>
              </div>

              <div class="legend-item">
                <span class="legend-dot legend-dot--labor"></span>
                <div class="legend-text">
                  <strong>{{ t('app.report.labor_share') }}</strong>
                  <span class="legend-val">{{ money(laborCost) }} ({{ laborPercent }}%)</span>
                </div>
              </div>

              <div class="legend-item">
                <span class="legend-dot legend-dot--overhead"></span>
                <div class="legend-text">
                  <strong>{{ t('app.report.overhead_share') }}</strong>
                  <span class="legend-val">{{ money(overheadExpenses) }} ({{ overheadPercent }}%)</span>
                </div>
              </div>
            </div>
          </div>

          <!-- DUAL STREAM BREAKDOWN (BESPOKE VS POS FABRICS) -->
          <div class="stream-grid">
            <div class="stream-card">
              <div class="stream-card__top">
                <div class="stream-icon stream-icon--bespoke"><NavIcon name="Orders" /></div>
                <div>
                  <h3 class="stream-title">{{ t('app.report.bespoke_orders') }}</h3>
                  <p class="stream-count">{{ t('app.report.p0_orders_booked', { p0: report.orders.count }) }}</p>
                </div>
              </div>
              <div class="stream-card__bottom">
                <div class="stream-stat">
                  <span class="stream-stat__label">{{ t('app.report.order_revenue') }}</span>
                  <span class="stream-stat__value">{{ money(report.orders.value_minor) }}</span>
                </div>
                <div class="stream-stat">
                  <span class="stream-stat__label">{{ t('app.report.cash_collected') }}</span>
                  <span class="stream-stat__value text-success">{{ money(report.orders.paid_minor) }}</span>
                </div>
              </div>
            </div>

            <div class="stream-card">
              <div class="stream-card__top">
                <div class="stream-icon stream-icon--pos"><NavIcon name="Tag" /></div>
                <div>
                  <h3 class="stream-title">{{ t('app.report.pos_fabric') }}</h3>
                  <p class="stream-count">{{ t('app.report.p0_pos_invoices', { p0: report.sales.count }) }}</p>
                </div>
              </div>
              <div class="stream-card__bottom">
                <div class="stream-stat">
                  <span class="stream-stat__label">{{ t('app.report.fabric_sales') }}</span>
                  <span class="stream-stat__value">{{ money(report.sales.value_minor) }}</span>
                </div>
                <div class="stream-stat">
                  <span class="stream-stat__label">{{ t('app.report.cash_collected') }}</span>
                  <span class="stream-stat__value text-success">{{ money(report.sales.paid_minor) }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 2: KARIGAR WAGES & PRODUCTION LIABILITIES -->
      <section v-show="activeTab === 'wages'" class="tab-pane">
        <div class="detail-panel">
          <div class="detail-panel__header">
            <div>
              <h2 class="card-heading">{{ t('app.report.tab_wages') }}</h2>
              <p class="card-caption">{{ t('app.report.wages_card_desc') }}</p>
            </div>
            <button type="button" class="mini-outline-btn" @click="router.push('/karigars')">
              <NavIcon name="Users" />
              <span>{{ t('app.report.go_to_karigars') }}</span>
            </button>
          </div>

          <div class="wages-cards-row">
            <div class="wages-stat-card">
              <div class="wages-stat-card__tag">{{ t('app.report.making_cost') }}</div>
              <div class="wages-stat-card__val">{{ money(laborCost) }}</div>
              <div class="wages-stat-card__sub">{{ t('app.report.wages_card_desc') }}</div>
            </div>

            <div class="wages-stat-card wages-stat-card--alert">
              <div class="wages-stat-card__tag text-danger">{{ t('app.report.unpaid_karigar_wages') }}</div>
              <div class="wages-stat-card__val text-danger">{{ money(report.wages_unpaid_minor) }}</div>
              <div class="wages-stat-card__sub">{{ t('app.report.unpaid_wages_card_desc') }}</div>
            </div>
          </div>

          <div class="callout-box">
            <div class="callout-box__icon"><NavIcon name="Karigars" /></div>
            <div class="callout-box__body">
              <strong>{{ t('app.report.piece_rate_wages_owed') }}</strong>
              <p>{{ t('app.report.revenue_minus_costs_and_wages') }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- TAB 3: LOW STOCK & RESTOCK ALERT LIST -->
      <section v-show="activeTab === 'stock'" class="tab-pane">
        <div class="detail-panel">
          <div class="detail-panel__header">
            <div>
              <h2 class="card-heading">{{ t('app.report.low_stock_reorder_list') }}</h2>
              <p class="card-caption">
                {{ report.low_stock.length ? t('app.report.stock_alert_summary', { p0: report.low_stock.length }) : t('app.report.all_items_are_above_their_reorder') }}
              </p>
            </div>
            <button type="button" class="mini-outline-btn" @click="router.push('/inventory')">
              <NavIcon name="Inventory" />
              <span>{{ t('app.report.go_to_inventory') }}</span>
            </button>
          </div>

          <!-- SEARCH & STOCK FILTER CONTROLS -->
          <div v-if="report.low_stock.length" class="stock-search-bar">
            <div class="search-input-box">
              <NavIcon name="Search" />
              <input
                v-model="stockQuery"
                type="text"
                :placeholder="t('app.report.search_low_stock')"
              />
            </div>

            <div class="stock-filter-pills">
              <button
                type="button"
                class="filter-pill"
                :class="{ 'is-active': stockFilter === 'all' }"
                @click="stockFilter = 'all'"
              >
                {{ t('app.report.filter_all') }} ({{ report.low_stock.length }})
              </button>
              <button
                type="button"
                class="filter-pill filter-pill--danger"
                :class="{ 'is-active': stockFilter === 'critical' }"
                @click="stockFilter = 'critical'"
              >
                {{ t('app.report.filter_critical') }} ({{ criticalStockCount }})
              </button>
            </div>
          </div>

          <!-- EMPTY STATE WHEN ALL ITEMS ARE HEALTHY -->
          <div v-if="!report.low_stock.length" class="stock-empty-state">
            <div class="stock-empty-icon"><NavIcon name="Check" /></div>
            <h3>{{ t('app.report.stock_levels_healthy') }}</h3>
            <p>{{ t('app.report.all_items_are_above_their_reorder') }}</p>
          </div>

          <!-- LIST OF LOW STOCK ITEMS -->
          <div v-else-if="filteredLowStock.length" class="stock-table-wrap">
            <table class="atelier-table">
              <thead>
                <tr>
                  <th>{{ t('app.common.name') }}</th>
                  <th>{{ t('app.report.sku') }}</th>
                  <th>{{ t('app.report.current_balance') }}</th>
                  <th>{{ t('app.report.reorder_threshold') }}</th>
                  <th>{{ t('app.report.restock_needed') }}</th>
                  <th>{{ t('app.common.status') }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="item in filteredLowStock" :key="item.id" class="stock-row">
                  <td class="stock-name-cell">
                    <strong>{{ item.name }}</strong>
                  </td>
                  <td>
                    <span class="sku-tag">{{ item.sku || '—' }}</span>
                  </td>
                  <td>
                    <span class="balance-tag" :class="Number(item.balance) <= 0 ? 'balance-tag--zero' : 'balance-tag--low'">
                      {{ item.balance || 0 }} {{ item.unit }}
                    </span>
                  </td>
                  <td class="text-muted">
                    {{ item.reorder_level }} {{ item.unit }}
                  </td>
                  <td>
                    <strong class="text-danger">
                      +{{ Math.max(0, Number(item.reorder_level) - Number(item.balance || 0)) }} {{ item.unit }}
                    </strong>
                  </td>
                  <td>
                    <span
                      class="stock-status-chip"
                      :class="Number(item.balance) <= 0 ? 'stock-status-chip--critical' : 'stock-status-chip--warning'"
                    >
                      {{ Number(item.balance) <= 0 ? t('app.report.filter_critical') : t('app.report.low_stock') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-else class="stock-empty-state">
            <p class="text-muted">{{ t('app.common.no_results') }}</p>
          </div>
        </div>
      </section>

      <!-- TAB 4: EXPORT & DATA BACKUP CENTER -->
      <section v-show="activeTab === 'exports'" class="tab-pane">
        <div class="exports-grid">
          <!-- CREATE EXPORT FORM CARD -->
          <div class="detail-panel">
            <div class="detail-panel__header">
              <div>
                <h2 class="card-heading">{{ t('app.report.export_reports') }}</h2>
                <p class="card-caption">{{ t('app.report.revenue_labour_cost_net_profit_and') }}</p>
              </div>
            </div>

            <div class="export-options">
              <!-- CSV SUMMARY OPTION -->
              <div class="export-option-card">
                <div class="export-option-card__header">
                  <span class="format-badge format-badge--csv">CSV</span>
                  <div>
                    <strong>{{ t('app.report.export_summary_csv') }}</strong>
                    <p>{{ t('app.report.export_summary_desc') }}</p>
                  </div>
                </div>
                <button
                  type="button"
                  class="action-btn action-btn--primary"
                  :disabled="exportingType === 'summary'"
                  @click="createExport('summary')"
                >
                  <NavIcon name="Download" />
                  <span>{{ exportingType === 'summary' ? t('app.common.loading') : t('app.report.export_summary_csv') }}</span>
                </button>
              </div>

              <!-- FULL BACKUP JSON OPTION -->
              <div class="export-option-card">
                <div class="export-option-card__header">
                  <span class="format-badge format-badge--json">JSON</span>
                  <div>
                    <strong>{{ t('app.report.export_full_backup_json') }}</strong>
                    <p>{{ t('app.report.export_backup_desc') }}</p>
                  </div>
                </div>
                <button
                  type="button"
                  class="action-btn action-btn--secondary"
                  :disabled="exportingType === 'tenant_data'"
                  @click="createExport('tenant_data')"
                >
                  <NavIcon name="Download" />
                  <span>{{ exportingType === 'tenant_data' ? t('app.common.loading') : t('app.report.export_full_backup_json') }}</span>
                </button>
              </div>
            </div>

            <!-- OPTIONAL EMAIL NOTIFICATION -->
            <div class="email-delivery-box">
              <label for="export-email-input">{{ t('app.report.send_to_email_optional') }}</label>
              <div class="input-with-icon">
                <NavIcon name="User" />
                <input
                  id="export-email-input"
                  v-model="exportEmail"
                  type="email"
                  placeholder="owner@tailors.com"
                />
              </div>
            </div>
          </div>

          <!-- EXPORT HISTORY CARD -->
          <div class="detail-panel">
            <div class="detail-panel__header">
              <div>
                <h2 class="card-heading">{{ t('app.report.export_history') }}</h2>
                <p class="card-caption">{{ exports.length }} {{ t('app.report.export_history').toLowerCase() }}</p>
              </div>
              <button type="button" class="mini-outline-btn" :class="{ 'is-spinning': loading }" @click="load">
                <NavIcon name="Refresh" />
              </button>
            </div>

            <p v-if="!exports.length" class="stock-empty-state">{{ t('app.report.no_exports_yet') }}</p>

            <div v-else class="export-history-cards">
              <article v-for="item in exports" :key="item.id" class="history-item">
                <div class="history-item__left">
                  <div class="history-item__title">
                    <span class="history-format-tag">{{ item.type.toUpperCase() }}</span>
                    <span class="history-item__date">{{ formatDateTime(item.created_at || item.completed_at) }}</span>
                  </div>
                  <small v-if="item.email" class="history-item__email">{{ item.email }}</small>
                  <small v-if="item.failure_message" class="text-danger">{{ item.failure_message }}</small>
                </div>

                <div class="history-item__right">
                  <span
                    class="status-chip"
                    :class="item.status === 'completed' ? 'status-chip--ready' : item.status === 'failed' ? 'status-chip--danger' : 'status-chip--pending'"
                  >
                    {{ item.status }}
                  </span>
                  <button
                    v-if="item.status === 'completed'"
                    type="button"
                    class="download-btn"
                    :title="t('app.report.download')"
                    @click="download(item)"
                  >
                    <NavIcon name="Download" />
                    <span>{{ t('app.report.download') }}</span>
                  </button>
                </div>
              </article>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- PRINT ONLY ATELIER STATEMENT LAYOUT -->
    <div v-if="report" class="print-statement print-only">
      <div class="print-header">
        <div class="print-brand">
          <h2>{{ api.tenantName.value || 'Dorjidokan Atelier' }}</h2>
          <p>{{ t('app.report.financial_operations_reports') }}</p>
        </div>
        <div class="print-meta">
          <div><strong>{{ t('app.report.workshop_reports') }}</strong>: {{ formattedPeriod }}</div>
          <div>{{ t('app.report.print_date_generated', { p0: generatedAtTime }) }}</div>
        </div>
      </div>

      <div class="print-divider"></div>

      <!-- PRINT SUMMARY TABLE -->
      <table class="print-table">
        <thead>
          <tr>
            <th>{{ t('app.report.tab_overview') }}</th>
            <th class="text-right">{{ t('app.common.amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>{{ t('app.report.order_revenue') }} ({{ report.orders.count }} {{ t('app.common.orders') }})</td>
            <td class="text-right">{{ money(report.orders.value_minor) }}</td>
          </tr>
          <tr>
            <td>{{ t('app.report.fabric_sales') }} ({{ report.sales.count }} {{ t('app.report.pos_fabric') }})</td>
            <td class="text-right">{{ money(report.sales.value_minor) }}</td>
          </tr>
          <tr class="print-row-bold">
            <td>{{ t('app.report.gross_revenue') }}</td>
            <td class="text-right">{{ money(grossIncome) }}</td>
          </tr>
          <tr>
            <td>{{ t('app.report.making_cost') }} ({{ t('app.report.labor_share') }})</td>
            <td class="text-right">-{{ money(laborCost) }}</td>
          </tr>
          <tr>
            <td>{{ t('app.report.expenses') }} ({{ t('app.report.overheads_and_supplies') }})</td>
            <td class="text-right">-{{ money(report.expenses_minor) }}</td>
          </tr>
          <tr class="print-row-highlight">
            <td><strong>{{ t('app.report.estimated_net_profit') }}</strong></td>
            <td class="text-right"><strong>{{ money(netProfit) }} ({{ profitMargin }}%)</strong></td>
          </tr>
          <tr>
            <td>{{ t('app.report.customer_dues') }} ({{ t('app.report.total_unpaid_by_customers') }})</td>
            <td class="text-right">{{ money(report.decision.receivables_minor) }}</td>
          </tr>
          <tr>
            <td>{{ t('app.report.unpaid_karigar_wages') }}</td>
            <td class="text-right">{{ money(report.wages_unpaid_minor) }}</td>
          </tr>
        </tbody>
      </table>

      <!-- PRINT LOW STOCK IF ANY -->
      <div v-if="report.low_stock.length" class="print-stock-section">
        <h3>{{ t('app.report.low_stock_reorder_list') }}</h3>
        <table class="print-table">
          <thead>
            <tr>
              <th>{{ t('app.common.name') }}</th>
              <th>{{ t('app.report.sku') }}</th>
              <th>{{ t('app.report.current_balance') }}</th>
              <th>{{ t('app.report.reorder_threshold') }}</th>
              <th>{{ t('app.report.restock_needed') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in report.low_stock" :key="item.id">
              <td>{{ item.name }}</td>
              <td>{{ item.sku || '—' }}</td>
              <td>{{ item.balance || 0 }} {{ item.unit }}</td>
              <td>{{ item.reorder_level }} {{ item.unit }}</td>
              <td>+{{ Math.max(0, Number(item.reorder_level) - Number(item.balance || 0)) }} {{ item.unit }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="print-signatures">
        <div class="sig-line">
          <div class="line"></div>
          <span>Prepared by</span>
        </div>
        <div class="sig-line">
          <div class="line"></div>
          <span>Authorized by</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.report-workspace-root {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  width: 100%;
}

/* ==========================================================================
   ATELIER EXECUTIVE HEADER
   ========================================================================== */
.report-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.5rem;
  padding: 1.65rem 1.75rem;
  background: linear-gradient(135deg, var(--surface) 0%, var(--primary-soft) 100%);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-sm);
  flex-wrap: wrap;
}

.report-header__brand {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
}

.report-header__tag {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.25rem 0.65rem;
  border-radius: var(--radius-pill);
  font-size: 0.72rem;
  font-weight: 700;
  width: fit-content;
}

.pulse-indicator {
  width: 8px;
  height: 8px;
  background-color: var(--primary);
  border-radius: 50%;
  box-shadow: 0 0 0 2px var(--primary-glow);
  animation: pulse-glow 2s infinite ease-in-out;
}

@keyframes pulse-glow {
  0%, 100% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.25); opacity: 0.6; }
}

.tag-title {
  color: var(--primary);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.tag-period {
  color: var(--muted);
  font-weight: 500;
}

.report-header__heading {
  margin: 0;
  font-family: var(--serif);
  font-size: 1.65rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.2;
}

.report-header__subtitle {
  margin: 0;
  color: var(--muted);
  font-size: 0.9rem;
  max-width: 40rem;
}

.report-header__actions {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  flex-wrap: wrap;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.6rem 1.05rem;
  font: inherit;
  font-size: 0.86rem;
  font-weight: 600;
  border-radius: var(--radius);
  cursor: pointer;
  transition: all 180ms ease-out;
  border: 1px solid transparent;
}

.action-btn--primary {
  background: var(--primary);
  color: #fff;
  border-color: var(--primary);
  box-shadow: 0 2px 8px var(--primary-glow);
}

.action-btn--primary:hover:not(:disabled) {
  background: var(--primary-hover);
  transform: translateY(-1px);
}

.action-btn--secondary {
  background: var(--surface);
  color: var(--ink);
  border-color: var(--line);
}

.action-btn--secondary:hover:not(:disabled) {
  background: var(--primary-soft);
  border-color: var(--primary);
  color: var(--primary);
}

.action-btn--icon {
  background: var(--surface);
  color: var(--ink);
  border-color: var(--line);
  padding: 0.6rem;
}

.action-btn--icon:hover {
  background: var(--primary-soft);
  color: var(--primary);
  border-color: var(--primary);
}

.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.is-spinning {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ==========================================================================
   TIMEFRAME FILTER BAR & PRESETS
   ========================================================================== */
.filter-card {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  padding: 1.15rem 1.35rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
}

.filter-card__presets {
  display: flex;
  gap: 0.5rem;
  flex-wrap: wrap;
  align-items: center;
}

.preset-pill {
  border: 1px solid var(--line);
  background: var(--paper);
  color: var(--ink);
  padding: 0.4rem 0.85rem;
  border-radius: var(--radius-pill);
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.preset-pill:hover {
  background: var(--primary-soft);
  border-color: var(--primary);
  color: var(--primary);
}

.preset-pill.is-active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
  box-shadow: 0 2px 6px var(--primary-glow);
}

.filter-card__inputs {
  display: flex;
  gap: 1rem;
  align-items: flex-end;
  flex-wrap: wrap;
}

.date-field {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  min-width: 10.5rem;
  flex: 1;
}

.date-field label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.input-with-icon {
  display: flex;
  align-items: center;
  position: relative;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  transition: border-color 150ms ease, box-shadow 150ms ease;
}

.input-with-icon :deep(svg) {
  position: absolute;
  left: 0.75rem;
  color: var(--muted);
  pointer-events: none;
}

.input-with-icon input {
  width: 100%;
  border: none;
  background: transparent;
  padding: 0.55rem 0.75rem 0.55rem 2.35rem;
  font: inherit;
  font-size: 0.88rem;
  color: var(--ink);
  outline: none;
}

.input-with-icon:focus-within {
  border-color: var(--primary);
  box-shadow: var(--focus-ring);
}

.run-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  padding: 0.6rem 1.25rem;
  background: var(--primary);
  color: #fff;
  border: 1px solid var(--primary);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 180ms ease;
  height: 2.5rem;
}

.run-btn:hover:not(:disabled) {
  background: var(--primary-hover);
}

.run-btn:disabled {
  opacity: 0.6;
}

/* ==========================================================================
   NAVIGATION TABS
   ========================================================================== */
.report-tabs {
  display: flex;
  gap: 0.5rem;
  border-bottom: 2px solid var(--line);
  padding-bottom: 0.25rem;
  overflow-x: auto;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  padding: 0.65rem 1.15rem;
  border: none;
  background: transparent;
  color: var(--muted);
  font: inherit;
  font-size: 0.88rem;
  font-weight: 600;
  cursor: pointer;
  border-radius: var(--radius-sm) var(--radius-sm) 0 0;
  position: relative;
  transition: color 150ms ease, background 150ms ease;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--primary-soft);
}

.tab-btn.is-active {
  color: var(--primary);
  background: var(--primary-soft);
}

.tab-btn.is-active::after {
  content: '';
  position: absolute;
  bottom: -0.25rem;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--primary);
  border-radius: 3px 3px 0 0;
}

.tab-badge {
  padding: 0.1rem 0.45rem;
  border-radius: var(--radius-pill);
  font-size: 0.72rem;
  font-weight: 700;
}

.tab-badge--neutral {
  background: var(--paper);
  color: var(--ink);
  border: 1px solid var(--line);
}

.tab-badge--warning {
  background: var(--status-inprogress-soft);
  color: var(--status-inprogress);
}

.tab-badge--danger {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
}

/* ==========================================================================
   SKELETON LOADER
   ========================================================================== */
.skeleton-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: 1.25rem;
}

.skeleton-card {
  height: 9rem;
  background: linear-gradient(90deg, var(--paper) 25%, var(--line) 50%, var(--paper) 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.5s infinite;
  border-radius: var(--radius);
}

@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ==========================================================================
   EXECUTIVE KPI GRID
   ========================================================================== */
.kpi-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr));
  gap: 1.25rem;
  margin-bottom: 1.75rem;
}

.kpi-card {
  display: flex;
  flex-direction: column;
  gap: 0.45rem;
  padding: 1.35rem 1.5rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  transition: transform 180ms ease, box-shadow 180ms ease;
}

.kpi-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.kpi-card--hero {
  border-color: var(--accent);
  background: linear-gradient(135deg, var(--surface) 0%, var(--accent-soft) 100%);
  position: relative;
  overflow: hidden;
}

.kpi-card--hero::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: var(--accent);
}

.kpi-card__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.kpi-label {
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--muted);
  letter-spacing: 0.04em;
  text-transform: uppercase;
}

.kpi-icon-wrap {
  color: var(--muted);
  opacity: 0.8;
}

.kpi-pill {
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-pill);
  font-size: 0.72rem;
  font-weight: 700;
}

.kpi-pill--profit {
  background: var(--status-ready-soft);
  color: var(--status-ready);
}

.kpi-pill--warning {
  background: var(--status-inprogress-soft);
  color: var(--status-inprogress);
}

.kpi-pill--danger {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
}

.kpi-pill--success {
  background: var(--status-ready-soft);
  color: var(--status-ready);
}

.kpi-card__value {
  font-family: var(--serif);
  font-size: 1.85rem;
  font-weight: 700;
  color: var(--ink);
  line-height: 1.2;
}

.kpi-card__value--profit {
  color: var(--status-ready);
}

.kpi-card__meta {
  margin: 0;
  font-size: 0.82rem;
  color: var(--muted);
}

/* ==========================================================================
   REVENUE & COST BREAKDOWN VISUALIZATION
   ========================================================================== */
.breakdown-section {
  display: grid;
  grid-template-columns: 1.4fr 1fr;
  gap: 1.5rem;
}

@media (width <= 64rem) {
  .breakdown-section {
    grid-template-columns: 1fr;
  }
}

.segment-card {
  padding: 1.5rem 1.65rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.segment-card__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
}

.card-heading {
  margin: 0;
  font-family: var(--serif);
  font-size: 1.2rem;
  font-weight: 700;
  color: var(--ink);
}

.card-caption {
  margin: 0.25rem 0 0;
  font-size: 0.84rem;
  color: var(--muted);
}

.segment-total {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}

.segment-total__label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
}

.segment-total__num {
  font-family: var(--serif);
  font-size: 1.35rem;
  font-weight: 700;
  color: var(--ink);
}

.stacked-bar {
  display: flex;
  height: 1.25rem;
  background: var(--paper);
  border-radius: var(--radius-pill);
  overflow: hidden;
  box-shadow: inset 0 1px 3px rgb(0 0 0 / 8%);
}

.stacked-bar__segment {
  height: 100%;
  transition: width 400ms ease;
}

.stacked-bar__segment--profit { background: var(--status-ready); }
.stacked-bar__segment--labor { background: var(--accent); }
.stacked-bar__segment--overhead { background: var(--status-cancelled); }

.stacked-legend {
  display: flex;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.legend-item {
  display: flex;
  align-items: flex-start;
  gap: 0.5rem;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  margin-top: 0.3rem;
  flex-shrink: 0;
}

.legend-dot--profit { background: var(--status-ready); }
.legend-dot--labor { background: var(--accent); }
.legend-dot--overhead { background: var(--status-cancelled); }

.legend-text {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.legend-text strong {
  font-size: 0.82rem;
  color: var(--ink);
}

.legend-val {
  font-size: 0.8rem;
  color: var(--muted);
}

.stream-grid {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.stream-card {
  padding: 1.25rem 1.35rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.stream-card__top {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.stream-icon {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stream-icon--bespoke {
  background: var(--primary-soft);
  color: var(--primary);
}

.stream-icon--pos {
  background: var(--accent-soft);
  color: var(--accent);
}

.stream-title {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--ink);
}

.stream-count {
  margin: 0;
  font-size: 0.8rem;
  color: var(--muted);
}

.stream-card__bottom {
  display: flex;
  justify-content: space-between;
  padding-top: 0.75rem;
  border-top: 1px solid var(--line);
}

.stream-stat {
  display: flex;
  flex-direction: column;
}

.stream-stat__label {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
}

.stream-stat__value {
  font-family: var(--serif);
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--ink);
}

/* ==========================================================================
   DETAIL PANELS (TABS 2, 3, 4)
   ========================================================================== */
.detail-panel {
  padding: 1.5rem 1.65rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  box-shadow: var(--shadow-xs);
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.detail-panel__header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1rem;
  flex-wrap: wrap;
}

.mini-outline-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.45rem 0.85rem;
  background: var(--paper);
  border: 1px solid var(--line);
  color: var(--ink);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.82rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.mini-outline-btn:hover {
  background: var(--primary-soft);
  border-color: var(--primary);
  color: var(--primary);
}

.wages-cards-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(16rem, 1fr));
  gap: 1.25rem;
}

.wages-stat-card {
  padding: 1.25rem 1.35rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.wages-stat-card--alert {
  border-color: var(--status-cancelled);
  background: var(--status-cancelled-soft);
}

.wages-stat-card__tag {
  font-size: 0.76rem;
  font-weight: 700;
  text-transform: uppercase;
}

.wages-stat-card__val {
  font-family: var(--serif);
  font-size: 1.75rem;
  font-weight: 700;
  color: var(--ink);
}

.wages-stat-card__sub {
  font-size: 0.8rem;
  color: var(--muted);
}

.callout-box {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 1rem 1.25rem;
  background: var(--primary-soft);
  border-left: 4px solid var(--primary);
  border-radius: var(--radius-sm);
}

.callout-box__icon {
  color: var(--primary);
  margin-top: 0.2rem;
}

.callout-box__body p {
  margin: 0.25rem 0 0;
  font-size: 0.85rem;
  color: var(--muted);
}

/* ==========================================================================
   LOW STOCK ALERT CENTER
   ========================================================================== */
.stock-search-bar {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-input-box {
  display: flex;
  align-items: center;
  position: relative;
  flex: 1;
  min-width: 14rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
}

.search-input-box :deep(svg) {
  position: absolute;
  left: 0.75rem;
  color: var(--muted);
}

.search-input-box input {
  width: 100%;
  border: none;
  background: transparent;
  padding: 0.55rem 0.75rem 0.55rem 2.25rem;
  font: inherit;
  font-size: 0.88rem;
  color: var(--ink);
  outline: none;
}

.stock-filter-pills {
  display: flex;
  gap: 0.4rem;
}

.filter-pill {
  padding: 0.4rem 0.75rem;
  border-radius: var(--radius-pill);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink);
  cursor: pointer;
  transition: all 150ms ease;
}

.filter-pill:hover {
  background: var(--primary-soft);
  border-color: var(--primary);
}

.filter-pill.is-active {
  background: var(--primary);
  border-color: var(--primary);
  color: #fff;
}

.filter-pill--danger.is-active {
  background: var(--status-cancelled);
  border-color: var(--status-cancelled);
  color: #fff;
}

.stock-empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1.5rem;
  text-align: center;
  color: var(--muted);
  gap: 0.5rem;
}

.stock-empty-icon {
  width: 3rem;
  height: 3rem;
  border-radius: 50%;
  background: var(--status-ready-soft);
  color: var(--status-ready);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.5rem;
}

.stock-empty-state h3 {
  margin: 0;
  color: var(--ink);
  font-size: 1.15rem;
}

.stock-table-wrap {
  overflow-x: auto;
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
}

.atelier-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
}

.atelier-table th {
  background: var(--paper);
  padding: 0.75rem 1rem;
  font-size: 0.76rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  border-bottom: 1px solid var(--line);
}

.atelier-table td {
  padding: 0.85rem 1rem;
  font-size: 0.88rem;
  border-bottom: 1px solid var(--line);
  color: var(--ink);
}

.stock-row:last-child td {
  border-bottom: none;
}

.stock-row:hover td {
  background: var(--primary-soft);
}

.sku-tag {
  display: inline-block;
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-xs);
  background: var(--paper);
  border: 1px solid var(--line);
  font-size: 0.78rem;
  font-family: monospace;
}

.balance-tag {
  font-weight: 700;
}

.balance-tag--zero {
  color: var(--status-cancelled);
}

.balance-tag--low {
  color: var(--status-inprogress);
}

.stock-status-chip {
  display: inline-flex;
  align-items: center;
  padding: 0.15rem 0.6rem;
  border-radius: var(--radius-pill);
  font-size: 0.75rem;
  font-weight: 700;
}

.stock-status-chip--critical {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
}

.stock-status-chip--warning {
  background: var(--status-inprogress-soft);
  color: var(--status-inprogress);
}

/* ==========================================================================
   EXPORTS & BACKUPS (TAB 4)
   ========================================================================== */
.exports-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1.5rem;
}

@media (width <= 64rem) {
  .exports-grid {
    grid-template-columns: 1fr;
  }
}

.export-options {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.export-option-card {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 1.15rem 1.25rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  flex-wrap: wrap;
}

.export-option-card__header {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  flex: 1;
}

.export-option-card__header p {
  margin: 0.2rem 0 0;
  font-size: 0.82rem;
  color: var(--muted);
}

.format-badge {
  padding: 0.2rem 0.5rem;
  border-radius: var(--radius-xs);
  font-size: 0.75rem;
  font-weight: 800;
  letter-spacing: 0.05em;
}

.format-badge--csv {
  background: var(--primary-soft);
  color: var(--primary);
  border: 1px solid var(--primary);
}

.format-badge--json {
  background: var(--accent-soft);
  color: var(--accent);
  border: 1px solid var(--accent);
}

.email-delivery-box {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-top: 0.5rem;
  padding-top: 1rem;
  border-top: 1px solid var(--line);
}

.email-delivery-box label {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted);
}

.export-history-cards {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.85rem 1.1rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  gap: 1rem;
}

.history-item__left {
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}

.history-item__title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.history-format-tag {
  font-size: 0.72rem;
  font-weight: 800;
  padding: 0.1rem 0.4rem;
  border-radius: var(--radius-xs);
  background: var(--surface);
  border: 1px solid var(--line);
}

.history-item__date {
  font-size: 0.82rem;
  color: var(--muted);
}

.history-item__email {
  color: var(--ink);
  font-size: 0.8rem;
}

.history-item__right {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.status-chip {
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-pill);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: capitalize;
}

.status-chip--ready {
  background: var(--status-ready-soft);
  color: var(--status-ready);
}

.status-chip--pending {
  background: var(--status-pending-soft);
  color: var(--status-pending);
}

.status-chip--danger {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
}

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.35rem 0.75rem;
  border: 1px solid var(--line);
  background: var(--surface);
  color: var(--ink);
  border-radius: var(--radius-sm);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.download-btn:hover {
  background: var(--primary-soft);
  border-color: var(--primary);
  color: var(--primary);
}

/* ==========================================================================
   UTILITY CLASSES
   ========================================================================== */
.text-success { color: var(--status-ready); }
.text-warning { color: var(--status-inprogress); }
.text-danger { color: var(--status-cancelled); }
.text-muted { color: var(--muted); }
.font-medium { font-weight: 600; }
.text-right { text-align: right; }

/* ==========================================================================
   PRINT STYLES (@media print)
   ========================================================================== */
.print-only {
  display: none !important;
}

@media print {
  .no-print {
    display: none !important;
  }

  .print-only {
    display: block !important;
  }

  .report-workspace-root {
    background: #fff !important;
    color: #000 !important;
    padding: 0 !important;
  }

  .print-statement {
    padding: 1.5cm;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #000;
  }

  .print-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
  }

  .print-brand h2 {
    margin: 0;
    font-size: 1.6rem;
  }

  .print-brand p {
    margin: 0.25rem 0 0;
    color: #555;
  }

  .print-meta {
    text-align: right;
    font-size: 0.85rem;
    color: #444;
  }

  .print-divider {
    border-top: 2px solid #000;
    margin-bottom: 1.5rem;
  }

  .print-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 2rem;
  }

  .print-table th,
  .print-table td {
    border-bottom: 1px solid #ddd;
    padding: 0.65rem 0.5rem;
    font-size: 0.9rem;
  }

  .print-table th {
    background: #f8f8f8;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
  }

  .print-row-bold td {
    font-weight: 700;
    border-bottom: 2px solid #000;
  }

  .print-row-highlight td {
    background: #f4f7f5;
    font-weight: 700;
  }

  .print-stock-section {
    margin-top: 2rem;
    page-break-inside: avoid;
  }

  .print-stock-section h3 {
    margin: 0 0 0.75rem;
    font-size: 1.1rem;
  }

  .print-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 4rem;
    page-break-inside: avoid;
  }

  .sig-line {
    width: 14rem;
    text-align: center;
  }

  .sig-line .line {
    border-bottom: 1px solid #888;
    margin-bottom: 0.5rem;
  }

  .sig-line span {
    font-size: 0.8rem;
    color: #666;
  }
}
</style>
