<script setup lang="ts">
const route = useRoute();
const initialQuery = computed(() => (route.query.query as string) || "");
const autoOpenNewModal = computed(() => route.query.new === "1");
const showModal = ref(false);

watch(
  autoOpenNewModal,
  (val) => {
    if (val) showModal.value = true;
  },
  { immediate: true }
);
</script>

<template>
  <OrderWorkspace
    :initial-query="initialQuery"
    :auto-open-new-modal="showModal"
    @clear-modal-flag="showModal = false"
  />
</template>
