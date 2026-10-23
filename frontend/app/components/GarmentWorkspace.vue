<script setup lang="ts">
import type { Garment } from "~/types/garments";

const { t } = useTailorsI18n();
const api = useTailorsApi();
const toast = useToast();
const { confirm: confirmAction } = useConfirmDialog();

// Anyone with garments.view may look at the catalogue; only garments.manage may change it (the API enforces the same).
const canManage = computed(() => api.can("garments.manage"));

const items = ref<Garment[]>([]);
const loading = ref(true);
const saving = ref(false);
const error = ref("");
const searchQuery = ref("");
const selectedCategory = ref<"all" | "gents" | "ladies" | "kids" | "unisex">("all");
const selectedGroup = ref<string>("all");

// The 3-column studio (create / edit / copy designs) is its own component.
type StudioTab = "general" | "measurements" | "designs";
const studio = ref<{ openCreateModal: () => void; openEditModal: (garment: Garment, tab?: StudioTab) => void } | null>(null);
const openCreateModal = () => studio.value?.openCreateModal();
const openEditModal = (garment: Garment, tab?: StudioTab) => studio.value?.openEditModal(garment, tab);

async function load() {
  loading.value = true;
  try {
    const res = await api.request<{ garments: Garment[] }>("/garments");
    items.value = res.data.garments;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.garment.could_not_load_garments');
  } finally {
    loading.value = false;
  }
}

const availableGroups = computed(() => {
  const source = selectedCategory.value === "all"
    ? items.value
    : items.value.filter((g) => g.category === selectedCategory.value);
  return Array.from(new Set(source.map((g) => g.group_name).filter(Boolean))) as string[];
});

const filteredGarments = computed(() => {
  return items.value.filter((g) => {
    const matchCat = selectedCategory.value === "all" || g.category === selectedCategory.value;
    const matchGroup = selectedGroup.value === "all" || g.group_name === selectedGroup.value;
    const matchSearch =
      !searchQuery.value ||
      g.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (g.group_name && g.group_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    return matchCat && matchGroup && matchSearch;
  });
});

// 1-Click Clone Service
async function cloneGarment(garment: Garment) {
  const confirmed = await confirmAction({
    title: t('app.garment.clone_this_garment'),
    message: t('app.garment.a_new_garment_will_be_created', { p0: garment.name }),
    confirmText: t('app.garment.yes_clone'),
    cancelText: t('app.common.cancel'),
  });
  if (!confirmed) return;

  try {
    await api.request(`/garments/${garment.public_id}/clone`, {
      method: "POST",
    });
    toast.success(t('app.garment.p0_cloned', { p0: garment.name }));
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || t('app.garment.could_not_clone'));
  }
}

// Toggle Quick Status (Active / Inactive)
async function toggleActiveStatus(garment: Garment) {
  try {
    const nextState = !garment.active;
    await api.request(`/garments/${garment.public_id}`, {
      method: "PATCH",
      body: { active: nextState },
    });
    garment.active = nextState;
    toast.success(t(nextState ? "app.garment.now_active" : "app.garment.now_inactive", { p0: garment.name }));
  } catch (e: any) {
    toast.error(apiErrorMessage(e, t('app.garment.could_not_change_the_status')));
  }
}

// Delete Garment
async function deleteGarment(garment: Garment) {
  const confirmed = await confirmAction({
    title: t('app.garment.delete_this_garment'),
    message: t('app.garment.are_you_sure_you_want_to', { p0: garment.name }),
    confirmText: t('app.garment.yes_delete'),
    cancelText: t('app.common.cancel'),
  });
  if (!confirmed) return;

  try {
    await api.request(`/garments/${garment.public_id}`, {
      method: "DELETE",
    });
    toast.success(t('app.garment.p0_deleted', { p0: garment.name }));
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || t('app.garment.could_not_delete'));
  }
}

onMounted(() => {
  load();
});
</script>

