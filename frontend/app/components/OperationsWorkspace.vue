<script setup lang="ts">
const { tr } = usePlatformFormat();
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

// Each tab is unlocked by the same permission the API checks for it.
const tabAccess: Record<string, string[]> = {
  Sale: ["sales.manage"],
  Purchase: ["purchases.manage"],
  Expense: ["expenses.view", "expenses.manage"],
  Rental: ["rentals.manage"],
  Workforce: ["employees.view", "payouts.view", "payouts.manage", "attendance.manage", "work.manage"],
  Suppliers: ["purchases.manage", "inventory.view"],
};
const canTab = (name: string) => api.can(...(tabAccess[name] ?? []));
const mode = ref(Object.keys(tabAccess).find(canTab) ?? "Sale");
const message = ref("");
const error = ref("");
const today = new Date().toISOString().slice(0, 10);

const inventory = ref<C[]>([]);
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
    // Only ask for what this role may read, and let one failing list (e.g. a plan without rentals) not hide the rest.
    const get = <T,>(allowed: boolean, path: string) => (allowed ? api.request<T>(path).catch(() => null) : Promise.resolve(null));
    const [i, e, s, p, r, x] = await Promise.all([
      get<{ items: C[] }>(api.can("inventory.view", "inventory.manage", "sales.manage", "purchases.manage"), "/inventory"),
      get<{ employees: C[] }>(api.can("employees.view", "employees.manage", "orders.assign"), "/employees"),
      get<{ items: C[] }>(api.can("inventory.view", "purchases.manage"), "/suppliers"),
      get<{ items: Payout[] }>(api.can("payouts.view", "payouts.manage"), "/payouts"),
      get<{ items: RentalRecord[] }>(api.can("rentals.manage"), "/rentals"),
      get<{ items: ExpenseRecord[] }>(api.can("expenses.view", "expenses.manage"), "/expenses"),
    ]);
    inventory.value = i?.data.items ?? [];
    employees.value = e?.data.employees ?? [];
    suppliers.value = s?.data.items ?? [];
    payouts.value = p?.data.items ?? [];
    rentals.value = r?.data.items ?? [];
    expenses.value = x?.data.items ?? [];
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
    error.value = tr("Cart is empty. Add items to sell.", "কার্ট খালি। বিক্রয়ের পণ্য যোগ করুন।");
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
    toast.success(tr(`Sale #${receipt.number} completed.`, `বিক্রয় #${receipt.number} সম্পন্ন হয়েছে।`));

    // Prepare print data
    printData.value = {
      type: "pos_invoice",
      shopName: "দর্জিদোকান",
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
    error.value = e?.data?.errors?.[0]?.message || tr("Could not complete the sale.", "বিক্রয় সম্পন্ন করা যায়নি।");
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
    toast.success(tr("Stock received.", "স্টক গ্রহণ হয়েছে।"));
    await catalogs();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || tr("Could not save the purchase.", "ক্রয় সংরক্ষণ করা যায়নি।"));
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
    toast.success(tr("Expense saved.", "খরচ সংরক্ষিত হয়েছে।"));
    await catalogs();
  } catch (e: any) {
    toast.error(tr("Could not save the expense.", "খরচ সংরক্ষণ করা যায়নি।"));
  }
}

async function approveExpense(id: string) {
  await api.request(`/expenses/${id}/approve`, { method: "POST" });
  toast.success(tr("Expense approved.", "খরচ অনুমোদিত হয়েছে।"));
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
    toast.success(tr("Rental booked.", "ভাড়া বুক হয়েছে।"));
    await catalogs();
  } catch (e: any) {
    toast.error(tr("Could not book the rental.", "ভাড়া বুক করা যায়নি।"));
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
    toast.success(tr("Rental returned and deposit settled.", "ভাড়া ফেরত ও জামানত নিষ্পত্তি হয়েছে।"));
    await catalogs();
  } catch (e: any) {
    toast.error(tr("Could not process the return.", "ফেরত প্রক্রিয়া করা যায়নি।"));
  }
}

