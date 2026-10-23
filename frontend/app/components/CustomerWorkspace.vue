<script setup lang="ts">
interface C {
  public_id: string;
  name: string;
  mobile_number: string;
  address?: string;
  marketing_consent: boolean;
  orders_count?: number;
  due_minor?: number;
  last_order_at?: string | null;
}

interface StatementLine {
  entry_id: string;
  occurred_at: string;
  memo: string;
  debit_minor: number;
  credit_minor: number;
  balance_minor: number;
}

const api = useTailorsApi();
const toast = useToast();
const { t } = useTailorsI18n();
const { money, date } = usePlatformFormat();

const items = ref<C[]>([]);
const loading = ref(false);
const error = ref("");
const query = ref("");
const archived = ref(false);
const editingId = ref("");
const showForm = ref(false);
const detail = ref<C | null>(null);
const nextCursor = ref<string | null>(null);
const loadingMore = ref(false);

// Each control mirrors the permission the API checks for it.
const canAdd = computed(() => api.can("customers.manage", "orders.create"));
const canEdit = computed(() => api.can("customers.manage"));
const canArchive = computed(() => api.can("customers.delete"));

// Customer Statement / Dues Modal
const activeCustomerStatement = ref<{ customer: C; lines: StatementLine[]; balance_minor: number } | null>(null);
const statementLoading = ref(false);

// Loyalty Points Modal
const pointsFor = ref<{ id: string; name: string; balance: number; total_earned: number; total_redeemed: number } | null>(null);
const redeemPoints = ref(0);
const pointsError = ref("");

const form = reactive({
  name: "",
  mobile_number: "",
  address: "",
  marketing_consent: true,
});

// Only the newest request may update the list; a slow earlier response must not overwrite it.
let loadSeq = 0;

async function load(append = false) {
  const seq = ++loadSeq;
  if (append) loadingMore.value = true;
  else loading.value = true;
  try {
    const cursor = append && nextCursor.value ? `&cursor=${encodeURIComponent(nextCursor.value)}` : "";
    const res = await api.request<{ items: C[] }>(
      `/customers?query=${encodeURIComponent(query.value)}&archived=${archived.value ? 1 : 0}${cursor}`
    );
    if (seq !== loadSeq) return;
    items.value = append ? [...items.value, ...res.data.items] : res.data.items;
    nextCursor.value = (res.meta?.next_cursor as string | null) ?? null;
  } catch (e: any) {
    if (seq === loadSeq) error.value = e?.data?.errors?.[0]?.message || t('app.customer.could_not_load_customers');
  } finally {
    if (seq === loadSeq) {
      loading.value = false;
      loadingMore.value = false;
    }
  }
}

function openAdd() {
  cancelEdit();
  showForm.value = true;
}

async function save() {
  error.value = "";
  try {
    await api.request(editingId.value ? `/customers/${editingId.value}` : "/customers", {
      method: editingId.value ? "PATCH" : "POST",
      body: form,
    });
    toast.success(editingId.value ? t('app.customer.customer_updated') : t('app.customer.new_customer_added'));
    editingId.value = "";
    Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: true });
    showForm.value = false;
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.customer.could_not_save_customer');
  }
}

function edit(customer: C) {
  detail.value = null;
  error.value = "";
  showForm.value = true;
  editingId.value = customer.public_id;
  Object.assign(form, {
    name: customer.name,
    mobile_number: customer.mobile_number,
    address: customer.address || "",
    marketing_consent: customer.marketing_consent,
  });
}

function cancelEdit() {
  showForm.value = false;
  error.value = "";
  editingId.value = "";
  Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: true });
}

async function toggleArchive(customer: C) {
  await api.request(`/customers/${customer.public_id}${archived.value ? "/restore" : ""}`, {
    method: archived.value ? "POST" : "DELETE",
  });
  toast.info(archived.value ? t('app.customer.customer_restored') : t('app.customer.customer_archived'));
  await load();
}

async function viewStatement(customer: C) {
  statementLoading.value = true;
  try {
    const res = await api.request<{ items: StatementLine[]; balance_minor: number }>(
      `/customers/${customer.public_id}/statement`
    );
    activeCustomerStatement.value = {
      customer,
      lines: res.data.items,
      balance_minor: res.data.balance_minor,
    };
  } catch (e: any) {
    toast.error(t('app.customer.could_not_load_the_ledger'));
  } finally {
    statementLoading.value = false;
  }
}

