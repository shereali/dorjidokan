<script setup lang="ts">
interface O{number:string;status:string;customer:{name:string};garment:{name:string}}
interface DueOrder{id:string;order_number:string;customer:{name:string;mobile_number:string};garment:{name:string};status:string;promised_at?:string}
interface D{metrics:{due_today:number;in_progress:number;ready:number;revenue_minor:number};due_today_orders:DueOrder[];recent_orders:O[];onboarding:Array<{key:string;label:string;complete:boolean}>}
const api=useTailorsApi(),{t}=useTailorsI18n(),data=ref<D|null>(null),loading=ref(true),error=ref('');
const navigate=defineEmits<{navigate:[string]}>();
const parts=[{id:'body',name:'Body'},{id:'chest',name:'Chest'},{id:'sleeve',name:'Sleeve'},{id:'collar',name:'Collar'},{id:'cuff',name:'Cuff'}],measurements=[{part_id:'body',value:42,unit:'inch'},{part_id:'chest',value:40,unit:'inch'},{part_id:'sleeve',value:24,unit:'inch'}];
const cards=computed(()=>{const m=data.value?.metrics;return[{value:m?.due_today??0,label:t('workspace.dashboard.due_today'),target:'Orders',action:'Open orders'},{value:m?.in_progress??0,label:t('workspace.dashboard.in_progress'),target:'Orders',action:'Open orders'},{value:m?.ready??0,label:t('workspace.dashboard.ready'),target:'Orders',action:'Open orders'},{value:`৳ ${((m?.revenue_minor??0)/100).toLocaleString()}`,label:t('workspace.dashboard.revenue_today'),target:'Reports',action:'See report'}]});
const todayLabel=new Intl.DateTimeFormat(undefined,{weekday:'long',day:'numeric',month:'long'}).format(new Date());
const dueTime=(iso?:string)=>iso?new Date(iso).toLocaleTimeString(undefined,{hour:'2-digit',minute:'2-digit'}):'';
onMounted(async()=>{try{data.value=(await api.request<D>('/dashboard')).data}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load dashboard.'}finally{loading.value=false}})
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        {{ t('workspace.dashboard.eyebrow') }}
      </p><h1>শুভ সকাল</h1><p>{{ t('workspace.dashboard.help') }} · {{ todayLabel }}</p>
    </div>
  </header><p v-if="error" class="error">
    {{ error }}
  </p><section class="metrics" :aria-busy="loading">
    <article v-for="m in cards" :key="String(m.label)">
      <strong>{{ m.value }}</strong><small>{{ m.label }}</small>
      <a class="metric-action" href="#" @click.prevent="navigate('navigate', m.target)">{{ m.action }}</a>
    </article>
  </section><section v-if="data?.onboarding.some(step => !step.complete)" class="panel onboarding-panel">
    <p class="eyebrow">GET STARTED</p><h2>{{ t('workspace.dashboard.onboarding') }}</h2>
    <div v-for="step in data.onboarding" :key="step.key" class="onboarding-step">
      <span class="step-dot" :class="step.complete ? 'step-dot--done' : 'step-dot--todo'">{{ step.complete ? "✓" : "•" }}</span>
      <strong>{{ step.label }}</strong>
    </div>
    <small>Complete these steps to make the workshop ready for daily use.</small>
  </section><div class="grid">
    <section class="panel due-panel">
      <p class="eyebrow">
        TODAY'S PROMISES
      </p><h2>Due today</h2><p v-if="loading">
        {{ t('common.loading') }}
      </p><p v-else-if="!data?.due_today_orders?.length" class="empty">
        Nothing promised for today. Enjoy the quiet.
      </p>
      <article v-for="o in data?.due_today_orders||[]" :key="o.id" class="due-order" :class="{ 'due-order--late': o.promised_at && new Date(o.promised_at) < new Date() }">
        <span class="due-order__time">{{ dueTime(o.promised_at) || '—' }}</span>
        <span class="due-order__main"><strong>{{ o.customer.name }}</strong><small>{{ o.order_number }} · {{ o.garment.name }} · {{ o.customer.mobile_number }}</small></span>
        <span class="status" :class="`status--${o.status}`">{{ statusLabel(o.status) }}</span>
      </article>
    </section><section class="panel">
      <p class="eyebrow">
        {{ t('workspace.dashboard.voice_session') }}
      </p><h2>{{ t('workspace.dashboard.live_prototype') }}</h2><GarmentPrototypeBuilder garment-name="Panjabi" :parts="parts" :measurements="measurements" />
    </section>
  </div><section class="panel recent-panel">
    <p class="eyebrow">
      WORKSHOP QUEUE
    </p><h2>{{ t('workspace.dashboard.recent_orders') }}</h2><p v-if="loading">
      {{ t('common.loading') }}
    </p><p v-else-if="!data?.recent_orders.length" class="empty">
      {{ t('workspace.dashboard.no_orders') }}
    </p><div v-for="o in data?.recent_orders||[]" :key="o.number" class="order">
      <i></i><span><strong>{{ o.customer.name }}</strong><small>{{ o.number }} · {{ o.garment.name }}</small></span><span class="status" :class="`status--${o.status}`">{{ statusLabel(o.status) }}</span>
    </div>
  </section>
</template>
