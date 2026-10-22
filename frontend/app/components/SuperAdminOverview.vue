<script setup lang="ts">
interface Overview {
  tenants: { total: number; by_status: Record<string, number>; new_today: number; new_this_month: number };
  subscriptions: {
    total_ever: number;
    by_state: Record<string, number>;
    new_today: number;
    new_this_month: number;
    expiring_soon_count: number;
    expiring_soon: Array<{ tenant: { id: string; name: string } | null; plan: string; state: string; ends_at: string; days_left: number }>;
  };
  revenue: { mrr_minor: number; arr_minor: number; today_minor: number; this_month_minor: number; all_time_minor: number };
  plans: Array<{ code: string; name: string; count: number }>;
  series: Array<{ month: string; revenue_minor: number; new_subscriptions: number }>;
  recent_payments: Array<{ id: string; tenant: { name: string } | null; amount_minor: number; method: string; paid_at: string }>;
  generated_at: string;
}

const api = useTailorsApi();
const toast = useToast();
const { tr, money, date, dateTime, methodLabel } = usePlatformFormat();

const data = ref<Overview | null>(null);
const loading = ref(true);
const exporting = ref(false);

const maxRevenue = computed(() => Math.max(1, ...(data.value?.series ?? []).map((s) => s.revenue_minor)));

async function load() {
  loading.value = true;
  try {
    data.value = (await api.request<Overview>("/super-admin/overview")).data;
  } catch {
    toast.error(tr("Could not load the platform overview.", "প্ল্যাটফর্ম ওভারভিউ লোড করা যায়নি।"));
  } finally {
    loading.value = false;
  }
}

async function exportCsv(type: "subscriptions" | "payments") {
  exporting.value = true;
  try {
    await api.download(`/super-admin/reports/export?type=${type}`, `tailors-${type}-${new Date().toISOString().slice(0, 10)}.csv`);
  } catch {
    toast.error(tr("Export failed.", "এক্সপোর্ট ব্যর্থ হয়েছে।"));
  } finally {
    exporting.value = false;
  }
}

function printReport() {
  window.print();
}

onMounted(load);
</script>

