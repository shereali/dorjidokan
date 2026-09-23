<script setup lang="ts">
interface C {
  public_id: string;
  name: string;
  mobile_number: string;
  address?: string;
  marketing_consent: boolean;
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

const items = ref<C[]>([]);
const loading = ref(false);
const error = ref("");
const query = ref("");
const archived = ref(false);
const editingId = ref("");

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

async function load() {
  loading.value = true;
  try {
    items.value = (
      await api.request<{ items: C[] }>(
        `/customers?query=${encodeURIComponent(query.value)}&archived=${archived.value ? 1 : 0}`
      )
    ).data.items;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not load customers.";
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request(editingId.value ? `/customers/${editingId.value}` : "/customers", {
      method: editingId.value ? "PATCH" : "POST",
      body: form,
    });
    toast.success(editingId.value ? "Customer updated." : "New customer added.");
    editingId.value = "";
    Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: true });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save customer.";
  }
}

function edit(customer: C) {
  editingId.value = customer.public_id;
  Object.assign(form, {
    name: customer.name,
    mobile_number: customer.mobile_number,
    address: customer.address || "",
    marketing_consent: customer.marketing_consent,
  });
}

function cancelEdit() {
  editingId.value = "";
  Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: true });
}

async function toggleArchive(customer: C) {
  await api.request(`/customers/${customer.public_id}${archived.value ? "/restore" : ""}`, {
    method: archived.value ? "POST" : "DELETE",
  });
  toast.info(archived.value ? "Customer restored." : "Customer archived.");
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
    toast.error("Could not load customer ledger statement.");
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
    toast.error("Could not fetch loyalty points balance.");
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
    toast.success(`Redeemed ${redeemPoints.value} points for ৳ ${(result.data.credit_minor / 100).toFixed(2)} credit!`);
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

    <div class="workspace-grid">
      <!-- Customer Add / Edit Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>{{ editingId ? t('workspace.customers.edit_customer') : t('workspace.customers.add_customer') }}</h2>
        <label>{{ t('common.name') }} *
          <input v-model="form.name" placeholder="Customer Name" required />
        </label>
        <label>{{ t('common.mobile') }} *
          <input v-model="form.mobile_number" placeholder="01XXXXXXXXX" required />
        </label>
        <label>{{ t('common.address') }}
          <textarea v-model="form.address" placeholder="Address / Location"></textarea>
        </label>
        <label class="check-label">
          <input v-model="form.marketing_consent" type="checkbox" />
          <span>{{ t('workspace.customers.sms_consent') }} (Customer consented to promotional SMS)</span>
        </label>

        <p v-if="error" class="error">{{ error }}</p>

        <div class="form-actions">
          <button class="primary">
            {{ editingId ? t('workspace.customers.update_customer') : t('workspace.customers.save_customer') }}
          </button>
          <button v-if="editingId" type="button" class="secondary" @click="cancelEdit">
            {{ t('common.cancel') }}
          </button>
        </div>
      </form>

      <!-- Customer Directory List -->
      <section class="panel">
        <div class="toolbar">
          <input
            v-model="query"
            :placeholder="t('workspace.customers.search_name')"
            @keyup.enter="load"
          />
          <button @click="load">
            <NavIcon name="Search" /> {{ t('common.search') }}
          </button>
          <label class="check-label" style="margin-left:auto">
            <input v-model="archived" type="checkbox" @change="load" />
            <span>{{ t('status.archived') }}</span>
          </label>
        </div>

        <p v-if="loading">{{ t('common.loading') }}</p>
        <p v-else-if="!items.length" class="empty">{{ t('workspace.customers.no_customers') }}</p>

        <div class="customer-cards-list">
          <div v-for="c in items" :key="c.public_id" class="customer-card">
            <div class="customer-card-main">
              <div class="customer-avatar">{{ c.name.slice(0, 1).toUpperCase() }}</div>
              <div>
                <strong>{{ c.name }}</strong>
                <div class="customer-phone">
                  <span>📞 {{ c.mobile_number }}</span>
                  <a
                    :href="getWhatsAppUrl(c.mobile_number)"
                    target="_blank"
                    class="whatsapp-link"
                    title="Chat on WhatsApp"
                  >
                    <NavIcon name="WhatsApp" /> WhatsApp
                  </a>
                </div>
                <small v-if="c.address" class="customer-address">📍 {{ c.address }}</small>
              </div>
            </div>

            <div class="customer-card-actions">
              <button v-if="!archived" class="mini-btn" @click="viewStatement(c)">
                <NavIcon name="Accounting" /> Ledger Statement
              </button>
              <button v-if="!archived" class="mini-btn" @click="showPoints(c)">
                <NavIcon name="Tag" /> Points
              </button>
              <button v-if="!archived" class="mini-btn" @click="edit(c)">
                Edit
              </button>
              <button class="mini-btn" @click="toggleArchive(c)">
                {{ archived ? t('common.restore') : t('common.archive') }}
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>

    <!-- CUSTOMER STATEMENT / LEDGER MODAL -->
    <div v-if="activeCustomerStatement" class="modal-backdrop" @click.self="activeCustomerStatement = null">
      <div class="modal-window" style="max-width:38rem">
        <div class="modal-header">
          <h2>Customer Account Statement — {{ activeCustomerStatement.customer.name }}</h2>
          <button class="modal-close-btn" @click="activeCustomerStatement = null">&times;</button>
        </div>

        <div class="modal-body">
          <div class="statement-balance-bar">
            <span>Outstanding Balance (বকেয়া):</span>
            <strong>৳ {{ (activeCustomerStatement.balance_minor / 100).toFixed(2) }}</strong>
          </div>

          <table class="statement-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Description</th>
                <th style="text-align:right">Debit (৳)</th>
                <th style="text-align:right">Credit (৳)</th>
                <th style="text-align:right">Balance (৳)</th>
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

          <p v-if="!activeCustomerStatement.lines.length" class="empty">No journal transactions recorded for this customer.</p>
        </div>

        <div class="modal-footer">
          <button class="secondary" @click="activeCustomerStatement = null">Close</button>
        </div>
      </div>
    </div>

    <!-- LOYALTY POINTS REDEMPTION MODAL -->
    <div v-if="pointsFor" class="modal-backdrop" @click.self="pointsFor = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>Loyalty Points — {{ pointsFor.name }}</h2>
          <button class="modal-close-btn" @click="pointsFor = null">&times;</button>
        </div>

        <div class="modal-body">
          <div class="points-card">
            <div class="points-balance">{{ pointsFor.balance }}</div>
            <small>Available Loyalty Points</small>
            <p>Earned: {{ pointsFor.total_earned }} · Redeemed: {{ pointsFor.total_redeemed }}</p>
          </div>

          <label>Redeem Points for Store Credit:
            <input
              v-model.number="redeemPoints"
              type="number"
              min="1"
              :max="pointsFor.balance"
              placeholder="Number of points"
            />
          </label>
          <small>Points are converted to store credit towards upcoming tailoring orders.</small>

          <p v-if="pointsError" class="error">{{ pointsError }}</p>
        </div>

        <div class="modal-footer">
          <button class="secondary" @click="pointsFor = null">Cancel</button>
          <button class="primary" :disabled="redeemPoints < 1" @click="redeem">
            Redeem Points
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

.check-label input {
  width: auto;
}

.form-actions {
  display: flex;
  gap: 0.5rem;
  margin-top: 0.5rem;
}

.customer-cards-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.customer-card {
  background: var(--paper);
  border: var(--border-width) solid var(--line);
  border-radius: var(--radius);
  padding: 1rem 1.25rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  transition: all 160ms ease;
}

.customer-card:hover {
  background: var(--surface);
  box-shadow: var(--shadow-xs);
  border-color: var(--primary);
}

.customer-card-main {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.customer-avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 1.1rem;
  flex-shrink: 0;
}

.customer-phone {
  display: flex;
  align-items: center;
  gap: 0.6rem;
  font-size: 0.85rem;
  color: var(--muted);
  margin-top: 0.15rem;
}

.whatsapp-link {
  color: #25d366;
  font-weight: 700;
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
  text-decoration: none;
  font-size: 0.8rem;
  background: rgb(37 211 102 / 10%);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.whatsapp-link:hover {
  background: rgb(37 211 102 / 20%);
}

.customer-address {
  display: block;
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.customer-card-actions {
  display: flex;
  gap: 0.35rem;
  flex-wrap: wrap;
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
