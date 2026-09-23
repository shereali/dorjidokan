<script setup lang="ts">
definePageMeta({
  layout: 'auth',
});

const api = useTailorsApi();
const { t, locale } = useTailorsI18n();

const email = ref('admin@tailors.test');
const password = ref('ChangeMe123!');
const twoFactorCode = ref('');
const loading = ref(false);
const error = ref('');
const twoFactorRequired = ref(false);
const authMode = ref<'login' | 'register' | 'reset'>('login');
const signup = reactive({
  business_name: '',
  slug: '',
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  locale: 'bn',
});
const reset = reactive({ token: '', password: '', password_confirmation: '' });

async function signIn() {
  error.value = '';
  loading.value = true;
  try {
    await api.login(email.value, password.value, twoFactorCode.value);
    twoFactorRequired.value = false;
    await navigateTo('/dashboard');
  } catch (e: any) {
    if (e?.data?.meta?.two_factor_required) {
      twoFactorRequired.value = true;
    }
    error.value = e?.data?.errors?.[0]?.message || t('auth.signin_failed');
  } finally {
    loading.value = false;
  }
}

async function createWorkshop() {
  error.value = '';
  loading.value = true;
  try {
    await api.register(signup);
    locale.value = signup.locale as 'bn' | 'en';
    await navigateTo('/dashboard');
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('auth.workshop_failed');
  } finally {
    loading.value = false;
  }
}

async function forgotPassword() {
  error.value = '';
  try {
    const result = await api.request<{ message: string }>('/auth/forgot-password', {
      method: 'POST',
      body: { tenant: api.tenant.value, email: email.value },
    });
    error.value = result.data.message;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('auth.reset_failed');
  }
}

async function resetPassword() {
  try {
    await api.request('/auth/reset-password', {
      method: 'POST',
      body: {
        tenant: api.tenant.value,
        email: email.value,
        token: reset.token,
        password: reset.password,
        password_confirmation: reset.password_confirmation,
      },
    });
    authMode.value = 'login';
    error.value = t('auth.reset_done');
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('auth.reset_failed2');
  }
}

function fillDemoUser(role: 'admin' | 'manager' | 'staff') {
  api.tenant.value = 'heritage-tailors';
  email.value = `${role}@tailors.test`;
  password.value = 'ChangeMe123!';
  twoFactorCode.value = '';
}

onMounted(() => {
  const params = new URLSearchParams(location.search);
  if (params.get('reset_token')) {
    reset.token = params.get('reset_token') || '';
    email.value = params.get('email') || '';
    api.tenant.value = params.get('tenant') || api.tenant.value;
    authMode.value = 'reset';
  }
});

useHead(() => ({
  title: locale.value === 'bn'
    ? 'প্রবেশ করুন — দর্জিদোকান ওয়ার্কশপ ওএস'
    : 'Sign In — Dorjidokan Workshop OS',
}));
</script>

