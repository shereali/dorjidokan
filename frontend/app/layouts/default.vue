<script setup lang="ts">
const api = useTailorsApi();
const { t, locale } = useTailorsI18n();
const route = useRoute();
const router = useRouter();
const localeCookie = useCookie<string>('tailors_locale', { default: () => 'en' });
const theme = useCookie<'light' | 'dark'>('tailors_theme', { default: () => 'light' });

const mobileMenuOpen = ref(false);
const globalSearch = ref('');

interface NavItem {
  key: string;
  path: string;
  icon: string;
  adminOnly?: boolean;
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
  { key: 'Accounting', path: '/accounting', icon: 'Accounting', adminOnly: true },
  { key: 'Billing', path: '/billing', icon: 'Billing' },
  { key: 'Help', path: '/help', icon: 'Help' },
  { key: 'Settings', path: '/settings', icon: 'Settings', adminOnly: true },
  { key: 'Super Admin', path: '/super-admin', icon: 'Super Admin', superAdminOnly: true },
];

const visibleNav = computed(() => {
  return navItems.filter((item) => {
    if (item.superAdminOnly && !api.isSuperAdmin.value) return false;
    if (item.adminOnly && !['admin', 'manager'].includes(api.role.value)) return false;
    return true;
  });
});

const navGroups = computed(() => {
  const workshopKeys = ['Dashboard', 'Orders', 'Customers', 'Garments', 'Karigars', 'Inventory', 'Operations', 'Notifications', 'Reports'];
  const workshop = visibleNav.value.filter((i) => workshopKeys.includes(i.key));
  const manage = visibleNav.value.filter((i) => !workshopKeys.includes(i.key));
  return [
    { label: 'Workshop', items: workshop },
    { label: 'Manage', items: manage },
  ].filter((g) => g.items.length);
});

function isNavActive(path: string) {
  if (path === '/dashboard') return route.path === '/dashboard';
  return route.path.startsWith(path);
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

onMounted(() => {
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
      e.preventDefault();
      const el = document.getElementById('global-search-input');
      if (el) el.focus();
    }
  });
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
            <span class="user-name">{{ api.userEmail.value || 'Master Cutter' }}</span>
            <span class="user-role-badge">{{ api.role.value }}</span>
          </div>
        </div>

        <div class="sidebar-controls">
          <button class="locale-btn" @click="changeLocale(locale === 'bn' ? 'en' : 'bn')">
            {{ locale === 'bn' ? 'English' : 'বাংলা' }}
          </button>
          <button class="theme-btn" @click="theme = theme === 'light' ? 'dark' : 'light'">
            {{ theme === 'light' ? '🌙 Dark' : '☀️ Light' }}
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
              placeholder="Scan barcode or search (Order #, Customer, Mobile)..."
              autocomplete="off"
            />
            <span class="global-search-shortcut">⌘K</span>
          </form>
        </div>

        <div class="topbar-right">
          <button class="action-pill-btn action-pill-btn--primary" :aria-label="locale === 'bn' ? 'নতুন অর্ডার' : 'New Order'" @click="triggerNewOrder">
            <NavIcon name="Plus" /> <span>{{ locale === 'bn' ? 'নতুন অর্ডার' : 'New Order' }}</span>
          </button>
          <button class="action-pill-btn action-pill-btn--accent" :aria-label="locale === 'bn' ? 'বিক্রয় POS' : 'Fabric POS'" @click="triggerPOS">
            <NavIcon name="Cash" /> <span>{{ locale === 'bn' ? 'বিক্রয় POS' : 'Fabric POS' }}</span>
          </button>
          <span class="status-pill" title="Live WebSocket connection active">
            <span class="pulse-dot"></span> <span>Live</span>
          </span>
        </div>
      </header>

      <main>
        <slot />
      </main>
    </div>
  </div>
</template>

<style scoped>
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
