<script setup lang="ts">
interface C {
  public_id?: string;
  id?: string;
  name: string;
}
interface Payout { id:string;number:string;employee:{name:string};period_from:string;period_to:string;total_minor:number;paid_at:string }
interface RentalRecord { id:string;number:string;status:string;customer:{name:string};due_on:string;rent_minor:number;deposit_minor:number;damage_charge_minor:number;deposit_refunded_minor:number;settlement_due_minor:number;settlement_note?:string;items:Array<{inventory_item:{id:string;name:string};quantity:number;condition_in?:string}> }
interface ExpenseRecord { id:string;category:string;amount_minor:number;note?:string;expense_date:string;status:string;attachments:Array<{id:string;name:string;download_path:string}> }
interface SaleLine { inventory_item_id:string; quantity:number; unit_price_minor:number }
interface SaleReceipt { number:string;customer?:{name:string};total_minor:number;paid_minor:number;items:Array<{inventory_item:{name:string};quantity:number;total_minor:number}> }
const api = useTailorsApi(),
  config = useRuntimeConfig(),
  mode = ref("Purchase"),
  message = ref(""),
  error = ref(""),
  inventory = ref<C[]>([]),
  customers = ref<C[]>([]),
  employees = ref<C[]>([]),
  suppliers = ref<C[]>([]),
  payouts = ref<Payout[]>([]),
  rentals = ref<RentalRecord[]>([]),
  expenses = ref<ExpenseRecord[]>([]),
  saleLines = ref<SaleLine[]>([]),
  receipt = ref<SaleReceipt | null>(null),
  expenseAttachment = ref<File | null>(null),
  today = new Date().toISOString().slice(0, 10);
const purchase = reactive({
    supplier_id: "",
    inventory_item_id: "",
    quantity: 1,
    unit_cost_minor: 0,
  }),
  sale = reactive({
    customer_id: "",
    inventory_item_id: "",
    quantity: 1,
    unit_price_minor: 0,
    paid_minor: 0,
    discount_minor: 0,
    payment_method: "cash",
  }),
  expense = reactive({
    category: "General",
    amount_minor: 0,
    note: "",
    expense_date: today,
  }),
  rental = reactive({
    customer_id: "",
    inventory_item_id: "",
    quantity: 1,
    starts_on: today,
    due_on: today,
    rent_minor: 0,
    deposit_minor: 0,
  }),
  attendance = reactive({
    employee_id: "",
    work_date: today,
    status: "present",
  }),
  work = reactive({
    employee_id: "",
    work_type: "Stitching",
    quantity: 1,
    rate_minor: 0,
  }),
  payout = reactive({
    employee_id: "",
    period_from: today,
    period_to: today,
    payment_method: "cash",
    reference: "",
  }),
  supplier = reactive({ name: "", mobile_number: "", address: "" });
const rentalReturns = reactive<Record<string, { damage_charge_minor: number; settlement_note: string; conditions: Record<string, string> }>>({});
// The API stores money in minor units (paisa); operators think in ৳.
const money = (obj: Record<string, unknown>, key: string) => ({
  get: () => (obj[key] as number) / 100,
  set: (v: number) => { obj[key] = Math.round((v || 0) * 100); },
});
const purchaseCostTaka = money(purchase, "unit_cost_minor");
const salePriceTaka = money(sale, "unit_price_minor");
const salePaidTaka = money(sale, "paid_minor");
const saleDiscountTaka = money(sale, "discount_minor");
const expenseAmountTaka = money(expense, "amount_minor");
const rentalRentTaka = money(rental, "rent_minor");
const rentalDepositTaka = money(rental, "deposit_minor");
const workRateTaka = money(work, "rate_minor");
async function catalogs() {
  const [i, c, e, s, p, r, x] = await Promise.all([
    api.request<{ items: C[] }>("/inventory"),
    api.request<{ items: C[] }>("/customers"),
    api.request<{ employees: C[] }>("/employees"),
    api.request<{ items: C[] }>("/suppliers"),
    api.request<{ items: Payout[] }>("/payouts"),
    api.request<{ items: RentalRecord[] }>("/rentals"),
    api.request<{ items: ExpenseRecord[] }>("/expenses"),
  ]);
  inventory.value = i.data.items;
  customers.value = c.data.items;
  employees.value = e.data.employees;
  suppliers.value = s.data.items;
  payouts.value = p.data.items;
  rentals.value = r.data.items;
  expenses.value = x.data.items;
}
async function submit(path: string, body: unknown) {
  error.value = "";
  message.value = "";
  try {
    await api.request(path, { method: "POST", body });
    message.value = "Saved successfully.";
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save.";
  }
}
const savePurchase = () =>
  submit("/purchases", {
    supplier_id: purchase.supplier_id || null,
    items: [
      {
        inventory_item_id: purchase.inventory_item_id,
        quantity: purchase.quantity,
        unit_cost_minor: purchase.unit_cost_minor,
      },
    ],
  });
