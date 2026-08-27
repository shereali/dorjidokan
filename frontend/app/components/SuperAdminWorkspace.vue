<script setup lang="ts">
interface Tenant{id:string;name:string;slug:string;status:string;users_count:number}
interface Audit{action:string;tenant?:{name:string};actor?:string;ip_address?:string;created_at:string}
const api=useTailorsApi(),tenants=ref<Tenant[]>([]),audits=ref<Audit[]>([]),query=ref(''),loading=ref(true),error=ref('')
async function load(){loading.value=true;error.value='';try{const [tenantResponse,auditResponse]=await Promise.all([api.request<{items:Tenant[]}>(`/super-admin/tenants?query=${encodeURIComponent(query.value)}`),api.request<{items:Audit[]}>('/super-admin/audits')]);tenants.value=tenantResponse.data.items;audits.value=auditResponse.data.items}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load platform controls.'}finally{loading.value=false}}
async function setStatus(tenant:Tenant){await api.request(`/super-admin/tenants/${tenant.id}/status`,{method:'PATCH',body:{status:tenant.status==='active'?'suspended':'active'}});await load()}
async function impersonate(tenant:Tenant){const result=(await api.request<{authenticated:boolean;tenant:{id:string;slug:string}}> (`/super-admin/tenants/${tenant.id}/impersonate`,{method:'POST'})).data;api.authenticated.value=result.authenticated;api.tenant.value=result.tenant.slug;api.tenantId.value=result.tenant.id;api.isSuperAdmin.value=false;location.reload()}
onMounted(load)
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        CONTROL PLANE
      </p><h1>Super Admin</h1><p>Tenant health, access controls and audited support actions.</p>
    </div>
  </header><p v-if="!api.hasTwoFactor.value" class="error" role="alert">
    Two-factor confirmation is required before privileged actions.
  </p><div class="workspace-grid">
    <section class="panel">
      <div class="toolbar">
        <input v-model="query" aria-label="Search tenants" placeholder="Search tenants" @keyup.enter="load"><button @click="load">
          Search
        </button>
      </div><p v-if="loading">
        Loading…
      </p><p v-if="error" class="error" role="alert">
        {{ error }}
      </p><div v-for="tenant in tenants" :key="tenant.id" class="record admin-record">
        <span><strong>{{ tenant.name }}</strong><small>{{ tenant.slug }} · {{ tenant.users_count }} users</small></span><b>{{ tenant.status }}</b><span><button @click="setStatus(tenant)">{{ tenant.status==='active'?'Suspend':'Activate' }}</button><button :disabled="tenant.status!=='active'" @click="impersonate(tenant)">Support login</button></span>
      </div>
    </section><section class="panel">
      <h2>Recent audit log</h2><p v-if="!audits.length" class="empty">
        No privileged actions recorded.
      </p><div v-for="audit in audits" :key="`${audit.created_at}-${audit.action}`" class="record">
        <strong>{{ audit.action }}</strong><span>{{ audit.tenant?.name||'Platform' }} · {{ audit.actor||'System' }}</span><small>{{ new Date(audit.created_at).toLocaleString() }} · {{ audit.ip_address }}</small>
      </div>
    </section>
  </div>
</template>
