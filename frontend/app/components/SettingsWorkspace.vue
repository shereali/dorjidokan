<script setup lang="ts">
const { t } = useTailorsI18n();
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
const roleName = (key: string) => roleLabel(key, locale.value === "bn" ? "bn" : "en");
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
    toast.success(t('app.settings.settings_saved'));
  } catch (e: any) {
    toast.error(t('app.settings.could_not_save_settings'));
  }
}

async function addMember() {
  error.value = "";
  try {
    await api.request("/members", { method: "POST", body: member });
    toast.success(t('app.settings.invitation_sent'));
    Object.assign(member, { name: "", email: "", role: "receptionist" });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.settings.could_not_add_the_member');
  }
}

async function changeRole(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "PATCH", body: { role: item.role } });
    toast.success(t('app.settings.role_updated_for_p0', { p0: item.name }));
  } catch {
    toast.error(t('app.settings.could_not_change_the_role'));
  }
}

async function removeMember(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "DELETE" });
    toast.info(t('app.settings.member_removed'));
    await load();
  } catch {
    toast.error(t('app.settings.could_not_remove_the_member'));
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
    toast.success(t('app.settings.two_factor_authentication_is_on'));
  } catch {
    toast.error(t('app.settings.that_code_is_not_valid'));
  }
}

async function disableTwoFactor() {
  try {
    await api.request("/auth/two-factor", { method: "DELETE", body: { password: twoFactor.password } });
    Object.assign(twoFactor, { enabled: false, pending: false, secret: "", recovery_codes: [], password: "" });
    api.hasTwoFactor.value = false;
    toast.info(t('app.settings.2fa_turned_off'));
  } catch {
    toast.error(t('app.settings.wrong_password'));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.settings.workshop_administration') }}</p>
        <h1>{{ t('app.settings.settings_workshop_profile') }}</h1>
        <p>{{ t('app.settings.defaults_currency_team_roles_security_and') }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- General Preferences Form -->
      <form v-if="api.can('settings.view', 'settings.manage')" class="panel form" @submit.prevent="saveSettings">
        <fieldset :disabled="!canEditSettings" style="border:0;padding:0;margin:0;display:contents">
          <h2>{{ t('app.settings.workshop_preferences') }}</h2>
          <label>{{ t('app.settings.business_name') }}
            <input v-model="settings.name" :placeholder="t('app.settings.e_g_dorjidokan_tailors')" required />
          </label>
          <div class="form-grid-2">
            <label>{{ t('app.settings.default_language') }}
              <select v-model="settings.default_locale">
                <option value="bn">{{ t('app.settings.bengali') }}</option>
                <option value="en">{{ t('app.settings.english') }}</option>
              </select>
            </label>
            <label>{{ t('app.settings.currency_code') }}
              <input v-model="settings.currency" minlength="3" maxlength="3" required />
            </label>
          </div>
          <div class="form-grid-2">
            <label>{{ t('app.settings.order_number_prefix') }}
              <input v-model="settings.preferences.order_prefix" :placeholder="t('app.settings.ord')" />
            </label>
            <label>{{ t('app.settings.default_delivery_days') }}
              <input v-model.number="settings.preferences.default_delivery_days" type="number" min="1" />
            </label>
          </div>
          <label class="check-label">
            <input v-model="settings.preferences.low_stock_alerts" type="checkbox" />
            <span>{{ t('app.settings.alert_me_when_fabric_stock_is') }}</span>
          </label>
          <label class="check-label">
            <input v-model="settings.preferences.order_ready_notifications" type="checkbox" />
            <span>{{ t('app.settings.send_an_sms_automatically_when_an') }}</span>
          </label>
          <button v-if="canEditSettings" class="primary">{{ t('app.settings.save_preferences') }}</button>
        </fieldset>
      </form>

      <!-- Staff Members Management -->
      <section v-if="api.can('members.manage')" class="panel">
        <form class="form" style="margin-bottom:1.5rem" @submit.prevent="addMember">
          <h2>{{ t('app.settings.invite_a_team_member') }}</h2>
          <label>{{ t('app.settings.name') }}
            <input v-model="member.name" required />
          </label>
          <label>{{ t('app.settings.email') }}
            <input v-model="member.email" type="email" required />
          </label>
          <label>{{ t('app.settings.role') }}
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
          <button class="primary">{{ t('app.settings.send_invitation') }}</button>
        </form>

        <h3>{{ t('app.common.team_members') }}</h3>
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
          <button class="mini-btn" @click="removeMember(item)">{{ t('app.settings.remove') }}</button>
        </div>
      </section>
    </div>

    <RoleMatrixEditor v-if="api.can('roles.manage', 'members.manage')" />

    <!-- Security & 2FA Section -->
    <section class="panel" style="margin-top:1.5rem">
      <h2>{{ t('app.settings.two_factor_security') }}</h2>
      <p>{{ twoFactor.enabled ? t('app.settings.authenticator_protection_is_on_for_this') : t('app.settings.protect_sign_in_with_an_authenticator') }}</p>

      <button v-if="!twoFactor.enabled && !twoFactor.pending" class="primary" @click="setupTwoFactor">
        {{ t('app.settings.set_up_authenticator_app') }}
      </button>

      <form v-if="twoFactor.pending" class="form" style="max-width:28rem" @submit.prevent="confirmTwoFactor">
        <p>{{ t('app.settings.enter_this_secret_in_google_authenticator') }}</p>
        <code style="padding:0.5rem;background:var(--paper);display:block;border-radius:4px">{{ twoFactor.secret }}</code>
        <label>{{ t('app.settings.6_digit_code') }}
          <input v-model="twoFactor.code" inputmode="numeric" placeholder="123456" required />
        </label>
        <button class="primary">{{ t('app.settings.confirm_turn_on_2fa') }}</button>
      </form>

      <form v-if="twoFactor.enabled" class="form" style="max-width:24rem;margin-top:1rem" @submit.prevent="disableTwoFactor">
        <label>{{ t('app.settings.enter_your_password_to_turn_off') }}
          <input v-model="twoFactor.password" type="password" required />
        </label>
        <button class="secondary">{{ t('app.settings.turn_off_2fa') }}</button>
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
