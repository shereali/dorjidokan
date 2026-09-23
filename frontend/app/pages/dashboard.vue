<script setup lang="ts">
const router = useRouter();

function handleNavigate(target: string) {
  const map: Record<string, string> = {
    Orders: '/orders',
    Customers: '/customers',
    Operations: '/operations',
    Reports: '/reports',
    Inventory: '/inventory',
    Garments: '/garments',
    Karigars: '/karigars',
    Notifications: '/notifications',
    Accounting: '/accounting',
    Billing: '/billing',
    Help: '/help',
    Settings: '/settings',
    'Super Admin': '/super-admin',
  };
  router.push(map[target] || '/dashboard');
}
</script>

<template>
  <DashboardWorkspace @navigate="handleNavigate" />
</template>
