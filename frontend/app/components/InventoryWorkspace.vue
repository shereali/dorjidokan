<script setup lang="ts">
const { t } = useTailorsI18n();

interface I {
  public_id: string;
  sku: string;
  name: string;
  unit: string;
  reorder_level?: number;
  movements_sum_quantity: number;
}

const api = useTailorsApi();
const toast = useToast();

const items = ref<I[]>([]);
const error = ref("");
const loading = ref(true);
const query = ref("");
const lowStockOnly = ref(false);
const showAddModal = ref(false);

const canManage = computed(() => api.can("inventory.manage"));

const form = reactive({
  sku: "",
  name: "",
  unit: "yard",
  reorder_level: 10,
});

// Stock Adjustment Modal
const adjustingItem = ref<I | null>(null);
const adjustQuantity = ref(1);
const adjustReason = ref("Manual physical stock count");

async function load() {
  loading.value = true;
  error.value = "";
  try {
    items.value = (await api.request<{ items: I[] }>("/inventory")).data.items;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t("app.inventory.could_not_load_inventory");
  } finally {
    loading.value = false;
  }
}

function openAdd() {
  Object.assign(form, { sku: "", name: "", unit: "yard", reorder_level: 10 });
  error.value = "";
  showAddModal.value = true;
}

function closeAdd() {
  showAddModal.value = false;
  error.value = "";
}

async function save() {
  error.value = "";
  try {
    await api.request("/inventory", { method: "POST", body: form });
    toast.success(t("app.inventory.item_p0_added", { p0: form.name }));
    closeAdd();
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t("app.inventory.could_not_save_item");
  }
}

function openAdjust(item: I) {
  adjustingItem.value = item;
  adjustQuantity.value = 1;
  adjustReason.value = "Manual physical stock count";
}

async function confirmAdjust() {
  if (!adjustingItem.value) return;
  try {
    await api.request(`/inventory/${adjustingItem.value.public_id}/adjust`, {
      method: "POST",
      body: {
        quantity: Number(adjustQuantity.value),
        reason: adjustReason.value,
      },
    });
    toast.success(t("app.inventory.stock_adjusted_for_p0", { p0: adjustingItem.value.name }));
    adjustingItem.value = null;
    await load();
  } catch {
    toast.error(t("app.inventory.could_not_adjust_stock"));
  }
}

const filteredItems = computed(() => {
  let list = items.value;
  if (lowStockOnly.value) {
    list = list.filter((i) => (i.movements_sum_quantity || 0) <= (i.reorder_level || 0));
  }
  if (!query.value.trim()) return list;
  const q = query.value.toLowerCase();
  return list.filter(
    (i) => i.name.toLowerCase().includes(q) || (i.sku && i.sku.toLowerCase().includes(q))
  );
});

