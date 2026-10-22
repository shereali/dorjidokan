<script setup lang="ts">
interface TenantRow {
  id: string;
  name: string;
  slug: string;
  status: string;
  status_reason: string | null;
  status_changed_at: string | null;
  users_count: number | null;
  orders_this_month: number;
  plan: { code: string; name: string } | null;
  subscription: { state: string; ends_at: string | null; days_left: number | null; locked: boolean };
  created_at: string;
}

interface PlanOption {
  code: string;
  name: string;
  price_minor: number;
  active: boolean;
}

interface Detail {
  tenant: TenantRow;
  members: Array<{ name: string; email: string; role: string }>;
  payments: Array<{ id: string; amount_minor: number; method: string; reference: string | null; months: number; paid_at: string; plan: string | null }>;
  limits: Record<string, unknown>;
  audits: Array<{ id: string; action: string; actor: string | null; created_at: string }>;
}

const api = useTailorsApi();
const toast = useToast();
const { confirm } = useConfirmDialog();
const { money, date, dateTime, methodLabel } = usePlatformFormat();
const { t } = useTailorsI18n();

const rows = ref<TenantRow[]>([]);
const plans = ref<PlanOption[]>([]);
const loading = ref(true);
const meta = reactive({ total: 0, page: 1, last_page: 1 });
const filters = reactive({ query: "", status: "", state: "" });
let debounce: ReturnType<typeof setTimeout> | undefined;

const detail = ref<Detail | null>(null);
const panel = ref<"" | "status" | "plan" | "payment">("");
const busy = ref(false);
const form = reactive({ status: "active", reason: "", plan_code: "", sub_status: "active", period_end: "", amount: 0, method: "bank_transfer", reference: "", months: 1, note: "" });
const methods = ["bank_transfer", "bkash", "nagad", "rocket", "cash", "card", "other"];

async function load(page = 1) {
  loading.value = true;
  try {
    const params = new URLSearchParams({ page: String(page), per_page: "15" });
    if (filters.query.trim()) params.set("query", filters.query.trim());
    if (filters.status) params.set("status", filters.status);
    if (filters.state) params.set("state", filters.state);
    const res = await api.request<{ items: TenantRow[] }>(`/super-admin/tenants?${params}`);
    rows.value = res.data.items;
    Object.assign(meta, res.meta as { total: number; page: number; last_page: number });
  } catch {
    toast.error(t('app.super_admin_tenants.could_not_load_workshops'));
  } finally {
    loading.value = false;
  }
}

async function loadPlans() {
  try {
    plans.value = (await api.request<{ items: PlanOption[] }>("/super-admin/plans")).data.items.filter((p) => p.active);
  } catch {
    plans.value = [];
  }
}

function onSearch() {
  clearTimeout(debounce);
  debounce = setTimeout(() => load(1), 300);
}

async function openDetail(row: TenantRow) {
  panel.value = "";
  try {
    detail.value = (await api.request<Detail>(`/super-admin/tenants/${row.id}`)).data;
  } catch {
    toast.error(t('app.super_admin_tenants.could_not_load_workshop_details'));
  }
}

function openPanel(kind: "status" | "plan" | "payment") {
  const t = detail.value!.tenant;
  Object.assign(form, { status: t.status === "active" ? "hold" : "active", reason: "", plan_code: t.plan?.code ?? plans.value[0]?.code ?? "", sub_status: "active", period_end: "", amount: 0, method: "bank_transfer", reference: "", months: 1, note: "" });
  if (kind === "payment") {
    const plan = plans.value.find((p) => p.code === form.plan_code);
    form.amount = plan ? plan.price_minor / 100 : 0;
  }
  panel.value = kind;
}

async function submit(path: string, method: "PATCH" | "POST", body: Record<string, unknown>, success: string) {
  busy.value = true;
  try {
    await api.request(`/super-admin/tenants/${detail.value!.tenant.id}${path}`, { method, body });
    toast.success(success);
    const id = detail.value!.tenant.id;
    panel.value = "";
    await Promise.all([load(meta.page), openDetail({ id } as TenantRow)]);
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || e?.data?.message || t('app.super_admin_tenants.action_failed'));
  } finally {
    busy.value = false;
  }
}

