<script setup lang="ts">
const { tr } = usePlatformFormat();
interface Member {
  id: string;
  name: string;
  email: string;
  role: string;
}

const api = useTailorsApi();
const toast = useToast();

const members = ref<Member[]>([]);
const message = ref("");
const error = ref("");

const settings = reactive({
  name: "",
  default_locale: "bn",
  currency: "BDT",
  preferences: {
    order_prefix: "ORD",
    default_delivery_days: 7,
    low_stock_alerts: true,
    order_ready_notifications: true,
  },
});

const member = reactive({ name: "", email: "", role: "receptionist" });
const { locale } = useTailorsI18n();
const roleName = (key: string) => {
  const r = ROLE_LABELS[key];
  return r ? r[locale.value === "bn" ? "bn" : "en"] : key;
};
const canEditSettings = computed(() => api.can("settings.manage"));
const twoFactor = reactive({
  enabled: false,
  pending: false,
  secret: "",
  otpauth_url: "",
  code: "",
  recovery_codes: [] as string[],
  password: "",
});

const deletion = ref<{ id: string; scheduled_for: string } | null>(null);
const deletionConfirmation = reactive({ tenant_slug: "", password: "" });

async function load() {
  try {
    if (api.can("settings.view", "settings.manage")) {
      const result = await api.request<{ settings: typeof settings }>("/settings");
      Object.assign(settings, result.data.settings);
    }
    if (api.can("members.manage")) {
      members.value = (await api.request<{ items: Member[] }>("/members")).data.items;
    }
    Object.assign(twoFactor, (await api.request<{ enabled: boolean; pending: boolean }>("/auth/two-factor")).data);
    if (api.can("tenant.delete")) {
      deletion.value = (await api.request<{ deletion: { id: string; scheduled_for: string } | null }>("/tenant-deletion")).data.deletion;
    }
  } catch {
    // Non-blocking
  }
}

async function saveSettings() {
  try {
    await api.request("/settings", {
      method: "PATCH",
      body: {
        name: settings.name,
        default_locale: settings.default_locale,
        currency: settings.currency,
        settings: settings.preferences,
      },
    });
    toast.success(tr("Settings saved.", "সেটিংস সংরক্ষিত হয়েছে।"));
  } catch (e: any) {
    toast.error(tr("Could not save settings.", "সেটিংস সংরক্ষণ করা যায়নি।"));
  }
}

async function addMember() {
  error.value = "";
  try {
    await api.request("/members", { method: "POST", body: member });
    toast.success(tr("Invitation sent.", "আমন্ত্রণ পাঠানো হয়েছে।"));
    Object.assign(member, { name: "", email: "", role: "receptionist" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || tr("Could not add the member.", "সদস্য যোগ করা যায়নি।");
  }
}

async function changeRole(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "PATCH", body: { role: item.role } });
    toast.success(tr(`Role updated for ${item.name}.`, `${item.name}-এর রোল বদলেছে।`));
  } catch {
    toast.error(tr("Could not change the role.", "রোল বদলানো যায়নি।"));
  }
}

async function removeMember(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "DELETE" });
    toast.info(tr("Member removed.", "সদস্য সরানো হয়েছে।"));
    await load();
  } catch {
    toast.error(tr("Could not remove the member.", "সদস্য সরানো যায়নি।"));
  }
}

async function setupTwoFactor() {
  const res = (await api.request<{ secret: string; otpauth_url: string }>("/auth/two-factor/setup", { method: "POST" })).data;
  Object.assign(twoFactor, res);
  twoFactor.pending = true;
}

async function confirmTwoFactor() {
  try {
    const res = await api.request<{ enabled: boolean; recovery_codes: string[] }>("/auth/two-factor/confirm", {
      method: "POST",
      body: { code: twoFactor.code },
    });
    twoFactor.enabled = true;
    twoFactor.pending = false;
    twoFactor.recovery_codes = res.data.recovery_codes;
    api.hasTwoFactor.value = true;
    toast.success(tr("Two-factor authentication is on.", "দ্বি-স্তর নিরাপত্তা চালু হয়েছে।"));
  } catch {
    toast.error(tr("That code is not valid.", "কোডটি সঠিক নয়।"));
  }
}

