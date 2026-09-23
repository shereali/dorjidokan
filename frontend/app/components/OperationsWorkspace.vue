<script setup lang="ts">
import type { PrintData } from "./PrintTemplates.vue";

interface C {
  public_id?: string;
  id?: string;
  name: string;
  sku?: string;
  unit?: string;
  movements_sum_quantity?: number;
  mobile_number?: string;
  address?: string;
}

interface Payout {
  id: string;
  number: string;
  employee: { name: string };
  period_from: string;
  period_to: string;
  total_minor: number;
  paid_at: string;
}

interface RentalRecord {
  id: string;
  number: string;
  status: string;
  customer: { name: string; mobile_number?: string };
  starts_on?: string;
  due_on: string;
  rent_minor: number;
  deposit_minor: number;
  damage_charge_minor: number;
  deposit_refunded_minor: number;
  settlement_due_minor: number;
  settlement_note?: string;
  items: Array<{ inventory_item: { id: string; name: string }; quantity: number; condition_in?: string }>;
}

interface ExpenseRecord {
  id: string;
  category: string;
  amount_minor: number;
  note?: string;
  expense_date: string;
  status: string;
  attachments: Array<{ id: string; name: string; download_path: string }>;
}

interface SaleLine {
  inventory_item_id: string;
  quantity: number;
  unit_price_minor: number;
}

interface SaleReceipt {
  number: string;
  customer?: { name: string; mobile_number?: string };
  total_minor: number;
  paid_minor: number;
  discount_minor?: number;
  items: Array<{ inventory_item: { name: string }; quantity: number; total_minor: number }>;
}

const api = useTailorsApi();
const toast = useToast();
const config = useRuntimeConfig();

const mode = ref("Sale");
const message = ref("");
const error = ref("");
const today = new Date().toISOString().slice(0, 10);

const inventory = ref<C[]>([]);
const customers = ref<C[]>([]);
const employees = ref<C[]>([]);
const suppliers = ref<C[]>([]);
const payouts = ref<Payout[]>([]);
const rentals = ref<RentalRecord[]>([]);
const expenses = ref<ExpenseRecord[]>([]);
const saleLines = ref<SaleLine[]>([]);
const expenseAttachment = ref<File | null>(null);

const printData = ref<PrintData | null>(null);

// Point of Sale State
const posSearch = ref("");
const sale = reactive({
  customer_id: "",
  discount_minor: 0,
  paid_minor: 0,
  payment_method: "cash",
});

// Purchases State
const purchase = reactive({
  supplier_id: "",
  inventory_item_id: "",
  quantity: 1,
  unit_cost_minor: 0,
});

// Expense State
const expense = reactive({
  category: "General Expense (দোকান খরচ)",
  amount_minor: 0,
  note: "",
  expense_date: today,
});

// Rental State
const rental = reactive({
  customer_id: "",
  inventory_item_id: "",
  quantity: 1,
  starts_on: today,
  due_on: new Date(Date.now() + 3 * 86400000).toISOString().slice(0, 10),
  rent_minor: 150000,
  deposit_minor: 300000,
});

// Attendance State
const attendance = reactive({
  employee_id: "",
  work_date: today,
  status: "present",
});

// Piece-work State
const work = reactive({
  employee_id: "",
  work_type: "Stitching (পাঞ্জাবি সেলাই)",
  quantity: 1,
  rate_minor: 35000,
});

// Payout State
const payout = reactive({
  employee_id: "",
  period_from: new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10),
  period_to: today,
  payment_method: "cash",
  reference: "",
});

const supplier = reactive({ name: "", mobile_number: "", address: "" });
const rentalReturns = reactive<Record<string, { damage_charge_minor: number; settlement_note: string; conditions: Record<string, string> }>>({});

// Money computed helpers
const money = (obj: Record<string, unknown>, key: string) => ({
  get: () => ((obj[key] as number) || 0) / 100,
  set: (v: number) => { obj[key] = Math.round((v || 0) * 100); },
});

