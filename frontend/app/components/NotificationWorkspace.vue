<script setup lang="ts">
interface Template{id:string;event:string;channel:string;name:string;body:string;active:boolean}
interface Campaign{id:string;name:string;occasion?:string;status:string;recipient_count:number;queued_at:string;scheduled_at?:string}
interface Delivery{id:string;event:string;channel:string;recipient:string;status:string;sent_at?:string;error?:string}
interface Reminder{id:string;order_number?:string;recipient?:string;channel:string;status:string;scheduled_at?:string;sent_at?:string}
const api=useTailorsApi(),templates=ref<Template[]>([]),campaigns=ref<Campaign[]>([]),deliveries=ref<Delivery[]>([]),reminders=ref<Reminder[]>([]),error=ref(''),message=ref(''),template=reactive({event:'order.ready',channel:'sms',name:'Order ready',body:'Hello {{customer_name}}, your order {{order_number}} is ready.',active:true}),campaign=reactive({name:'',channel:'sms',body:'Hello {{customer_name}},',consent_confirmed:false}),occasion=reactive({name:'',occasion:'eid',channel:'sms',body:'Happy {{occasion}} from {{shop_name}}!',consent_confirmed:false,scheduled_at:''})
async function load(){const result=(await api.request<{templates:Template[];campaigns:Campaign[];deliveries:Delivery[];reminders:Reminder[]}>('/notifications')).data;templates.value=result.templates;campaigns.value=result.campaigns;deliveries.value=result.deliveries;reminders.value=result.reminders||[]}
async function saveTemplate(){error.value='';try{await api.request('/notification-templates',{method:'POST',body:template});message.value='Template saved.';await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not save template.'}}
async function sendCampaign(){error.value='';try{const result=(await api.request<{campaign:{recipient_count:number}}>('/notification-campaigns',{method:'POST',body:campaign})).data;message.value=`Campaign queued for ${result.campaign.recipient_count} consenting customers.`;Object.assign(campaign,{name:'',channel:'sms',body:'Hello {{customer_name}},',consent_confirmed:false});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Campaign unavailable on this plan.'}}
async function sendOccasion(){error.value='';try{const body:any={name:occasion.name,occasion:occasion.occasion,channel:'sms',body:occasion.body,consent_confirmed:occasion.consent_confirmed};if(occasion.scheduled_at)body.scheduled_at=new Date(occasion.scheduled_at).toISOString();const result=(await api.request<{campaign:{status:string;scheduled_at?:string}}>('/notification-campaigns/occasion',{method:'POST',body})).data;message.value=result.campaign.scheduled_at?`Occasion campaign scheduled for ${new Date(result.campaign.scheduled_at).toLocaleString()}.`:'Occasion campaign queued.';Object.assign(occasion,{name:'',occasion:'eid',body:'Happy {{occasion}} from {{shop_name}}!',consent_confirmed:false,scheduled_at:''});await load()}catch(e:any){error.value=e?.data?.errors?.[0]?.message||'Could not queue occasion campaign.'}}
onMounted(load)
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        COMMUNICATIONS
      </p><h1>Notifications</h1><p>Transactional templates, consent-aware campaigns and delivery status.</p>
    </div>
  </header><p v-if="message" class="success" role="status">
    {{ message }}
  </p><p v-if="error" class="error" role="alert">
    {{ error }}
  </p><div class="operation-cards">
    <form class="panel form" @submit.prevent="saveTemplate">
      <h2>Transactional template</h2><label>Event<select v-model="template.event"><option value="order.ready">Order ready</option><option value="order.reminder">Delivery reminder</option></select></label><label>Channel<select v-model="template.channel"><option>sms</option><option>email</option></select></label><label>Name<input v-model="template.name" required></label><label>Message<textarea v-model="template.body" required /></label><small>Available variables: &#123;&#123;customer_name&#125;&#125;, &#123;&#123;order_number&#125;&#125;</small><button class="primary">
        Save template
      </button>
    </form><form class="panel form" @submit.prevent="sendCampaign">
      <h2>Campaign</h2><label>Name<input v-model="campaign.name" required></label><label>SMS message<textarea v-model="campaign.body" required /></label><label class="check"><input v-model="campaign.consent_confirmed" type="checkbox" required> Send only to customers with recorded marketing consent</label><button class="primary">
        Queue campaign
      </button>
    </form><form class="panel form" @submit.prevent="sendOccasion">
      <h2>Scheduled occasion campaign</h2><label>Name<input v-model="occasion.name" required></label><label>Occasion<select v-model="occasion.occasion"><option value="eid">Eid</option><option value="pohela-boishakh">Pohela Boishakh</option><option value="wedding">Wedding season</option><option value="winter">Winter collection</option><option value="custom">Custom</option></select></label><label>SMS message<textarea v-model="occasion.body" required /></label><label>Schedule (optional)<input v-model="occasion.scheduled_at" type="datetime-local"></label><label class="check"><input v-model="occasion.consent_confirmed" type="checkbox" required> Send only to customers with recorded marketing consent</label><button class="primary">
        Schedule occasion campaign
      </button>
    </form>
  </div><div class="workspace-grid">
    <section class="panel">
      <h2>Templates</h2><div v-for="item in templates" :key="item.id" class="record">
        <strong>{{ item.name }}</strong><span>{{ item.event }} · {{ item.channel }}</span><small>{{ item.active?'Active':'Inactive' }}</small>
      </div>
    </section><section class="panel">
      <h2>Delivery log</h2><div v-for="delivery in deliveries" :key="delivery.id" class="record">
        <strong>{{ delivery.event }}</strong><span>{{ delivery.recipient }} · {{ delivery.channel }}</span><small>{{ delivery.status }} {{ delivery.error||'' }}</small>
      </div><p v-if="!deliveries.length" class="empty">
        No deliveries yet.
      </p>
    </section><section class="panel">
      <h2>Delivery reminders</h2><div v-for="reminder in reminders" :key="reminder.id" class="record">
        <strong>{{ reminder.order_number||'—' }}</strong><span>{{ reminder.recipient||'—' }} · {{ reminder.channel }}</span><small>{{ reminder.status }} {{ reminder.scheduled_at?`due ${new Date(reminder.scheduled_at).toLocaleDateString()}`:'' }}</small>
      </div><p v-if="!reminders.length" class="empty">
        No delivery reminders yet. Orders with a promised date auto-schedule one.
      </p>
    </section>
  </div>
</template>