<template>
  <div class="garment-studio-page">
    <!-- Header Section -->
    <header class="page-topbar">
      <div class="topbar-title-block">
        <div class="badge-atelier">{{ t('app.garment.atelier_studio') }}</div>
        <h1>{{ t('app.garment.garments_designs') }}</h1>
        <p class="subtitle">{{ t('app.garment.manage_garment_types_making_rates_karigar') }}</p>
      </div>

      <div class="topbar-actions">
        <button v-if="canManage" type="button" class="btn-atelier-primary" @click="openCreateModal">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19" />
            <line x1="5" y1="12" x2="19" y2="12" />
          </svg>
          {{ t('app.garment.new_garment') }}
        </button>
      </div>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="toolbar-card">
      <div class="category-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'all' }"
          @click="selectedCategory = 'all'"
        >
          {{ t('app.garment.all_garments') }} ({{ items.length }})
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'gents' }"
          @click="selectedCategory = 'gents'"
        >
          {{ t('app.garment.gents') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'ladies' }"
          @click="selectedCategory = 'ladies'"
        >
          {{ t('app.garment.ladies') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'kids' }"
          @click="selectedCategory = 'kids'"
        >
          {{ t('app.garment.kids') }}
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'unisex' }"
          @click="selectedCategory = 'unisex'"
        >
          {{ t('app.garment.unisex') }}
        </button>
      </div>

      <div class="toolbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8" />
            <line x1="21" y1="21" x2="16.65" y2="16.65" />
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            :placeholder="t('app.garment.search_garment_or_group')"
          />
        </div>

        <select v-if="availableGroups.length" v-model="selectedGroup" class="select-group-filter">
          <option value="all">{{ t('app.garment.all_groups') }}</option>
          <option v-for="g in availableGroups" :key="g" :value="g">{{ g }}</option>
        </select>
      </div>
    </div>

    <!-- Main Garments Table Card -->
    <div class="main-table-card">
      <div v-if="loading" class="state-loading">
        <div class="atelier-spinner"></div>
        <span>{{ t('app.garment.loading_garments') }}</span>
      </div>

      <div v-else-if="error" class="state-error">
        <span>⚠️ {{ error }}</span>
        <button type="button" class="btn-retry" @click="load">{{ t('app.garment.try_again') }}</button>
      </div>

      <div v-else-if="!filteredGarments.length" class="state-empty">
        <div class="empty-icon">✂️</div>
        <h3>{{ t('app.garment.no_garments_found') }}</h3>
        <p>{{ t('app.garment.use_the_new_garment_button_above') }}</p>
      </div>

      <div v-else class="table-responsive">
        <table class="atelier-table">
          <thead>
            <tr>
              <th style="width: 50px;">{{ t('app.garment.image') }}</th>
              <th>{{ t('app.garment.garment_group') }}</th>
              <th>{{ t('app.garment.measurement_points') }}</th>
              <th>{{ t('app.garment.design_options') }}</th>
              <th class="text-right">{{ t('app.garment.customer_price') }}</th>
              <th class="text-right">{{ t('app.garment.master_fee') }}</th>
              <th class="text-right">{{ t('app.garment.karigar_fee') }}</th>
              <th class="text-center" style="width: 110px;">{{ t('app.common.status') }}</th>
              <th v-if="canManage" class="text-right" style="width: 180px;">{{ t('app.common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="garment in filteredGarments" :key="garment.public_id" :class="{ 'is-inactive-row': !garment.active }">
              <td class="col-img">
                <div class="garment-avatar">
                  {{ garment.category === 'ladies' ? '🥻' : garment.category === 'kids' ? '👶' : '👔' }}
                </div>
              </td>
              <td class="col-name">
                <strong class="garment-title">{{ garment.name }}</strong>
                <span class="garment-group-sub">{{ garment.group_name || t('app.garment.default_group') }}</span>
              </td>
              <td class="col-parts">
                <div class="parts-badges-wrap">
                  <span v-for="p in garment.parts.slice(0, 4)" :key="p.slug" class="badge-part-chip">
                    {{ p.name }}
                  </span>
                  <span v-if="garment.parts.length > 4" class="badge-part-more">
                    {{ t('app.garment.p0_more', { p0: garment.parts.length - 4 }) }}
                  </span>
                </div>
              </td>
              <td class="col-designs">
                <div
                  v-if="garment.design_options?.length"
                  class="design-summary-chip is-clickable"
                  :title="garment.design_options.map(d => d.name).join(', ') + (canManage ? t('app.garment.click_to_edit_designs') : '')"
                  @click="canManage && openEditModal(garment, 'designs')"
                >
                  🎨 {{ t('app.garment.p0_options_p1_values', { p0: garment.design_options.length, p1: garment.design_options.reduce((acc, curr) => acc + (curr.values?.length || 0), 0) }) }}
                </div>
                <button
                  v-else-if="canManage"
                  type="button"
                  class="btn-add-design-pill"
                  :title="t('app.garment.add_collar_pocket_and_other_design')"
                  @click="openEditModal(garment, 'designs')"
                >
                  {{ t('app.garment.add_design') }}
                </button>
              </td>
              <td class="col-price text-right">
                <span class="price-val">৳ {{ Math.round((garment.base_making_minor || 0) / 100) }}</span>
              </td>
              <td class="col-master text-right">
                <span class="fee-val">৳ {{ Math.round((garment.master_rate_minor || 0) / 100) }}</span>
              </td>
              <td class="col-karigar text-right">
                <span class="fee-val">৳ {{ Math.round((garment.karigar_rate_minor || 0) / 100) }}</span>
              </td>
              <td class="col-status text-center">
                <button
                  v-if="canManage"
                  type="button"
                  class="toggle-status-pill"
                  :class="{ 'is-active': garment.active, 'is-inactive': !garment.active }"
                  :title="garment.active ? 'অর্ডারে সক্রিয় (ক্লিক করে বন্ধ করুন)' : 'অর্ডারে বন্ধ (ক্লিক করে চালু করুন)'"
                  @click="toggleActiveStatus(garment)"
                >
                  {{ garment.active ? t('app.garment.active') : t('app.garment.off') }}
                </button>
                <span v-else class="toggle-status-pill is-readonly" :class="{ 'is-active': garment.active, 'is-inactive': !garment.active }">
                  {{ garment.active ? t('app.garment.active') : t('app.garment.off') }}
                </span>
              </td>
              <td v-if="canManage" class="col-actions text-right">
                <div class="action-btn-group">
                  <button
                    type="button"
                    class="btn-act btn-act-edit"
                    :title="t('app.garment.edit_in_the_studio')"
                    @click="openEditModal(garment)"
                  >
                    {{ t('app.garment.edit') }}
                  </button>
                  <button
                    type="button"
                    class="btn-act btn-act-clone"
                    :title="t('app.garment.clone')"
                    @click="cloneGarment(garment)"
                  >
                    {{ t('app.garment.clone_2') }}
                  </button>
                  <button
                    type="button"
                    class="btn-act btn-act-del"
                    :title="t('app.garment.delete')"
                    @click="deleteGarment(garment)"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <GarmentStudio ref="studio" :garments="items" @saved="load" />
  </div>
</template>

<style scoped>
.garment-studio-page {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
  font-family: inherit;
  color: var(--ink);
}

.page-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.badge-atelier {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--primary);
  background: rgba(10, 61, 49, 0.08);
  padding: 2px 8px;
  border-radius: 4px;
  margin-bottom: 0.25rem;
}

.topbar-title-block h1 {
  font-size: 1.45rem;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
}

.subtitle {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0.2rem 0 0;
}

.btn-atelier-primary {
  background: var(--primary);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0.65rem 1.25rem;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 12px rgba(10, 61, 49, 0.2);
  transition: all 0.2s ease;
}

.btn-atelier-primary:hover {
  background: #0d4b3d;
  transform: translateY(-1px);
}

/* Toolbar Card */
.toolbar-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.category-tabs {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.tab-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  background: var(--paper);
  color: var(--ink);
}

.tab-btn.is-active {
  background: var(--paper);
  border-color: var(--line);
  color: var(--primary);
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.75rem;
  gap: 0.5rem;
  color: var(--muted);
}

.search-box input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.82rem;
  color: var(--ink);
  width: 180px;
}

.select-group-filter {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.65rem;
  font-size: 0.82rem;
  color: var(--ink);
  outline: none;
}

/* Main Table Card */
.main-table-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.table-responsive {
  overflow-x: auto;
}

.atelier-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.88rem;
}

.atelier-table thead th {
  background: var(--paper);
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--line);
}

