<script setup lang="ts">
const props = defineProps<{
  activeTab?: "general" | "users" | "roles";
}>();

const { t, locale } = useTailorsI18n();
const route = useRoute();

interface Member {
  id: string;
  name: string;
  email: string;
  role: string;
}

const api = useTailorsApi();
const toast = useToast();

const currentTab = computed<"general" | "users" | "roles">(() => {
  if (props.activeTab) return props.activeTab;
  if (route.path.includes("/settings/users") || route.query.tab === "users") return "users";
  if (route.path.includes("/settings/roles") || route.query.tab === "roles") return "roles";
  return "general";
});

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
const showInviteForm = ref(false);

const roleName = (key: string) => roleLabel(key, locale.value === "bn" ? "bn" : "en");
const canEditSettings = computed(() => api.can("settings.manage"));
const canManageMembers = computed(() => api.can("members.manage"));
const canManageRoles = computed(() => api.can("roles.manage", "members.manage"));

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
    toast.success(t("app.settings.settings_saved"));
  } catch (e: any) {
    toast.error(t("app.settings.could_not_save_settings"));
  }
}

async function addMember() {
  error.value = "";
  try {
    await api.request("/members", { method: "POST", body: member });
    toast.success(t("app.settings.invitation_sent"));
    Object.assign(member, { name: "", email: "", role: "receptionist" });
    showInviteForm.value = false;
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t("app.settings.could_not_add_the_member");
  }
}

async function changeRole(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "PATCH", body: { role: item.role } });
    toast.success(t("app.settings.role_updated_for_p0", { p0: item.name }));
  } catch {
    toast.error(t("app.settings.could_not_change_the_role"));
  }
}

async function removeMember(item: Member) {
  try {
    await api.request(`/members/${item.id}`, { method: "DELETE" });
    toast.info(t("app.settings.member_removed"));
    await load();
  } catch {
    toast.error(t("app.settings.could_not_remove_the_member"));
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
    toast.success(t("app.settings.two_factor_authentication_is_on"));
  } catch {
    toast.error(t("app.settings.that_code_is_not_valid"));
  }
}

