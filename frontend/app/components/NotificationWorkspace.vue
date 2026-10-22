<script setup lang="ts">
const { t } = useTailorsI18n();
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
    toast.error(t('app.notification.could_not_load_notifications'));
  } finally {
    loading.value = false;
  }
}

async function saveTemplate() {
  try {
    await api.request("/notification-templates", { method: "POST", body: template });
    toast.success(t('app.notification.template_saved'));
    await load();
  } catch (e: any) {
    toast.error(t('app.notification.could_not_save_the_template'));
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
    toast.success(t('app.notification.campaign_queued_for_p0_consenting_customers', { p0: result.campaign.recipient_count }));
    Object.assign(campaign, {
      name: "",
      channel: "sms",
      body: "Hello {{customer_name}},",
      consent_confirmed: true,
    });
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || t('app.notification.could_not_queue_the_campaign'));
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
    toast.error(t('app.notification.could_not_schedule_the_campaign'));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.notification.customer_messages') }}</p>
        <h1>{{ t('app.notification.sms_notifications') }}</h1>
        <p>{{ t('app.notification.order_ready_alerts_delivery_reminders_and') }}</p>
      </div>
    </header>

    <div class="operation-cards">
      <!-- Transactional Template -->
      <form class="panel form" @submit.prevent="saveTemplate">
        <h2>{{ t('app.notification.automatic_message_template') }}</h2>
        <div class="form-grid-2">
          <label>{{ t('app.notification.when_to_send') }}
            <select v-model="template.event">
              <option value="order.ready">{{ t('app.notification.order_ready_for_pickup') }}</option>
              <option value="order.reminder">{{ t('app.notification.delivery_date_reminder') }}</option>
            </select>
          </label>
          <label>{{ t('app.notification.channel') }}
            <select v-model="template.channel"><option>{{ t('app.notification.sms') }}</option><option>{{ t('app.notification.email') }}</option></select>
          </label>
        </div>
        <label>{{ t('app.notification.template_name') }}
          <input v-model="template.name" required />
        </label>
        <label>{{ t('app.notification.message') }}
          <textarea v-model="template.body" required></textarea>
        </label>
        <small style="color:var(--muted)">Variables: &#123;&#123;customer_name&#125;&#125;, &#123;&#123;order_number&#125;&#125;, &#123;&#123;shop_name&#125;&#125;</small>
        <button class="primary">{{ t('app.notification.save_template') }}</button>
      </form>

      <!-- Occasion Campaign -->
      <form class="panel form" @submit.prevent="sendOccasion">
        <h2>{{ t('app.notification.festival_occasion_campaign') }}</h2>
        <label>{{ t('app.notification.campaign_name') }}
          <input v-model="occasion.name" :placeholder="t('app.notification.e_g_eid_ul_fitr_2026')" required />
        </label>
        <label>{{ t('app.notification.occasion') }}
          <select v-model="occasion.occasion">
            <option value="eid">{{ t('app.notification.eid_greetings') }}</option>
            <option value="pohela-boishakh">{{ t('app.notification.pohela_boishakh') }}</option>
            <option value="wedding">{{ t('app.notification.wedding_season') }}</option>
            <option value="winter">{{ t('app.notification.winter_collection') }}</option>
            <option value="custom">{{ t('app.notification.special_offer') }}</option>
          </select>
        </label>
        <label>{{ t('app.notification.sms_text') }}
          <textarea v-model="occasion.body" required></textarea>
        </label>
        <label>{{ t('app.notification.send_time_optional') }}
          <input v-model="occasion.scheduled_at" type="datetime-local" />
        </label>
        <button class="primary">{{ t('app.notification.schedule_campaign') }}</button>
      </form>
    </div>

    <!-- Delivery History Logs -->
    <div class="workspace-grid">
      <section class="panel">
        <h2>{{ t('app.notification.delivery_reminders_queue') }}</h2>
        <p v-if="!reminders.length" class="empty">{{ t('app.notification.no_reminders_pending_orders_with_a') }}</p>
        <div v-for="r in reminders" :key="r.id" class="record record--stock">
          <div>
            <strong>#{{ r.order_number || '—' }}</strong>
            <small style="display:block">📞 {{ r.recipient || '—' }} · {{ r.channel }}</small>
          </div>
          <span class="status" :class="`status--${r.status}`">{{ statusLabel(r.status) }}</span>
        </div>
      </section>

      <section class="panel">
        <h2>{{ t('app.notification.recently_sent_messages') }}</h2>
        <p v-if="!deliveries.length" class="empty">{{ t('app.notification.no_messages_sent_yet') }}</p>
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
