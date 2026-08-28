<script setup lang="ts">interface O{number:string;status:string;customer:{name:string};garment:{name:string}}interface D{metrics:{due_today:number;in_progress:number;ready:number;revenue_minor:number};recent_orders:O[];onboarding:Array<{key:string;label:string;complete:boolean}>}const api=useTailorsApi(),{t}=useTailorsI18n(),data=ref<D|null>(null),loading=ref(true),error=ref('');const parts=[{id:'body',name:'Body'},{id:'chest',name:'Chest'},{id:'sleeve',name:'Sleeve'},{id:'collar',name:'Collar'},{id:'cuff',name:'Cuff'}],measurements=[{part_id:'body',value:42,unit:'inch'},{part_id:'chest',value:40,unit:'inch'},{part_id:'sleeve',value:24,unit:'inch'}];const cards=computed(()=>{const m=data.value?.metrics;return[[m?.due_today??0,t('workspace.dashboard.due_today')],[m?.in_progress??0,t('workspace.dashboard.in_progress')],[m?.ready??0,t('workspace.dashboard.ready')],[`৳ ${((m?.revenue_minor??0)/100).toLocaleString()}`,t('workspace.dashboard.revenue_today')]]});onMounted(async()=>{try{data.value=(await api.request<D>('/dashboard')).data}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not load dashboard.'}finally{loading.value=false}})</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        {{ t('workspace.dashboard.eyebrow') }}
      </p><h1>শুভ সকাল</h1><p>{{ t('workspace.dashboard.help') }}</p>
    </div>
  </header><p v-if="error" class="error">
    {{ error }}
  </p><section class="metrics" :aria-busy="loading">
    <article v-for="m in cards" :key="String(m[1])">
      <strong>{{ m[0] }}</strong><small>{{ m[1] }}</small>
    </article>
  </section><section v-if="data?.onboarding.some(step => !step.complete)" class="panel onboarding-panel">
    <p class="eyebrow">GET STARTED</p><h2>{{ t('workspace.dashboard.onboarding') }}</h2>
    <div v-for="step in data.onboarding" :key="step.key" class="record"><strong>{{ step.complete ? "✓" : "○" }} {{ step.label }}</strong></div>
    <small>Complete these steps to make the workshop ready for daily use.</small>
  </section><div class="grid">
    <section class="panel">
      <p class="eyebrow">
        WORKSHOP QUEUE
      </p><h2>{{ t('workspace.dashboard.recent_orders') }}</h2><p v-if="loading">
        {{ t('common.loading') }}
      </p><p v-else-if="!data?.recent_orders.length" class="empty">
        {{ t('workspace.dashboard.no_orders') }}
      </p><div v-for="o in data?.recent_orders||[]" :key="o.number" class="order">
        <i></i><span><strong>{{ o.customer.name }}</strong><small>{{ o.number }} · {{ o.garment.name }}</small></span><em>{{ o.status }}</em>
      </div>
    </section><section class="panel">
      <p class="eyebrow">
        {{ t('workspace.dashboard.voice_session') }}
      </p><h2>{{ t('workspace.dashboard.live_prototype') }}</h2><GarmentPrototypeBuilder garment-name="Panjabi" :parts="parts" :measurements="measurements" />
    </section>
  </div>
</template>