const saveStatus = () => submit("/status", "PATCH", { status: form.status, reason: form.status === "active" ? null : form.reason }, t('app.super_admin_tenants.status_updated'));
const savePlan = () => submit("/subscription", "POST", { plan_code: form.plan_code, status: form.sub_status, period_end: form.sub_status === "active" && form.period_end ? form.period_end : null }, t('app.super_admin_tenants.subscription_updated'));
const savePayment = () => submit("/payments", "POST", { plan_code: form.plan_code || null, amount_minor: Math.round(Number(form.amount) * 100), method: form.method, reference: form.reference || null, months: form.months, note: form.note || null }, t('app.super_admin_tenants.payment_recorded_and_subscription_extended'));

async function impersonate(row: TenantRow) {
  const ok = await confirm({
    title: t('app.super_admin_tenants.sign_in_as_this_workshop_s'),
    message: t('app.super_admin_tenants.you_will_be_signed_in_as'),
    highlight: row.name,
    confirmText: t('app.super_admin_tenants.continue'),
    cancelText: t('app.common.cancel'),
    type: "warning",
  });
  if (!ok) return;
  try {
    await api.request(`/super-admin/tenants/${row.id}/impersonate`, { method: "POST" });
    api.tenant.value = row.slug;
    api.isSuperAdmin.value = false;
    await api.refreshMe();
    await navigateTo("/dashboard", { external: true });
  } catch {
    toast.error(t('app.super_admin_tenants.impersonation_failed'));
  }
}

onMounted(() => Promise.all([load(1), loadPlans()]));
</script>

