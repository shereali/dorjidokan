<script setup lang="ts">
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

const member = reactive({ name: "", email: "", role: "staff" });
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
    const result = await api.request<{ settings: typeof settings }>("/settings");
    Object.assign(settings, result.data.settings);
    if (api.role.value === "admin") {
      members.value = (await api.request<{ items: Member[] }>("/members")).data.items;
    }
    Object.assign(twoFactor, (await api.request<{ enabled: boolean; pending: boolean }>("/auth/two-factor")).data);
    deletion.value = (await api.request<{ deletion: { id: string; scheduled_for: string } | null }>("/tenant-deletion")).data.deletion;
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
    toast.success("Workshop settings saved successfully.");
  } catch (e: any) {
    toast.error("Could not save workshop settings.");
  }
}

async function addMember() {
  error.value = "";
  try {
    await api.request("/members", { method: "POST", body: member });
    toast.success("Invitation sent to staff member.");
    Object.assign(member, { name: "", email: "", role: "staff" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not add member.";
  }
}

async function changeRole(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "PATCH", body: { role: item.role } });
    toast.success(`Role updated for ${item.name}.`);
  } catch {
    toast.error("Could not change role.");
  }
}

async function removeMember(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "DELETE" });
    toast.info("Member removed.");
    await load();
  } catch {
    toast.error("Could not remove member.");
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
    toast.success("Two-Factor Authentication enabled!");
  } catch {
    toast.error("Invalid 2FA code.");
  }
}

async function disableTwoFactor() {
  try {
    await api.request("/auth/two-factor", { method: "DELETE", body: { password: twoFactor.password } });
    Object.assign(twoFactor, { enabled: false, pending: false, secret: "", recovery_codes: [], password: "" });
    api.hasTwoFactor.value = false;
    toast.info("2FA disabled.");
  } catch {
    toast.error("Invalid password.");
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKSHOP ADMINISTRATION</p>
        <h1>Settings & Workshop Profile</h1>
        <p>Branding defaults, currency, staff roles, security and data portability.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- General Preferences Form -->
      <form class="panel form" @submit.prevent="saveSettings">
        <h2>Workshop Preferences (দোকানের তথ্য)</h2>
        <label>Business Name *
          <input v-model="settings.name" placeholder="e.g. Suto Tailors" required />
        </label>
        <div class="form-grid-2">
          <label>Default Language
            <select v-model="settings.default_locale">
              <option value="bn">বাংলা (Bengali)</option>
              <option value="en">English</option>
            </select>
          </label>
          <label>Currency Code
            <input v-model="settings.currency" minlength="3" maxlength="3" required />
          </label>
        </div>
        <div class="form-grid-2">
          <label>Order Number Prefix
            <input v-model="settings.preferences.order_prefix" placeholder="ORD" />
          </label>
          <label>Default Delivery Days
            <input v-model.number="settings.preferences.default_delivery_days" type="number" min="1" />
          </label>
        </div>
        <label class="check-label">
          <input v-model="settings.preferences.low_stock_alerts" type="checkbox" />
          <span>Enable low-stock fabric alerts</span>
        </label>
        <label class="check-label">
          <input v-model="settings.preferences.order_ready_notifications" type="checkbox" />
          <span>Send automatic SMS when orders are marked ready</span>
        </label>
        <button class="primary">Save Preferences</button>
      </form>

      <!-- Staff Members Management -->
      <section v-if="api.role.value === 'admin'" class="panel">
        <form class="form" style="margin-bottom:1.5rem" @submit.prevent="addMember">
          <h2>Invite Staff & Receptionist (স্টাফ যোগ করুন)</h2>
          <label>Name *
            <input v-model="member.name" required />
          </label>
          <label>Email *
            <input v-model="member.email" type="email" required />
          </label>
          <label>Role *
            <select v-model="member.role">
              <option value="staff">Staff (অর্ডার বুকিং ও রসিদ)</option>
              <option value="manager">Manager (মাস্টার / ম্যানেজার)</option>
              <option value="admin">Admin (পূর্ণ অ্যাক্সেস)</option>
            </select>
          </label>
          <p v-if="error" class="error">{{ error }}</p>
          <button class="primary">Send Invitation</button>
        </form>

        <h3>Active Staff Members</h3>
        <div v-for="item in members" :key="item.id" class="record record--stock">
          <div>
            <strong>{{ item.name }}</strong>
            <small style="display:block">{{ item.email }}</small>
          </div>
          <select v-model="item.role" :aria-label="`Role for ${item.name}`" @change="changeRole(item)">
            <option value="staff">Staff</option>
            <option value="manager">Manager</option>
            <option value="admin">Admin</option>
          </select>
          <button class="mini-btn" @click="removeMember(item)">Remove</button>
        </div>
      </section>
    </div>

    <!-- Security & 2FA Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>Two-Factor Security (দ্বি-স্তর বিশিষ্ট নিরাপত্তা)</h2>
      <p>{{ twoFactor.enabled ? "Authenticator protection is enabled on this account." : "Protect administrative login with TOTP Google Authenticator." }}</p>

      <button v-if="!twoFactor.enabled && !twoFactor.pending" class="primary" @click="setupTwoFactor">
        Set Up Authenticator App
      </button>

      <form v-if="twoFactor.pending" class="form" style="max-width:28rem" @submit.prevent="confirmTwoFactor">
        <p>Enter this secret in Google Authenticator or scan OTP link:</p>
        <code style="padding:0.5rem;background:var(--paper);display:block;border-radius:4px">{{ twoFactor.secret }}</code>
        <label>6-Digit Verification Code
          <input v-model="twoFactor.code" inputmode="numeric" placeholder="123456" required />
        </label>
        <button class="primary">Confirm & Activate 2FA</button>
      </form>

      <form v-if="twoFactor.enabled" class="form" style="max-width:24rem;margin-top:1rem" @submit.prevent="disableTwoFactor">
        <label>Confirm Password to Disable 2FA
          <input v-model="twoFactor.password" type="password" required />
        </label>
        <button class="secondary">Disable 2FA</button>
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
