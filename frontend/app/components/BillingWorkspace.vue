<script setup lang="ts">
const { t } = useTailorsI18n();
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
const shownLimits = ["orders_per_month", "staff_seats", "inventory", "rentals", "bulk_notifications", "custom_branding"];
const limitRows = computed(() =>
  Object.entries(billing.value?.limits || {})
    .filter(([key]) => shownLimits.includes(key))
    .map(([key, value]) => ({
      key,
      label: t(`app.common.limit_${key}`),
      value: typeof value === "boolean" ? (value ? "✔" : "✖") : value ? String(value) : t('app.common.unlimited'),
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
    error.value = e?.data?.errors?.[0]?.message || t('app.billing.billing_is_unavailable');
  } finally {
    loading.value = false;
  }
});

async function redirect(path: string, body?: unknown) {
  try {
    const r = await api.request<{ url: string }>(path, { method: "POST", body });
    location.href = r.data.url;
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || t('app.billing.that_billing_action_is_unavailable'));
  }
}
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.billing.workshop_subscription') }}</p>
        <h1>{{ t('app.billing.billing_plans') }}</h1>
        <p>{{ t('app.billing.your_current_plan_usage_limits_payment') }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Current Subscription Status -->
      <section class="panel">
        <h2>{{ t('app.billing.current_plan') }}</h2>
        <div class="subscription-header">
          <span class="status" :class="`status--${billing?.subscription?.status || 'active'}`">
            {{ statusLabel(billing?.subscription?.status || 'Active Plan') }}
          </span>
          <p v-if="billing?.subscription?.trial_ends_at" style="margin:0.5rem 0 0;font-size:0.85rem">
            {{ t('app.billing.free_trial_until') }} {{ new Date(billing.subscription.trial_ends_at).toLocaleDateString() }}
          </p>
        </div>

        <div class="limits-grid" style="margin:1rem 0">
          <div v-for="row in limitRows" :key="row.key" class="record record--stock">
            <strong>{{ row.label }}</strong>
            <b>{{ row.value }}</b>
          </div>
        </div>

        <button class="primary" @click="redirect('/billing/portal')">
          {{ t('app.billing.manage_invoices_payment_method') }}
        </button>
      </section>

      <!-- Available Upgrade Plans -->
      <section class="panel">
        <h2>{{ t('app.billing.available_plans') }}</h2>
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
              {{ t('app.billing.choose_plan') }}
            </button>
            <span v-else class="status status--ready">{{ t('app.billing.current_plan') }}</span>
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
