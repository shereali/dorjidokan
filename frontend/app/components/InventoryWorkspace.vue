<script setup lang="ts">
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
    error.value = e?.data?.errors?.[0]?.message || "Could not load inventory.";
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/inventory", { method: "POST", body: form });
    toast.success(`Inventory item "${form.name}" added.`);
    Object.assign(form, { sku: "", name: "", unit: "yard", reorder_level: 10 });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not save item.";
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
    toast.success(`Stock adjusted for ${adjustingItem.value.name}.`);
    adjustingItem.value = null;
    await load();
  } catch (e: any) {
    toast.error("Could not adjust inventory stock.");
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
        <p class="eyebrow">FABRIC & STOCK LEDGER</p>
        <h1>Inventory Management</h1>
        <p>Track fabric bolts, threads, buttons, lining and accessories with full movement audit trails.</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Add Item Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>Add Stock Item (নতুন পণ্য এন্ট্রি)</h2>
        <label>SKU / Barcode
          <input v-model="form.sku" placeholder="e.g. FAB-COT-01" required />
        </label>
        <label>Item Name *
          <input v-model="form.name" placeholder="e.g. Cotton Voile White 58\"" required />
        </label>
        <div class="form-grid-2">
          <label>Measuring Unit *
            <select v-model="form.unit">
              <option value="yard">Yard (গজ)</option>
              <option value="meter">Meter (মিটার)</option>
              <option value="piece">Piece (পিস / থান)</option>
            </select>
          </label>
          <label>Low-stock Alert Level
            <input v-model.number="form.reorder_level" type="number" min="0" />
          </label>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="primary">Add Inventory Item</button>
      </form>

      <!-- Inventory Catalog Table / List -->
      <section class="panel">
        <div class="toolbar">
          <input v-model="query" placeholder="Search inventory by name, SKU or barcode..." />
        </div>

        <p v-if="loading">Loading inventory…</p>
        <p v-else-if="!filteredItems.length" class="empty">No inventory items found.</p>

        <div class="inventory-list">
          <div v-for="item in filteredItems" :key="item.public_id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block">SKU: {{ item.sku || 'N/A' }} · Reorder Alert: {{ item.reorder_level || 0 }} {{ item.unit }}</small>
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
              Adjust Stock
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- ADJUST STOCK MODAL -->
    <div v-if="adjustingItem" class="modal-backdrop" @click.self="adjustingItem = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>Adjust Stock — {{ adjustingItem.name }}</h2>
          <button class="modal-close-btn" @click="adjustingItem = null">&times;</button>
        </div>
        <form class="modal-body" @submit.prevent="confirmAdjust">
          <p><strong>Current Stock:</strong> {{ adjustingItem.movements_sum_quantity || 0 }} {{ adjustingItem.unit }}</p>
          <label>Quantity Adjustment (+ for add, - for remove)
            <input v-model.number="adjustQuantity" type="number" step="0.5" required />
          </label>
          <label>Reason for Adjustment
            <input v-model="adjustReason" placeholder="e.g. Physical inventory reconciliation, damaged bolt" required />
          </label>
          <button class="primary" style="margin-top:0.75rem">Save Stock Adjustment</button>
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
