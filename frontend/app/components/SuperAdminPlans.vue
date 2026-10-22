<script setup lang="ts">
interface PlanRow {
  code: string;
  name: string;
  description: string | null;
  price_minor: number;
  currency: string;
  billing_interval: "month" | "year";
  trial_days: number;
  sort_order: number;
  active: boolean;
  feature_limits: { orders_per_month?: number | null; staff_seats?: number | null; inventory?: boolean; rentals?: boolean; bulk_notifications?: boolean; custom_branding?: boolean };
  stripe_connected: boolean;
  subscriptions_count: number;
}

const api = useTailorsApi();
const toast = useToast();
const { money } = usePlatformFormat();
const { t } = useTailorsI18n();

const plans = ref<PlanRow[]>([]);
const loading = ref(true);
const editing = ref<string | "new" | null>(null);
const saving = ref(false);
const form = reactive({ code: "", name: "", description: "", price: 0, billing_interval: "month", trial_days: 14, sort_order: 0, active: true, orders_per_month: 100 as number | null, staff_seats: 5 as number | null, inventory: true, rentals: true, bulk_notifications: true, custom_branding: true });
const flags = ["inventory", "rentals", "bulk_notifications", "custom_branding"] as const;

async function load() {
  loading.value = true;
  try {
    plans.value = (await api.request<{ items: PlanRow[] }>("/super-admin/plans")).data.items;
  } catch {
    toast.error(t('app.super_admin_plans.could_not_load_plans'));
  } finally {
    loading.value = false;
  }
}

function edit(plan: PlanRow | null) {
  editing.value = plan ? plan.code : "new";
  Object.assign(form, plan
    ? { code: plan.code, name: plan.name, description: plan.description ?? "", price: plan.price_minor / 100, billing_interval: plan.billing_interval, trial_days: plan.trial_days, sort_order: plan.sort_order, active: plan.active, orders_per_month: plan.feature_limits.orders_per_month ?? null, staff_seats: plan.feature_limits.staff_seats ?? null, inventory: !!plan.feature_limits.inventory, rentals: !!plan.feature_limits.rentals, bulk_notifications: !!plan.feature_limits.bulk_notifications, custom_branding: !!plan.feature_limits.custom_branding }
    : { code: "", name: "", description: "", price: 0, billing_interval: "month", trial_days: 14, sort_order: plans.value.length + 1, active: true, orders_per_month: 100, staff_seats: 5, inventory: true, rentals: true, bulk_notifications: true, custom_branding: true });
}

async function save() {
  saving.value = true;
  const isNew = editing.value === "new";
  const body: Record<string, unknown> = {
    name: form.name,
    description: form.description || null,
    price_minor: Math.round(Number(form.price) * 100),
    billing_interval: form.billing_interval,
    trial_days: form.trial_days,
    sort_order: form.sort_order,
    active: form.active,
    feature_limits: { orders_per_month: form.orders_per_month || null, staff_seats: form.staff_seats || null, inventory: form.inventory, rentals: form.rentals, bulk_notifications: form.bulk_notifications, custom_branding: form.custom_branding },
  };
  if (isNew) body.code = form.code;
  try {
    await api.request(isNew ? "/super-admin/plans" : `/super-admin/plans/${editing.value}`, { method: isNew ? "POST" : "PATCH", body });
    toast.success(t('app.super_admin_plans.plan_saved'));
    editing.value = null;
    await load();
  } catch (e: any) {
    toast.error(e?.data?.message || e?.data?.errors?.[0]?.message || t('app.super_admin_plans.could_not_save_the_plan'));
  } finally {
    saving.value = false;
  }
}

const limitText = (v: number | null | undefined) => (v ? String(v) : t('app.common.unlimited'));

onMounted(load);
</script>

