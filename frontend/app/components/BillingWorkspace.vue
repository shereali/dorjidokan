<script setup lang="ts">
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
    error.value = e?.data?.errors?.[0]?.message || "Billing is unavailable.";
  } finally {
    loading.value = false;
  }
});

async function redirect(path: string, body?: unknown) {
  try {
    const r = await api.request<{ url: string }>(path, { method: "POST", body });
    location.href = r.data.url;
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "Billing action unavailable.");
  }
}
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKSHOP SUBSCRIPTION</p>
        <h1>Billing & Capacity Plans</h1>
        <p>Active workshop tier, usage limits, payment cards, and invoices.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Current Subscription Status -->
      <section class="panel">
        <h2>Current Subscription Plan</h2>
        <div class="subscription-header">
          <span class="status" :class="`status--${billing?.subscription?.status || 'active'}`">
            {{ statusLabel(billing?.subscription?.status || 'Active Plan') }}
          </span>
          <p v-if="billing?.subscription?.trial_ends_at" style="margin:0.5rem 0 0;font-size:0.85rem">
            Free trial active until {{ new Date(billing.subscription.trial_ends_at).toLocaleDateString() }}
          </p>
        </div>

        <div class="limits-grid" style="margin:1rem 0">
          <div v-for="(value, key) in billing?.limits || {}" :key="key" class="record record--stock">
            <strong>{{ String(key).replaceAll('_', ' ').toUpperCase() }}</strong>
            <b>{{ value }}</b>
          </div>
        </div>

        <button class="primary" @click="redirect('/billing/portal')">
          Manage Invoices & Payment Method
        </button>
      </section>

      <!-- Available Upgrade Plans -->
      <section class="panel">
        <h2>Available Workshop Tiers</h2>
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
              Choose Plan
            </button>
            <span v-else class="status status--ready">Current Tier</span>
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
