<script setup lang="ts">
const api = useTailorsApi();
const { t, locale } = useTailorsI18n();
const route = useRoute();
const router = useRouter();
const localeCookie = useCookie<string>('tailors_locale', { default: () => 'en' });
const theme = useCookie<'light' | 'dark'>('tailors_theme', { default: () => 'light' });

const mobileMenuOpen = ref(false);
const resending = ref(false);
const toast = useToast();

async function resendVerification() {
  resending.value = true;
  try {
    const result = await api.resendVerification();
    toast.success(result.already_verified ? t('app.default.verify_email_done') : t('app.default.verify_email_sent'));
  } catch {
    toast.error(t('app.default.verify_email_failed'));
  } finally {
    resending.value = false;
  }
}

// Honest connectivity indicator: the browser's own online/offline state, not a hard-coded "Live".
const online = ref(true);
const syncOnline = () => (online.value = navigator.onLine);
const globalSearch = ref('');

interface NavItem {
  key: string;
  path: string;
  icon: string;
  superAdminOnly?: boolean;
}

const navItems: NavItem[] = [
  { key: 'Dashboard', path: '/dashboard', icon: 'Dashboard' },
  { key: 'Orders', path: '/orders', icon: 'Orders' },
  { key: 'Customers', path: '/customers', icon: 'Customers' },
  { key: 'Garments', path: '/garments', icon: 'Garments' },
  { key: 'Karigars', path: '/karigars', icon: 'Karigars' },
  { key: 'Inventory', path: '/inventory', icon: 'Inventory' },
  { key: 'Operations', path: '/operations', icon: 'Operations' },
  { key: 'Notifications', path: '/notifications', icon: 'Notifications' },
  { key: 'Reports', path: '/reports', icon: 'Reports' },
  { key: 'Accounting', path: '/accounting', icon: 'Accounting' },
  { key: 'Billing', path: '/billing', icon: 'Billing' },
  { key: 'Settings', path: '/settings', icon: 'Settings' },
  { key: 'Users', path: '/settings/users', icon: 'Users' },
  { key: 'Roles', path: '/settings/roles', icon: 'Roles' },
  { key: 'Help', path: '/help', icon: 'Help' },
  { key: 'Super Admin', path: '/super-admin', icon: 'Super Admin', superAdminOnly: true },
];

const visibleNav = computed(() => {
  return navItems.filter((item) => {
    if (item.superAdminOnly && !api.isSuperAdmin.value) return false;
    if (item.superAdminOnly) return true;
    return canOpen(item.path, api.permissions.value);
  });
});

const navGroups = computed(() => {
  const workshopKeys = ['Dashboard', 'Orders', 'Customers', 'Garments', 'Karigars', 'Inventory', 'Operations', 'Notifications', 'Reports'];
  const workshop = visibleNav.value.filter((i) => workshopKeys.includes(i.key));
  const manage = visibleNav.value.filter((i) => !workshopKeys.includes(i.key));
  return [
    { label: t('app.common.workshop'), items: workshop },
    { label: t('app.default.manage'), items: manage },
  ].filter((g) => g.items.length);
});

const currentRoleLabel = computed(() => roleLabel(api.role.value, locale.value === 'bn' ? 'bn' : 'en'));

function isNavActive(path: string) {
  if (path === '/dashboard' || path === '/settings') return route.path === path;
  return route.path === path || route.path.startsWith(`${path}/`);
}

async function changeLocale(code: 'bn' | 'en') {
  locale.value = code;
  localeCookie.value = code;
}

async function handleSignOut() {
  await api.logout();
  mobileMenuOpen.value = false;
  await navigateTo('/login');
}

function handleGlobalSearch() {
  if (!globalSearch.value.trim()) return;
  router.push({ path: '/orders', query: { query: globalSearch.value } });
  globalSearch.value = '';
}

function triggerNewOrder() {
  router.push({ path: '/orders', query: { new: '1' } });
  mobileMenuOpen.value = false;
}

function triggerPOS() {
  router.push('/operations');
  mobileMenuOpen.value = false;
}

function focusSearchShortcut(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    document.getElementById('global-search-input')?.focus();
  }
}

onMounted(async () => {
  window.addEventListener('keydown', focusSearchShortcut);
  window.addEventListener('online', syncOnline);
  window.addEventListener('offline', syncOnline);
  syncOnline();
  // Pick up role/permission changes made by the owner since the last sign-in.
  await api.refreshMe();
  if (!canOpen(route.path, api.permissions.value) && !route.path.startsWith('/super-admin')) {
    await navigateTo(firstAllowedPath(api.permissions.value));
  }
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', focusSearchShortcut);
  window.removeEventListener('online', syncOnline);
  window.removeEventListener('offline', syncOnline);
});
</script>