const purchaseCostTaka = money(purchase, "unit_cost_minor");
const salePaidTaka = money(sale, "paid_minor");
const saleDiscountTaka = money(sale, "discount_minor");
const expenseAmountTaka = money(expense, "amount_minor");
const rentalRentTaka = money(rental, "rent_minor");
const rentalDepositTaka = money(rental, "deposit_minor");
const workRateTaka = money(work, "rate_minor");

const filteredInventory = computed(() => {
  if (!posSearch.value.trim()) return inventory.value;
  const q = posSearch.value.toLowerCase();
  return inventory.value.filter(
    (i) => i.name.toLowerCase().includes(q) || (i.sku && i.sku.toLowerCase().includes(q))
  );
});

const saleSubtotal = computed(() =>
  saleLines.value.reduce((sum, line) => sum + line.quantity * line.unit_price_minor, 0)
);

const saleTotal = computed(() =>
  Math.max(0, saleSubtotal.value - sale.discount_minor)
);

async function catalogs() {
  try {
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
  } catch {
    // Non-blocking catch
  }
}

function addProductToCart(item: C) {
  const itemId = item.public_id || item.id || "";
  const existing = saleLines.value.find((l) => l.inventory_item_id === itemId);
  if (existing) {
    existing.quantity += 1;
  } else {
    saleLines.value.push({
      inventory_item_id: itemId,
      quantity: 1,
      unit_price_minor: 45000, // ৳ 450 default or customize
    });
  }
  // Auto set paid to total
  sale.paid_minor = saleTotal.value;
}

function removeSaleLine(index: number) {
  saleLines.value.splice(index, 1);
  sale.paid_minor = saleTotal.value;
}

function getItemName(id: string) {
  return inventory.value.find((i) => (i.public_id || i.id) === id)?.name || "Item";
}

async function completeSale() {
  error.value = "";
  if (!saleLines.value.length) {
    error.value = "Cart is empty. Select items to sell.";
    return;
  }
  try {
    const res = await api.request<{ sale: SaleReceipt }>("/sales", {
      method: "POST",
      body: {
        customer_id: sale.customer_id || null,
        sale_type: "direct",
        discount_minor: sale.discount_minor,
        paid_minor: sale.paid_minor,
        payment_method: sale.payment_method,
        items: saleLines.value,
      },
    });

    const receipt = res.data.sale;
    toast.success(`Sale #${receipt.number} completed!`);

    // Prepare print data
    printData.value = {
      type: "pos_invoice",
      shopName: "সুতো TAILORS & FABRICS",
      orderNumber: receipt.number,
      customerName: receipt.customer?.name || "Walk-in Customer",
      customerPhone: receipt.customer?.mobile_number,
      totalAmount: receipt.total_minor / 100,
      advancePaid: receipt.paid_minor / 100,
      dueAmount: (receipt.total_minor - receipt.paid_minor) / 100,
      discount: (receipt.discount_minor || 0) / 100,
      items: receipt.items.map((i) => ({
        name: i.inventory_item.name,
        quantity: i.quantity,
        price: i.total_minor / 100,
      })),
    };

    saleLines.value = [];
    sale.discount_minor = 0;
    sale.paid_minor = 0;
    await catalogs();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not complete sale.";
    toast.error(error.value);
  }
}

async function savePurchase() {
  error.value = "";
  try {
    await api.request("/purchases", {
      method: "POST",
      body: {
        supplier_id: purchase.supplier_id || null,
        items: [
          {
            inventory_item_id: purchase.inventory_item_id,
            quantity: purchase.quantity,
            unit_cost_minor: purchase.unit_cost_minor,
          },
        ],
      },
    });
    toast.success("Stock received successfully.");
    await catalogs();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "Could not receive purchase.");
  }
}

function chooseExpenseAttachment(event: Event) {
  expenseAttachment.value = (event.target as HTMLInputElement).files?.[0] || null;
}