.atelier-table tbody tr {
  border-bottom: 1px solid var(--line);
  transition: background 0.15s ease;
}

.atelier-table tbody tr:hover {
  background: rgba(10, 61, 49, 0.02);
}

.atelier-table tbody tr.is-inactive-row {
  opacity: 0.6;
  background: #fdfdfd;
}

.atelier-table td {
  padding: 0.85rem 1rem;
  vertical-align: middle;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.garment-avatar {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--paper);
  border: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.garment-title {
  display: block;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
}

.garment-group-sub {
  font-size: 0.75rem;
  color: var(--muted);
}

.parts-badges-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.badge-part-chip {
  background: var(--paper);
  border: 1px solid var(--line);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--ink);
  padding: 2px 6px;
  border-radius: 4px;
}

.badge-part-more {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--primary);
  background: rgba(10, 61, 49, 0.08);
  padding: 2px 6px;
  border-radius: 4px;
}

.design-summary-chip {
  display: inline-block;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  color: #3730a3;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.design-summary-chip.is-clickable {
  cursor: pointer;
  transition: all 0.15s ease;
}

.design-summary-chip.is-clickable:hover {
  background: #e0e7ff;
  border-color: #818cf8;
  transform: translateY(-1px);
}

.btn-add-design-pill {
  background: rgba(99, 102, 241, 0.08);
  border: 1px dashed #6366f1;
  color: #4f46e5;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-add-design-pill:hover {
  background: #4f46e5;
  color: #ffffff;
  border-style: solid;
}

.text-muted-empty {
  font-size: 0.75rem;
  color: var(--muted);
  font-style: italic;
}

.price-val {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--primary);
}

.fee-val {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--ink);
}

.toggle-status-pill {
  border: none;
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: 12px;
  padding: 3px 10px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.toggle-status-pill.is-readonly {
  cursor: default;
}

.toggle-status-pill.is-active {
  background: #dcfce7;
  color: #166534;
}

.toggle-status-pill.is-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.action-btn-group {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.btn-act {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.3rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-act:hover {
  background: #ffffff;
  border-color: var(--primary);
  color: var(--primary);
}

.btn-act-del:hover {
  border-color: #ef4444;
  color: #ef4444;
}

.reorder-btns-col {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.btn-reorder {
  background: transparent;
  border: none;
  font-size: 0.55rem;
  color: var(--muted);
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
  border-radius: 2px;
}

.btn-reorder:hover:not(:disabled) {
  background: var(--line);
  color: var(--primary);
}

.btn-reorder:disabled {
  opacity: 0.2;
  cursor: not-allowed;
}

/* State views */
.state-loading,
.state-error,
.state-empty {
  padding: 4rem 2rem;
  text-align: center;
  color: var(--muted);
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.atelier-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(10, 61, 49, 0.1);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 0.75rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