<template>
  <div class="overview">
    <div class="overview__toolbar no-print">
      <p class="overview__stamp">{{ tr("As of", "হালনাগাদ") }} {{ dateTime(data?.generated_at) }}</p>
      <div class="overview__actions">
        <button type="button" class="secondary" :disabled="exporting" @click="exportCsv('subscriptions')">⬇ {{ tr("Subscriptions CSV", "সাবস্ক্রিপশন CSV") }}</button>
        <button type="button" class="secondary" :disabled="exporting" @click="exportCsv('payments')">⬇ {{ tr("Payments CSV", "পেমেন্ট CSV") }}</button>
        <button type="button" class="primary" @click="printReport">🖨 {{ tr("Print / PDF report", "প্রিন্ট / PDF রিপোর্ট") }}</button>
      </div>
    </div>

    <p v-if="loading">{{ tr("Loading…", "লোড হচ্ছে…") }}</p>

    <template v-else-if="data">
      <div class="kpis">
        <article class="kpi"><small>{{ tr("Total workshops", "মোট ওয়ার্কশপ") }}</small><strong>{{ data.tenants.total }}</strong><span>+{{ data.tenants.new_today }} {{ tr("today", "আজ") }}</span></article>
        <article class="kpi"><small>{{ tr("Subscriptions so far", "এ পর্যন্ত সাবস্ক্রিপশন") }}</small><strong>{{ data.subscriptions.total_ever }}</strong><span>+{{ data.subscriptions.new_today }} {{ tr("today", "আজ") }} · +{{ data.subscriptions.new_this_month }} {{ tr("this month", "এই মাসে") }}</span></article>
        <article class="kpi"><small>{{ tr("Monthly revenue (MRR)", "মাসিক রেভিনিউ (MRR)") }}</small><strong>{{ money(data.revenue.mrr_minor) }}</strong><span>{{ tr("Yearly run-rate", "বার্ষিক") }} {{ money(data.revenue.arr_minor) }}</span></article>
        <article class="kpi"><small>{{ tr("Collected this month", "এ মাসে আদায়") }}</small><strong>{{ money(data.revenue.this_month_minor) }}</strong><span>{{ tr("Today", "আজ") }} {{ money(data.revenue.today_minor) }}</span></article>
      </div>

      <div class="grid">
        <section class="panel">
          <h2>{{ tr("Workshops by status", "স্ট্যাটাস অনুযায়ী ওয়ার্কশপ") }}</h2>
          <ul class="stat-list">
            <li v-for="(count, status) in data.tenants.by_status" :key="status"><StatusChip :status="String(status)" /><b>{{ count }}</b></li>
          </ul>
          <h2 class="mt">{{ tr("Subscriptions by state", "সাবস্ক্রিপশনের অবস্থা") }}</h2>
          <ul class="stat-list">
            <li v-for="(count, state) in data.subscriptions.by_state" :key="state"><StatusChip :status="String(state)" /><b>{{ count }}</b></li>
          </ul>
        </section>

        <section class="panel">
          <h2>{{ tr("Revenue, last 6 months", "গত ৬ মাসের রেভিনিউ") }}</h2>
          <ol class="bars" :aria-label="tr('Monthly revenue', 'মাসিক রেভিনিউ')">
            <li v-for="point in data.series" :key="point.month">
              <span class="bars__value">{{ money(point.revenue_minor) }}</span>
              <span class="bars__bar" :style="{ height: `${Math.max(4, (point.revenue_minor / maxRevenue) * 100)}%` }" />
              <span class="bars__label">{{ point.month.slice(5) }}/{{ point.month.slice(2, 4) }}</span>
              <span class="bars__sub">+{{ point.new_subscriptions }}</span>
            </li>
          </ol>
          <p class="hint">{{ tr("Bars show payments collected; “+n” is new subscriptions.", "বারে আদায়কৃত পেমেন্ট; “+n” নতুন সাবস্ক্রিপশন।") }}</p>
        </section>

        <section class="panel">
          <h2>{{ tr("Expiring within 7 days", "৭ দিনের মধ্যে মেয়াদ শেষ") }} ({{ data.subscriptions.expiring_soon_count }})</h2>
          <p v-if="!data.subscriptions.expiring_soon.length" class="empty">{{ tr("Nothing expires soon.", "শীঘ্রই কারো মেয়াদ শেষ হচ্ছে না।") }}</p>
          <ul class="rows">
            <li v-for="row in data.subscriptions.expiring_soon" :key="row.tenant?.id">
              <span><b>{{ row.tenant?.name }}</b><small>{{ row.plan }} · {{ date(row.ends_at) }}</small></span>
              <StatusChip :status="row.days_left < 0 ? 'expired' : 'past_due'" />
              <b>{{ row.days_left }} {{ tr("d", "দিন") }}</b>
            </li>
          </ul>
        </section>

        <section class="panel">
          <h2>{{ tr("Plan distribution", "প্ল্যান বণ্টন") }}</h2>
          <p v-if="!data.plans.length" class="empty">{{ tr("No paying or trial workshops yet.", "এখনো কোনো ট্রায়াল বা পেইড ওয়ার্কশপ নেই।") }}</p>
          <ul class="stat-list">
            <li v-for="plan in data.plans" :key="plan.code"><span>{{ plan.name }}</span><b>{{ plan.count }}</b></li>
          </ul>
          <h2 class="mt">{{ tr("Latest payments", "সর্বশেষ পেমেন্ট") }}</h2>
          <ul class="rows">
            <li v-for="payment in data.recent_payments" :key="payment.id">
              <span><b>{{ payment.tenant?.name }}</b><small>{{ methodLabel(payment.method) }} · {{ dateTime(payment.paid_at) }}</small></span>
              <b>{{ money(payment.amount_minor) }}</b>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </div>
</template>

<style scoped>
.overview__toolbar { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; flex-wrap: wrap; margin-bottom: 1rem; }
.overview__actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.overview__stamp, .hint { margin: 0; color: var(--muted); font-size: 0.8rem; }
.kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); gap: 0.85rem; margin-bottom: 1rem; }
.kpi { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 1rem 1.15rem; display: flex; flex-direction: column; gap: 0.2rem; }
.kpi small { color: var(--muted); font-weight: 600; }
.kpi strong { font-size: 1.7rem; line-height: 1.1; }
.kpi span { color: var(--muted); font-size: 0.78rem; }
.grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr)); gap: 1rem; }
.mt { margin-top: 1.25rem; }
.stat-list, .rows { list-style: none; margin: 0.5rem 0 0; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.stat-list li { display: flex; justify-content: space-between; align-items: center; }
.rows li { display: flex; justify-content: space-between; align-items: center; gap: 0.75rem; padding: 0.5rem 0; border-bottom: 1px solid var(--line); }
.rows li:last-child { border-bottom: 0; }
.rows small { display: block; color: var(--muted); }
.empty { color: var(--muted); }
.bars { list-style: none; margin: 1rem 0 0.5rem; padding: 0; display: grid; grid-template-columns: repeat(6, 1fr); gap: 0.5rem; height: 12rem; align-items: end; }
.bars li { display: flex; flex-direction: column; justify-content: flex-end; align-items: center; height: 100%; min-width: 0; }
.bars__bar { width: 70%; max-width: 3rem; background: var(--primary); border-radius: 4px 4px 0 0; min-height: 4px; }
.bars__value { font-size: 0.62rem; color: var(--muted); margin-bottom: 0.2rem; text-align: center; overflow-wrap: anywhere; }
.bars__label { font-size: 0.72rem; margin-top: 0.3rem; font-weight: 600; }
.bars__sub { font-size: 0.68rem; color: var(--muted); }

@media print {
  .no-print { display: none !important; }
}
</style>
