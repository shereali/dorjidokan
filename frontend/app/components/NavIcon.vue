<script setup lang="ts">
// Comprehensive line-icon set. 1.75px stroke, currentColor, aria-hidden.
defineProps<{ name: string }>()
</script>
<template>
  <svg class="nav-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <template v-if="name === 'Dashboard'">
      <rect x="3" y="3" width="7" height="7" rx="1.5" /><rect x="14" y="3" width="7" height="7" rx="1.5" /><rect x="3" y="14" width="7" height="7" rx="1.5" /><rect x="14" y="14" width="7" height="7" rx="1.5" />
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
      <rect x="4" y="4" width="16" height="6" rx="1.5" /><path d="M4 14h16v6H4z" /><path d="M8 10v10M16 10v10" />
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
      <rect x="4" y="3" width="16" height="18" rx="1.5" /><path d="M8 8h8M8 12h8M8 16h5" />
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
    <template v-else-if="name === 'Search'">
      <circle cx="11" cy="11" r="8" /><path d="m21 21-4.35-4.35" />
    </template>
    <template v-else-if="name === 'Barcode'">
      <path d="M3 5v14M6 5v14M10 5v14M13 5v14M17 5v14M21 5v14" />
    </template>
    <template v-else-if="name === 'Plus'">
      <path d="M12 5v14M5 12h14" />
    </template>
    <template v-else-if="name === 'Printer'">
      <polyline points="6 9 6 2 18 2 18 9" /><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" /><rect x="6" y="14" width="12" height="8" />
    </template>
    <template v-else-if="name === 'Cash'">
      <rect x="2" y="6" width="20" height="12" rx="2" /><circle cx="12" cy="12" r="2" /><path d="M6 12h.01M18 12h.01" />
    </template>
    <template v-else-if="name === 'Kanban'">
      <rect x="3" y="3" width="5" height="18" rx="1" /><rect x="10" y="3" width="5" height="12" rx="1" /><rect x="17" y="3" width="5" height="15" rx="1" />
    </template>
    <template v-else-if="name === 'List'">
      <line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" /><line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />
    </template>
    <template v-else-if="name === 'Phone'">
      <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" />
    </template>
    <template v-else-if="name === 'WhatsApp'">
      <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" />
    </template>
    <template v-else-if="name === 'Scissors'">
      <circle cx="6" cy="6" r="3" /><circle cx="6" cy="18" r="3" /><line x1="20" y1="4" x2="8.12" y2="15.88" /><line x1="14.47" y1="14.48" x2="20" y2="20" /><line x1="8.12" y1="8.12" x2="12" y2="12" />
    </template>
    <template v-else-if="name === 'Check'">
      <polyline points="20 6 9 17 4 12" />
    </template>
    <template v-else-if="name === 'Close'">
      <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
    </template>
    <template v-else-if="name === 'Calendar'">
      <rect x="3" y="4" width="18" height="18" rx="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
    </template>
    <template v-else-if="name === 'Tag'">
      <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z" /><line x1="7" y1="7" x2="7.01" y2="7" />
    </template>
    <template v-else-if="name === 'Download'">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" />
    </template>
    <template v-else-if="name === 'Refresh'">
      <polyline points="23 4 23 10 17 10" /><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10" />
    </template>
    <template v-else-if="name === 'Edit' || name === 'Pencil'">
      <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" /><path d="m15 5 4 4" />
    </template>
    <template v-else-if="name === 'Trash' || name === 'Delete'">
      <polyline points="3 6 5 6 21 6" /><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" /><line x1="10" y1="11" x2="10" y2="17" /><line x1="14" y1="11" x2="14" y2="17" />
    </template>
    <template v-else>
      <!-- Super Admin / Shield / default -->
      <path d="M12 2l8 4v6c0 5-3.5 8-8 10-4.5-2-8-5-8-10V6z" /><path d="M12 8v4" /><path d="M12 15h.01" />
    </template>
  </svg>
</template>