async function saveExpense() {
  try {
    const res = await api.request<{ expense: ExpenseRecord }>("/expenses", {
      method: "POST",
      body: expense,
    });
    if (expenseAttachment.value) {
      const form = new FormData();
      form.append("attachment", expenseAttachment.value);
      await api.request(`/expenses/${res.data.expense.id}/attachments`, {
        method: "POST",
        body: form,
      });
    }
    expenseAttachment.value = null;
    toast.success("Expense submitted.");
    await catalogs();
  } catch (e: any) {
    toast.error("Could not save expense.");
  }
}

async function approveExpense(id: string) {
  await api.request(`/expenses/${id}/approve`, { method: "POST" });
  toast.success("Expense approved.");
  await catalogs();
}

async function downloadAttachment(att: { name: string; download_path: string }) {
  const res = await $fetch.raw(`${config.public.apiBase}${att.download_path}`, {
    credentials: "include",
    headers: { "X-Tenant": api.tenant.value },
  });
  const url = URL.createObjectURL(res._data as Blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = att.name;
  a.click();
  URL.revokeObjectURL(url);
}

async function saveRental() {
  try {
    await api.request("/rentals", {
      method: "POST",
      body: {
        customer_id: rental.customer_id,
        starts_on: rental.starts_on,
        due_on: rental.due_on,
        rent_minor: rental.rent_minor,
        deposit_minor: rental.deposit_minor,
        items: [{ inventory_item_id: rental.inventory_item_id, quantity: rental.quantity }],
      },
    });
    toast.success("Rental reserved successfully.");
    await catalogs();
  } catch (e: any) {
    toast.error("Could not reserve rental.");
  }
}

function getRentalReturnState(c: RentalRecord) {
  return (rentalReturns[c.id] ||= {
    damage_charge_minor: 0,
    settlement_note: "",
    conditions: Object.fromEntries(c.items.map((i) => [i.inventory_item.id, "Good"])),
  });
}

async function returnRental(c: RentalRecord) {
  try {
    await api.request(`/rentals/${c.id}/return`, {
      method: "POST",
      body: getRentalReturnState(c),
    });
    toast.success("Rental returned and deposit settled.");
    await catalogs();
  } catch (e: any) {
    toast.error("Could not process rental return.");
  }
}

async function saveAttendance() {
  try {
    await api.request("/attendance", { method: "POST", body: attendance });
    toast.success("Attendance saved.");
  } catch (e: any) {
    toast.error("Could not save attendance.");
  }
}

async function saveWork() {
  try {
    await api.request("/work-entries", { method: "POST", body: work });
    toast.success("Piece-work logged for craftsman.");
  } catch (e: any) {
    toast.error("Could not log piece-work.");
  }
}

async function savePayout() {
  try {
    await api.request("/payouts", { method: "POST", body: payout });
    toast.success("Wage payout statement generated and settled.");
    await catalogs();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "Could not generate payout.");
  }
}

async function saveSupplier() {
  try {
    await api.request("/suppliers", { method: "POST", body: supplier });
    toast.success("Supplier added.");
    Object.assign(supplier, { name: "", mobile_number: "", address: "" });
    await catalogs();
  } catch (e: any) {
    toast.error("Could not save supplier.");
  }
}

