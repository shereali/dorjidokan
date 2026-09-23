<script setup lang="ts">
const { locale } = useTailorsI18n();
const localeCookie = useCookie<string>('tailors_locale', { default: () => 'en' });
const theme = useCookie<'light' | 'dark'>('tailors_theme', { default: () => 'light' });

watch(locale, (value) => {
  localeCookie.value = value;
});

useHead(() => ({
  htmlAttrs: {
    lang: locale.value,
    'data-theme': theme.value,
  },
}));
</script>

<template>
  <div>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>

    <!-- Global Toast Notification Container -->
    <ToastNotification />

    <!-- Global Premium Confirmation Dialog Container -->
    <GlobalConfirmDialog />
  </div>
</template>
