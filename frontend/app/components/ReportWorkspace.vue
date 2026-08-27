<script setup lang="ts">
interface ExportItem { id:string;type:string;status:string;email?:string;failure_message?:string;download_path?:string }
const api=useTailorsApi(),config=useRuntimeConfig(),from=ref(new Date(new Date().getFullYear(),new Date().getMonth(),1).toISOString().slice(0,10)),to=ref(new Date().toISOString().slice(0,10)),report=ref<any>(null),loading=ref(false),exports=ref<ExportItem[]>([]),exportEmail=ref(""),money=(v:number)=>`৳ ${(v/100).toLocaleString()}`
async function load(){loading.value=true;const [summary,history]=await Promise.all([api.request<any>(`/reports/summary?from=${from.value}&to=${to.value}`),api.request<{items:ExportItem[]}>("/exports")]);report.value=summary.data;exports.value=history.data.items;loading.value=false}
async function createExport(type="summary"){await api.request("/exports",{method:"POST",body:{type,from:from.value,to:to.value,email:exportEmail.value||null}});await load()}
async function download(item:ExportItem){if(!item.download_path)return;const response=await $fetch.raw(`${config.public.apiBase}${item.download_path}`,{credentials:"include",responseType:"blob",headers:{"X-Tenant":api.tenant.value}});const url=URL.createObjectURL(response._data as Blob);const link=document.createElement("a");link.href=url;link.download=`tailors-${item.type}`;link.click();URL.revokeObjectURL(url)}
function preset(days:number){const end=new Date();const start=new Date();start.setDate(end.getDate()-days+1);from.value=start.toISOString().slice(0,10);to.value=end.toISOString().slice(0,10);load()}
onMounted(load)
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        BUSINESS INTELLIGENCE
      </p><h1>Reports</h1><p>Reconciled operational and financial totals.</p>
    </div>
  </header><form class="report-filter" @submit.prevent="load">
    <label>From<input v-model="from" type="date"></label><label>To<input v-model="to" type="date"></label><button>Run report</button><button type="button" @click="preset(1)">Today</button><button type="button" @click="preset(7)">7 days</button><button type="button" @click="preset(30)">30 days</button>
  </form><p v-if="loading">
    Loading…
  </p><div v-else-if="report" class="metrics">
    <article><strong>{{ report.orders.count }}</strong><small>Orders · {{ money(report.orders.value_minor) }}</small></article><article><strong>{{ report.sales.count }}</strong><small>Sales · {{ money(report.sales.value_minor) }}</small></article><article><strong>{{ money(report.expenses_minor) }}</strong><small>Expenses</small></article><article><strong>{{ money(report.wages_unpaid_minor) }}</strong><small>Unpaid wages</small></article>
  </div><section class="panel">
    <h2>Decision dashboard</h2><div v-if="report?.decision" class="metrics"><article><strong>{{ money(report.decision.net_profit_minor) }}</strong><small>Estimated net profit</small></article><article><strong>{{ money(report.decision.receivables_minor) }}</strong><small>Receivables</small></article><article><strong>{{ report.decision.overdue_orders }}</strong><small>Overdue orders</small></article><article><strong>{{ money(report.decision.labor_cost_minor) }}</strong><small>Labor cost</small></article></div>
  </section><section class="panel">
    <h2>Low stock</h2><p v-if="!report?.low_stock.length" class="empty">
      No items below reorder level.
    </p><div v-for="item in report?.low_stock||[]" :key="item.id" class="record">
      <strong>{{ item.name }}</strong><span>{{ item.balance || 0 }} {{ item.unit }}</span>
    </div>
  </section>
  <div class="workspace-grid">
    <form class="panel form" @submit.prevent="createExport('summary')">
      <h2>Export or email report</h2><label>Email (optional)<input v-model="exportEmail" type="email"></label><button class="primary">
        Queue CSV export
      </button><button type="button" @click="createExport('tenant_data')">
        Export all tenant data (JSON)
      </button>
    </form>
    <section class="panel">
      <h2>Export history</h2><p v-if="!exports.length" class="empty">
        No exports yet.
      </p><article v-for="item in exports" :key="item.id" class="record record--stock">
        <span><strong>{{ item.type }}</strong><small>{{ item.status }} {{ item.email ? `· ${item.email}` : '' }}</small><small v-if="item.failure_message" class="error">{{ item.failure_message }}</small></span><button v-if="item.status==='completed'" @click="download(item)">
          Download
        </button>
      </article>
    </section>
  </div>
</template>
