<script setup lang="ts">interface Plan{code:string;name:string;price_minor:number;currency:string;billing_interval:string}const api=useTailorsApi(),billing=ref<any>(null),plans=ref<Plan[]>([]),error=ref('');onMounted(async()=>{const [status,catalog]=await Promise.all([api.request<any>('/billing'),api.request<{plans:Plan[]}>('/plans')]);billing.value=status.data;plans.value=catalog.data.plans});async function redirect(path:string,body?:unknown){try{const r=await api.request<{url:string}>(path,{method:'POST',body});location.href=r.data.url}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Billing is unavailable.'}}</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        SUBSCRIPTION
      </p><h1>Billing & plan</h1><p>Usage limits and payment management.</p>
    </div>
  </header><section class="panel billing">
    <h2>{{ billing?.subscription?.status||'Loading…' }}</h2><p v-if="billing?.subscription?.trial_ends_at">
      Trial ends {{ new Date(billing.subscription.trial_ends_at).toLocaleDateString() }}
    </p><p v-if="billing?.subscription?.grace_ends_at">
      Payment grace period ends {{ new Date(billing.subscription.grace_ends_at).toLocaleDateString() }}
    </p><div v-for="(value,key) in billing?.limits||{}" :key="key" class="record">
      <strong>{{ String(key).replaceAll('_',' ') }}</strong><span>{{ value }}</span>
    </div><p v-if="error" class="error">
      {{ error }}
    </p><button class="primary" @click="redirect('/billing/portal')">
      Manage subscription, invoices and payment method
    </button>
  </section><section class="panel"><h2>Available plans</h2><article v-for="plan in plans" :key="plan.code" class="record record--stock"><span><strong>{{ plan.name }}</strong><small>{{ plan.currency }} {{ (plan.price_minor/100).toFixed(2) }} / {{ plan.billing_interval }}</small></span><button v-if="billing?.subscription?.plan?.code!==plan.code" @click="redirect('/billing/checkout',{plan_code:plan.code})">Choose plan</button><em v-else>Current</em></article></section>
</template>