async function saveAttendance() {
  try {
    await api.request("/attendance", { method: "POST", body: attendance });
    toast.success(tr("Attendance saved.", "হাজিরা সংরক্ষিত হয়েছে।"));
  } catch (e: any) {
    toast.error(tr("Could not save attendance.", "হাজিরা সংরক্ষণ করা যায়নি।"));
  }
}

async function saveWork() {
  try {
    await api.request("/work-entries", { method: "POST", body: work });
    toast.success(tr("Piece-work saved.", "পিস-রেট কাজ সংরক্ষিত হয়েছে।"));
  } catch (e: any) {
    toast.error(tr("Could not save piece-work.", "কাজ সংরক্ষণ করা যায়নি।"));
  }
}

async function savePayout() {
  try {
    await api.request("/payouts", { method: "POST", body: payout });
    toast.success(tr("Wage payout created and settled.", "মজুরি পেআউট তৈরি ও পরিশোধ হয়েছে।"));
    await catalogs();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || tr("Could not create the payout.", "পেআউট তৈরি করা যায়নি।"));
  }
}

async function saveSupplier() {
  try {
    await api.request("/suppliers", { method: "POST", body: supplier });
    toast.success(tr("Supplier added.", "সরবরাহকারী যোগ হয়েছে।"));
    Object.assign(supplier, { name: "", mobile_number: "", address: "" });
    await catalogs();
  } catch (e: any) {
    toast.error(tr("Could not save the supplier.", "সরবরাহকারী সংরক্ষণ করা যায়নি।"));
  }
}