onMounted(load);
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">{{ t('app.inventory.fabric_stock') }}</p>
        <h1>{{ t('app.inventory.inventory') }}</h1>
        <p>{{ t('app.inventory.track_fabric_thread_buttons_lining_and') }}</p>
      </div>
    </header>

    <section class="panel">
      <!-- Toolbar: Search, Add Item, Low stock filter -->
      <div class="toolbar">
        <input
          v-model="query"
          :placeholder="t('app.inventory.search_by_name_sku_or_barcode')"
          @keyup.enter="load"
        />
        <button type="button" @click="load">
          <NavIcon name="Search" /> {{ t('common.search') }}
        </button>
        <button v-if="canManage" type="button" class="primary" @click="openAdd">
          <NavIcon name="Plus" /> {{ t('app.inventory.add_stock_item') }}
        </button>
        <label class="check-label check-label--end">
          <input v-model="lowStockOnly" type="checkbox" />
          <span>{{ t('app.inventory.low_stock_alert_level') }} (Alerts)</span>
        </label>
      </div>

      <p v-if="loading && !items.length">{{ t('app.inventory.loading_inventory') }}</p>
      <p v-else-if="!filteredItems.length" class="empty">{{ t('app.inventory.no_items_found') }}</p>

      <div v-else class="table-scroll">
        <table class="customer-table">
          <thead>
            <tr>
              <th>{{ t('app.inventory.sku_barcode') }}</th>
              <th>{{ t('app.inventory.item_name') }}</th>
              <th>{{ t('app.inventory.unit') }}</th>
              <th class="num">{{ t('app.inventory.current_stock') }}</th>
              <th class="num">{{ t('app.inventory.reorder_alert') }}</th>
              <th class="actions-col">{{ t('app.common.actions') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="item in filteredItems" :key="item.public_id" class="clickable-row">
              <td>
                <span class="sku-tag">{{ item.sku || '—' }}</span>
              </td>
              <td>
                <strong class="item-name">{{ item.name }}</strong>
              </td>
              <td>
                <span class="unit-badge">{{ item.unit }}</span>
              </td>
              <td class="num">
                <span
                  class="stock-level-pill"
                  :class="{ 'stock-level-pill--low': (item.movements_sum_quantity || 0) <= (item.reorder_level || 0) }"
                >
                  <NavIcon v-if="(item.movements_sum_quantity || 0) <= (item.reorder_level || 0)" name="Warning" class="warning-icon" />
                  {{ item.movements_sum_quantity || 0 }} {{ item.unit }}
                </span>
              </td>
              <td class="num text-muted">
                {{ item.reorder_level || 0 }} {{ item.unit }}
              </td>
              <td class="actions-col nowrap">
                <button v-if="canManage" class="mini-btn" @click="openAdjust(item)">
                  {{ t('app.inventory.adjust_stock') }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ADD ITEM MODAL -->
    <div v-if="showAddModal" class="modal-backdrop" @click.self="closeAdd">
      <form class="modal-window form" style="max-width:30rem" @submit.prevent="save">
        <div class="modal-header">
          <h2>{{ t('app.inventory.add_stock_item') }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="closeAdd">✕</button>
        </div>
        <div class="modal-body">
          <label>{{ t('app.inventory.sku_barcode') }} *
            <input v-model="form.sku" :placeholder="t('app.inventory.e_g_fab_cot_01')" required />
          </label>
          <label>{{ t('app.inventory.item_name') }} *
            <input v-model="form.name" :placeholder="t('app.inventory.e_g_cotton_voile_white_58')" required />
          </label>
          <div class="form-grid-2">
            <label>{{ t('app.inventory.unit') }} *
              <select v-model="form.unit" required>
                <option value="yard">{{ t('app.inventory.yard') }}</option>
                <option value="meter">{{ t('app.inventory.meter') }}</option>
                <option value="piece">{{ t('app.inventory.piece') }}</option>
              </select>
            </label>
            <label>{{ t('app.inventory.low_stock_alert_level') }}
              <input v-model.number="form.reorder_level" type="number" min="0" />
            </label>
          </div>
          <p v-if="error" class="error">{{ error }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="secondary" @click="closeAdd">{{ t('common.cancel') }}</button>
          <button class="primary">{{ t('app.inventory.add_item') }}</button>
        </div>
      </form>
    </div>

    <!-- ADJUST STOCK MODAL -->
    <div v-if="adjustingItem" class="modal-backdrop" @click.self="adjustingItem = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>{{ t('app.inventory.adjust_stock') }} — {{ adjustingItem.name }}</h2>
          <button type="button" class="modal-close-btn" :aria-label="t('app.common.close')" @click="adjustingItem = null">✕</button>
        </div>
        <form class="modal-body" @submit.prevent="confirmAdjust">
          <div class="stock-current-summary">
            <span>{{ t('app.inventory.current_stock') }}:</span>
            <strong>{{ adjustingItem.movements_sum_quantity || 0 }} {{ adjustingItem.unit }}</strong>
          </div>
          <label>{{ t('app.inventory.quantity_change_add_remove') }} *
            <input v-model.number="adjustQuantity" type="number" step="0.5" required />
            <small class="text-muted">Positive to add (+), negative to subtract (-)</small>
          </label>
          <label>{{ t('app.common.reason') }} *
            <input v-model="adjustReason" :placeholder="t('app.inventory.e_g_stock_count_correction_damaged')" required />
          </label>
          <div class="modal-footer" style="padding: 1rem 0 0 0; margin-top: 1rem;">
            <button type="button" class="secondary" @click="adjustingItem = null">{{ t('common.cancel') }}</button>
            <button class="primary">{{ t('app.inventory.save_adjustment') }}</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.check-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.85rem;
  font-weight: 500;
  cursor: pointer;
}

.check-label input {
  flex: none;
  width: 1.1rem;
  min-width: 0;
  min-height: 0;
  height: 1.1rem;
  padding: 0;
  accent-color: var(--accent, currentcolor);
}

.toolbar {
  flex-wrap: wrap;
  align-items: center;
}

.toolbar > input[type="text"],
.toolbar > input:not([type]) {
  flex: 1 1 14rem;
  width: auto;
  min-width: 0;
}

.check-label--end {
  margin-left: auto;
  white-space: nowrap;
}

.table-scroll {
  overflow-x: auto;
}

.customer-table {
  width: 100%;
  min-width: 45rem;
  border-collapse: collapse;
  font-size: 0.88rem;
}

.customer-table th,
.customer-table td {
  padding: 0.65rem 0.75rem;
  border-bottom: 1px solid var(--line);
  text-align: left;
  vertical-align: middle;
}

.customer-table th {
  color: var(--muted);
  font-weight: 600;
  font-size: 0.78rem;
  white-space: nowrap;
}

.customer-table .num {
  text-align: right;
}

.customer-table .actions-col {
  text-align: right;
}

.nowrap {
  white-space: nowrap;
}

.clickable-row {
  transition: background 120ms ease;
}

.clickable-row:hover {
  background: var(--paper);
}

.sku-tag {
  font-family: monospace;
  font-size: 0.82rem;
  background: var(--paper);
  border: 1px solid var(--line);
  padding: 0.15rem 0.4rem;
  border-radius: var(--radius-sm);
  color: var(--ink);
}

.item-name {
  color: var(--ink);
}

.unit-badge {
  text-transform: capitalize;
  font-size: 0.8rem;
  color: var(--muted);
}

.stock-level-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  font-size: 0.88rem;
  font-weight: 700;
  padding: 0.2rem 0.6rem;
  border-radius: var(--radius-sm);
  background: var(--paper);
  border: 1px solid var(--line);
}

.stock-level-pill--low {
  background: rgb(239 68 68 / 10%);
  color: #dc2626;
  border-color: rgb(239 68 68 / 30%);
}

.warning-icon {
  width: 14px;
  height: 14px;
}

.stock-current-summary {
  display: flex;
  justify-content: space-between;
  padding: 0.65rem 0.85rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  margin-bottom: 1rem;
}

.text-muted {
  color: var(--muted);
  font-size: 0.82rem;
}
</style>