async function disableTwoFactor() {
  try {
    await api.request("/auth/two-factor", { method: "DELETE", body: { password: twoFactor.password } });
    Object.assign(twoFactor, { enabled: false, pending: false, secret: "", recovery_codes: [], password: "" });
    api.hasTwoFactor.value = false;
    toast.info(t("app.settings.2fa_turned_off"));
  } catch {
    toast.error(t("app.settings.wrong_password"));
  }
}

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.settings.workshop_administration') }}</p>
        <h1>
          {{
            currentTab === 'users'
              ? (t('nav.Users') || 'User Management')
              : currentTab === 'roles'
              ? (t('nav.Roles') || 'Role Permissions')
              : t('app.settings.settings_workshop_profile')
          }}
        </h1>
        <p>{{ t('app.settings.defaults_currency_team_roles_security_and') }}</p>
      </div>
    </header>

    <!-- Settings Submenu / Tab Navigation Bar -->
    <div class="settings-tabs-nav">
      <NuxtLink
        to="/settings"
        class="tab-btn"
        :class="{ active: currentTab === 'general' }"
      >
        <NavIcon name="Settings" />
        <span>{{ t('app.settings.workshop_preferences') }}</span>
      </NuxtLink>

      <NuxtLink
        v-if="canManageMembers"
        to="/settings/users"
        class="tab-btn"
        :class="{ active: currentTab === 'users' }"
      >
        <NavIcon name="Users" />
        <span>{{ t('nav.Users') || 'Users' }}</span>
      </NuxtLink>

      <NuxtLink
        v-if="canManageRoles"
        to="/settings/roles"
        class="tab-btn"
        :class="{ active: currentTab === 'roles' }"
      >
        <NavIcon name="Roles" />
        <span>{{ t('nav.Roles') || 'Roles & Permissions' }}</span>
      </NuxtLink>
    </div>

    <!-- TAB 1: GENERAL WORKSHOP SETTINGS & 2FA -->
    <div v-if="currentTab === 'general'">
      <div class="settings-layout">
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
            <button v-if="canEditSettings" class="primary" style="margin-top:0.5rem">{{ t('app.settings.save_preferences') }}</button>
          </fieldset>
        </form>

        <!-- Security & 2FA Section -->
        <section class="panel">
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
    </div>

    <!-- TAB 2: USER & STAFF MANAGEMENT -->
    <div v-else-if="currentTab === 'users' && canManageMembers">
      <section class="panel">
        <div class="toolbar" style="margin-bottom:1.5rem">
          <div>
            <h2 style="margin:0">{{ t('app.common.team_members') }}</h2>
            <small class="text-muted">{{ members.length }} {{ t('app.settings.role') }} accounts</small>
          </div>
          <button class="primary" style="margin-left:auto" @click="showInviteForm = !showInviteForm">
            <NavIcon :name="showInviteForm ? 'Close' : 'Plus'" />
            {{ showInviteForm ? t('common.cancel') : t('app.settings.invite_a_team_member') }}
          </button>
        </div>

        <!-- Invite User Form (Toggleable) -->
        <div v-if="showInviteForm" class="invite-panel">
          <form class="form" @submit.prevent="addMember">
            <h3>{{ t('app.settings.invite_a_team_member') }}</h3>
            <div class="form-grid-3">
              <label>{{ t('app.settings.name') }} *
                <input v-model="member.name" :placeholder="t('app.settings.name')" required />
              </label>
              <label>{{ t('app.settings.email') }} *
                <input v-model="member.email" type="email" placeholder="user@workshop.com" required />
              </label>
              <label>{{ t('app.settings.role') }} *
                <select v-model="member.role">
                  <option value="receptionist">{{ roleName('receptionist') }}</option>
                  <option value="master">{{ roleName('master') }}</option>
                  <option value="karigar">{{ roleName('karigar') }}</option>
                  <option value="accountant">{{ roleName('accountant') }}</option>
                  <option value="manager">{{ roleName('manager') }}</option>
                  <option value="owner">{{ roleName('owner') }}</option>
                </select>
              </label>
            </div>
            <p v-if="error" class="error">{{ error }}</p>
            <div style="display:flex;gap:0.75rem;margin-top:0.75rem">
              <button class="primary">{{ t('app.settings.send_invitation') }}</button>
              <button type="button" class="secondary" @click="showInviteForm = false">{{ t('common.cancel') }}</button>
            </div>
          </form>
        </div>

        <!-- Members Table -->
        <div class="table-scroll">
          <table class="customer-table">
            <thead>
              <tr>
                <th>{{ t('app.settings.name') }}</th>
                <th>{{ t('app.settings.email') }}</th>
                <th>{{ t('app.settings.role') }}</th>
                <th class="actions-col">{{ t('app.common.actions') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="item in members" :key="item.id">
                <td>
                  <div class="name-cell">
                    <div class="member-avatar">{{ item.name.slice(0, 1).toUpperCase() }}</div>
                    <strong>{{ item.name }}</strong>
                  </div>
                </td>
                <td>{{ item.email }}</td>
                <td>
                  <select v-model="item.role" class="role-select" :aria-label="`Role for ${item.name}`" @change="changeRole(item)">
                    <option value="receptionist">{{ roleName('receptionist') }}</option>
                    <option value="master">{{ roleName('master') }}</option>
                    <option value="karigar">{{ roleName('karigar') }}</option>
                    <option value="accountant">{{ roleName('accountant') }}</option>
                    <option value="manager">{{ roleName('manager') }}</option>
                    <option value="owner">{{ roleName('owner') }}</option>
                  </select>
                </td>
                <td class="actions-col nowrap">
                  <button class="mini-btn text-danger" @click="removeMember(item)">
                    {{ t('app.settings.remove') }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>

    <!-- TAB 3: ROLE PERMISSIONS -->
    <div v-else-if="currentTab === 'roles' && canManageRoles">
      <RoleMatrixEditor />
    </div>
  </div>
</template>

<style scoped>
.settings-tabs-nav {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1.5rem;
  border-bottom: 1px solid var(--line);
  padding-bottom: 0.75rem;
  overflow-x: auto;
}

.tab-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.55rem 1rem;
  border-radius: var(--radius-sm);
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--muted);
  text-decoration: none;
  background: transparent;
  border: 1px solid transparent;
  transition: all 120ms ease;
  white-space: nowrap;
}

.tab-btn:hover {
  color: var(--ink);
  background: var(--paper);
}

.tab-btn.active {
  color: var(--primary);
  background: var(--paper);
  border-color: var(--line);
}

.settings-layout {
  display: grid;
  grid-template-columns: 1fr;
  gap: 1.5rem;
  align-items: start;
}

@media (min-width: 900px) {
  .settings-layout {
    grid-template-columns: minmax(0, 1.25fr) minmax(0, 1fr);
  }
}

.check-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
}

.check-label input {
  flex: none;
  width: 1.1rem;
  min-width: 0;
  min-height: 0;
  height: 1.1rem;
  padding: 0;
  accent-color: var(--accent, currentcolor);
}

.invite-panel {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.form-grid-3 {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr));
  gap: 1rem;
}

.table-scroll {
  overflow-x: auto;
}

.customer-table {
  width: 100%;
  min-width: 40rem;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.customer-table th,
.customer-table td {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: middle;
}

.customer-table th {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.78rem;
  white-space: nowrap;
}

.customer-table .actions-col {
  text-align: right;
}

.nowrap {
  white-space: nowrap;
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.member-avatar {
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 50%;
  background: var(--primary);
  color: #fff;
  display: grid;
  place-items: center;
  font-weight: 700;
  font-size: 0.9rem;
  flex-shrink: 0;
}

.role-select {
  padding: 0.35rem 0.65rem;
  font-size: 0.85rem;
  border-radius: var(--radius-sm);
  border: 1px solid var(--line);
  background: var(--paper);
}

.text-danger {
  color: #dc2626 !important;
}

.text-muted {
  color: var(--muted);
  font-size: 0.82rem;
}
</style>
