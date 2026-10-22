<script setup lang="ts">
const { t } = useTailorsI18n();
const api = useTailorsApi();

type Tab = "overview" | "workshops" | "payments" | "plans" | "audit";
const tab = ref<Tab>("overview");

const tabs = computed<Array<{ key: Tab; label: string }>>(() => [
  { key: "overview", label: t('app.super_admin.overview') },
  { key: "workshops", label: t('app.super_admin.workshops') },
  { key: "payments", label: t('app.common.payments') },
  { key: "plans", label: t('app.super_admin.plans') },
  { key: "audit", label: t('app.super_admin.audit_trail') },
]);
</script>

<template>
  <div class="super-admin">
    <header>
      <div>
        <p class="eyebrow">{{ t('app.super_admin.platform_super_administration') }}</p>
        <h1>{{ t('app.super_admin.workshops_subscriptions_revenue') }}</h1>
        <p>{{ t('app.super_admin.control_every_registered_tailor_shop_status') }}</p>
      </div>
    </header>

    <div v-if="api.hasTwoFactor.value" class="tabs no-print" role="tablist" :aria-label="t('app.super_admin.super_admin_sections')">
      <button
        v-for="item in tabs"
        :id="`sa-tab-${item.key}`"
        :key="item.key"
        type="button"
        role="tab"
        :class="{ active: tab === item.key }"
        :aria-selected="tab === item.key"
        :aria-controls="`sa-panel-${item.key}`"
        @click="tab = item.key"
      >
        {{ item.label }}
      </button>
    </div>

    <section v-if="!api.hasTwoFactor.value" class="panel gate" role="alert">
      <h2>{{ t('app.super_admin.two_factor_authentication_required') }}</h2>
      <p>{{ t('app.super_admin.super_admin_can_suspend_workshops_and') }}</p>
      <NuxtLink to="/settings" class="primary gate__link">{{ t('app.super_admin.set_up_two_factor_in_settings') }}</NuxtLink>
    </section>

    <section :id="`sa-panel-${tab}`" role="tabpanel" :aria-labelledby="`sa-tab-${tab}`">
      <template v-if="api.hasTwoFactor.value">
        <SuperAdminOverview v-if="tab === 'overview'" />
        <SuperAdminTenants v-else-if="tab === 'workshops'" />
        <SuperAdminPayments v-else-if="tab === 'payments'" />
        <SuperAdminPlans v-else-if="tab === 'plans'" />
        <SuperAdminAudit v-else />
      </template>
    </section>
  </div>
</template>

<style scoped>
.gate { max-width: 40rem; display: flex; flex-direction: column; gap: 0.75rem; align-items: flex-start; }
.gate__link { display: inline-block; text-decoration: none; padding: 0.7rem 1.1rem; border-radius: var(--radius-sm); }
</style>

<style>
@media print {
  .shell > aside,
  .topbar,
  .mobile-menu-toggle,
  .drawer-backdrop {
    display: none !important;
  }

  .workspace-wrapper {
    margin: 0 !important;
  }
}
</style>