onMounted(catalogs);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKSHOP OPERATIONS</p>
        <h1>Operations Hub</h1>
        <p>POS fabric sales, stock receiving, daily expenses, rentals, and workforce piece-wages.</p>
      </div>

      <div class="tabs">
        <button :class="{ active: mode === 'Sale' }" @click="mode = 'Sale'">
          <NavIcon name="Cash" /> Fabric & POS Sale
        </button>
        <button :class="{ active: mode === 'Purchase' }" @click="mode = 'Purchase'">
          <NavIcon name="Inventory" /> Purchases
        </button>
        <button :class="{ active: mode === 'Expense' }" @click="mode = 'Expense'">
          <NavIcon name="Accounting" /> Expenses
        </button>
        <button :class="{ active: mode === 'Rental' }" @click="mode = 'Rental'">
          <NavIcon name="Garments" /> Rentals
        </button>
        <button :class="{ active: mode === 'Workforce' }" @click="mode = 'Workforce'">
          <NavIcon name="Karigars" /> Karigar Wages
        </button>
        <button :class="{ active: mode === 'Suppliers' }" @click="mode = 'Suppliers'">
          <NavIcon name="Customers" /> Suppliers
        </button>
      </div>
    </header>

    <!-- TAB 1: POINT OF SALE (POS) -->
    <div v-if="mode === 'Sale'" class="pos-layout">
      <!-- Product Catalog Section -->
      <section class="panel">
        <div class="toolbar">
          <input
            v-model="posSearch"
            placeholder="Search fabric or goods by name/SKU..."
            inputmode="search"
          />
        </div>

        <div class="pos-products">
          <div
            v-for="item in filteredInventory"
            :key="item.public_id || item.id"
            class="pos-card"
            @click="addProductToCart(item)"
          >
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block;color:var(--muted)">SKU: {{ item.sku || 'N/A' }}</small>
            </div>
            <div class="pos-card-bottom">
              <span class="pos-stock">{{ item.movements_sum_quantity || 0 }} {{ item.unit }} in stock</span>
              <button type="button" class="mini-btn">+ Add</button>
            </div>
          </div>
        </div>

        <p v-if="!filteredInventory.length" class="empty">No inventory products found.</p>
      </section>

      <!-- Cart & Checkout Panel -->
      <section class="pos-cart">
        <h2>Sale Cart (বিলিং কার্ট)</h2>

        <label>Customer (Optional)
          <select v-model="sale.customer_id">
            <option value="">Walk-in Customer (খুচরা ক্রেতা)</option>
            <option v-for="c in customers" :key="c.public_id" :value="c.public_id">
              {{ c.name }} ({{ c.mobile_number }})
            </option>
          </select>
        </label>

        <!-- Cart items list -->
        <div class="cart-items-list">
          <div v-for="(line, idx) in saleLines" :key="idx" class="cart-line-row">
            <div>
              <strong>{{ getItemName(line.inventory_item_id) }}</strong>
              <div class="cart-qty-stepper">
                <button type="button" @click="line.quantity = Math.max(1, line.quantity - 1)">-</button>
                <span>{{ line.quantity }}</span>
                <button type="button" @click="line.quantity += 1">+</button>
              </div>
            </div>
            <div class="cart-line-price">
              <input
                :value="(line.unit_price_minor / 100).toFixed(2)"
                type="number"
                step="1"
                min="0"
                @input="line.unit_price_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
              />
              <button type="button" class="cart-remove-btn" @click="removeSaleLine(idx)">&times;</button>
            </div>
          </div>

          <p v-if="!saleLines.length" class="empty" style="padding:1rem 0">Cart is empty.</p>
        </div>

        <!-- Totals summary -->
        <div class="cart-summary-block">
          <label>Discount (৳)
            <input v-model.number="saleDiscountTaka" type="number" step="10" min="0" />
          </label>
          <div class="cart-total-row">
            <span>Payable Total:</span>
            <strong>৳ {{ (saleTotal / 100).toFixed(2) }}</strong>
          </div>
          <label>Amount Received (৳)
            <input v-model.number="salePaidTaka" type="number" step="10" min="0" />
          </label>
          <label>Payment Method
            <select v-model="sale.payment_method">
              <option value="cash">Cash (নগদ)</option>
              <option value="mobile_banking">bKash / Nagad (বিকাশ / নগদ)</option>
              <option value="card">Card (কার্ড)</option>
            </select>
          </label>
        </div>

        <button class="primary" :disabled="!saleLines.length" @click="completeSale">
          Complete Sale & Print Invoice
        </button>
      </section>
    </div>

    <!-- TAB 2: PURCHASES / STOCK RECEIVING -->
    <div v-else-if="mode === 'Purchase'" class="workspace-grid">
      <form class="panel form" @submit.prevent="savePurchase">
        <h2>Receive Fabric & Stock Purchase (ক্রয় রসিদ)</h2>
        <label>Supplier (সরবরাহকারী)
          <select v-model="purchase.supplier_id">
            <option value="">No Supplier / Cash Purchase</option>
            <option v-for="s in suppliers" :key="s.public_id" :value="s.public_id">
              {{ s.name }}
            </option>
          </select>
        </label>
        <label>Inventory Item *
          <select v-model="purchase.inventory_item_id" required>
            <option value="">Select Item</option>
            <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
              {{ i.name }} ({{ i.unit }})
            </option>
          </select>
        </label>
        <label>Quantity *
          <input v-model.number="purchase.quantity" type="number" step="0.1" min="0.1" required />
        </label>
        <label>Unit Cost (৳) *
          <input v-model.number="purchaseCostTaka" type="number" step="10" min="0" required />
        </label>
        <button class="primary">Receive Stock to Inventory</button>
      </form>

      <section class="panel">
        <h2>Recent Inventory Balances</h2>
        <div v-for="item in inventory.slice(0, 8)" :key="item.public_id" class="record record--stock">
          <div>
            <strong>{{ item.name }}</strong>
            <small>SKU: {{ item.sku || '—' }}</small>
          </div>
          <b>{{ item.movements_sum_quantity || 0 }} {{ item.unit }}</b>
        </div>
      </section>
    </div>

    <!-- TAB 3: DAILY EXPENSES -->
    <div v-else-if="mode === 'Expense'" class="workspace-grid">
      <form class="panel form" @submit.prevent="saveExpense">
        <h2>Record Workshop Expense (দৈনন্দিন খরচ)</h2>
        <label>Category (খরচের খাত)
          <input v-model="expense.category" placeholder="e.g., Shop Rent, Electricity, Tea/Snacks, Thread/Needle" required />
        </label>
        <label>Amount (৳) *
          <input v-model.number="expenseAmountTaka" type="number" step="1" min="1" required />
        </label>
        <label>Expense Date
          <input v-model="expense.expense_date" type="date" required />
        </label>
        <label>Note / Remarks
          <textarea v-model="expense.note" placeholder="Expense description..."></textarea>
        </label>
        <label>Receipt Voucher Photo / PDF
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="chooseExpenseAttachment" />
        </label>
        <button class="primary">Submit Expense</button>
      </form>

      <section class="panel">
        <h2>Expense History & Approvals</h2>
        <p v-if="!expenses.length" class="empty">No expenses logged yet.</p>
        <div v-for="item in expenses" :key="item.id" class="record record--stock">
          <div>
            <strong>{{ item.category }} · ৳ {{ (item.amount_minor / 100).toFixed(2) }}</strong>
            <small>{{ item.expense_date }} · {{ item.note || 'No note' }}</small>
            <div v-if="item.attachments.length" class="actions" style="margin-top:0.3rem">
              <button
                v-for="att in item.attachments"
                :key="att.id"
                class="mini-btn"
                type="button"
                @click="downloadAttachment(att)"
              >
                📎 {{ att.name }}
              </button>
            </div>
          </div>
          <span class="status" :class="item.status === 'approved' ? 'status--ready' : 'status--pending'">
            {{ item.status }}
          </span>
          <button v-if="item.status !== 'approved'" class="mini-btn" @click="approveExpense(item.id)">
            Approve
          </button>
        </div>
      </section>
    </div>

    <!-- TAB 4: RENTALS & RETURNS -->
    <div v-else-if="mode === 'Rental'" class="workspace-grid">
      <form class="panel form" @submit.prevent="saveRental">
        <h2>Book Garment Rental (শেরওয়ানি / স্যুট ভাড়া)</h2>
        <label>Customer *
          <select v-model="rental.customer_id" required>
            <option value="">Select Customer</option>
            <option v-for="c in customers" :key="c.public_id" :value="c.public_id">
              {{ c.name }} ({{ c.mobile_number }})
            </option>
          </select>
        </label>
        <label>Rental Item *
          <select v-model="rental.inventory_item_id" required>
            <option value="">Select Item</option>
            <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
              {{ i.name }}
            </option>
          </select>
        </label>
        <div class="form-grid-2">
          <label>Start Date
            <input v-model="rental.starts_on" type="date" required />
          </label>
          <label>Return Due Date
            <input v-model="rental.due_on" type="date" required />
          </label>
        </div>
        <div class="form-grid-2">
          <label>Rental Fee (৳)
            <input v-model.number="rentalRentTaka" type="number" step="10" min="0" required />
          </label>
          <label>Security Deposit (৳)
            <input v-model.number="rentalDepositTaka" type="number" step="10" min="0" required />
          </label>
        </div>
        <button class="primary">Confirm Rental Booking</button>
      </form>

      <section class="panel">
        <h2>Active Rentals & Returns</h2>
        <p v-if="!rentals.length" class="empty">No active rental contracts.</p>
        <div v-for="c in rentals" :key="c.id" class="record" style="flex-direction:column;align-items:stretch">
          <div style="display:flex;justify-content:space-between">
            <div>
              <strong>#{{ c.number }} · {{ c.customer.name }}</strong>
              <small style="display:block">Due: {{ c.due_on }} · Rent ৳ {{ (c.rent_minor / 100).toFixed(2) }} · Deposit ৳ {{ (c.deposit_minor / 100).toFixed(2) }}</small>
            </div>
            <span class="status" :class="c.status === 'returned' ? 'status--ready' : 'status--pending'">
              {{ c.status }}
            </span>
          </div>

          <form v-if="c.status !== 'returned'" class="form" style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px dashed var(--line)" @submit.prevent="returnRental(c)">
            <label>Damage Deduction (৳)
              <input
                :value="(getRentalReturnState(c).damage_charge_minor / 100).toFixed(2)"
                type="number"
                min="0"
                step="10"
                @input="getRentalReturnState(c).damage_charge_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
              />
            </label>
            <label>Settlement Note
              <input v-model="getRentalReturnState(c).settlement_note" placeholder="Condition remarks..." />
            </label>
            <button class="primary">Settle & Mark Returned</button>
          </form>
        </div>
      </section>
    </div>

    <!-- TAB 5: WORKFORCE PIECE-WORK & WAGES -->
    <div v-else-if="mode === 'Workforce'" class="workspace-grid">
      <div>
        <!-- Attendance Form -->
        <form class="panel form" style="margin-bottom:1.5rem" @submit.prevent="saveAttendance">
          <h2>Daily Karigar Attendance (হাজিরা)</h2>
          <label>Employee / Karigar
            <select v-model="attendance.employee_id" required>
              <option value="">Select Worker</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <div class="form-grid-2">
            <label>Date<input v-model="attendance.work_date" type="date" required /></label>
            <label>Status
              <select v-model="attendance.status">
                <option value="present">Present (উপস্থিত)</option>
                <option value="absent">Absent (অনুপস্থিত)</option>
                <option value="half_day">Half Day (অর্ধ দিবস)</option>
                <option value="leave">Leave (ছুটি)</option>
              </select>
            </label>
          </div>
          <button class="primary">Save Attendance</button>
        </form>

        <!-- Piece-work logger -->
        <form class="panel form" @submit.prevent="saveWork">
          <h2>Log Piece-Work (পিস রেট কাজ রেকর্ড)</h2>
          <label>Karigar
            <select v-model="work.employee_id" required>
              <option value="">Select Worker</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <label>Work Type<input v-model="work.work_type" placeholder="e.g. Panjabi Stitching, Pant Cutting" required /></label>
          <div class="form-grid-2">
            <label>Quantity<input v-model.number="work.quantity" type="number" min="1" step="1" required /></label>
            <label>Rate per piece (৳)<input v-model.number="workRateTaka" type="number" min="0" step="10" required /></label>
          </div>
          <button class="primary">Record Piece Work</button>
        </form>
      </div>

      <!-- Payouts generator -->
      <section class="panel">
        <form class="form" @submit.prevent="savePayout">
          <h2>Generate Karigar Wage Payout (মজুরি বিল পরিশোধ)</h2>
          <label>Worker
            <select v-model="payout.employee_id" required>
              <option value="">Select Worker</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <div class="form-grid-2">
            <label>Period From<input v-model="payout.period_from" type="date" required /></label>
            <label>Period To<input v-model="payout.period_to" type="date" required /></label>
          </div>
          <label>Payment Method
            <select v-model="payout.payment_method">
              <option value="cash">Cash (নগদ)</option>
              <option value="mobile_banking">bKash / Nagad</option>
              <option value="bank_transfer">Bank</option>
            </select>
          </label>
          <button class="primary">Generate & Pay Batch</button>
        </form>

        <h3 style="margin-top:1.5rem">Recent Payout Vouchers</h3>
        <p v-if="!payouts.length" class="empty">No payout statements generated.</p>
        <div v-for="p in payouts" :key="p.id" class="record">
          <div>
            <strong>{{ p.employee.name }} · ৳ {{ (p.total_minor / 100).toFixed(2) }}</strong>
            <small>{{ p.number }} · {{ p.period_from }} to {{ p.period_to }}</small>
          </div>
          <small>{{ new Date(p.paid_at).toLocaleDateString() }}</small>
        </div>
      </section>
    </div>

    <!-- TAB 6: SUPPLIERS DIRECTORY -->
    <div v-else class="workspace-grid">
      <form class="panel form" @submit.prevent="saveSupplier">
        <h2>Add Supplier (সরবরাহকারী যোগ করুন)</h2>
        <label>Supplier / Company Name *<input v-model="supplier.name" required /></label>
        <label>Mobile Number<input v-model="supplier.mobile_number" placeholder="01XXXXXXXXX" /></label>
        <label>Address<textarea v-model="supplier.address"></textarea></label>
        <button class="primary">Save Supplier</button>
      </form>

      <section class="panel">
        <h2>Supplier Directory</h2>
        <p v-if="!suppliers.length" class="empty">No suppliers registered.</p>
        <div v-for="s in suppliers" :key="s.public_id" class="record">
          <div>
            <strong>{{ s.name }}</strong>
            <small style="display:block">📞 {{ (s as any).mobile_number || 'No phone' }} · 📍 {{ (s as any).address || 'No address' }}</small>
          </div>
        </div>
      </section>
    </div>

    <!-- GLOBAL PRINT MODAL -->
    <PrintTemplates :data="printData" @close="printData = null" />
  </div>