function addSaleLine() {
  if (!sale.inventory_item_id || sale.quantity <= 0 || sale.unit_price_minor < 0) return;
  saleLines.value.push({ inventory_item_id: sale.inventory_item_id, quantity: sale.quantity, unit_price_minor: sale.unit_price_minor });
  Object.assign(sale, { inventory_item_id: "", quantity: 1, unit_price_minor: 0 });
}
const saleTotal = computed(() => Math.max(0, saleLines.value.reduce((sum, line) => sum + Math.round(line.quantity * line.unit_price_minor), 0) - sale.discount_minor));
async function saveSale() {
  error.value = "";
  if (!saleLines.value.length) { error.value = "Add at least one sale item."; return; }
  try {
    const result = await api.request<{ sale: SaleReceipt }>("/sales", { method: "POST", body: { customer_id: sale.customer_id || null, sale_type: "direct", discount_minor: sale.discount_minor, paid_minor: sale.paid_minor, payment_method: sale.payment_method, items: saleLines.value } });
    receipt.value = result.data.sale; saleLines.value = []; message.value = "Sale completed."; await catalogs();
  } catch (e: any) { error.value = e?.data?.errors?.[0]?.message || "Could not complete sale."; }
}
function itemName(id:string) { return inventory.value.find(item => item.public_id === id)?.name || "Item" }
function printReceipt() {
  document.body.classList.add("printing-receipt");
  window.addEventListener("afterprint", () => document.body.classList.remove("printing-receipt"), { once: true });
  window.print();
}
const saveRental = () =>
  submit("/rentals", {
    customer_id: rental.customer_id,
    starts_on: rental.starts_on,
    due_on: rental.due_on,
    rent_minor: rental.rent_minor,
    deposit_minor: rental.deposit_minor,
    items: [
      {
        inventory_item_id: rental.inventory_item_id,
        quantity: rental.quantity,
      },
    ],
  });
