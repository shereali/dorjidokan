<script setup lang="ts">
const { tr } = usePlatformFormat();

type Tab = "overview" | "workshops" | "payments" | "plans" | "audit";
const tab = ref<Tab>("overview");

const tabs = computed<Array<{ key: Tab; label: string }>>(() => [
  { key: "overview", label: tr("Overview", "সারসংক্ষেপ") },
  { key: "workshops", label: tr("Workshops", "ওয়ার্কশপ") },
  { key: "payments", label: tr("Payments", "পেমেন্ট") },
  { key: "plans", label: tr("Plans", "প্যাকেজ") },
  { key: "audit", label: tr("Audit trail", "অডিট লগ") },
]);
</script>

<template>
  <div class="super-admin">
    <header>
      <div>
        <p class="eyebrow">{{ tr("PLATFORM SUPER ADMINISTRATION", "প্ল্যাটফর্ম সুপার অ্যাডমিন") }}</p>
        <h1>{{ tr("Workshops, subscriptions & revenue", "ওয়ার্কশপ, সাবস্ক্রিপশন ও রেভিনিউ") }}</h1>
        <p>{{ tr("Control every registered tailor shop: status, plan, payments and reports.", "নিবন্ধিত প্রতিটি টেইলার শপ নিয়ন্ত্রণ করুন: স্ট্যাটাস, প্ল্যান, পেমেন্ট ও রিপোর্ট।") }}</p>
      </div>
    </header>

    <div class="tabs no-print" role="tablist" :aria-label="tr('Super admin sections', 'সুপার অ্যাডমিন বিভাগ')">
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

    <section :id="`sa-panel-${tab}`" role="tabpanel" :aria-labelledby="`sa-tab-${tab}`">
      <SuperAdminOverview v-if="tab === 'overview'" />
      <SuperAdminTenants v-else-if="tab === 'workshops'" />
      <SuperAdminPayments v-else-if="tab === 'payments'" />
      <SuperAdminPlans v-else-if="tab === 'plans'" />
      <SuperAdminAudit v-else />
    </section>
  </div>
</template>

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
