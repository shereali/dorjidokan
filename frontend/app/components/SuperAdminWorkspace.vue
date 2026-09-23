<script setup lang="ts">
interface Tenant {
  id: string;
  slug: string;
  name: string;
  status: string;
  plan: string;
  orders_this_month: number;
}

interface Audit {
  id: string;
  action: string;
  ip_address: string;
  created_at: string;
}

const api = useTailorsApi();
const toast = useToast();

const tenants = ref<Tenant[]>([]);
const audits = ref<Audit[]>([]);
const loading = ref(true);

async function load() {
  loading.value = true;
  try {
    const [tList, aList] = await Promise.all([
      api.request<{ items: Tenant[] }>("/super-admin/tenants"),
      api.request<{ items: Audit[] }>("/super-admin/audits"),
    ]);
    tenants.value = tList.data.items;
    audits.value = aList.data.items;
  } catch (e: any) {
    toast.error("Could not load super admin portal.");
  } finally {
    loading.value = false;
  }
}

async function suspend(t: Tenant) {
  const next = t.status === "suspended" ? "active" : "suspended";
  try {
    await api.request(`/super-admin/tenants/${t.id}/status`, {
      method: "PATCH",
      body: { status: next },
    });
    toast.info(`Tenant ${t.name} is now ${next}.`);
    await load();
  } catch {
    toast.error("Could not change tenant status.");
  }
}

async function impersonate(t: Tenant) {
  try {
    const res = await api.request<{ token: string }>(`/super-admin/tenants/${t.id}/impersonate`, {
      method: "POST",
    });
    api.authenticated.value = true;
    api.tenant.value = t.slug;
    location.reload();
  } catch {
    toast.error("Impersonation failed.");
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">PLATFORM SUPER ADMINISTRATION</p>
        <h1>Tenant Directory & Audit Log</h1>
        <p>Manage multi-tenant tailoring workshops, suspend abusive instances, and audit logs.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Tenant List -->
      <section class="panel">
        <h2>Registered Workshops ({{ tenants.length }})</h2>
        <p v-if="loading">Loading workshops…</p>
        <p v-else-if="!tenants.length" class="empty">No tenants registered.</p>

        <div class="tenant-list">
          <article v-for="t in tenants" :key="t.id" class="record record--stock">
            <div>
              <strong>{{ t.name }} ({{ t.slug }})</strong>
              <small style="display:block">Plan: {{ t.plan }} · {{ t.orders_this_month }} orders this month</small>
            </div>
            <span class="status" :class="t.status === 'active' ? 'status--ready' : 'status--cancelled'">
              {{ t.status }}
            </span>
            <div class="actions">
              <button class="mini-btn" @click="impersonate(t)">Impersonate</button>
              <button class="mini-btn" @click="suspend(t)">
                {{ t.status === 'suspended' ? 'Reactivate' : 'Suspend' }}
              </button>
            </div>
          </article>
        </div>
      </section>

      <!-- Audit Logs -->
      <section class="panel">
        <h2>Super Admin Audit Trail</h2>
        <p v-if="!audits.length" class="empty">No recent security events.</p>
        <div class="audit-list">
          <div v-for="a in audits" :key="a.id" class="record">
            <div>
              <strong>{{ a.action }}</strong>
              <small style="display:block">IP: {{ a.ip_address }} · {{ new Date(a.created_at).toLocaleString() }}</small>
            </div>
          </div>
        </div>
      </section>
    </div>
  </div>
</template>

<style scoped>
.tenant-list,
.audit-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}
</style>