async function showPoints(customer: C) {
  pointsError.value = "";
  redeemPoints.value = 0;
  try {
    const result = await api.request<{ account: { balance: number; total_earned: number; total_redeemed: number } }>(
      `/customers/${customer.public_id}/points`
    );
    pointsFor.value = { id: customer.public_id, name: customer.name, ...result.data.account };
  } catch (e: any) {
    toast.error(t('app.customer.could_not_load_loyalty_points'));
  }
}

async function redeem() {
  pointsError.value = "";
  if (!pointsFor.value) return;
  try {
    const result = await api.request<{ balance: number; credit_minor: number }>(
      `/customers/${pointsFor.value.id}/redeem`,
      { method: "POST", body: { points: redeemPoints.value } }
    );
    toast.success(t('app.customer.redeemed_p0_points_for_p1_credit', { p0: redeemPoints.value, p1: (result.data.credit_minor / 100).toFixed(2) }));
    pointsFor.value.balance = result.data.balance;
    redeemPoints.value = 0;
  } catch (e: any) {
    pointsError.value = e?.data?.errors?.[0]?.message || "Could not redeem points.";
  }
}

function getWhatsAppUrl(mobile: string) {
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  return `https://wa.me/${num}?text=${encodeURIComponent("Hello from Tailors OS")}`;
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('workspace.customers.eyebrow') }}</p>
        <h1>{{ t('workspace.customers.title') }}</h1>
        <p>{{ t('workspace.customers.help') }}</p>
      </div>
    </header>

    <section class="panel">
      <div class="toolbar">
        <input
          v-model="query"
          :placeholder="t('workspace.customers.search_name')"
          @keyup.enter="load()"
        />
        <button @click="load()">
          <NavIcon name="Search" /> {{ t('common.search') }}
        </button>
        <button v-if="canAdd && !archived" class="primary" @click="openAdd">
          <NavIcon name="Plus" /> {{ t('workspace.customers.add_customer') }}
        </button>
        <label class="check-label check-label--end">
          <input v-model="archived" type="checkbox" @change="load()" />
          <span>{{ t('status.archived') }}</span>
        </label>
      </div>

      <p v-if="loading && !items.length">{{ t('common.loading') }}</p>
      <p v-else-if="!items.length" class="empty">{{ t('workspace.customers.no_customers') }}</p>

      <div v-else class="table-scroll">
        <table class="customer-table">
          <thead>
            <tr>
              <th>{{ t('app.customer.col_customer') }}</th>
              <th>{{ t('common.mobile') }}</th>
              <th>{{ t('common.address') }}</th>
              <th class="num">{{ t('app.customer.col_orders') }}</th>
              <th class="num">{{ t('app.customer.col_due') }}</th>
              <th>{{ t('app.customer.col_last_order') }}</th>
              <th class="actions-col">{{ t('app.common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="c in items" :key="c.public_id" class="clickable-row" @click="detail = c">
              <td>
                <div class="name-cell">
                  <div class="customer-avatar">{{ c.name.slice(0, 1).toUpperCase() }}</div>
                  <button type="button" class="name-link" @click.stop="detail = c">{{ c.name }}</button>
                </div>
              </td>
              <td class="nowrap">
                <span>{{ c.mobile_number }}</span>
                <a
                  :href="getWhatsAppUrl(c.mobile_number)"
                  target="_blank"
                  rel="noopener"
                  class="whatsapp-link"
                  :title="t('app.customer.chat_on_whatsapp')"
                  @click.stop
                >
                  <NavIcon name="WhatsApp" />
                </a>
              </td>
              <td class="address-cell">{{ c.address || "—" }}</td>
              <td class="num">{{ c.orders_count ?? 0 }}</td>
              <td class="num" :class="{ 'is-due': (c.due_minor ?? 0) > 0 }">{{ (c.due_minor ?? 0) > 0 ? money(c.due_minor ?? 0) : "—" }}</td>
              <td class="nowrap">{{ c.last_order_at ? date(c.last_order_at) : "—" }}</td>
              <td class="actions-col nowrap" @click.stop>
                <button class="mini-btn" @click="detail = c">{{ t('app.customer.view_details') }}</button>
                <button v-if="!archived && canEdit" class="mini-btn" @click="edit(c)">{{ t('app.common.edit') }}</button>
                <button v-if="canArchive" class="mini-btn" @click="toggleArchive(c)">
                  {{ archived ? t('common.restore') : t('common.archive') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="nextCursor" class="load-more">
        <button class="secondary" :disabled="loadingMore" @click="load(true)">
          {{ loadingMore ? t('common.loading') : t('app.customer.load_more') }}
        </button>
      </div>
    </section>

    <!-- ADD / EDIT CUSTOMER -->
    <div v-if="showForm" class="modal-backdrop" @click.self="cancelEdit">
      <form class="modal-window form" style="max-width:30rem" @submit.prevent="save">
        <div class="modal-header">
          <h2>{{ editingId ? t('workspace.customers.edit_customer') : t('workspace.customers.add_customer') }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="cancelEdit">✕</button>
        </div>
        <div class="modal-body">
          <label>{{ t('common.name') }} *
            <input v-model="form.name" :placeholder="t('app.customer.customer_name')" required />
          </label>
          <label>{{ t('common.mobile') }} *
            <input v-model="form.mobile_number" :placeholder="t('app.common.01xxxxxxxxx')" required />
          </label>
          <label>{{ t('common.address') }}
            <textarea v-model="form.address" :placeholder="t('app.customer.address_location')"></textarea>
          </label>
          <label class="check-label">
            <input v-model="form.marketing_consent" type="checkbox" />
            <span>{{ t('workspace.customers.sms_consent') }} ({{ t('app.customer.customer_agreed_to_promotional_sms') }})</span>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="secondary" @click="cancelEdit">{{ t('common.cancel') }}</button>
          <button class="primary">
            {{ editingId ? t('workspace.customers.update_customer') : t('workspace.customers.save_customer') }}
          </button>
        </div>
      </form>
    </div>

    <!-- CUSTOMER DETAIL + ORDER HISTORY -->
    <CustomerDetail
      v-if="detail"
      :customer="detail"
      :archived="archived"
      @close="detail = null"
      @edit="edit(detail)"
      @ledger="viewStatement(detail)"
      @points="showPoints(detail)"
    />

    <!-- CUSTOMER STATEMENT / LEDGER MODAL -->
    <div v-if="activeCustomerStatement" class="modal-backdrop" @click.self="activeCustomerStatement = null">
      <div class="modal-window" style="max-width:38rem">
        <div class="modal-header">
          <h2>{{ t('app.customer.customer_statement') }} — {{ activeCustomerStatement.customer.name }}</h2>
          <button class="modal-close-btn" @click="activeCustomerStatement = null">{{ t('app.common.text') }}</button>
        </div>

        <div class="modal-body">
          <div class="statement-balance-bar">
            <span>{{ t('app.customer.outstanding_balance') }}</span>
            <strong>৳ {{ (activeCustomerStatement.balance_minor / 100).toFixed(2) }}</strong>
          </div>

          <table class="statement-table">
            <thead>
              <tr>
                <th>{{ t('app.common.date') }}</th>
                <th>{{ t('app.common.description') }}</th>
                <th style="text-align:right">{{ t('app.customer.debit') }}</th>
                <th style="text-align:right">{{ t('app.customer.credit') }}</th>
                <th style="text-align:right">{{ t('app.customer.balance') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="line in activeCustomerStatement.lines" :key="line.entry_id">
                <td><small>{{ new Date(line.occurred_at).toLocaleDateString() }}</small></td>
                <td><strong>{{ line.memo }}</strong></td>
                <td style="text-align:right">{{ (line.debit_minor / 100).toFixed(2) }}</td>
                <td style="text-align:right;color:var(--status-ready)">{{ (line.credit_minor / 100).toFixed(2) }}</td>
                <td style="text-align:right;font-weight:700">{{ (line.balance_minor / 100).toFixed(2) }}</td>
              </tr>
            </tbody>
          </table>

          <p v-if="!activeCustomerStatement.lines.length" class="empty">{{ t('app.customer.no_transactions_recorded_for_this_customer') }}</p>
        </div>

        <div class="modal-footer">
          <button class="secondary" @click="activeCustomerStatement = null">{{ t('app.common.close') }}</button>
        </div>
      </div>
    </div>

    <!-- LOYALTY POINTS REDEMPTION MODAL -->
    <div v-if="pointsFor" class="modal-backdrop" @click.self="pointsFor = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>{{ t('app.customer.loyalty_points') }} — {{ pointsFor.name }}</h2>
          <button class="modal-close-btn" @click="pointsFor = null">{{ t('app.common.text') }}</button>
        </div>

        <div class="modal-body">
          <div class="points-card">
            <div class="points-balance">{{ pointsFor.balance }}</div>
            <small>{{ t('app.customer.available_loyalty_points') }}</small>
            <p>{{ t('app.customer.earned') }}: {{ pointsFor.total_earned }} · {{ t('app.customer.redeemed') }}: {{ pointsFor.total_redeemed }}</p>
          </div>

          <label>{{ t('app.customer.redeem_points_as_store_credit') }}
            <input
              v-model.number="redeemPoints"
              type="number"
              min="1"
              :max="pointsFor.balance"
              :placeholder="t('app.customer.number_of_points')"
            />
          </label>
          <small>{{ t('app.customer.points_become_store_credit_against_upcoming') }}</small>

          <p v-if="pointsError" class="error">{{ pointsError }}</p>
        </div>

        <div class="modal-footer">
          <button class="secondary" @click="pointsFor = null">{{ t('app.common.cancel') }}</button>
          <button class="primary" :disabled="redeemPoints < 1" @click="redeem">
            {{ t('app.customer.redeem_points') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.check-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
}

/* .toolbar input (global) pads and stretches text fields; a checkbox must stay checkbox-sized. */
.check-label input {
  flex: none;
  width: 1.1rem;
  min-width: 0;
  min-height: 0;
  height: 1.1rem;
  padding: 0;
  accent-color: var(--accent, currentcolor);
}

.toolbar {
  flex-wrap: wrap;
  align-items: center;
}

/* The global .toolbar input is full width; let the search box share the row with the buttons instead. */
.toolbar > input[type="text"],
.toolbar > input:not([type]) {
  flex: 1 1 14rem;
  width: auto;
  min-width: 0;
}

.check-label--end {
  margin-left: auto;
  white-space: nowrap;
}

.table-scroll {
  overflow-x: auto;
}

.customer-table {
  width: 100%;
  min-width: 50rem;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.customer-table th,
.customer-table td {
  padding: 0.55rem 0.6rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: middle;
}

.customer-table th {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.78rem;
  white-space: nowrap;
}

.customer-table .num {
  text-align: right;
}

.customer-table .actions-col {
  text-align: right;
}

.nowrap {
  white-space: nowrap;
}

.clickable-row {
  cursor: pointer;
  transition: background 120ms ease;
}

.clickable-row:hover {
  background: var(--paper);
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  min-width: 11rem;
}

.name-link {
  border: 0;
  background: transparent;
  padding: 0;
  font: inherit;
  font-weight: 700;
  color: var(--ink);
  text-align: left;
  cursor: pointer;
}

.name-link:hover {
  text-decoration: underline;
}

.address-cell {
  color: var(--muted);
  max-width: 10rem;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.is-due {
  color: var(--color-danger, #b91c1c);
  font-weight: 700;
}

.load-more {
  display: flex;
  justify-content: center;
  padding: 1rem 0 0.25rem;
}

.customer-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 0.95rem;
  flex-shrink: 0;
}

.whatsapp-link {
  color: #25d366;
  display: inline-flex;
  align-items: center;
  margin-left: 0.4rem;
  text-decoration: none;
  background: rgb(37 211 102 / 10%);
  padding: 0.15rem 0.4rem;
  border-radius: 4px;
}

.whatsapp-link:hover {
  background: rgb(37 211 102 / 20%);
}

.statement-balance-bar {
  background: var(--paper);
  border: 1px solid var(--line);
  padding: 0.85rem 1.2rem;
  border-radius: var(--radius-sm);
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1.05rem;
}

.statement-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 1rem;
}

.statement-table th {
  border-bottom: 1px solid var(--line);
  padding: 0.5rem 0.4rem;
  font-size: 0.8rem;
  color: var(--muted);
}

.statement-table td {
  border-bottom: 1px solid var(--line);
  padding: 0.6rem 0.4rem;
  font-size: 0.85rem;
}

.points-card {
  text-align: center;
  background: var(--paper);
  border-radius: var(--radius-sm);
  padding: 1.5rem;
  margin-bottom: 1rem;
}

.points-balance {
  font-size: 2.5rem;
  font-weight: 800;
  color: var(--accent);
}
</style>
