<script setup lang="ts">
const { tr } = usePlatformFormat();
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
const accountTypes: Record<string, [string, string]> = {
  asset: ["Asset account", "সম্পদ খাত"],
  liability: ["Liability account", "দায় খাত"],
  equity: ["Equity account", "মূলধন খাত"],
  revenue: ["Income account", "আয় খাত"],
  income: ["Income account", "আয় খাত"],
  expense: ["Expense account", "ব্যয় খাত"],
};
const accountType = (type: string) => {
  const names = accountTypes[type];
  return names ? tr(names[0], names[1]) : type;
};

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
    toast.error(tr("Could not load accounts.", "হিসাব লোড করা যায়নি।"));
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
    toast.success(tr("Adjustment posted.", "সমন্বয় এন্ট্রি হয়েছে।"));
    Object.assign(adjustment, { type: "charge", amount_minor: 0, memo: "" });
    await load();
  } catch (e: any) {
    toast.error(tr("Could not post the adjustment.", "সমন্বয় এন্ট্রি করা যায়নি।"));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("LEDGER & ACCOUNTS", "হিসাব খাতা") }}</p>
        <h1>{{ tr("Accounting & customer dues", "হিসাব ও গ্রাহকের বকেয়া") }}</h1>
        <p>{{ tr("Customer dues, audit trail, adjustments and the workshop ledger.", "গ্রাহকের বকেয়া, অডিট ট্রেইল, সমন্বয় এন্ট্রি ও ওয়ার্কশপের হিসাব খাতা।") }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Customer Statement Panel -->
      <section class="panel">
        <h2>{{ tr("Customer statement", "গ্রাহকের হিসাব খাতা") }}</h2>
        <label>{{ tr("Select customer", "গ্রাহক বাছুন") }}
          <CustomerPicker v-model="selected" :placeholder="tr('Search customer name / mobile', 'গ্রাহকের নাম / মোবাইল খুঁজুন')" @select="statement" />
        </label>

        <div class="balance-card">
          <span>{{ tr("Outstanding balance:", "বকেয়া ব্যালেন্স:") }}</span>
          <strong>৳ {{ (balance / 100).toFixed(2) }}</strong>
        </div>

        <div class="statement-rows">
          <article v-for="line in lines" :key="line.entry_id" class="record record--stock">
            <div>
              <strong>{{ line.memo }}</strong>
              <small style="display:block">{{ new Date(line.occurred_at).toLocaleString() }}</small>
            </div>
            <div>
              <span v-if="line.debit_minor > 0">{{ tr("Dr", "ডেবিট") }} ৳ {{ (line.debit_minor / 100).toFixed(2) }}</span>
              <span v-if="line.credit_minor > 0" style="color:var(--status-ready)">{{ tr("Cr", "ক্রেডিট") }} ৳ {{ (line.credit_minor / 100).toFixed(2) }}</span>
            </div>
            <strong style="font-size:1rem">৳ {{ (line.balance_minor / 100).toFixed(2) }}</strong>
          </article>
        </div>

        <p v-if="!lines.length" class="empty">{{ tr("No transactions for this customer.", "এই গ্রাহকের কোনো লেনদেন নেই।") }}</p>
      </section>

      <!-- Post Adjustment Form -->
      <form class="panel form" @submit.prevent="postAdjustment">
        <h2>{{ tr("Post an adjustment", "হিসাব সমন্বয় করুন") }}</h2>
        <p style="font-size:0.85rem;color:var(--muted)">{{ tr("Use for opening balances, discounts or disputes. Every entry is permanently audited.", "শুরুর ব্যালেন্স, ছাড় বা বিরোধ নিষ্পত্তিতে ব্যবহার করুন। প্রতিটি এন্ট্রি স্থায়ীভাবে অডিট হয়।") }}</p>

        <label>{{ tr("Adjustment type *", "সমন্বয়ের ধরন *") }}
          <select v-model="adjustment.type">
            <option value="charge">{{ tr("Extra charge / due", "অতিরিক্ত চার্জ / বকেয়া বৃদ্ধি") }}</option>
            <option value="credit">{{ tr("Credit / discount", "জমা / মওকুফ") }}</option>
          </select>
        </label>

        <label>{{ tr("Amount (৳) *", "পরিমাণ (৳) *") }}
          <input
            :value="(adjustment.amount_minor / 100).toFixed(2)"
            type="number"
            min="0.01"
            step="10"
            required
            @input="adjustment.amount_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
          />
        </label>

        <label>{{ tr("Reason / audit note *", "কারণ / অডিট নোট *") }}
          <textarea v-model="adjustment.memo" :placeholder="tr('Why is this adjustment needed…', 'এই সমন্বয়ের কারণ লিখুন…')" required></textarea>
        </label>

        <button class="primary">{{ tr("Post adjustment", "সমন্বয় এন্ট্রি দিন") }}</button>
      </form>
    </div>

    <!-- Trial Balance Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>{{ tr("Workshop accounts (trial balance)", "ওয়ার্কশপের হিসাব (ট্রায়াল ব্যালেন্স)") }}</h2>
      <div class="trial-balance-grid">
        <article v-for="acc in accounts" :key="acc.id" class="record record--stock">
          <div>
            <strong>{{ acc.code }} — {{ acc.name }}</strong>
            <small style="display:block;text-transform:capitalize">{{ accountType(acc.type) }}</small>
          </div>
          <div>
            <span>{{ tr("Dr", "ডেবিট") }} ৳ {{ (acc.debit_minor / 100).toFixed(2) }}</span>
            <span> · </span>
            <span>{{ tr("Cr", "ক্রেডিট") }} ৳ {{ (acc.credit_minor / 100).toFixed(2) }}</span>
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