<template>
  <div>
    <!-- Standard Login Form -->
    <form v-if="authMode === 'login'" class="login__card" @submit.prevent="signIn">
      <div class="brand">
        <b>দ</b><span><strong>{{ t('brand') }}</strong><small>WORKSHOP OS</small></span>
      </div>
      <h1>{{ t('auth.welcome') }}</h1>
      <p class="subtitle">{{ t('auth.signin_help') }}</p>

      <!-- Quick Role Fill Pills for Demo Testing -->
      <div class="demo-roles-selector">
        <div class="demo-header" style="display: flex; justify-content: space-between; align-items: center;">
          <span class="demo-label">{{ locale === 'bn' ? 'কুইক ডেমো এক্সেস:' : 'Quick Demo Access:' }}</span>
          <button
            type="button"
            class="lang-toggle-btn"
            style="background: transparent; border: 1px solid var(--line); border-radius: var(--radius-xs); padding: 0.2rem 0.5rem; font-size: 0.75rem; cursor: pointer; color: var(--ink); font-weight: 600;"
            @click="locale = locale === 'bn' ? 'en' : 'bn'"
          >
            🌐 {{ locale === 'bn' ? 'English' : 'বাংলা' }}
          </button>
        </div>
        <div class="demo-pills">
          <button type="button" class="demo-pill" @click="fillDemoUser('admin')">
            👑 {{ locale === 'bn' ? 'অ্যাডমিন' : 'Admin' }}
          </button>
          <button type="button" class="demo-pill" @click="fillDemoUser('manager')">
            ✂ {{ locale === 'bn' ? 'মাস্টার' : 'Master' }}
          </button>
          <button type="button" class="demo-pill" @click="fillDemoUser('staff')">
            📋 {{ locale === 'bn' ? 'স্টাফ' : 'Staff' }}
          </button>
        </div>
      </div>

      <label>{{ t('auth.email') }}<input v-model="email" type="email" autocomplete="username" placeholder="admin@tailors.test" required /></label>
      <label>{{ t('auth.password') }}<input v-model="password" type="password" autocomplete="current-password" placeholder="••••••••••••" required /></label>
      <label v-if="twoFactorRequired">{{ t('auth.two_factor') }}<input v-model="twoFactorCode" autocomplete="one-time-code" inputmode="numeric" :placeholder="t('auth.two_factor')" required /></label>
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

    <!-- Register Workshop Form -->
    <form v-else-if="authMode === 'register'" class="login__card" @submit.prevent="createWorkshop">
      <div class="brand">
        <b>দ</b><span><strong>{{ t('brand') }}</strong><small>WORKSHOP OS</small></span>
      </div>
      <h1>{{ t('auth.start_workshop') }}</h1>
      <p class="subtitle">{{ t('auth.start_help') }}</p>
      <label>{{ t('auth.business_name') }}<input v-model="signup.business_name" required placeholder="Master Tailors" /></label>
      <label>{{ t('auth.shop') }}<input v-model="signup.slug" required pattern="[a-z0-9-]+" placeholder="master-tailors" /></label>
      <label>{{ t('auth.your_name') }}<input v-model="signup.name" required placeholder="Abdul Karim" /></label>
      <label>{{ t('auth.email') }}<input v-model="signup.email" required type="email" autocomplete="username" /></label>
      <label>{{ t('auth.password') }}<input v-model="signup.password" required minlength="12" type="password" autocomplete="new-password" /></label>
      <label>{{ t('auth.confirm_password') }}<input v-model="signup.password_confirmation" required minlength="12" type="password" autocomplete="new-password" /></label>
      <label>{{ t('auth.language_label') }}<select v-model="signup.locale"><option value="bn">বাংলা</option><option value="en">English</option></select></label>
      <p v-if="error" class="error" role="alert">
        {{ error }}
      </p>
      <button class="primary" :disabled="loading">
        {{ loading ? t('auth.creating') : t('auth.create_workshop') }}
      </button>
      <button type="button" class="text-button" @click="authMode = 'login'">
        {{ t('auth.back_signin') }}
      </button>
    </form>

    <!-- Password Reset Form -->
    <form v-else class="login__card" @submit.prevent="resetPassword">
      <div class="brand">
        <b>দ</b><span><strong>{{ t('brand') }}</strong><small>WORKSHOP OS</small></span>
      </div>
      <h1>{{ t('auth.new_password') }}</h1>
      <label>{{ t('auth.email') }}<input v-model="email" type="email" required /></label>
      <label>{{ t('auth.new_password_label') }}<input v-model="reset.password" type="password" minlength="12" required /></label>
      <label>{{ t('auth.confirm_password') }}<input v-model="reset.password_confirmation" type="password" minlength="12" required /></label>
      <p v-if="error" class="error">{{ error }}</p>
      <button class="primary">{{ t('auth.reset_password') }}</button>
      <button type="button" class="text-button" @click="authMode = 'login'">{{ t('auth.back_signin') }}</button>
    </form>
  </div>
</template>
