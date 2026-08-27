<script setup lang="ts">
const api = useTailorsApi();
const { t, locale } = useI18n();
const localeCookie = useCookie<string>('tailors_locale', { default: () => 'en' });
const theme = useCookie<'light' | 'dark'>('tailors_theme', { default: () => 'light' });
const active = ref("Dashboard");
const email = ref("admin@tailors.test");
const password = ref("ChangeMe123!");
const twoFactorCode = ref("");
const loading = ref(false);
const error = ref("");
const authMode = ref<"login" | "register" | "reset">("login");
const signup = reactive({ business_name: "", slug: "", name: "", email: "", password: "", password_confirmation: "", locale: "bn" });
const reset = reactive({ token: "", password: "", password_confirmation: "" });
const nav = computed(() => [
  "Dashboard",
  "Orders",
  "Customers",
  "Garments",
  "Karigars",
  "Inventory",
  "Operations",
  "Notifications",
  "Reports",
  ...(["admin", "manager"].includes(api.role.value) ? ["Accounting"] : []),
  "Billing",
  "Help",
  ...(["admin", "manager"].includes(api.role.value) ? ["Settings"] : []),
  ...(api.isSuperAdmin.value ? ["Super Admin"] : []),
]);
watch(locale, value => { localeCookie.value = value; });
useHead(() => ({ htmlAttrs: { lang: locale.value, 'data-theme': theme.value } }));
async function signIn() {
  error.value = "";
  loading.value = true;
  try {
    await api.login(email.value, password.value, twoFactorCode.value);
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Sign in failed.";
  } finally {
    loading.value = false;
  }
}
async function signOut() {
  await api.logout();
  active.value = "Dashboard";
}
async function createWorkshop() {
  error.value = "";
  loading.value = true;
  try {
    await api.register(signup);
    locale.value = signup.locale as "bn" | "en";
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Workshop setup failed.";
  } finally {
    loading.value = false;
  }
}
async function forgotPassword() {
  error.value = "";
  try { const result = await api.request<{message:string}>("/auth/forgot-password", { method: "POST", body: { tenant: api.tenant.value, email: email.value } }); error.value = result.data.message; }
  catch (e:any) { error.value = e?.data?.errors?.[0]?.message || "Could not request a reset link."; }
}
async function resetPassword() {
  try { await api.request("/auth/reset-password", { method: "POST", body: { tenant: api.tenant.value, email: email.value, token: reset.token, password: reset.password, password_confirmation: reset.password_confirmation } }); authMode.value = "login"; error.value = "Password reset. Sign in with your new password."; }
  catch (e:any) { error.value = e?.data?.errors?.[0]?.message || "Could not reset password."; }
}
onMounted(() => {
  const params = new URLSearchParams(location.search);
  if (params.get("reset_token")) { reset.token = params.get("reset_token") || ""; email.value = params.get("email") || ""; api.tenant.value = params.get("tenant") || api.tenant.value; authMode.value = "reset"; }
});
</script>
<template>
  <div v-if="!api.token.value" class="login">
    <form v-if="authMode === 'login'" class="login__card" @submit.prevent="signIn">
      <div class="brand">
        <b>সু</b><span><strong>সুতো</strong><small>TAILOR OS</small></span>
      </div>
      <h1>{{ t('auth.welcome') }}</h1>
      <p>{{ t('auth.signin_help') }}</p>
      <label>{{ t('auth.shop') }}<input v-model="api.tenant.value" /></label><label>{{ t('auth.email') }}<input
        v-model="email"
        type="email"
        autocomplete="username"
      /></label><label>{{ t('auth.password') }}<input
        v-model="password"
        type="password"
        autocomplete="current-password"
      /></label>
      <label>{{ t('auth.two_factor') }} <small>(only if enabled)</small><input v-model="twoFactorCode" autocomplete="one-time-code" inputmode="numeric" /></label>
      <p v-if="error" class="error" role="alert">
        {{ error }}
      </p>
      <button class="primary" :disabled="loading">
        {{ loading ? t('auth.signing_in') : t('auth.signin') }}
      </button>
      <button type="button" class="text-button" @click="authMode = 'register'">
        {{ t('auth.create') }}
      </button>
      <button type="button" class="text-button" @click="forgotPassword">{{ t('auth.forgot') }}</button>
    </form>
    <form v-else-if="authMode === 'register'" class="login__card" @submit.prevent="createWorkshop">
      <div class="brand">
        <b>সু</b><span><strong>সুতো</strong><small>TAILOR OS</small></span>
      </div>
      <h1>Start your workshop</h1>
      <p>Your garment catalog and starter workflow are prepared automatically.</p>
      <label>Business name<input v-model="signup.business_name" required /></label>
      <label>Shop slug<input v-model="signup.slug" required pattern="[a-z0-9-]+" /></label>
      <label>Your name<input v-model="signup.name" required /></label>
      <label>Email<input v-model="signup.email" required type="email" autocomplete="username" /></label>
      <label>Password<input v-model="signup.password" required minlength="12" type="password" autocomplete="new-password" /></label>
      <label>Confirm password<input v-model="signup.password_confirmation" required minlength="12" type="password" autocomplete="new-password" /></label>
      <label>Language<select v-model="signup.locale"><option value="bn">বাংলা</option><option value="en">English</option></select></label>
      <p v-if="error" class="error" role="alert">
        {{ error }}
      </p>
      <button class="primary" :disabled="loading">
        {{ loading ? "Preparing…" : "Create workshop" }}
      </button>
      <button type="button" class="text-button" @click="authMode = 'login'">
        Back to sign in
      </button>
    </form>
    <form v-else class="login__card" @submit.prevent="resetPassword">
      <div class="brand"><b>সু</b><span><strong>সুতো</strong><small>TAILOR OS</small></span></div>
      <h1>Choose a new password</h1>
      <label>Email<input v-model="email" type="email" required /></label><label>New password<input v-model="reset.password" type="password" minlength="12" required /></label><label>Confirm password<input v-model="reset.password_confirmation" type="password" minlength="12" required /></label>
      <p v-if="error" class="error">{{ error }}</p><button class="primary">Reset password</button><button type="button" class="text-button" @click="authMode = 'login'">Back to sign in</button>
    </form>
  </div>
  <div v-else class="shell">
    <aside>
      <div class="brand">
        <b>সু</b><span><strong>সুতো</strong><small>TAILOR OS</small></span>
      </div>
      <nav>
        <button
          v-for="item in nav"
          :key="item"
          :class="{ active: item === active }"
          @click="active = item"
        >
          ◇ {{ t(`nav.${item}`) }}
        </button>
      </nav>
      <label class="locale-switcher">{{ t('language') }}<select v-model="locale"><option value="bn">বাংলা</option><option value="en">English</option></select></label>
      <button class="signout" @click="theme = theme === 'light' ? 'dark' : 'light'">
        {{ theme === 'light' ? t('theme.dark') : t('theme.light') }}
      </button>
      <button class="signout" @click="signOut">
        {{ t('auth.signout') }}
      </button>
    </aside>
    <main>
      <DashboardWorkspace v-if="active === 'Dashboard'" /><CustomerWorkspace
        v-else-if="active === 'Customers'"
      /><InventoryWorkspace
        v-else-if="active === 'Inventory'"
      /><OperationsWorkspace
        v-else-if="active === 'Operations'"
      /><NotificationWorkspace
        v-else-if="active === 'Notifications'"
      /><OrderWorkspace v-else-if="active === 'Orders'" /><GarmentWorkspace
        v-else-if="active === 'Garments'"
      /><WorkforceWorkspace v-else-if="active === 'Karigars'" /><ReportWorkspace
        v-else-if="active === 'Reports'"
      /><BillingWorkspace
        v-else-if="active === 'Billing'"
      /><HelpWorkspace v-else-if="active === 'Help'" /><SettingsWorkspace v-else-if="active === 'Settings'" /><AccountingWorkspace v-else-if="active === 'Accounting'" /><SuperAdminWorkspace v-else-if="active === 'Super Admin'" />
    </main>
  </div>
</template>