</template>

<style scoped>
.cart-items-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  max-height: 14rem;
  overflow-y: auto;
  border-top: 1px solid var(--line);
  border-bottom: 1px solid var(--line);
  padding: 0.75rem 0;
}

.cart-line-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  background: var(--paper);
  padding: 0.4rem 0.6rem;
  border-radius: var(--radius-sm);
}

.cart-qty-stepper {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-top: 0.2rem;
}

.cart-qty-stepper button {
  width: 1.4rem;
  height: 1.4rem;
  padding: 0;
  border-radius: 3px;
  border: 1px solid var(--line);
  background: var(--surface);
  cursor: pointer;
}

.cart-line-price {
  display: flex;
  align-items: center;
  gap: 0.35rem;
}

.cart-line-price input {
  width: 5rem;
  text-align: right;
  padding: 0.25rem 0.4rem;
  border: 1px solid var(--line);
  border-radius: 3px;
}

.cart-remove-btn {
  background: transparent;
  border: none;
  color: var(--status-cancelled);
  font-size: 1.2rem;
  cursor: pointer;
}

.cart-summary-block {
  display: grid;
  gap: 0.5rem;
  background: var(--paper);
  padding: 0.85rem;
  border-radius: var(--radius-sm);
}

.cart-total-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.15rem;
  border-top: 1px dashed var(--line);
  padding-top: 0.4rem;
}

@media (max-width: 48rem) {
  .tabs {
    overflow-x: auto;
    width: 100%;
    padding-bottom: 0.35rem;
    flex-wrap: nowrap;
    -webkit-overflow-scrolling: touch;
  }
  .tabs button {
    white-space: nowrap;
  }
}
</style>