async function disableTwoFactor() {
  try {
    await api.request("/auth/two-factor", { method: "DELETE", body: { password: twoFactor.password } });
    Object.assign(twoFactor, { enabled: false, pending: false, secret: "", recovery_codes: [], password: "" });
    api.hasTwoFactor.value = false;
    toast.info(tr("2FA turned off.", "2FA বন্ধ হয়েছে।"));
  } catch {
    toast.error(tr("Wrong password.", "পাসওয়ার্ড ভুল।"));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ tr("WORKSHOP ADMINISTRATION", "ওয়ার্কশপ প্রশাসন") }}</p>
        <h1>{{ tr("Settings & workshop profile", "সেটিংস ও ওয়ার্কশপ প্রোফাইল") }}</h1>
        <p>{{ tr("Defaults, currency, team roles, security and data export.", "ডিফল্ট সেটিংস, মুদ্রা, টিমের রোল, নিরাপত্তা ও ডেটা এক্সপোর্ট।") }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- General Preferences Form -->
      <form v-if="api.can('settings.view', 'settings.manage')" class="panel form" @submit.prevent="saveSettings">
        <fieldset :disabled="!canEditSettings" style="border:0;padding:0;margin:0;display:contents">
          <h2>{{ tr("Workshop preferences", "দোকানের তথ্য") }}</h2>
          <label>{{ tr("Business name *", "প্রতিষ্ঠানের নাম *") }}
            <input v-model="settings.name" :placeholder="tr('e.g. Dorjidokan Tailors', 'যেমন: দর্জিদোকান টেইলার্স')" required />
          </label>
          <div class="form-grid-2">
            <label>{{ tr("Default language", "ডিফল্ট ভাষা") }}
              <select v-model="settings.default_locale">
                <option value="bn">{{ tr("Bengali (বাংলা)", "বাংলা") }}</option>
                <option value="en">{{ tr("English", "English") }}</option>
              </select>
            </label>
            <label>{{ tr("Currency code", "মুদ্রার কোড") }}
              <input v-model="settings.currency" minlength="3" maxlength="3" required />
            </label>
          </div>
          <div class="form-grid-2">
            <label>{{ tr("Order number prefix", "অর্ডার নম্বরের প্রিফিক্স") }}
              <input v-model="settings.preferences.order_prefix" :placeholder="tr('ORD', 'ORD')" />
            </label>
            <label>{{ tr("Default delivery days", "ডিফল্ট ডেলিভারি দিন") }}
              <input v-model.number="settings.preferences.default_delivery_days" type="number" min="1" />
            </label>
          </div>
          <label class="check-label">
            <input v-model="settings.preferences.low_stock_alerts" type="checkbox" />
            <span>{{ tr("Alert me when fabric stock is low", "কাপড়ের স্টক কমলে সতর্ক করুন") }}</span>
          </label>
          <label class="check-label">
            <input v-model="settings.preferences.order_ready_notifications" type="checkbox" />
            <span>{{ tr("Send an SMS automatically when an order is ready", "অর্ডার প্রস্তুত হলে নিজে থেকে SMS পাঠান") }}</span>
          </label>
          <button v-if="canEditSettings" class="primary">{{ tr("Save preferences", "সংরক্ষণ করুন") }}</button>
        </fieldset>
      </form>

      <!-- Staff Members Management -->
      <section v-if="api.can('members.manage')" class="panel">
        <form class="form" style="margin-bottom:1.5rem" @submit.prevent="addMember">
          <h2>{{ tr("Invite a team member", "টিমের সদস্যকে আমন্ত্রণ") }}</h2>
          <label>{{ tr("Name *", "নাম *") }}
            <input v-model="member.name" required />
          </label>
          <label>{{ tr("Email *", "ইমেইল *") }}
            <input v-model="member.email" type="email" required />
          </label>
          <label>{{ tr("Role *", "রোল *") }}
            <select v-model="member.role">
              <option value="receptionist">{{ roleName('receptionist') }}</option>
              <option value="master">{{ roleName('master') }}</option>
              <option value="karigar">{{ roleName('karigar') }}</option>
              <option value="accountant">{{ roleName('accountant') }}</option>
              <option value="manager">{{ roleName('manager') }}</option>
              <option value="owner">{{ roleName('owner') }}</option>
            </select>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
          <button class="primary">{{ tr("Send invitation", "আমন্ত্রণ পাঠান") }}</button>
        </form>

        <h3>{{ tr("Team members", "টিমের সদস্য") }}</h3>
        <div v-for="item in members" :key="item.id" class="record record--stock">
          <div>
            <strong>{{ item.name }}</strong>
            <small style="display:block">{{ item.email }}</small>
          </div>
          <select v-model="item.role" :aria-label="`Role for ${item.name}`" @change="changeRole(item)">
            <option value="receptionist">{{ roleName('receptionist') }}</option>
            <option value="master">{{ roleName('master') }}</option>
            <option value="karigar">{{ roleName('karigar') }}</option>
            <option value="accountant">{{ roleName('accountant') }}</option>
            <option value="manager">{{ roleName('manager') }}</option>
            <option value="owner">{{ roleName('owner') }}</option>
          </select>
          <button class="mini-btn" @click="removeMember(item)">{{ tr("Remove", "সরান") }}</button>
        </div>
      </section>
    </div>

    <RoleMatrixEditor v-if="api.can('roles.manage', 'members.manage')" />

    <!-- Security & 2FA Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>{{ tr("Two-factor security", "দ্বি-স্তর নিরাপত্তা") }}</h2>
      <p>{{ twoFactor.enabled ? tr("Authenticator protection is on for this account.", "এই অ্যাকাউন্টে অথেনটিকেটর সুরক্ষা চালু আছে।") : tr("Protect sign-in with an authenticator app such as Google Authenticator.", "Google Authenticator-এর মতো অ্যাপ দিয়ে লগইন সুরক্ষিত করুন।") }}</p>

      <button v-if="!twoFactor.enabled && !twoFactor.pending" class="primary" @click="setupTwoFactor">
        {{ tr("Set up authenticator app", "অথেনটিকেটর অ্যাপ চালু করুন") }}
      </button>

      <form v-if="twoFactor.pending" class="form" style="max-width:28rem" @submit.prevent="confirmTwoFactor">
        <p>{{ tr("Enter this secret in Google Authenticator:", "এই সিক্রেট Google Authenticator-এ বসান:") }}</p>
        <code style="padding:0.5rem;background:var(--paper);display:block;border-radius:4px">{{ twoFactor.secret }}</code>
        <label>{{ tr("6-digit code", "৬ সংখ্যার কোড") }}
          <input v-model="twoFactor.code" inputmode="numeric" placeholder="123456" required />
        </label>
        <button class="primary">{{ tr("Confirm & turn on 2FA", "নিশ্চিত করে 2FA চালু করুন") }}</button>
      </form>

      <form v-if="twoFactor.enabled" class="form" style="max-width:24rem;margin-top:1rem" @submit.prevent="disableTwoFactor">
        <label>{{ tr("Enter your password to turn off 2FA", "2FA বন্ধ করতে পাসওয়ার্ড দিন") }}
          <input v-model="twoFactor.password" type="password" required />
        </label>
        <button class="secondary">{{ tr("Turn off 2FA", "2FA বন্ধ করুন") }}</button>
      </form>
    </section>
  </div>
</template>

<style scoped>
.check-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
}
</style>
