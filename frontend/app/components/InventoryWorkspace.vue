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
  try {
    items.value = (await api.request<{ items: I[] }>("/inventory")).data.items;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.inventory.could_not_load_inventory');
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/inventory", { method: "POST", body: form });
    toast.success(t('app.inventory.item_p0_added', { p0: form.name }));
    Object.assign(form, { sku: "", name: "", unit: "yard", reorder_level: 10 });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || t('app.inventory.could_not_save_item');
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
    toast.success(t('app.inventory.stock_adjusted_for_p0', { p0: adjustingItem.value.name }));
    adjustingItem.value = null;
    await load();
  } catch {
    toast.error(t('app.inventory.could_not_adjust_stock'));
  }
}

const filteredItems = computed(() => {
  if (!query.value.trim()) return items.value;
  const q = query.value.toLowerCase();
  return items.value.filter(
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

    <div class="workspace-grid">
      <!-- Add Item Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>{{ t('app.inventory.add_stock_item') }}</h2>
        <label>{{ t('app.inventory.sku_barcode') }}
          <input v-model="form.sku" :placeholder="t('app.inventory.e_g_fab_cot_01')" required />
        </label>
        <label>{{ t('app.inventory.item_name') }}
          <input v-model="form.name" :placeholder="t('app.inventory.e_g_cotton_voile_white_58')" required />
        </label>
        <div class="form-grid-2">
          <label>{{ t('app.inventory.unit') }}
            <select v-model="form.unit">
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
        <button class="primary">{{ t('app.inventory.add_item') }}</button>
      </form>

      <!-- Inventory Catalog Table / List -->
      <section class="panel">
        <div class="toolbar">
          <input v-model="query" :placeholder="t('app.inventory.search_by_name_sku_or_barcode')" />
        </div>

        <p v-if="loading">{{ t('app.inventory.loading_inventory') }}</p>
        <p v-else-if="!filteredItems.length" class="empty">{{ t('app.inventory.no_items_found') }}</p>

        <div class="inventory-list">
          <div v-for="item in filteredItems" :key="item.public_id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block">SKU: {{ item.sku || '—' }} · {{ t('app.inventory.reorder_alert') }}: {{ item.reorder_level || 0 }} {{ item.unit }}</small>
            </div>
            <div class="stock-badge-container">
              <span
                class="stock-level-pill"
                :class="{ 'stock-level-pill--low': (item.movements_sum_quantity || 0) <= (item.reorder_level || 0) }"
              >
                {{ item.movements_sum_quantity || 0 }} {{ item.unit }}
              </span>
            </div>
            <button class="mini-btn" @click="openAdjust(item)">
              {{ t('app.inventory.adjust_stock') }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- ADJUST STOCK MODAL -->
    <div v-if="adjustingItem" class="modal-backdrop" @click.self="adjustingItem = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>{{ t('app.inventory.adjust_stock') }} — {{ adjustingItem.name }}</h2>
          <button class="modal-close-btn" @click="adjustingItem = null">{{ t('app.common.text') }}</button>
        </div>
        <form class="modal-body" @submit.prevent="confirmAdjust">
          <p><strong>{{ t('app.inventory.current_stock') }}</strong> {{ adjustingItem.movements_sum_quantity || 0 }} {{ adjustingItem.unit }}</p>
          <label>{{ t('app.inventory.quantity_change_add_remove') }}
            <input v-model.number="adjustQuantity" type="number" step="0.5" required />
          </label>
          <label>{{ t('app.common.reason') }}
            <input v-model="adjustReason" :placeholder="t('app.inventory.e_g_stock_count_correction_damaged')" required />
          </label>
          <button class="primary" style="margin-top:0.75rem">{{ t('app.inventory.save_adjustment') }}</button>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
.inventory-list {
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

.stock-level-pill {
  font-size: 0.95rem;
  font-weight: 700;
  padding: 0.25rem 0.65rem;
  border-radius: var(--radius-sm);
  background: var(--paper);
  border: 1px solid var(--line);
}

.stock-level-pill--low {
  background: var(--status-cancelled-soft);
  color: var(--status-cancelled);
  border-color: var(--status-cancelled);
}
</style>
