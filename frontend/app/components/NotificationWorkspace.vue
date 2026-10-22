<script setup lang="ts">
const { tr } = usePlatformFormat();
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
    toast.error(tr("Could not load notifications.", "নোটিফিকেশন লোড করা যায়নি।"));
  } finally {
    loading.value = false;
  }
}

async function saveTemplate() {
  try {
    await api.request("/notification-templates", { method: "POST", body: template });
    toast.success(tr("Template saved.", "টেমপ্লেট সংরক্ষিত হয়েছে।"));
    await load();
  } catch (e: any) {
    toast.error(tr("Could not save the template.", "টেমপ্লেট সংরক্ষণ করা যায়নি।"));
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
    toast.success(tr(`Campaign queued for ${result.campaign.recipient_count} consenting customers.`, `${result.campaign.recipient_count} জন সম্মত গ্রাহকের জন্য ক্যাম্পেইন সাজানো হয়েছে।`));
    Object.assign(campaign, {
      name: "",
      channel: "sms",
      body: "Hello {{customer_name}},",
      consent_confirmed: true,
    });
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || tr("Could not queue the campaign.", "ক্যাম্পেইন সাজানো যায়নি।"));
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
    toast.error(tr("Could not schedule the campaign.", "ক্যাম্পেইন নির্ধারণ করা যায়নি।"));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("CUSTOMER MESSAGES", "গ্রাহক বার্তা") }}</p>
        <h1>{{ tr("SMS & notifications", "SMS ও নোটিফিকেশন") }}</h1>
        <p>{{ tr("Order-ready alerts, delivery reminders and festival campaigns.", "অর্ডার প্রস্তুতের বার্তা, ডেলিভারির রিমাইন্ডার ও উৎসবের ক্যাম্পেইন।") }}</p>
      </div>
    </header>

    <div class="operation-cards">
      <!-- Transactional Template -->
      <form class="panel form" @submit.prevent="saveTemplate">
        <h2>{{ tr("Automatic message template", "স্বয়ংক্রিয় বার্তার টেমপ্লেট") }}</h2>
        <div class="form-grid-2">
          <label>{{ tr("When to send", "কখন পাঠাবে") }}
            <select v-model="template.event">
              <option value="order.ready">{{ tr("Order ready for pickup", "অর্ডার ডেলিভারির জন্য প্রস্তুত") }}</option>
              <option value="order.reminder">{{ tr("Delivery date reminder", "ডেলিভারির তারিখের রিমাইন্ডার") }}</option>
            </select>
          </label>
          <label>{{ tr("Channel", "মাধ্যম") }}
            <select v-model="template.channel"><option>{{ tr("SMS", "SMS") }}</option><option>{{ tr("Email", "ইমেইল") }}</option></select>
          </label>
        </div>
        <label>{{ tr("Template name", "টেমপ্লেটের নাম") }}
          <input v-model="template.name" required />
        </label>
        <label>{{ tr("Message", "বার্তা") }}
          <textarea v-model="template.body" required></textarea>
        </label>
        <small style="color:var(--muted)">Variables: &#123;&#123;customer_name&#125;&#125;, &#123;&#123;order_number&#125;&#125;, &#123;&#123;shop_name&#125;&#125;</small>
        <button class="primary">{{ tr("Save template", "টেমপ্লেট সংরক্ষণ") }}</button>
      </form>

      <!-- Occasion Campaign -->
      <form class="panel form" @submit.prevent="sendOccasion">
        <h2>{{ tr("Festival & occasion campaign", "উৎসবের শুভেচ্ছা ক্যাম্পেইন") }}</h2>
        <label>{{ tr("Campaign name", "ক্যাম্পেইনের নাম") }}
          <input v-model="occasion.name" :placeholder="tr('e.g. Eid-ul-Fitr 2026 greetings', 'যেমন: ঈদুল ফিতর ২০২৬ শুভেচ্ছা')" required />
        </label>
        <label>{{ tr("Occasion", "উপলক্ষ") }}
          <select v-model="occasion.occasion">
            <option value="eid">{{ tr("Eid greetings", "ঈদ মোবারক") }}</option>
            <option value="pohela-boishakh">{{ tr("Pohela Boishakh", "পহেলা বৈশাখ") }}</option>
            <option value="wedding">{{ tr("Wedding season", "বিয়ের মৌসুম") }}</option>
            <option value="winter">{{ tr("Winter collection", "শীতের কালেকশন") }}</option>
            <option value="custom">{{ tr("Special offer", "বিশেষ ছাড়") }}</option>
          </select>
        </label>
        <label>{{ tr("SMS text", "SMS-এর লেখা") }}
          <textarea v-model="occasion.body" required></textarea>
        </label>
        <label>{{ tr("Send time (optional)", "পাঠানোর সময় (ঐচ্ছিক)") }}
          <input v-model="occasion.scheduled_at" type="datetime-local" />
        </label>
        <button class="primary">{{ tr("Schedule campaign", "ক্যাম্পেইন নির্ধারণ") }}</button>
      </form>
    </div>

    <!-- Delivery History Logs -->
    <div class="workspace-grid">
      <section class="panel">
        <h2>{{ tr("Delivery reminders queue", "ডেলিভারি রিমাইন্ডারের তালিকা") }}</h2>
        <p v-if="!reminders.length" class="empty">{{ tr("No reminders pending. Orders with a delivery date schedule reminders automatically.", "কোনো রিমাইন্ডার বাকি নেই। ডেলিভারির তারিখ থাকলে রিমাইন্ডার নিজে থেকে ঠিক হয়।") }}</p>
        <div v-for="r in reminders" :key="r.id" class="record record--stock">
          <div>
            <strong>#{{ r.order_number || '—' }}</strong>
            <small style="display:block">📞 {{ r.recipient || '—' }} · {{ r.channel }}</small>
          </div>
          <span class="status" :class="`status--${r.status}`">{{ statusLabel(r.status) }}</span>
        </div>
      </section>

      <section class="panel">
        <h2>{{ tr("Recently sent messages", "সর্বশেষ পাঠানো বার্তা") }}</h2>
        <p v-if="!deliveries.length" class="empty">{{ tr("No messages sent yet.", "এখনো কোনো বার্তা পাঠানো হয়নি।") }}</p>
        <div v-for="d in deliveries" :key="d.id" class="record record--stock">
          <div>
            <strong>{{ d.event }}</strong>
            <small style="display:block">{{ d.recipient }} · {{ d.channel }}</small>
          </div>
          <span class="status" :class="`status--${d.status}`">{{ statusLabel(d.status) }}</span>
        </div>
      </section>
    </div>
  </div>
</template>
