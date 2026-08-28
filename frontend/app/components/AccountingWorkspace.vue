<script setup lang="ts">
interface Customer { id: string; name: string; mobile_number: string }
interface StatementLine { entry_id:string;occurred_at:string;memo:string;debit_minor:number;credit_minor:number;balance_minor:number }
interface Account { id:string;code:string;name:string;type:string;debit_minor:number;credit_minor:number;balance_minor:number }
const api = useTailorsApi()
const customers = ref<Customer[]>([]), selected = ref(""), lines = ref<StatementLine[]>([]), accounts = ref<Account[]>([]), balance = ref(0), message = ref("")
const adjustment = reactive({ type: "charge", amount_minor: 0, memo: "" })
async function load() {
  const [customerResult, trial] = await Promise.all([api.request<{ items: Customer[] }>("/customers"), api.request<{ items: Account[] }>("/accounting/trial-balance")])
  customers.value = customerResult.data.items; accounts.value = trial.data.items
  if (!selected.value && customers.value.length) selected.value = customers.value[0]!.id
  if (selected.value) await statement()
}
async function statement() { const result = await api.request<{ items: StatementLine[]; balance_minor: number }>(`/customers/${selected.value}/statement`); lines.value = result.data.items; balance.value = result.data.balance_minor }
async function postAdjustment() { await api.request(`/customers/${selected.value}/adjustments`, { method: "POST", body: adjustment }); Object.assign(adjustment, { type: "charge", amount_minor: 0, memo: "" }); message.value = "Adjustment posted to the balanced journal."; await load() }
onMounted(load)
</script>

<template>
  <header>
    <div>
      <p class="eyebrow">
        FINANCIAL LEDGER
      </p><h1>Accounting</h1><p>Customer receivables and balanced workshop accounts.</p>
    </div>
  </header>
  <p v-if="message" class="success" role="status">
    {{ message }}
  </p>
  <div class="workspace-grid">
    <section class="panel">
      <h2>Customer statement</h2>
      <label>Customer<select v-model="selected" @change="statement"><option v-for="customer in customers" :key="customer.id" :value="customer.id">{{ customer.name }} · {{ customer.mobile_number }}</option></select></label>
      <strong>Balance due: ৳ {{ (balance / 100).toFixed(2) }}</strong>
      <article v-for="line in lines" :key="line.entry_id" class="record record--stock">
        <span><strong>{{ line.memo }}</strong><small>{{ new Date(line.occurred_at).toLocaleString() }}</small></span><span>Dr ৳ {{ (line.debit_minor/100).toFixed(2) }} · Cr ৳ {{ (line.credit_minor/100).toFixed(2) }}</span><em>৳ {{ (line.balance_minor/100).toFixed(2) }}</em>
      </article>
      <p v-if="!lines.length" class="empty">
        No journal activity for this customer.
      </p>
    </section>
    <form class="panel form" @submit.prevent="postAdjustment">
      <h2>Post adjustment</h2><p>Use this for an approved opening balance or correction. Entries are never silently overwritten.</p>
      <label>Type<select v-model="adjustment.type"><option value="charge">Additional charge</option><option value="credit">Credit customer</option></select></label>
      <label>Amount (৳)<input :value="(adjustment.amount_minor/100).toFixed(2)" @change="adjustment.amount_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)" type="number" min="0.01" step="0.01" required></label><label>Reason<textarea v-model="adjustment.memo" required></textarea></label><button class="primary">
        Post adjustment
      </button>
    </form>
  </div>
  <section class="panel">
    <h2>Trial balance</h2><article v-for="account in accounts" :key="account.id" class="record record--stock">
      <span><strong>{{ account.code }}</strong><small>{{ account.name }} · {{ account.type }}</small></span><span>Dr ৳ {{ (account.debit_minor/100).toFixed(2) }} · Cr ৳ {{ (account.credit_minor/100).toFixed(2) }}</span><em>৳ {{ (account.balance_minor/100).toFixed(2) }}</em>
    </article>
  </section>
</template>
