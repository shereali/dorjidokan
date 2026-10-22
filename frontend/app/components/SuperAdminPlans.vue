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
const { tr, money } = usePlatformFormat();

const plans = ref<PlanRow[]>([]);
const loading = ref(true);
const editing = ref<string | "new" | null>(null);
const saving = ref(false);
const form = reactive({ code: "", name: "", description: "", price: 0, billing_interval: "month", trial_days: 14, sort_order: 0, active: true, orders_per_month: 100 as number | null, staff_seats: 5 as number | null, inventory: true, rentals: true, bulk_notifications: true, custom_branding: true });
const flags = [
  ["inventory", "Inventory & POS", "ইনভেন্টরি ও POS"],
  ["rentals", "Rentals", "ভাড়া"],
  ["bulk_notifications", "Bulk SMS campaigns", "বাল্ক SMS ক্যাম্পেইন"],
  ["custom_branding", "Custom branding", "কাস্টম ব্র্যান্ডিং"],
] as const;

async function load() {
  loading.value = true;
  try {
    plans.value = (await api.request<{ items: PlanRow[] }>("/super-admin/plans")).data.items;
  } catch {
    toast.error(tr("Could not load plans.", "প্ল্যান লোড করা যায়নি।"));
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
    toast.success(tr("Plan saved.", "প্ল্যান সংরক্ষিত হয়েছে।"));
    editing.value = null;
    await load();
  } catch (e: any) {
    toast.error(e?.data?.message || e?.data?.errors?.[0]?.message || tr("Could not save the plan.", "প্ল্যান সংরক্ষণ করা যায়নি।"));
  } finally {
    saving.value = false;
  }
}

const limitText = (v: number | null | undefined) => (v ? String(v) : tr("Unlimited", "সীমাহীন"));

onMounted(load);
</script>

<template>
  <div>
    <div class="head">
      <p class="hint">{{ tr("Packages workshops can subscribe to. Changing a plan updates limits for everyone on it.", "ওয়ার্কশপ যে প্যাকেজ নিতে পারে। প্ল্যান বদলালে সবার লিমিট বদলায়।") }}</p>
      <button type="button" class="primary" @click="edit(null)">+ {{ tr("New plan", "নতুন প্ল্যান") }}</button>
    </div>

    <p v-if="loading">{{ tr("Loading…", "লোড হচ্ছে…") }}</p>
    <div class="cards">
      <article v-for="plan in plans" :key="plan.code" class="plan" :class="{ 'plan--off': !plan.active }">
        <header>
          <h3>{{ plan.name }}</h3>
          <StatusChip :status="plan.active ? 'active' : 'inactive'" />
        </header>
        <p class="price">{{ money(plan.price_minor, plan.currency) }}<small> / {{ plan.billing_interval === "year" ? tr("year", "বছর") : tr("month", "মাস") }}</small></p>
        <p v-if="plan.description" class="hint">{{ plan.description }}</p>
        <ul>
          <li>{{ tr("Orders / month", "অর্ডার / মাস") }}: <b>{{ limitText(plan.feature_limits.orders_per_month) }}</b></li>
          <li>{{ tr("Staff seats", "স্টাফ সংখ্যা") }}: <b>{{ limitText(plan.feature_limits.staff_seats) }}</b></li>
          <li v-for="[key, en, bn] in flags" :key="key">{{ plan.feature_limits[key] ? "✔" : "✖" }} {{ tr(en, bn) }}</li>
        </ul>
        <footer>
          <small>{{ plan.subscriptions_count }} {{ tr("subscriptions", "সাবস্ক্রিপশন") }} · {{ plan.stripe_connected ? "Stripe ✔" : tr("Manual billing", "ম্যানুয়াল বিলিং") }}</small>
          <button type="button" class="mini-btn" @click="edit(plan)">{{ tr("Edit", "সম্পাদনা") }}</button>
        </footer>
      </article>
    </div>

    <div v-if="editing" class="modal-backdrop" role="dialog" aria-modal="true" @click.self="editing = null" @keydown.esc="editing = null">
      <form class="modal-window" @submit.prevent="save">
        <div class="modal-header">
          <h2>{{ editing === "new" ? tr("New plan", "নতুন প্ল্যান") : tr("Edit plan", "প্ল্যান সম্পাদনা") }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="tr('Close', 'বন্ধ করুন')" @click="editing = null">×</button>
        </div>
        <div class="modal-body">
          <div class="grid2">
            <label>{{ tr("Code", "কোড") }}<input v-model="form.code" :disabled="editing !== 'new'" pattern="[A-Za-z0-9_-]+" maxlength="40" required /></label>
            <label>{{ tr("Name", "নাম") }}<input v-model="form.name" maxlength="80" required /></label>
            <label>{{ tr("Price (৳)", "মূল্য (৳)") }}<input v-model.number="form.price" type="number" min="0" step="0.01" required /></label>
            <label>{{ tr("Billing", "বিলিং") }}<select v-model="form.billing_interval"><option value="month">{{ tr("Monthly", "মাসিক") }}</option><option value="year">{{ tr("Yearly", "বার্ষিক") }}</option></select></label>
            <label>{{ tr("Orders per month (empty = unlimited)", "মাসে অর্ডার (ফাঁকা = সীমাহীন)") }}<input v-model.number="form.orders_per_month" type="number" min="0" /></label>
            <label>{{ tr("Staff seats (empty = unlimited)", "স্টাফ সংখ্যা (ফাঁকা = সীমাহীন)") }}<input v-model.number="form.staff_seats" type="number" min="0" /></label>
            <label>{{ tr("Trial days", "ট্রায়াল দিন") }}<input v-model.number="form.trial_days" type="number" min="0" max="365" /></label>
            <label>{{ tr("Display order", "ক্রম") }}<input v-model.number="form.sort_order" type="number" min="0" /></label>
          </div>
          <label>{{ tr("Description", "বিবরণ") }}<input v-model="form.description" maxlength="500" /></label>
          <fieldset>
            <legend>{{ tr("Features", "ফিচার") }}</legend>
            <label v-for="[key, en, bn] in flags" :key="key" class="check"><input v-model="form[key]" type="checkbox" /> {{ tr(en, bn) }}</label>
            <label class="check"><input v-model="form.active" type="checkbox" /> {{ tr("Active (offered to workshops)", "সক্রিয় (ওয়ার্কশপকে দেখানো হবে)") }}</label>
          </fieldset>
        </div>
        <div class="modal-footer">
          <button type="button" class="secondary" @click="editing = null">{{ tr("Cancel", "বাতিল") }}</button>
          <button class="primary" :disabled="saving">{{ tr("Save plan", "প্ল্যান সংরক্ষণ") }}</button>
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
