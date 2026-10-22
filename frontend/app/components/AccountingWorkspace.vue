<script setup lang="ts">
const { t } = useTailorsI18n();
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
const knownAccountTypes = ["asset", "liability", "equity", "revenue", "income", "expense"];
const accountType = (type: string) => (knownAccountTypes.includes(type) ? t(`app.accounting.type_${type}`) : type);

const selected = ref("");
const lines = ref<StatementLine[]>([]);
const accounts = ref<Account[]>([]);
const balance = ref(0);
const loading = ref(false);

const adjustment = reactive({ type: "charge", amount_minor: 0, memo: "" });

async function load() {
  loading.value = true;
  try {
    accounts.value = (await api.request<{ items: Account[] }>("/accounting/trial-balance")).data.items;
    if (selected.value) await statement();
  } catch (e: any) {
    toast.error(t('app.accounting.could_not_load_accounts'));
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
    toast.success(t('app.accounting.adjustment_posted'));
    Object.assign(adjustment, { type: "charge", amount_minor: 0, memo: "" });
    await load();
  } catch (e: any) {
    toast.error(t('app.accounting.could_not_post_the_adjustment'));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.accounting.ledger_accounts') }}</p>
        <h1>{{ t('app.accounting.accounting_customer_dues') }}</h1>
        <p>{{ t('app.accounting.customer_dues_audit_trail_adjustments_and') }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Customer Statement Panel -->
      <section class="panel">
        <h2>{{ t('app.accounting.customer_statement') }}</h2>
        <label>{{ t('app.accounting.select_customer') }}
          <CustomerPicker v-model="selected" :placeholder="t('app.accounting.search_customer_name_mobile')" @select="statement" />
        </label>

        <div class="balance-card">
          <span>{{ t('app.accounting.outstanding_balance') }}</span>
          <strong>৳ {{ (balance / 100).toFixed(2) }}</strong>
        </div>

        <div class="statement-rows">
          <article v-for="line in lines" :key="line.entry_id" class="record record--stock">
            <div>
              <strong>{{ line.memo }}</strong>
              <small style="display:block">{{ new Date(line.occurred_at).toLocaleString() }}</small>
            </div>
            <div>
              <span v-if="line.debit_minor > 0">{{ t('app.accounting.dr') }} ৳ {{ (line.debit_minor / 100).toFixed(2) }}</span>
              <span v-if="line.credit_minor > 0" style="color:var(--status-ready)">{{ t('app.accounting.cr') }} ৳ {{ (line.credit_minor / 100).toFixed(2) }}</span>
            </div>
            <strong style="font-size:1rem">৳ {{ (line.balance_minor / 100).toFixed(2) }}</strong>
          </article>
        </div>

        <p v-if="!lines.length" class="empty">{{ t('app.accounting.no_transactions_for_this_customer') }}</p>
      </section>

      <!-- Post Adjustment Form -->
      <form class="panel form" @submit.prevent="postAdjustment">
        <h2>{{ t('app.accounting.post_an_adjustment') }}</h2>
        <p style="font-size:0.85rem;color:var(--muted)">{{ t('app.accounting.use_for_opening_balances_discounts_or') }}</p>

        <label>{{ t('app.accounting.adjustment_type') }}
          <select v-model="adjustment.type">
            <option value="charge">{{ t('app.accounting.extra_charge_due') }}</option>
            <option value="credit">{{ t('app.accounting.credit_discount') }}</option>
          </select>
        </label>

        <label>{{ t('app.common.amount') }}
          <input
            :value="(adjustment.amount_minor / 100).toFixed(2)"
            type="number"
            min="0.01"
            step="10"
            required
            @input="adjustment.amount_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
          />
        </label>

        <label>{{ t('app.accounting.reason_audit_note') }}
          <textarea v-model="adjustment.memo" :placeholder="t('app.accounting.why_is_this_adjustment_needed')" required></textarea>
        </label>

        <button class="primary">{{ t('app.accounting.post_adjustment') }}</button>
      </form>
    </div>

    <!-- Trial Balance Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>{{ t('app.accounting.workshop_accounts_trial_balance') }}</h2>
      <div class="trial-balance-grid">
        <article v-for="acc in accounts" :key="acc.id" class="record record--stock">
          <div>
            <strong>{{ acc.code }} — {{ acc.name }}</strong>
            <small style="display:block;text-transform:capitalize">{{ accountType(acc.type) }}</small>
          </div>
          <div>
            <span>{{ t('app.accounting.dr') }} ৳ {{ (acc.debit_minor / 100).toFixed(2) }}</span>
            <span> · </span>
            <span>{{ t('app.accounting.cr') }} ৳ {{ (acc.credit_minor / 100).toFixed(2) }}</span>
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
