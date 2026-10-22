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
const { tr, money, dateTime, methodLabel } = usePlatformFormat();

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
    toast.error(tr("Could not load payments.", "পেমেন্ট লোড করা যায়নি।"));
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
    toast.error(tr("Export failed.", "এক্সপোর্ট ব্যর্থ হয়েছে।"));
  }
}

onMounted(() => load(1));
</script>

<template>
  <div>
    <form class="filters" @submit.prevent="load(1)">
      <label>{{ tr("From", "থেকে") }}<input v-model="range.from" type="date" /></label>
      <label>{{ tr("To", "পর্যন্ত") }}<input v-model="range.to" type="date" /></label>
      <button class="primary">{{ tr("Apply", "প্রয়োগ") }}</button>
      <button type="button" class="secondary" @click="exportCsv">⬇ CSV</button>
      <p class="total">{{ tr("Total in range", "মোট") }}: <b>{{ money(meta.sum_minor) }}</b></p>
    </form>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>{{ tr("Date", "তারিখ") }}</th>
            <th>{{ tr("Workshop", "ওয়ার্কশপ") }}</th>
            <th>{{ tr("Plan", "প্ল্যান") }}</th>
            <th>{{ tr("Method", "পদ্ধতি") }}</th>
            <th>{{ tr("Reference", "রেফারেন্স") }}</th>
            <th>{{ tr("Months", "মাস") }}</th>
            <th class="num">{{ tr("Amount", "পরিমাণ") }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7">{{ tr("Loading…", "লোড হচ্ছে…") }}</td></tr>
          <tr v-else-if="!rows.length"><td colspan="7">{{ tr("No payments in this range.", "এই সময়ে কোনো পেমেন্ট নেই।") }}</td></tr>
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

    <nav v-if="meta.last_page > 1" class="pager" :aria-label="tr('Pagination', 'পেজিনেশন')">
      <button type="button" class="mini-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">‹ {{ tr("Prev", "আগে") }}</button>
      <span>{{ meta.page }} / {{ meta.last_page }}</span>
      <button type="button" class="mini-btn" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">{{ tr("Next", "পরে") }} ›</button>
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