async function savePayout(){await submit("/payouts",payout);await catalogs()}
async function saveSupplier(){await submit("/suppliers", supplier);Object.assign(supplier,{name:"",mobile_number:"",address:""});await catalogs()}
function rentalReturn(contract: RentalRecord) {
  return rentalReturns[contract.id] ||= { damage_charge_minor: 0, settlement_note: "", conditions: Object.fromEntries(contract.items.map(item => [item.inventory_item.id, "Good"])) };
}
async function returnRental(contract: RentalRecord){await submit(`/rentals/${contract.id}/return`,rentalReturn(contract));await catalogs()}
function chooseExpenseAttachment(event: Event) { expenseAttachment.value = (event.target as HTMLInputElement).files?.[0] || null }
async function saveExpense() {
  const result = await api.request<{ expense: ExpenseRecord }>("/expenses", { method: "POST", body: expense })
  if (expenseAttachment.value) { const body = new FormData(); body.append("attachment", expenseAttachment.value); await api.request(`/expenses/${result.data.expense.id}/attachments`, { method: "POST", body }) }
  expenseAttachment.value = null; message.value = "Expense submitted for approval."; await catalogs()
}
async function approveExpense(id:string) { await api.request(`/expenses/${id}/approve`, { method: "POST" }); await catalogs() }
async function downloadAttachment(attachment:{name:string;download_path:string}) { const response = await $fetch.raw(`${config.public.apiBase}${attachment.download_path}`, { credentials: "include", headers: { "X-Tenant": api.tenant.value } }); const url = URL.createObjectURL(await response._data as Blob); const link = document.createElement("a"); link.href = url; link.download = attachment.name; link.click(); URL.revokeObjectURL(url) }
onMounted(catalogs);
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        DAILY OPERATIONS
      </p>
      <h1>Operations</h1>
      <p>Purchasing, sales, expenses, rentals and workforce.</p>
    </div>
  </header>
  <div class="tabs">
    <button
      v-for="tab in [
        'Purchase',
        'Sale',
        'Expense',
        'Rental',
        'Rental returns',
        'Suppliers',
        'Attendance',
        'Piece work',
        'Payout',
      ]"
      :key="tab"
      :class="{ active: mode === tab }"
      @click="mode = tab"
    >
      {{ tab }}
    </button>
  </div>
  <p v-if="message" class="success">
    {{ message }}
  </p>
  <p v-if="error" class="error">
    {{ error }}
  </p>
  <form
    v-if="mode === 'Purchase'"
    class="panel form operation-form"
    @submit.prevent="savePurchase"
  >
    <h2>Receive purchase</h2>
    <label>Supplier<select v-model="purchase.supplier_id">
      <option value="">No supplier</option>
      <option v-for="s in suppliers" :key="s.public_id" :value="s.public_id">
        {{ s.name }}
      </option>
    </select></label><label>Item<select v-model="purchase.inventory_item_id" required>
      <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
        {{ i.name }}
      </option>
    </select></label><label>Quantity<input
      v-model.number="purchase.quantity"
      type="number"
      step="0.001"
    /></label><label>Unit cost (৳)<input
      v-model.number="purchaseCostTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><button class="primary">
      Receive stock
    </button>
  </form>
  <form
    v-else-if="mode === 'Sale'"
    class="panel form operation-form"
    @submit.prevent="saveSale"
  >
    <h2>Point of sale</h2>
    <label>Customer<select v-model="sale.customer_id">
      <option value="">Walk-in</option>
      <option v-for="c in customers" :key="c.public_id" :value="c.public_id">
        {{ c.name }}
      </option>
    </select></label><label>Item<select v-model="sale.inventory_item_id">
      <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
        {{ i.name }}
      </option>
    </select></label><label>Quantity<input
      v-model.number="sale.quantity"
      type="number"
      step="0.001"
    /></label><label>Unit price (৳)<input
      v-model.number="salePriceTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><button type="button" @click="addSaleLine">Add line</button>
    <div v-for="(line,index) in saleLines" :key="index" class="record">
      <span>{{ itemName(line.inventory_item_id) }} × {{ line.quantity }}</span><strong>৳ {{ ((line.quantity * line.unit_price_minor)/100).toFixed(2) }}</strong><button type="button" @click="saleLines.splice(index,1)">Remove</button>
    </div>
    <label>Discount (৳)<input v-model.number="saleDiscountTaka" type="number" step="0.01" min="0" /></label><strong>Total: ৳ {{ (saleTotal/100).toFixed(2) }}</strong><label>Paid (৳)<input
      v-model.number="salePaidTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><button class="primary">
      Complete sale
    </button>
    <section v-if="receipt" class="receipt print-receipt">
      <h2>Invoice {{ receipt.number }}</h2><p>{{ receipt.customer?.name || "Walk-in customer" }}</p>
      <div v-for="item in receipt.items" :key="item.inventory_item.name" class="record"><span>{{ item.inventory_item.name }} × {{ item.quantity }}</span><strong>৳ {{ (item.total_minor/100).toFixed(2) }}</strong></div>
      <p>Total ৳ {{ (receipt.total_minor/100).toFixed(2) }} · Paid ৳ {{ (receipt.paid_minor/100).toFixed(2) }} · Due ৳ {{ ((receipt.total_minor-receipt.paid_minor)/100).toFixed(2) }}</p>
      <button type="button" @click="printReceipt">Print invoice</button>
    </section>
  </form>
  <div
    v-else-if="mode === 'Expense'"
    class="workspace-grid"
  >
    <form class="panel form operation-form" @submit.prevent="saveExpense">
      <h2>Record expense</h2>
      <label>Category<input v-model="expense.category" /></label><label>Amount (৳)<input v-model.number="expenseAmountTaka" type="number" step="0.01" min="0" /></label><label>Date<input v-model="expense.expense_date" type="date" /></label><label>Note<textarea v-model="expense.note" /></label>
      <label>Receipt (PDF/JPG/PNG, max 5 MB)<input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="chooseExpenseAttachment"></label><button class="primary">
        Submit expense
      </button>
    </form>
    <section class="panel">
      <h2>Expense approvals</h2><p v-if="!expenses.length" class="empty">
        No expenses yet.
      </p>
      <article v-for="item in expenses" :key="item.id" class="record record--stock">
        <span><strong>{{ item.category }} · ৳ {{ (item.amount_minor/100).toFixed(2) }}</strong><small>{{ item.expense_date }} · {{ item.note }}</small><button v-for="attachment in item.attachments" :key="attachment.id" @click="downloadAttachment(attachment)">{{ attachment.name }}</button></span><em>{{ item.status }}</em><button v-if="item.status !== 'approved'" @click="approveExpense(item.id)">
          Approve
        </button>
      </article>
    </section>
  </div>
  <form
    v-else-if="mode === 'Rental'"
    class="panel form operation-form"
    @submit.prevent="saveRental"
  >
    <h2>Reserve rental</h2>
    <label>Customer<select v-model="rental.customer_id" required>
      <option v-for="c in customers" :key="c.public_id" :value="c.public_id">
        {{ c.name }}
      </option>
    </select></label><label>Item<select v-model="rental.inventory_item_id" required>
      <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
        {{ i.name }}
      </option>
    </select></label><label>Starts<input v-model="rental.starts_on" type="date" /></label><label>Due<input v-model="rental.due_on" type="date" /></label><label>Rent (৳)<input
      v-model.number="rentalRentTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><label>Deposit (৳)<input
      v-model.number="rentalDepositTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><button class="primary">
      Reserve
    </button>
  </form>
  <form
    v-else-if="mode === 'Attendance'"
    class="panel form operation-form"
    @submit.prevent="submit('/attendance', attendance)"
  >
    <h2>Attendance</h2>
    <label>Employee<select v-model="attendance.employee_id">
      <option v-for="e in employees" :key="e.id || e.public_id" :value="e.id || e.public_id">
        {{ e.name }}
      </option>
    </select></label><label>Date<input v-model="attendance.work_date" type="date" /></label><label>Status<select v-model="attendance.status">
      <option>present</option>
      <option>absent</option>
      <option>leave</option>
      <option>half_day</option>
    </select></label><button class="primary">
      Save attendance
    </button>
  </form>
  <form
    v-else-if="mode === 'Piece work'"
    class="panel form operation-form"
    @submit.prevent="submit('/work-entries', work)"
  >
    <h2>Piece work</h2>
    <label>Employee<select v-model="work.employee_id">
      <option v-for="e in employees" :key="e.id || e.public_id" :value="e.id || e.public_id">
        {{ e.name }}
      </option>
    </select></label><label>Work type<input v-model="work.work_type" /></label><label>Quantity<input
      v-model.number="work.quantity"
      type="number"
      step="0.01"
    /></label><label>Rate (৳)<input
      v-model.number="workRateTaka"
      type="number"
      step="0.01"
      min="0"
    /></label><button class="primary">
      Record work
    </button>
  </form>
  <div v-else-if="mode === 'Payout'" class="workspace-grid">
    <form class="panel form operation-form" @submit.prevent="savePayout">
      <h2>Pay piece-work wages</h2>
      <label>Employee<select v-model="payout.employee_id" required><option v-for="e in employees" :key="e.id || e.public_id" :value="e.id || e.public_id">{{ e.name }}</option></select></label>
      <label>Period from<input v-model="payout.period_from" type="date" required></label>
      <label>Period to<input v-model="payout.period_to" type="date" required></label>
      <label>Method<select v-model="payout.payment_method"><option value="cash">Cash</option><option value="mobile_banking">Mobile banking</option><option value="bank_transfer">Bank transfer</option></select></label>
      <label>Reference<input v-model="payout.reference"></label>
      <button class="primary">
        Create paid batch
      </button>
    </form>
    <section class="panel">
      <h2>Payout statements</h2><p v-if="!payouts.length" class="empty">
        No payouts yet.
      </p><article v-for="batch in payouts" :key="batch.id" class="record">
        <strong>{{ batch.employee.name }} · ৳ {{ (batch.total_minor/100).toFixed(2) }}</strong><span>{{ batch.number }} · {{ batch.period_from }} to {{ batch.period_to }}</span><small>{{ new Date(batch.paid_at).toLocaleString() }}</small>
      </article>
    </section>
  </div>
  <div v-else-if="mode === 'Rental returns'" class="panel">
    <h2>Rental contracts</h2>
    <p v-if="!rentals.length" class="empty">
      No rentals yet.
    </p>
    <article v-for="contract in rentals" :key="contract.id" class="record record--stock">
      <span><strong>{{ contract.number }} · {{ contract.customer.name }}</strong><small>Due {{ contract.due_on }} · {{ contract.items.map(item => `${item.inventory_item.name} × ${item.quantity}`).join(', ') }}</small></span>
      <em>{{ contract.status }}</em>
      <form v-if="contract.status !== 'returned'" class="form" @submit.prevent="returnRental(contract)">
        <label v-for="item in contract.items" :key="item.inventory_item.id">{{ item.inventory_item.name }} condition<input v-model="rentalReturn(contract).conditions[item.inventory_item.id]"></label>
        <label>Damage charge (৳)<input :value="(rentalReturn(contract).damage_charge_minor/100).toFixed(2)" @change="rentalReturn(contract).damage_charge_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)" type="number" min="0" step="0.01"></label>
        <label>Settlement note<input v-model="rentalReturn(contract).settlement_note"></label>
        <button>Settle and return</button>
      </form>
      <small v-else>Damage ৳ {{ (contract.damage_charge_minor/100).toFixed(2) }} · Deposit refunded ৳ {{ (contract.deposit_refunded_minor/100).toFixed(2) }} · Additional due ৳ {{ (contract.settlement_due_minor/100).toFixed(2) }}</small>
    </article>
  </div>
  <div v-else class="workspace-grid">
    <form class="panel form operation-form" @submit.prevent="saveSupplier">
      <h2>Add supplier</h2>
      <label>Name<input v-model="supplier.name" required></label>
      <label>Mobile<input v-model="supplier.mobile_number"></label>
      <label>Address<textarea v-model="supplier.address"></textarea></label>
      <button class="primary">
        Save supplier
      </button>
    </form>
    <section class="panel">
      <h2>Supplier directory</h2>
      <p v-if="!suppliers.length" class="empty">
        No suppliers yet.
      </p>
      <article v-for="item in suppliers" :key="item.public_id" class="record">
        <strong>{{ item.name }}</strong>
      </article>
    </section>
  </div>
</template>
