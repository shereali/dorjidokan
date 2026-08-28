<script setup lang="ts">
interface C {
  public_id: string;
  name: string;
  mobile_number: string;
  address?: string;
  marketing_consent: boolean;
}
const api = useTailorsApi(),
  { t } = useTailorsI18n(),
  items = ref<C[]>([]),
  loading = ref(false),
  error = ref(""),
  query = ref(""),
  archived = ref(false),
  editingId = ref(""),
  pointsFor = ref<{ id: string; name: string; balance: number; total_earned: number; total_redeemed: number } | null>(null),
  redeemPoints = ref(0),
  pointsError = ref(""),
  form = reactive({ name: "", mobile_number: "", address: "", marketing_consent: false });
async function showPoints(customer: C) {
  pointsError.value = "";
  redeemPoints.value = 0;
  const result = await api.request<{ account: { balance: number; total_earned: number; total_redeemed: number } }>(`/customers/${customer.public_id}/points`);
  pointsFor.value = { id: customer.public_id, name: customer.name, ...result.data.account };
}
async function redeem() {
  pointsError.value = "";
  if (!pointsFor.value) return;
  try {
    const result = await api.request<{ balance: number; credit_minor: number }>(`/customers/${pointsFor.value.id}/redeem`, { method: "POST", body: { points: redeemPoints.value } });
    pointsFor.value.balance = result.data.balance;
    redeemPoints.value = 0;
  } catch (e: any) {
    pointsError.value = e?.data?.errors?.[0]?.message || "Could not redeem points.";
  }
}
async function load() {
  loading.value = true;
  try {
    items.value = (
      await api.request<{ items: C[] }>(
        `/customers?query=${encodeURIComponent(query.value)}&archived=${archived.value ? 1 : 0}`,
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
    await api.request(editingId.value ? `/customers/${editingId.value}` : "/customers", { method: editingId.value ? "PATCH" : "POST", body: form });
    editingId.value = "";
    Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: false });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save customer.";
  }
}
function edit(customer: C) {
  editingId.value = customer.public_id;
  Object.assign(form, { name: customer.name, mobile_number: customer.mobile_number, address: customer.address || "", marketing_consent: customer.marketing_consent });
}
function cancelEdit() {
  editingId.value = "";
  Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: false });
}
async function toggleArchive(customer: C) {
  await api.request(`/customers/${customer.public_id}${archived.value ? "/restore" : ""}`, { method: archived.value ? "POST" : "DELETE" });
  await load();
}
onMounted(load);
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        {{ t('workspace.customers.eyebrow') }}
      </p>
      <h1>{{ t('workspace.customers.title') }}</h1>
      <p>{{ t('workspace.customers.help') }}</p>
    </div>
  </header>
  <div class="workspace-grid">
    <form class="panel form" @submit.prevent="save">
      <h2>{{ editingId ? t('workspace.customers.edit_customer') : t('workspace.customers.add_customer') }}</h2>
      <label>{{ t('common.name') }}<input v-model="form.name" required /></label><label>{{ t('common.mobile') }}<input v-model="form.mobile_number" required /></label><label>{{ t('common.address') }}<textarea v-model="form.address" /></label>
      <label class="check"><input v-model="form.marketing_consent" type="checkbox" /> Customer consented to occasion and promotional SMS</label>
      <p v-if="error" class="error">
        {{ error }}
      </p>
      <button class="primary">{{ editingId ? t('workspace.customers.update_customer') : t('workspace.customers.save_customer') }}</button>
      <button v-if="editingId" type="button" @click="cancelEdit">{{ t('common.cancel') }}</button>
    </form>
    <section class="panel">
      <div class="toolbar">
        <input
          v-model="query"
          :placeholder="t('workspace.customers.search_name')"
          @keyup.enter="load"
        /><button @click="load">
          {{ t('common.search') }}
        </button>
        <label class="check"><input v-model="archived" type="checkbox" @change="load" /> {{ t('status.archived') }}</label>
      </div>
      <p v-if="loading">
        {{ t('common.loading') }}
      </p>
      <p v-else-if="!items.length" class="empty">
        {{ t('workspace.customers.no_customers') }}
      </p>
      <div v-for="c in items" :key="c.public_id" class="record">
        <strong>{{ c.name }}</strong><span>{{ c.mobile_number }}</span><small>{{ c.address || t('workspace.customers.no_address') }} · {{ c.marketing_consent ? t('workspace.customers.sms_consent') : t('workspace.customers.transactional_only') }}</small>
        <div class="actions"><button v-if="!archived" @click="edit(c)">{{ t('common.edit') }}</button><button @click="toggleArchive(c)">{{ archived ? t('common.restore') : t('common.archive') }}</button><button v-if="!archived" @click="showPoints(c)">{{ t('common.points') }}</button></div>
      </div>
      <section v-if="pointsFor" class="panel form">
        <h2>{{ t('workspace.customers.loyalty') }} — {{ pointsFor.name }}</h2>
        <p class="record">
          <strong>{{ t('workspace.customers.balance') }}</strong><span>{{ pointsFor.balance }} {{ t('common.points') }}</span><small>{{ t('workspace.customers.earned_redeemed', { earned: pointsFor.total_earned, redeemed: pointsFor.total_redeemed }) }}</small>
        </p>
        <label>{{ t('workspace.customers.redeem_points') }}<input v-model.number="redeemPoints" type="number" min="1" :max="pointsFor.balance" /></label>
        <p v-if="pointsError" class="error">
          {{ pointsError }}
        </p>
        <button class="primary" :disabled="redeemPoints < 1" @click="redeem">
          {{ t('workspace.customers.redeem') }}
        </button>
        <button type="button" @click="pointsFor = null">{{ t('common.close') }}</button>
      </section>
    </section>
  </div>
</template>
