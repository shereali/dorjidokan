<script setup lang="ts">
// Compact line-icon set for the sidebar. 1.5px stroke, currentColor,
// aria-hidden so screen readers announce only the label text.
defineProps<{ name: string }>()
</script>
<template>
  <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name === 'Dashboard'">
      <rect x="3" y="3" width="7" height="7" rx="1" /><rect x="14" y="3" width="7" height="7" rx="1" /><rect x="3" y="14" width="7" height="7" rx="1" /><rect x="14" y="14" width="7" height="7" rx="1" />
    </template>
    <template v-else-if="name === 'Orders'">
      <path d="M12 3v6" /><path d="M8 6l4-3 4 3" /><path d="M5 9h14l-1 11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2z" />
    </template>
    <template v-else-if="name === 'Customers'">
      <circle cx="12" cy="8" r="4" /><path d="M4 21c0-4 4-6 8-6s8 2 8 6" />
    </template>
    <template v-else-if="name === 'Garments'">
      <path d="M9 3h6l1 4 4 1-2 6-4-1v8h-4v-8l-4 1-2-6 4-1z" />
    </template>
    <template v-else-if="name === 'Karigars'">
      <path d="M12 2a3 3 0 0 1 3 3v2a3 3 0 0 1-6 0V5a3 3 0 0 1 3-3z" /><path d="M7 13h10l-1 8H8z" /><path d="M6 9l-3 4h4" /><path d="M18 9l3 4h-4" />
    </template>
    <template v-else-if="name === 'Inventory'">
      <rect x="4" y="4" width="16" height="6" rx="1" /><path d="M4 14h16v6H4z" /><path d="M8 10v10M16 10v10" />
    </template>
    <template v-else-if="name === 'Operations'">
      <path d="M14 4a4 4 0 0 1 6 6l-9 10H4v-7z" /><path d="M4 4h6" /><path d="M12 8l4 4" />
    </template>
    <template v-else-if="name === 'Notifications'">
      <path d="M6 9a6 6 0 0 1 12 0c0 5 2 6 2 6H4s2-1 2-6" /><path d="M10 19a2 2 0 0 0 4 0" />
    </template>
    <template v-else-if="name === 'Reports'">
      <path d="M4 20V10" /><path d="M10 20V4" /><path d="M16 20v-8" /><path d="M22 20H2" />
    </template>
    <template v-else-if="name === 'Accounting'">
      <rect x="4" y="3" width="16" height="18" rx="1" /><path d="M8 8h8M8 12h8M8 16h5" />
    </template>
    <template v-else-if="name === 'Billing'">
      <rect x="2" y="5" width="20" height="14" rx="2" /><path d="M2 10h20" /><path d="M6 15h4" />
    </template>
    <template v-else-if="name === 'Help'">
      <circle cx="12" cy="12" r="9" /><path d="M9.5 9a2.5 2.5 0 1 1 4 2c-.8.6-1.5 1-1.5 2" /><path d="M12 17h.01" />
    </template>
    <template v-else-if="name === 'Settings'">
      <circle cx="12" cy="12" r="3" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9L17 7M7 17l-2.1 2.1" />
    </template>
    <template v-else>
      <!-- Super Admin / default -->
      <path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z" /><path d="M12 8v4" /><path d="M12 15h.01" />
    </template>
  </svg>
</template>
