<script setup lang="ts">
const { tr } = usePlatformFormat();
interface Plan {
  code: string;
  name: string;
  price_minor: number;
  currency: string;
  billing_interval: string;
}

const api = useTailorsApi();
const toast = useToast();
const billing = ref<any>(null);

// Plan limits as readable rows; internal keys (e.g. the Stripe price id) are never shown to the workshop.
const limitLabels: Record<string, [string, string]> = {
  orders_per_month: ['Orders per month', 'মাসে অর্ডার'],
  staff_seats: ['Staff seats', 'স্টাফ সংখ্যা'],
  inventory: ['Inventory & POS', 'ইনভেন্টরি ও POS'],
  rentals: ['Rentals', 'ভাড়া'],
  bulk_notifications: ['Bulk SMS campaigns', 'বাল্ক SMS ক্যাম্পেইন'],
  custom_branding: ['Custom branding', 'কাস্টম ব্র্যান্ডিং'],
};
const limitRows = computed(() =>
  Object.entries(billing.value?.limits || {})
    .filter(([key]) => key in limitLabels)
    .map(([key, value]) => ({
      key,
      label: tr(limitLabels[key]![0], limitLabels[key]![1]),
      value: typeof value === "boolean" ? (value ? "✔" : "✖") : value ? String(value) : tr("Unlimited", "সীমাহীন"),
    })),
);
const plans = ref<Plan[]>([]);
const error = ref("");
const loading = ref(true);

onMounted(async () => {
  try {
    const [status, catalog] = await Promise.all([
      api.request<any>("/billing"),
      api.request<{ plans: Plan[] }>("/plans"),
    ]);
    billing.value = status.data;
    plans.value = catalog.data.plans;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || tr("Billing is unavailable.", "বিলিং এখন পাওয়া যাচ্ছে না।");
  } finally {
    loading.value = false;
  }
});

async function redirect(path: string, body?: unknown) {
  try {
    const r = await api.request<{ url: string }>(path, { method: "POST", body });
    location.href = r.data.url;
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || tr("That billing action is unavailable.", "এই বিলিং কাজটি এখন করা যাচ্ছে না।"));
  }
}
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("WORKSHOP SUBSCRIPTION", "ওয়ার্কশপ সাবস্ক্রিপশন") }}</p>
        <h1>{{ tr("Billing & plans", "বিলিং ও প্ল্যান") }}</h1>
        <p>{{ tr("Your current plan, usage limits, payment method and invoices.", "আপনার বর্তমান প্ল্যান, ব্যবহারের সীমা, পেমেন্ট পদ্ধতি ও ইনভয়েস।") }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Current Subscription Status -->
      <section class="panel">
        <h2>{{ tr("Current plan", "বর্তমান প্ল্যান") }}</h2>
        <div class="subscription-header">
          <span class="status" :class="`status--${billing?.subscription?.status || 'active'}`">
            {{ statusLabel(billing?.subscription?.status || 'Active Plan') }}
          </span>
          <p v-if="billing?.subscription?.trial_ends_at" style="margin:0.5rem 0 0;font-size:0.85rem">
            {{ tr("Free trial until", "ফ্রি ট্রায়াল চলবে") }} {{ new Date(billing.subscription.trial_ends_at).toLocaleDateString() }}
          </p>
        </div>

        <div class="limits-grid" style="margin:1rem 0">
          <div v-for="row in limitRows" :key="row.key" class="record record--stock">
            <strong>{{ row.label }}</strong>
            <b>{{ row.value }}</b>
          </div>
        </div>

        <button class="primary" @click="redirect('/billing/portal')">
          {{ tr("Manage invoices & payment method", "ইনভয়েস ও পেমেন্ট পদ্ধতি পরিচালনা") }}
        </button>
      </section>

      <!-- Available Upgrade Plans -->
      <section class="panel">
        <h2>{{ tr("Available plans", "উপলব্ধ প্ল্যান") }}</h2>
        <div class="plans-list">
          <article v-for="plan in plans" :key="plan.code" class="record record--stock">
            <div>
              <strong>{{ plan.name }}</strong>
              <small style="display:block">{{ plan.currency }} {{ (plan.price_minor / 100).toFixed(2) }} / {{ plan.billing_interval }}</small>
            </div>
            <button
              v-if="billing?.subscription?.plan?.code !== plan.code"
              class="primary"
              @click="redirect('/billing/checkout', { plan_code: plan.code })"
            >
              {{ tr("Choose plan", "প্ল্যান বাছুন") }}
            </button>
            <span v-else class="status status--ready">{{ tr("Current plan", "বর্তমান প্ল্যান") }}</span>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.subscription-header {
  background: var(--paper);
  border: 1px solid var(--line);
  padding: 1rem 1.25rem;
  border-radius: var(--radius-sm);
  margin: 1rem 0;
}

.limits-grid {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.plans-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 1rem;
}
</style>
