<script setup lang="ts">
interface C {
  public_id: string;
  name: string;
  mobile_number: string;
  address?: string;
  marketing_consent: boolean;
}
const api = useTailorsApi(),
  items = ref<C[]>([]),
  loading = ref(false),
  error = ref(""),
  query = ref(""),
  archived = ref(false),
  editingId = ref(""),
  form = reactive({ name: "", mobile_number: "", address: "", marketing_consent: false });
async function load() {
  loading.value = true;
  try {
    items.value = (
      await api.request<{ items: C[] }>(
        `/customers?query=${encodeURIComponent(query.value)}&archived=${archived.value ? 1 : 0}`,
      )
    ).data.items;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not load customers.";
  } finally {
    loading.value = false;
  }
}
async function save() {
  error.value = "";
  try {
    await api.request(editingId.value ? `/customers/${editingId.value}` : "/customers", { method: editingId.value ? "PATCH" : "POST", body: form });
    editingId.value = "";
    Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: false });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save customer.";
  }
}
function edit(customer: C) {
  editingId.value = customer.public_id;
  Object.assign(form, { name: customer.name, mobile_number: customer.mobile_number, address: customer.address || "", marketing_consent: customer.marketing_consent });
}
function cancelEdit() {
  editingId.value = "";
  Object.assign(form, { name: "", mobile_number: "", address: "", marketing_consent: false });
}
async function toggleArchive(customer: C) {
  await api.request(`/customers/${customer.public_id}${archived.value ? "/restore" : ""}`, { method: archived.value ? "POST" : "DELETE" });
  await load();
}
onMounted(load);
</script>
<template>
  <header>
    <div>
      <p class="eyebrow">
        RELATIONSHIPS
      </p>
      <h1>Customers</h1>
      <p>Profiles, contact details and order history.</p>
    </div>
  </header>
  <div class="workspace-grid">
    <form class="panel form" @submit.prevent="save">
      <h2>{{ editingId ? "Edit customer" : "Add customer" }}</h2>
      <label>Name<input v-model="form.name" required /></label><label>Mobile<input v-model="form.mobile_number" required /></label><label>Address<textarea v-model="form.address" /></label>
      <label class="check"><input v-model="form.marketing_consent" type="checkbox" /> Customer consented to occasion and promotional SMS</label>
      <p v-if="error" class="error">
        {{ error }}
      </p>
      <button class="primary">{{ editingId ? "Update customer" : "Save customer" }}</button>
      <button v-if="editingId" type="button" @click="cancelEdit">Cancel</button>
    </form>
    <section class="panel">
      <div class="toolbar">
        <input
          v-model="query"
          placeholder="Search by name"
          @keyup.enter="load"
        /><button @click="load">
          Search
        </button>
        <label class="check"><input v-model="archived" type="checkbox" @change="load" /> Archived</label>
      </div>
      <p v-if="loading">
        Loading…
      </p>
      <p v-else-if="!items.length" class="empty">
        No customers found.
      </p>
      <div v-for="c in items" :key="c.public_id" class="record">
        <strong>{{ c.name }}</strong><span>{{ c.mobile_number }}</span><small>{{ c.address || "No address" }} · {{ c.marketing_consent ? "SMS consent" : "Transactional only" }}</small>
        <div class="actions"><button v-if="!archived" @click="edit(c)">Edit</button><button @click="toggleArchive(c)">{{ archived ? "Restore" : "Archive" }}</button></div>
      </div>
    </section>
  </div>
</template>
