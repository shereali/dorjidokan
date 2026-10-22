<script setup lang="ts">
interface PaymentRow {
  id: string;
  tenant: { id: string; name: string; slug: string } | null;
  plan: string | null;
  amount_minor: number;
  method: string;
  reference: string | null;
  months: number;
  paid_at: string;
}

const api = useTailorsApi();
const toast = useToast();
const { money, dateTime, methodLabel } = usePlatformFormat();
const { t } = useTailorsI18n();

const rows = ref<PaymentRow[]>([]);
const loading = ref(true);
const meta = reactive({ total: 0, page: 1, last_page: 1, sum_minor: 0 });
const range = reactive({ from: "", to: "" });

async function load(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "20" });
    if (range.from) params.set("from", range.from);
    if (range.to) params.set("to", range.to);
    const res = await api.request<{ items: PaymentRow[] }>(`/super-admin/payments?${params}`);
    rows.value = res.data.items;
    Object.assign(meta, res.meta as typeof meta);
  } catch {
    toast.error(t('app.super_admin_payments.could_not_load_payments'));
  } finally {
    loading.value = false;
  }
}

async function exportCsv() {
  const params = new URLSearchParams({ type: "payments" });
  if (range.from) params.set("from", range.from);
  if (range.to) params.set("to", range.to);
  try {
    await api.download(`/super-admin/reports/export?${params}`, `tailors-payments-${new Date().toISOString().slice(0, 10)}.csv`);
  } catch {
    toast.error(t('app.common.export_failed'));
  }
}

onMounted(() => load(1));
</script>

<template>
  <div>
    <form class="filters" @submit.prevent="load(1)">
      <label>{{ t('app.super_admin_payments.from') }}<input v-model="range.from" type="date" /></label>
      <label>{{ t('app.super_admin_payments.to') }}<input v-model="range.to" type="date" /></label>
      <button class="primary">{{ t('app.super_admin_payments.apply') }}</button>
      <button type="button" class="secondary" @click="exportCsv">⬇ CSV</button>
      <p class="total">{{ t('app.super_admin_payments.total_in_range') }}: <b>{{ money(meta.sum_minor) }}</b></p>
    </form>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>{{ t('app.common.date') }}</th>
            <th>{{ t('app.common.workshop') }}</th>
            <th>{{ t('app.common.plan') }}</th>
            <th>{{ t('app.common.method') }}</th>
            <th>{{ t('app.super_admin_payments.reference') }}</th>
            <th>{{ t('app.super_admin_payments.months') }}</th>
            <th class="num">{{ t('app.common.amount_2') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7">{{ t('app.common.loading') }}</td></tr>
          <tr v-else-if="!rows.length"><td colspan="7">{{ t('app.super_admin_payments.no_payments_in_this_range') }}</td></tr>
          <tr v-for="row in rows" :key="row.id">
            <td>{{ dateTime(row.paid_at) }}</td>
            <td>{{ row.tenant?.name }}</td>
            <td>{{ row.plan ?? "—" }}</td>
            <td>{{ methodLabel(row.method) }}</td>
            <td>{{ row.reference ?? "—" }}</td>
            <td>{{ row.months }}</td>
            <td class="num"><b>{{ money(row.amount_minor) }}</b></td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="meta.last_page > 1" class="pager" :aria-label="t('app.common.pagination')">
      <button type="button" class="mini-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">‹ {{ t('app.common.prev') }}</button>
      <span>{{ meta.page }} / {{ meta.last_page }}</span>
      <button type="button" class="mini-btn" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">{{ t('app.common.next') }} ›</button>
    </nav>
  </div>
</template>

<style scoped>
.filters { display: flex; gap: 0.6rem; align-items: flex-end; flex-wrap: wrap; margin-bottom: 0.75rem; }
.filters label { display: flex; flex-direction: column; gap: 0.2rem; font-size: 0.8rem; font-weight: 600; }
.total { margin: 0 0 0 auto; }
.num { text-align: right; }
.pager { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 0.85rem; }
</style>
