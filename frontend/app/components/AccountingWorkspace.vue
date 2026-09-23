<script setup lang="ts">
interface Customer {
  id: string;
  name: string;
  mobile_number: string;
}

interface StatementLine {
  entry_id: string;
  occurred_at: string;
  memo: string;
  debit_minor: number;
  credit_minor: number;
  balance_minor: number;
}

interface Account {
  id: string;
  code: string;
  name: string;
  type: string;
  debit_minor: number;
  credit_minor: number;
  balance_minor: number;
}

const api = useTailorsApi();
const toast = useToast();

const customers = ref<Customer[]>([]);
const selected = ref("");
const lines = ref<StatementLine[]>([]);
const accounts = ref<Account[]>([]);
const balance = ref(0);
const loading = ref(false);

const adjustment = reactive({ type: "charge", amount_minor: 0, memo: "" });

async function load() {
  loading.value = true;
  try {
    const [customerResult, trial] = await Promise.all([
      api.request<{ items: Customer[] }>("/customers"),
      api.request<{ items: Account[] }>("/accounting/trial-balance"),
    ]);
    customers.value = customerResult.data.items;
    accounts.value = trial.data.items;
    if (!selected.value && customers.value.length) selected.value = customers.value[0]!.id;
    if (selected.value) await statement();
  } catch (e: any) {
    toast.error("Could not load accounting data.");
  } finally {
    loading.value = false;
  }
}

async function statement() {
  if (!selected.value) return;
  try {
    const result = await api.request<{ items: StatementLine[]; balance_minor: number }>(
      `/customers/${selected.value}/statement`
    );
    lines.value = result.data.items;
    balance.value = result.data.balance_minor;
  } catch {
    // Non-blocking
  }
}

async function postAdjustment() {
  if (!selected.value || adjustment.amount_minor <= 0) return;
  try {
    await api.request(`/customers/${selected.value}/adjustments`, {
      method: "POST",
      body: adjustment,
    });
    toast.success("Adjustment posted to double-entry journal.");
    Object.assign(adjustment, { type: "charge", amount_minor: 0, memo: "" });
    await load();
  } catch (e: any) {
    toast.error("Could not post adjustment.");
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">FINANCIAL LEDGER & ACCOUNTS</p>
        <h1>Accounting & Customer Dues</h1>
        <p>Customer receivables, audit trails, journal adjustments, and balanced workshop ledger.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Customer Statement Panel -->
      <section class="panel">
        <h2>Customer Running Statement (গ্রাহক হিসাব খাতা)</h2>
        <label>Select Customer
          <select v-model="selected" @change="statement">
            <option v-for="c in customers" :key="c.id" :value="c.id">
              {{ c.name }} · {{ c.mobile_number }}
            </option>
          </select>
        </label>

        <div class="balance-card">
          <span>Outstanding Receivable (বকেয়া ব্যালেন্স):</span>
          <strong>৳ {{ (balance / 100).toFixed(2) }}</strong>
        </div>

        <div class="statement-rows">
          <article v-for="line in lines" :key="line.entry_id" class="record record--stock">
            <div>
              <strong>{{ line.memo }}</strong>
              <small style="display:block">{{ new Date(line.occurred_at).toLocaleString() }}</small>
            </div>
            <div>
              <span v-if="line.debit_minor > 0">Dr ৳ {{ (line.debit_minor / 100).toFixed(2) }}</span>
              <span v-if="line.credit_minor > 0" style="color:var(--status-ready)">Cr ৳ {{ (line.credit_minor / 100).toFixed(2) }}</span>
            </div>
            <strong style="font-size:1rem">৳ {{ (line.balance_minor / 100).toFixed(2) }}</strong>
          </article>
        </div>

        <p v-if="!lines.length" class="empty">No journal transactions for this customer.</p>
      </section>

      <!-- Post Adjustment Form -->
      <form class="panel form" @submit.prevent="postAdjustment">
        <h2>Post Journal Adjustment (হিসাব সমন্বয়)</h2>
        <p style="font-size:0.85rem;color:var(--muted)">Use for opening balances, discounts or dispute settlements. All entries are permanently audited.</p>

        <label>Adjustment Type *
          <select v-model="adjustment.type">
            <option value="charge">Additional Charge / Due (বকেয়া বৃদ্ধি)</option>
            <option value="credit">Credit Customer / Discount (জমা / মওকুফ)</option>
          </select>
        </label>

        <label>Amount (৳) *
          <input
            :value="(adjustment.amount_minor / 100).toFixed(2)"
            type="number"
            min="0.01"
            step="10"
            required
            @input="adjustment.amount_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
          />
        </label>

        <label>Reason / Audit Memo *
          <textarea v-model="adjustment.memo" placeholder="Explanation for this financial adjustment..." required></textarea>
        </label>

        <button class="primary">Post Adjustment to Journal</button>
      </form>
    </div>

    <!-- Trial Balance Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>Balanced Workshop Accounts (Trial Balance)</h2>
      <div class="trial-balance-grid">
        <article v-for="acc in accounts" :key="acc.id" class="record record--stock">
          <div>
            <strong>{{ acc.code }} — {{ acc.name }}</strong>
            <small style="display:block;text-transform:capitalize">{{ acc.type }} Account</small>
          </div>
          <div>
            <span>Dr ৳ {{ (acc.debit_minor / 100).toFixed(2) }}</span>
            <span> · </span>
            <span>Cr ৳ {{ (acc.credit_minor / 100).toFixed(2) }}</span>
          </div>
          <strong style="font-size:1rem">৳ {{ (acc.balance_minor / 100).toFixed(2) }}</strong>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.balance-card {
  background: var(--paper);
  border: 1px solid var(--line);
  padding: 1rem 1.25rem;
  border-radius: var(--radius-sm);
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 1rem 0;
  font-size: 1.1rem;
}

.balance-card strong {
  font-size: 1.35rem;
  color: var(--status-cancelled);
}

.statement-rows {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  max-height: 22rem;
  overflow-y: auto;
}

.trial-balance-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  margin-top: 1rem;
}
</style>