<template>
  <div>
    <div class="head">
      <p class="hint">{{ t('app.super_admin_plans.packages_workshops_can_subscribe_to_changing') }}</p>
      <button type="button" class="primary" @click="edit(null)">+ {{ t('app.super_admin_plans.new_plan') }}</button>
    </div>

    <p v-if="loading">{{ t('app.common.loading') }}</p>
    <div class="cards">
      <article v-for="plan in plans" :key="plan.code" class="plan" :class="{ 'plan--off': !plan.active }">
        <header>
          <h3>{{ plan.name }}</h3>
          <StatusChip :status="plan.active ? 'active' : 'inactive'" />
        </header>
        <p class="price">{{ money(plan.price_minor, plan.currency) }}<small> / {{ plan.billing_interval === "year" ? t('app.super_admin_plans.year') : t('app.super_admin_plans.month') }}</small></p>
        <p v-if="plan.description" class="hint">{{ plan.description }}</p>
        <ul>
          <li>{{ t('app.super_admin_plans.orders_month') }}: <b>{{ limitText(plan.feature_limits.orders_per_month) }}</b></li>
          <li>{{ t('app.super_admin_plans.staff_seats') }}: <b>{{ limitText(plan.feature_limits.staff_seats) }}</b></li>
          <li v-for="key in flags" :key="key">{{ plan.feature_limits[key] ? "✔" : "✖" }} {{ t(`app.common.limit_${key}`) }}</li>
        </ul>
        <footer>
          <small>{{ plan.subscriptions_count }} {{ t('app.super_admin_plans.subscriptions') }} · {{ plan.stripe_connected ? "Stripe ✔" : t('app.super_admin_plans.manual_billing') }}</small>
          <button type="button" class="mini-btn" @click="edit(plan)">{{ t('app.common.edit') }}</button>
        </footer>
      </article>
    </div>

    <div v-if="editing" class="modal-backdrop" role="dialog" aria-modal="true" @click.self="editing = null" @keydown.esc="editing = null">
      <form class="modal-window" @submit.prevent="save">
        <div class="modal-header">
          <h2>{{ editing === "new" ? t('app.super_admin_plans.new_plan') : t('app.super_admin_plans.edit_plan') }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="editing = null">×</button>
        </div>
        <div class="modal-body">
          <div class="grid2">
            <label>{{ t('app.super_admin_plans.code') }}<input v-model="form.code" :disabled="editing !== 'new'" pattern="[A-Za-z0-9_-]+" maxlength="40" required /></label>
            <label>{{ t('app.common.name') }}<input v-model="form.name" maxlength="80" required /></label>
            <label>{{ t('app.super_admin_plans.price') }}<input v-model.number="form.price" type="number" min="0" step="0.01" required /></label>
            <label>{{ t('app.super_admin_plans.billing') }}<select v-model="form.billing_interval"><option value="month">{{ t('app.super_admin_plans.monthly') }}</option><option value="year">{{ t('app.super_admin_plans.yearly') }}</option></select></label>
            <label>{{ t('app.super_admin_plans.orders_per_month_empty_unlimited') }}<input v-model.number="form.orders_per_month" type="number" min="0" /></label>
            <label>{{ t('app.super_admin_plans.staff_seats_empty_unlimited') }}<input v-model.number="form.staff_seats" type="number" min="0" /></label>
            <label>{{ t('app.super_admin_plans.trial_days') }}<input v-model.number="form.trial_days" type="number" min="0" max="365" /></label>
            <label>{{ t('app.super_admin_plans.display_order') }}<input v-model.number="form.sort_order" type="number" min="0" /></label>
          </div>
          <label>{{ t('app.common.description') }}<input v-model="form.description" maxlength="500" /></label>
          <fieldset>
            <legend>{{ t('app.super_admin_plans.features') }}</legend>
            <label v-for="key in flags" :key="key" class="check"><input v-model="form[key]" type="checkbox" /> {{ t(`app.common.limit_${key}`) }}</label>
            <label class="check"><input v-model="form.active" type="checkbox" /> {{ t('app.super_admin_plans.active_offered_to_workshops') }}</label>
          </fieldset>
        </div>
        <div class="modal-footer">
          <button type="button" class="secondary" @click="editing = null">{{ t('app.common.cancel') }}</button>
          <button class="primary" :disabled="saving">{{ t('app.common.save_plan') }}</button>
        </div>
      </form>
    </div>
  </div>
</template>

<style scoped>
.head { display: flex; justify-content: space-between; align-items: center; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
.hint { color: var(--muted); font-size: 0.82rem; margin: 0; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1rem; }
.plan { background: var(--surface); border: 1px solid var(--line); border-radius: var(--radius); padding: 1.1rem; display: flex; flex-direction: column; gap: 0.5rem; }
.plan--off { opacity: 0.65; }
.plan header, .plan footer { display: flex; justify-content: space-between; align-items: center; gap: 0.5rem; }
.plan h3 { margin: 0; }
.price { font-size: 1.5rem; font-weight: 800; margin: 0; }
.price small { font-size: 0.8rem; font-weight: 500; color: var(--muted); }
.plan ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.88rem; }
.plan footer { margin-top: auto; padding-top: 0.5rem; border-top: 1px solid var(--line); color: var(--muted); }
.modal-body label { display: flex; flex-direction: column; gap: 0.25rem; font-weight: 600; font-size: 0.85rem; }
.grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
fieldset { border: 1px solid var(--line); border-radius: var(--radius-sm); display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; }
.check { flex-direction: row !important; align-items: center; font-weight: 500 !important; }

@media (width <= 600px) {
  .grid2, fieldset { grid-template-columns: 1fr; }
}
</style>