<template>
  <div class="shell">
    <!-- Slide-over Mobile Drawer Backdrop -->
    <div
      v-if="mobileMenuOpen"
      class="drawer-backdrop"
      @click="mobileMenuOpen = false"
    ></div>

    <aside :class="{ 'drawer-open': mobileMenuOpen }">
      <div class="brand">
        <b>দ</b>
        <span>
          <strong>{{ t('brand') }}</strong>
          <small>{{ (api.tenant.value || 'ATELIER').toUpperCase() }}</small>
        </span>
      </div>

      <nav>
        <template v-for="group in navGroups" :key="group.label">
          <p v-if="navGroups.length > 1" class="nav-section">
            {{ group.label }}
          </p>
          <NuxtLink
            v-for="item in group.items"
            :key="item.key"
            :to="item.path"
            class="nav-link-btn"
            :class="{ active: isNavActive(item.path) }"
            @click="mobileMenuOpen = false"
          >
            <NavIcon :name="item.icon" />
            <span>{{ t(`nav.${item.key}`) }}</span>
          </NuxtLink>
        </template>
      </nav>

      <div class="aside-footer">
        <div class="user-profile-card">
          <div class="user-avatar">{{ (api.role.value || 'A').slice(0, 1).toUpperCase() }}</div>
          <div class="user-info">
            <span class="user-name">{{ api.userEmail.value || t('app.default.signed_in') }}</span>
            <span class="user-role-badge">{{ currentRoleLabel }}</span>
          </div>
        </div>

        <div class="sidebar-controls">
          <button class="locale-btn" @click="changeLocale(locale === 'bn' ? 'en' : 'bn')">
            {{ locale === 'bn' ? 'English' : 'বাংলা' }}
          </button>
          <button class="theme-btn" @click="theme = theme === 'light' ? 'dark' : 'light'">
            {{ theme === 'light' ? t('app.default.dark') : t('app.default.light') }}
          </button>
        </div>

        <button class="locale-btn" style="width:100%;margin-top:0.25rem" @click="handleSignOut">
          {{ t('auth.signout') }}
        </button>
      </div>
    </aside>

    <div class="workspace-wrapper">
      <!-- Global Top Command Bar -->
      <header class="topbar">
        <div class="topbar-left">
          <button
            class="mobile-menu-toggle"
            aria-label="Open Navigation Menu"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <NavIcon name="List" />
          </button>

          <form class="global-search-container" @submit.prevent="handleGlobalSearch">
            <input
              id="global-search-input"
              v-model="globalSearch"
              class="global-search-input"
              :placeholder="t('app.default.scan_barcode_or_search_order_customer')"
              :aria-label="t('app.default.search_orders_and_customers')"
              autocomplete="off"
            />
            <span class="global-search-shortcut">⌘K</span>
          </form>
        </div>

        <div class="topbar-right">
          <button v-if="api.can('orders.create')" class="action-pill-btn action-pill-btn--primary" :aria-label="locale === 'bn' ? 'নতুন অর্ডার' : 'New Order'" @click="triggerNewOrder">
            <NavIcon name="Plus" /> <span>{{ locale === 'bn' ? 'নতুন অর্ডার' : 'New Order' }}</span>
          </button>
          <button v-if="api.can('sales.manage')" class="action-pill-btn action-pill-btn--accent" :aria-label="locale === 'bn' ? 'বিক্রয় POS' : 'Fabric POS'" @click="triggerPOS">
            <NavIcon name="Cash" /> <span>{{ locale === 'bn' ? 'বিক্রয় POS' : 'Fabric POS' }}</span>
          </button>
          <span class="status-pill" :class="{ 'status-pill--offline': !online }" role="status" :title="online ? t('app.default.connected') : t('app.default.no_internet_changes_cannot_be_saved')">
            <span class="pulse-dot"></span> <span>{{ online ? t('app.default.online') : t('app.default.offline') }}</span>
          </span>
        </div>
      </header>

      <div v-if="api.authenticated.value && !api.emailVerified.value" class="verify-banner no-print" role="status">
        <span>{{ t('app.default.verify_email_banner') }}</span>
        <button type="button" class="text-button" :disabled="resending" @click="resendVerification">{{ t('app.default.verify_email_resend') }}</button>
      </div>

      <main>
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
.verify-banner {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.75rem;
  padding: 0.6rem 1rem;
  background: var(--warning-bg, #fff4d6);
  color: var(--ink);
  border-bottom: 1px solid var(--line);
  font-size: 0.875rem;
}

.nav-link-btn {
  text-decoration: none;
  border: 0;
  background: transparent;
  color: var(--sidebar-muted);
  padding: 0.7rem 0.85rem;
  border-radius: var(--radius-sm);
  text-align: left;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  font: inherit;
  font-size: 0.9rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
  position: relative;
  min-height: var(--touch-target);
}

.nav-link-btn:hover {
  background: rgb(255 255 255 / 7%);
  color: #fff;
  transform: translateX(2px);
}

.nav-link-btn.active {
  background: var(--sidebar-surface);
  color: #fff;
  box-shadow: inset 3px 0 0 var(--accent-gold), 0 2px 8px rgb(0 0 0 / 25%);
}

.nav-link-btn.active::after {
  content: "";
  position: absolute;
  right: 0.75rem;
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background: var(--accent-gold);
  box-shadow: 0 0 8px var(--accent-gold);
}

.drawer-backdrop {
  position: fixed;
  inset: 0;
  background: rgb(0 0 0 / 55%);
  backdrop-filter: blur(4px);
  z-index: 35;
}
</style>