<template>
  <div>
    <form class="filters" role="search" @submit.prevent="load(1)">
      <input v-model="filters.query" type="search" :placeholder="t('app.super_admin_tenants.search_name_or_slug')" :aria-label="t('app.super_admin_tenants.search_workshops')" @input="onSearch" />
      <select v-model="filters.status" :aria-label="t('app.super_admin_tenants.filter_by_status')" @change="load(1)">
        <option value="">{{ t('app.super_admin_tenants.all_statuses') }}</option>
        <option v-for="s in ['active', 'hold', 'inactive', 'suspended']" :key="s" :value="s">{{ s }}</option>
      </select>
      <select v-model="filters.state" :aria-label="t('app.super_admin_tenants.filter_by_subscription')" @change="load(1)">
        <option value="">{{ t('app.super_admin_tenants.all_subscriptions') }}</option>
        <option v-for="s in ['trialing', 'active', 'past_due', 'expired', 'cancelled', 'none']" :key="s" :value="s">{{ s }}</option>
      </select>
    </form>

    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>{{ t('app.common.workshop') }}</th>
            <th>{{ t('app.common.status') }}</th>
            <th>{{ t('app.common.plan') }}</th>
            <th>{{ t('app.super_admin_tenants.subscription') }}</th>
            <th>{{ t('app.super_admin_tenants.orders_month') }}</th>
            <th>{{ t('app.super_admin_tenants.staff') }}</th>
            <th><span class="sr-only">{{ t('app.common.actions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading"><td colspan="7">{{ t('app.common.loading') }}</td></tr>
          <tr v-else-if="!rows.length"><td colspan="7">{{ t('app.super_admin_tenants.no_workshops_match_these_filters') }}</td></tr>
          <tr v-for="row in rows" :key="row.id">
            <td><b>{{ row.name }}</b><small class="sub">{{ row.slug }}</small></td>
            <td><StatusChip :status="row.status" /></td>
            <td>{{ row.plan?.name ?? "—" }}</td>
            <td><StatusChip :status="row.subscription.state" /><small class="sub">{{ date(row.subscription.ends_at) }}</small></td>
            <td>{{ row.orders_this_month }}</td>
            <td>{{ row.users_count }}</td>
            <td class="end"><button type="button" class="mini-btn" @click="openDetail(row)">{{ t('app.super_admin_tenants.manage') }}</button></td>
          </tr>
        </tbody>
      </table>
    </div>

    <nav v-if="meta.last_page > 1" class="pager" :aria-label="t('app.common.pagination')">
      <button type="button" class="mini-btn" :disabled="meta.page <= 1" @click="load(meta.page - 1)">‹ {{ t('app.common.prev') }}</button>
      <span>{{ meta.page }} / {{ meta.last_page }} · {{ meta.total }}</span>
      <button type="button" class="mini-btn" :disabled="meta.page >= meta.last_page" @click="load(meta.page + 1)">{{ t('app.common.next') }} ›</button>
    </nav>

    <div v-if="detail" class="modal-backdrop" role="dialog" aria-modal="true" :aria-label="detail.tenant.name" @click.self="detail = null" @keydown.esc="detail = null">
      <div class="modal-window">
        <div class="modal-header">
          <h2>{{ detail.tenant.name }} <StatusChip :status="detail.tenant.status" /></h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="detail = null">×</button>
        </div>
        <div class="modal-body">
          <p v-if="detail.tenant.status_reason" class="reason">{{ t('app.common.reason') }}: {{ detail.tenant.status_reason }} · {{ dateTime(detail.tenant.status_changed_at) }}</p>

          <div class="facts">
            <div><small>{{ t('app.common.plan') }}</small><b>{{ detail.tenant.plan?.name ?? "—" }}</b></div>
            <div><small>{{ t('app.super_admin_tenants.subscription') }}</small><StatusChip :status="detail.tenant.subscription.state" /></div>
            <div><small>{{ t('app.super_admin_tenants.valid_until') }}</small><b>{{ date(detail.tenant.subscription.ends_at) }}</b></div>
            <div><small>{{ t('app.super_admin_tenants.orders_this_month') }}</small><b>{{ detail.tenant.orders_this_month }}{{ detail.limits.orders_per_month ? ` / ${detail.limits.orders_per_month}` : "" }}</b></div>
          </div>

          <div class="btn-row">
            <button type="button" class="primary" @click="openPanel('payment')">{{ t('app.common.record_payment') }}</button>
            <button type="button" class="secondary" @click="openPanel('plan')">{{ t('app.super_admin_tenants.change_plan') }}</button>
            <button type="button" class="secondary" @click="openPanel('status')">{{ t('app.super_admin_tenants.change_status') }}</button>
            <button type="button" class="secondary" @click="impersonate(detail.tenant)">{{ t('app.super_admin_tenants.sign_in_as_owner') }}</button>
          </div>

          <form v-if="panel === 'status'" class="subform" @submit.prevent="saveStatus">
            <label>{{ t('app.super_admin_tenants.new_status') }}
              <select v-model="form.status">
                <option value="active">{{ t('app.super_admin_tenants.active_full_access') }}</option>
                <option value="hold">{{ t('app.super_admin_tenants.hold_read_only') }}</option>
                <option value="inactive">{{ t('app.super_admin_tenants.inactive_no_access') }}</option>
                <option value="suspended">{{ t('app.super_admin_tenants.suspended_blocked') }}</option>
              </select>
            </label>
            <label v-if="form.status !== 'active'">{{ t('app.super_admin_tenants.reason_shown_to_the_workshop') }}
              <input v-model="form.reason" maxlength="255" required />
            </label>
            <button class="primary" :disabled="busy">{{ t('app.super_admin_tenants.save_status') }}</button>
          </form>

          <form v-if="panel === 'plan'" class="subform" @submit.prevent="savePlan">
            <label>{{ t('app.common.plan') }}
              <select v-model="form.plan_code" required>
                <option v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }} — {{ money(p.price_minor) }}</option>
              </select>
            </label>
            <label>{{ t('app.super_admin_tenants.mode') }}
              <select v-model="form.sub_status">
                <option value="active">{{ t('app.super_admin_tenants.active_paid') }}</option>
                <option value="trialing">{{ t('app.super_admin_tenants.trial') }}</option>
              </select>
            </label>
            <label v-if="form.sub_status === 'active'">{{ t('app.super_admin_tenants.valid_until_empty_no_expiry') }}
              <input v-model="form.period_end" type="date" />
            </label>
            <button class="primary" :disabled="busy">{{ t('app.common.save_plan') }}</button>
          </form>

          <form v-if="panel === 'payment'" class="subform" @submit.prevent="savePayment">
            <div class="grid2">
              <label>{{ t('app.common.plan') }}
                <select v-model="form.plan_code">
                  <option v-for="p in plans" :key="p.code" :value="p.code">{{ p.name }}</option>
                </select>
              </label>
              <label>{{ t('app.super_admin_tenants.months_covered') }}
                <input v-model.number="form.months" type="number" min="1" max="36" required />
              </label>
              <label>{{ t('app.common.amount_received') }}
                <input v-model.number="form.amount" type="number" min="0" step="0.01" required />
              </label>
              <label>{{ t('app.common.method') }}
                <select v-model="form.method"><option v-for="m in methods" :key="m" :value="m">{{ methodLabel(m) }}</option></select>
              </label>
            </div>
            <label>{{ t('app.super_admin_tenants.transaction_reference_no') }}<input v-model="form.reference" maxlength="120" /></label>
            <label>{{ t('app.super_admin_tenants.note') }}<input v-model="form.note" maxlength="255" /></label>
            <p class="hint">{{ t('app.super_admin_tenants.the_subscription_becomes_active_and_is') }}</p>
            <button class="primary" :disabled="busy">{{ t('app.super_admin_tenants.save_payment') }}</button>
          </form>

          <section>
            <h3>{{ t('app.common.payments') }}</h3>
            <p v-if="!detail.payments.length" class="hint">{{ t('app.super_admin_tenants.no_payments_recorded_yet') }}</p>
            <ul class="rows">
              <li v-for="p in detail.payments" :key="p.id"><span><b>{{ money(p.amount_minor) }}</b> · {{ methodLabel(p.method) }}<small>{{ p.plan }} · {{ p.months }} {{ t('app.super_admin_tenants.mo') }} · {{ p.reference }}</small></span><small>{{ date(p.paid_at) }}</small></li>
            </ul>
          </section>

          <section>
            <h3>{{ t('app.super_admin_tenants.team') }} ({{ detail.members.length }})</h3>
            <ul class="rows">
              <li v-for="m in detail.members" :key="m.email"><span><b>{{ m.name }}</b><small>{{ m.email }}</small></span><small>{{ m.role }}</small></li>
            </ul>
          </section>

          <section>
            <h3>{{ t('app.super_admin_tenants.recent_activity') }}</h3>
            <ul class="rows">
              <li v-for="a in detail.audits" :key="a.id"><span>{{ a.action }}<small>{{ a.actor }}</small></span><small>{{ dateTime(a.created_at) }}</small></li>
            </ul>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.filters { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.75rem; }
.filters input { flex: 1 1 14rem; }
.sub { display: block; color: var(--muted); font-size: 0.75rem; }
.end { text-align: right; }
.pager { display: flex; justify-content: center; align-items: center; gap: 1rem; margin-top: 0.85rem; }
.reason { background: color-mix(in srgb, var(--color-danger, #8b2e2e) 8%, transparent); border: 1px solid var(--line); padding: 0.6rem 0.8rem; border-radius: var(--radius-sm); margin: 0; }
.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr)); gap: 0.75rem; }
.facts div { display: flex; flex-direction: column; gap: 0.15rem; }
.facts small, .hint { color: var(--muted); font-size: 0.78rem; }
.btn-row { display: flex; gap: 0.5rem; flex-wrap: wrap; }
.subform { display: flex; flex-direction: column; gap: 0.75rem; padding: 1rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--paper); }
.subform label { display: flex; flex-direction: column; gap: 0.25rem; font-weight: 600; font-size: 0.85rem; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.modal-body h3 { margin: 0 0 0.4rem; font-size: 0.95rem; }
.rows { list-style: none; margin: 0; padding: 0; }
.rows li { display: flex; justify-content: space-between; gap: 0.75rem; padding: 0.45rem 0; border-bottom: 1px solid var(--line); }
.rows small { display: block; color: var(--muted); }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }

@media (width <= 600px) {
  .grid2 { grid-template-columns: 1fr; }
}
</style>
