<script setup lang="ts">
interface Template {
  id: string;
  event: string;
  channel: string;
  name: string;
  body: string;
  active: boolean;
}

interface Campaign {
  id: string;
  name: string;
  occasion?: string;
  status: string;
  recipient_count: number;
  queued_at: string;
  scheduled_at?: string;
}

interface Delivery {
  id: string;
  event: string;
  channel: string;
  recipient: string;
  status: string;
  sent_at?: string;
  error?: string;
}

interface Reminder {
  id: string;
  order_number?: string;
  recipient?: string;
  channel: string;
  status: string;
  scheduled_at?: string;
  sent_at?: string;
}

const api = useTailorsApi();
const toast = useToast();

const templates = ref<Template[]>([]);
const campaigns = ref<Campaign[]>([]);
const deliveries = ref<Delivery[]>([]);
const reminders = ref<Reminder[]>([]);
const loading = ref(true);

const template = reactive({
  event: "order.ready",
  channel: "sms",
  name: "Order ready notification",
  body: "Hello {{customer_name}}, your tailoring order {{order_number}} is ready for pickup at Dorjidokan.",
  active: true,
});

const campaign = reactive({
  name: "",
  channel: "sms",
  body: "Hello {{customer_name}}, special promotion for you!",
  consent_confirmed: true,
});

const occasion = reactive({
  name: "",
  occasion: "eid",
  channel: "sms",
  body: "Happy {{occasion}} to you and your family from {{shop_name}}! May this festive season bring joy.",
  consent_confirmed: true,
  scheduled_at: "",
});

async function load() {
  loading.value = true;
  try {
    const result = (
      await api.request<{
        templates: Template[];
        campaigns: Campaign[];
        deliveries: Delivery[];
        reminders: Reminder[];
      }>("/notifications")
    ).data;
    templates.value = result.templates;
    campaigns.value = result.campaigns;
    deliveries.value = result.deliveries;
    reminders.value = result.reminders || [];
  } catch (e: any) {
    toast.error("Could not load notification data.");
  } finally {
    loading.value = false;
  }
}

async function saveTemplate() {
  try {
    await api.request("/notification-templates", { method: "POST", body: template });
    toast.success("Transactional template saved.");
    await load();
  } catch (e: any) {
    toast.error("Could not save template.");
  }
}

async function sendCampaign() {
  try {
    const result = (
      await api.request<{ campaign: { recipient_count: number } }>("/notification-campaigns", {
        method: "POST",
        body: campaign,
      })
    ).data;
    toast.success(`Campaign queued for ${result.campaign.recipient_count} consenting customers.`);
    Object.assign(campaign, {
      name: "",
      channel: "sms",
      body: "Hello {{customer_name}},",
      consent_confirmed: true,
    });
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "Could not queue campaign.");
  }
}

async function sendOccasion() {
  try {
    const body: any = {
      name: occasion.name,
      occasion: occasion.occasion,
      channel: "sms",
      body: occasion.body,
      consent_confirmed: occasion.consent_confirmed,
    };
    if (occasion.scheduled_at) body.scheduled_at = new Date(occasion.scheduled_at).toISOString();
    const result = (
      await api.request<{ campaign: { status: string; scheduled_at?: string } }>(
        "/notification-campaigns/occasion",
        { method: "POST", body }
      )
    ).data;
    toast.success(
      result.campaign.scheduled_at
        ? `Occasion campaign scheduled for ${new Date(result.campaign.scheduled_at).toLocaleString()}.`
        : "Occasion campaign queued."
    );
    Object.assign(occasion, {
      name: "",
      occasion: "eid",
      body: "Happy {{occasion}} from {{shop_name}}!",
      consent_confirmed: true,
      scheduled_at: "",
    });
    await load();
  } catch (e: any) {
    toast.error("Could not schedule occasion campaign.");
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">CUSTOMER COMMUNICATIONS</p>
        <h1>SMS & Notifications</h1>
        <p>Automated order ready alerts, delivery reminders, and festival/occasion campaigns.</p>
      </div>
    </header>

    <div class="operation-cards">
      <!-- Transactional Template -->
      <form class="panel form" @submit.prevent="saveTemplate">
        <h2>Transactional Template (স্বয়ংক্রিয় বার্তা)</h2>
        <div class="form-grid-2">
          <label>Trigger Event
            <select v-model="template.event">
              <option value="order.ready">Order Ready for Pickup</option>
              <option value="order.reminder">Delivery Promised Reminder</option>
            </select>
          </label>
          <label>Channel
            <select v-model="template.channel"><option>sms</option><option>email</option></select>
          </label>
        </div>
        <label>Template Name
          <input v-model="template.name" required />
        </label>
        <label>Message Content
          <textarea v-model="template.body" required></textarea>
        </label>
        <small style="color:var(--muted)">Variables: &#123;&#123;customer_name&#125;&#125;, &#123;&#123;order_number&#125;&#125;, &#123;&#123;shop_name&#125;&#125;</small>
        <button class="primary">Save Template</button>
      </form>

      <!-- Occasion Campaign -->
      <form class="panel form" @submit.prevent="sendOccasion">
        <h2>Festival & Occasion Campaign (উৎসবের শুভেচ্ছা)</h2>
        <label>Campaign Name
          <input v-model="occasion.name" placeholder="e.g. Eid-ul-Fitr 2026 Greetings" required />
        </label>
        <label>Festival Occasion
          <select v-model="occasion.occasion">
            <option value="eid">Eid Greetings (ঈদ মোবারক)</option>
            <option value="pohela-boishakh">Pohela Boishakh (পহেলা বৈশাখ)</option>
            <option value="wedding">Wedding Season (বিয়ে উৎসব)</option>
            <option value="winter">Winter Collection (শীত কালেকশন)</option>
            <option value="custom">Custom Offer (বিশেষ ছাড়)</option>
          </select>
        </label>
        <label>SMS Body
          <textarea v-model="occasion.body" required></textarea>
        </label>
        <label>Scheduled Send Time (Optional)
          <input v-model="occasion.scheduled_at" type="datetime-local" />
        </label>
        <button class="primary">Schedule Occasion Campaign</button>
      </form>
    </div>

    <!-- Delivery History Logs -->
    <div class="workspace-grid">
      <section class="panel">
        <h2>Delivery Reminders Queue</h2>
        <p v-if="!reminders.length" class="empty">No reminders pending. Orders with promised dates auto-schedule reminders.</p>
        <div v-for="r in reminders" :key="r.id" class="record record--stock">
          <div>
            <strong>#{{ r.order_number || '—' }}</strong>
            <small style="display:block">📞 {{ r.recipient || '—' }} · {{ r.channel }}</small>
          </div>
          <span class="status" :class="`status--${r.status}`">{{ r.status }}</span>
        </div>
      </section>

      <section class="panel">
        <h2>Recent Sent Messages Log</h2>
        <p v-if="!deliveries.length" class="empty">No sent SMS logs.</p>
        <div v-for="d in deliveries" :key="d.id" class="record record--stock">
          <div>
            <strong>{{ d.event }}</strong>
            <small style="display:block">{{ d.recipient }} · {{ d.channel }}</small>
          </div>
          <span class="status" :class="`status--${d.status}`">{{ d.status }}</span>
        </div>
      </section>
    </div>
  </div>
</template>