onMounted(catalogs);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("WORKSHOP OPERATIONS", "ওয়ার্কশপ কার্যক্রম") }}</p>
        <h1>{{ tr("Operations", "কার্যক্রম") }}</h1>
        <p>{{ tr("Fabric sales, stock receiving, daily expenses, rentals and karigar piece-wages.", "কাপড় বিক্রয়, স্টক গ্রহণ, দৈনিক খরচ, ভাড়া ও কারিগরের পিস-রেট মজুরি।") }}</p>
      </div>

      <div class="tabs">
        <button v-if="canTab('Sale')" :class="{ active: mode === 'Sale' }" @click="mode = 'Sale'">
          <NavIcon name="Cash" /> {{ tr("Fabric sale (POS)", "কাপড় বিক্রয় (POS)") }}
        </button>
        <button v-if="canTab('Purchase')" :class="{ active: mode === 'Purchase' }" @click="mode = 'Purchase'">
          <NavIcon name="Inventory" /> {{ tr("Purchases", "ক্রয়") }}
        </button>
        <button v-if="canTab('Expense')" :class="{ active: mode === 'Expense' }" @click="mode = 'Expense'">
          <NavIcon name="Accounting" /> {{ tr("Expenses", "খরচ") }}
        </button>
        <button v-if="canTab('Rental')" :class="{ active: mode === 'Rental' }" @click="mode = 'Rental'">
          <NavIcon name="Garments" /> {{ tr("Rentals", "ভাড়া") }}
        </button>
        <button v-if="canTab('Workforce')" :class="{ active: mode === 'Workforce' }" @click="mode = 'Workforce'">
          <NavIcon name="Karigars" /> {{ tr("Karigar wages", "কারিগরের মজুরি") }}
        </button>
        <button v-if="canTab('Suppliers')" :class="{ active: mode === 'Suppliers' }" @click="mode = 'Suppliers'">
          <NavIcon name="Customers" /> {{ tr("Suppliers", "সরবরাহকারী") }}
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
            :placeholder="tr('Search by name or SKU…', 'নাম বা SKU দিয়ে খুঁজুন…')"
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
              <small style="display:block;color:var(--muted)">SKU: {{ item.sku || '—' }}</small>
            </div>
            <div class="pos-card-bottom">
              <span class="pos-stock">{{ item.movements_sum_quantity || 0 }} {{ item.unit }} {{ tr("in stock", "স্টকে") }}</span>
              <button type="button" class="mini-btn">{{ tr("+ Add", "+ যোগ") }}</button>
            </div>
          </div>
        </div>

        <p v-if="!filteredInventory.length" class="empty">{{ tr("No products found.", "কোনো পণ্য পাওয়া যায়নি।") }}</p>
      </section>

      <!-- Cart & Checkout Panel -->
      <section class="pos-cart">
        <h2>{{ tr("Sale cart", "বিলিং কার্ট") }}</h2>

        <label>{{ tr("Customer (optional)", "গ্রাহক (ঐচ্ছিক)") }}
          <CustomerPicker v-model="sale.customer_id" empty-label="Walk-in Customer (খুচরা ক্রেতা)" :placeholder="tr('Walk-in — or search name / mobile', 'খুচরা ক্রেতা — বা নাম / মোবাইল খুঁজুন')" />
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
              <button type="button" class="cart-remove-btn" @click="removeSaleLine(idx)">{{ tr("×", "×") }}</button>
            </div>
          </div>

          <p v-if="!saleLines.length" class="empty" style="padding:1rem 0">{{ tr("Cart is empty.", "কার্ট খালি।") }}</p>
        </div>

        <!-- Totals summary -->
        <div class="cart-summary-block">
          <label>{{ tr("Discount (৳)", "ছাড় (৳)") }}
            <input v-model.number="saleDiscountTaka" type="number" step="10" min="0" />
          </label>
          <div class="cart-total-row">
            <span>{{ tr("Total payable:", "মোট প্রদেয়:") }}</span>
            <strong>৳ {{ (saleTotal / 100).toFixed(2) }}</strong>
          </div>
          <label>{{ tr("Amount received (৳)", "প্রাপ্ত টাকা (৳)") }}
            <input v-model.number="salePaidTaka" type="number" step="10" min="0" />
          </label>
          <label>{{ tr("Payment method", "পেমেন্ট পদ্ধতি") }}
            <select v-model="sale.payment_method">
              <option value="cash">{{ tr("Cash", "নগদ") }}</option>
              <option value="mobile_banking">{{ tr("bKash / Nagad", "বিকাশ / নগদ") }}</option>
              <option value="card">{{ tr("Card", "কার্ড") }}</option>
            </select>
          </label>
        </div>

        <button class="primary" :disabled="!saleLines.length" @click="completeSale">
          {{ tr("Complete sale & print invoice", "বিক্রয় সম্পন্ন ও ইনভয়েস প্রিন্ট") }}
        </button>
      </section>
    </div>

    <!-- TAB 2: PURCHASES / STOCK RECEIVING -->
    <div v-else-if="mode === 'Purchase'" class="workspace-grid">
      <form class="panel form" @submit.prevent="savePurchase">
        <h2>{{ tr("Receive stock purchase", "ক্রয়কৃত স্টক গ্রহণ") }}</h2>
        <label>{{ tr("Supplier", "সরবরাহকারী") }}
          <select v-model="purchase.supplier_id">
            <option value="">{{ tr("No supplier / cash purchase", "সরবরাহকারী নেই / নগদ ক্রয়") }}</option>
            <option v-for="s in suppliers" :key="s.public_id" :value="s.public_id">
              {{ s.name }}
            </option>
          </select>
        </label>
        <label>{{ tr("Item *", "পণ্য *") }}
          <select v-model="purchase.inventory_item_id" required>
            <option value="">{{ tr("Select item", "পণ্য বাছুন") }}</option>
            <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
              {{ i.name }} ({{ i.unit }})
            </option>
          </select>
        </label>
        <label>{{ tr("Quantity *", "পরিমাণ *") }}
          <input v-model.number="purchase.quantity" type="number" step="0.1" min="0.1" required />
        </label>
        <label>{{ tr("Unit cost (৳) *", "একক দাম (৳) *") }}
          <input v-model.number="purchaseCostTaka" type="number" step="10" min="0" required />
        </label>
        <button class="primary">{{ tr("Add to stock", "স্টকে যোগ করুন") }}</button>
      </form>

      <section class="panel">
        <h2>{{ tr("Current stock", "বর্তমান স্টক") }}</h2>
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
        <h2>{{ tr("Record expense", "খরচ লিখুন") }}</h2>
        <label>{{ tr("Category", "খরচের খাত") }}
          <input v-model="expense.category" :placeholder="tr('e.g. shop rent, electricity, tea, thread', 'যেমন: দোকান ভাড়া, বিদ্যুৎ, চা-নাস্তা, সুতা')" required />
        </label>
        <label>{{ tr("Amount (৳) *", "পরিমাণ (৳) *") }}
          <input v-model.number="expenseAmountTaka" type="number" step="1" min="1" required />
        </label>
        <label>{{ tr("Date", "তারিখ") }}
          <input v-model="expense.expense_date" type="date" required />
        </label>
        <label>{{ tr("Note", "মন্তব্য") }}
          <textarea v-model="expense.note" :placeholder="tr('Expense description…', 'খরচের বিবরণ…')"></textarea>
        </label>
        <label>{{ tr("Receipt photo / PDF", "রসিদের ছবি / PDF") }}
          <input type="file" accept=".pdf,.jpg,.jpeg,.png" @change="chooseExpenseAttachment" />
        </label>
        <button class="primary">{{ tr("Save expense", "খরচ সংরক্ষণ") }}</button>
      </form>

      <section class="panel">
        <h2>{{ tr("Expense history & approvals", "খরচের ইতিহাস ও অনুমোদন") }}</h2>
        <p v-if="!expenses.length" class="empty">{{ tr("No expenses yet.", "এখনো কোনো খরচ নেই।") }}</p>
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
            {{ tr("Approve", "অনুমোদন") }}
          </button>
        </div>
      </section>
    </div>

    <!-- TAB 4: RENTALS & RETURNS -->
    <div v-else-if="mode === 'Rental'" class="workspace-grid">
      <form class="panel form" @submit.prevent="saveRental">
        <h2>{{ tr("Book a rental (sherwani / suit)", "ভাড়া বুক করুন (শেরওয়ানি / স্যুট)") }}</h2>
        <label>{{ tr("Customer *", "গ্রাহক *") }}
          <CustomerPicker v-model="rental.customer_id" required :placeholder="tr('Search name / mobile', 'নাম / মোবাইল খুঁজুন')" />
        </label>
        <label>{{ tr("Rental item *", "ভাড়ার পণ্য *") }}
          <select v-model="rental.inventory_item_id" required>
            <option value="">{{ tr("Select item", "পণ্য বাছুন") }}</option>
            <option v-for="i in inventory" :key="i.public_id" :value="i.public_id">
              {{ i.name }}
            </option>
          </select>
        </label>
        <div class="form-grid-2">
          <label>{{ tr("Start date", "শুরুর তারিখ") }}
            <input v-model="rental.starts_on" type="date" required />
          </label>
          <label>{{ tr("Return due date", "ফেরতের তারিখ") }}
            <input v-model="rental.due_on" type="date" required />
          </label>
        </div>
        <div class="form-grid-2">
          <label>{{ tr("Rental fee (৳)", "ভাড়া (৳)") }}
            <input v-model.number="rentalRentTaka" type="number" step="10" min="0" required />
          </label>
          <label>{{ tr("Security deposit (৳)", "জামানত (৳)") }}
            <input v-model.number="rentalDepositTaka" type="number" step="10" min="0" required />
          </label>
        </div>
        <button class="primary">{{ tr("Confirm booking", "বুকিং নিশ্চিত করুন") }}</button>
      </form>

      <section class="panel">
        <h2>{{ tr("Active rentals & returns", "চলমান ভাড়া ও ফেরত") }}</h2>
        <p v-if="!rentals.length" class="empty">{{ tr("No active rentals.", "কোনো চলমান ভাড়া নেই।") }}</p>
        <div v-for="c in rentals" :key="c.id" class="record" style="flex-direction:column;align-items:stretch">
          <div style="display:flex;justify-content:space-between">
            <div>
              <strong>#{{ c.number }} · {{ c.customer.name }}</strong>
              <small style="display:block">{{ tr("Due", "ফেরত") }}: {{ c.due_on }} · {{ tr("Rent", "ভাড়া") }} ৳ {{ (c.rent_minor / 100).toFixed(2) }} · {{ tr("Deposit", "জামানত") }} ৳ {{ (c.deposit_minor / 100).toFixed(2) }}</small>
            </div>
            <span class="status" :class="c.status === 'returned' ? 'status--ready' : 'status--pending'">
              {{ c.status }}
            </span>
          </div>

          <form v-if="c.status !== 'returned'" class="form" style="margin-top:0.75rem;padding-top:0.75rem;border-top:1px dashed var(--line)" @submit.prevent="returnRental(c)">
            <label>{{ tr("Damage deduction (৳)", "ক্ষতির কর্তন (৳)") }}
              <input
                :value="(getRentalReturnState(c).damage_charge_minor / 100).toFixed(2)"
                type="number"
                min="0"
                step="10"
                @input="getRentalReturnState(c).damage_charge_minor = Math.round(parseFloat(($event.target as HTMLInputElement).value || '0') * 100)"
              />
            </label>
            <label>{{ tr("Settlement note", "নিষ্পত্তির মন্তব্য") }}
              <input v-model="getRentalReturnState(c).settlement_note" :placeholder="tr('Condition remarks…', 'অবস্থার মন্তব্য…')" />
            </label>
            <button class="primary">{{ tr("Settle & mark returned", "নিষ্পত্তি ও ফেরত দেখান") }}</button>
          </form>
        </div>
      </section>
    </div>

    <!-- TAB 5: WORKFORCE PIECE-WORK & WAGES -->
    <div v-else-if="mode === 'Workforce'" class="workspace-grid">
      <div>
        <!-- Attendance Form -->
        <form class="panel form" style="margin-bottom:1.5rem" @submit.prevent="saveAttendance">
          <h2>{{ tr("Daily attendance", "দৈনিক হাজিরা") }}</h2>
          <label>{{ tr("Karigar", "কারিগর") }}
            <select v-model="attendance.employee_id" required>
              <option value="">{{ tr("Select worker", "কারিগর বাছুন") }}</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <div class="form-grid-2">
            <label>{{ tr("Date", "তারিখ") }}<input v-model="attendance.work_date" type="date" required /></label>
            <label>{{ tr("Status", "অবস্থা") }}
              <select v-model="attendance.status">
                <option value="present">{{ tr("Present", "উপস্থিত") }}</option>
                <option value="absent">{{ tr("Absent", "অনুপস্থিত") }}</option>
                <option value="half_day">{{ tr("Half day", "অর্ধ দিবস") }}</option>
                <option value="leave">{{ tr("Leave", "ছুটি") }}</option>
              </select>
            </label>
          </div>
          <button class="primary">{{ tr("Save attendance", "হাজিরা সংরক্ষণ") }}</button>
        </form>

        <!-- Piece-work logger -->
        <form class="panel form" @submit.prevent="saveWork">
          <h2>{{ tr("Record piece-work", "পিস-রেট কাজ লিখুন") }}</h2>
          <label>{{ tr("Karigar", "কারিগর") }}
            <select v-model="work.employee_id" required>
              <option value="">{{ tr("Select worker", "কারিগর বাছুন") }}</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <label>{{ tr("Work type", "কাজের ধরন") }}<input v-model="work.work_type" :placeholder="tr('e.g. panjabi stitching, pant cutting', 'যেমন: পাঞ্জাবি সেলাই, প্যান্ট কাটা')" required /></label>
          <div class="form-grid-2">
            <label>{{ tr("Quantity", "পরিমাণ") }}<input v-model.number="work.quantity" type="number" min="1" step="1" required /></label>
            <label>{{ tr("Rate per piece (৳)", "প্রতি পিসের রেট (৳)") }}<input v-model.number="workRateTaka" type="number" min="0" step="10" required /></label>
          </div>
          <button class="primary">{{ tr("Save piece-work", "কাজ সংরক্ষণ") }}</button>
        </form>
      </div>

      <!-- Payouts generator -->
      <section class="panel">
        <form class="form" @submit.prevent="savePayout">
          <h2>{{ tr("Pay karigar wages", "কারিগরের মজুরি পরিশোধ") }}</h2>
          <label>{{ tr("Worker", "কারিগর") }}
            <select v-model="payout.employee_id" required>
              <option value="">{{ tr("Select worker", "কারিগর বাছুন") }}</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
            </select>
          </label>
          <div class="form-grid-2">
            <label>{{ tr("Period from", "সময় শুরু") }}<input v-model="payout.period_from" type="date" required /></label>
            <label>{{ tr("Period to", "সময় শেষ") }}<input v-model="payout.period_to" type="date" required /></label>
          </div>
          <label>{{ tr("Payment method", "পেমেন্ট পদ্ধতি") }}
            <select v-model="payout.payment_method">
              <option value="cash">{{ tr("Cash", "নগদ") }}</option>
              <option value="mobile_banking">bKash / Nagad</option>
              <option value="bank_transfer">{{ tr("Bank", "ব্যাংক") }}</option>
            </select>
          </label>
          <button class="primary">{{ tr("Create payout", "পেআউট তৈরি করুন") }}</button>
        </form>

        <h3 style="margin-top:1.5rem">{{ tr("Recent payouts", "সর্বশেষ পেআউট") }}</h3>
        <p v-if="!payouts.length" class="empty">{{ tr("No payouts yet.", "এখনো কোনো পেআউট নেই।") }}</p>
        <div v-for="p in payouts" :key="p.id" class="record">
          <div>
            <strong>{{ p.employee.name }} · ৳ {{ (p.total_minor / 100).toFixed(2) }}</strong>
            <small>{{ p.number }} · {{ p.period_from }} {{ tr("to", "থেকে") }} {{ p.period_to }}</small>
          </div>
          <small>{{ new Date(p.paid_at).toLocaleDateString() }}</small>
        </div>
      </section>
    </div>

    <!-- TAB 6: SUPPLIERS DIRECTORY -->
    <div v-else class="workspace-grid">
      <form class="panel form" @submit.prevent="saveSupplier">
        <h2>{{ tr("Add supplier", "সরবরাহকারী যোগ করুন") }}</h2>
        <label>{{ tr("Supplier / company name *", "সরবরাহকারী / কোম্পানির নাম *") }}<input v-model="supplier.name" required /></label>
        <label>{{ tr("Mobile number", "মোবাইল নম্বর") }}<input v-model="supplier.mobile_number" :placeholder="tr('01XXXXXXXXX', '০১XXXXXXXXX')" /></label>
        <label>{{ tr("Address", "ঠিকানা") }}<textarea v-model="supplier.address"></textarea></label>
        <button class="primary">{{ tr("Save supplier", "সরবরাহকারী সংরক্ষণ") }}</button>
      </form>

      <section class="panel">
        <h2>{{ tr("Supplier directory", "সরবরাহকারীর তালিকা") }}</h2>
        <p v-if="!suppliers.length" class="empty">{{ tr("No suppliers yet.", "এখনো কোনো সরবরাহকারী নেই।") }}</p>
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
