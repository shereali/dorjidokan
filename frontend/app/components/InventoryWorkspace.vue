<script setup lang="ts">
const { tr } = usePlatformFormat();
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
    error.value = e?.data?.errors?.[0]?.message || tr("Could not load inventory.", "ইনভেন্টরি লোড করা যায়নি।");
  } finally {
    loading.value = false;
  }
}

async function save() {
  error.value = "";
  try {
    await api.request("/inventory", { method: "POST", body: form });
    toast.success(tr(`Item "${form.name}" added.`, `"${form.name}" যোগ হয়েছে।`));
    Object.assign(form, { sku: "", name: "", unit: "yard", reorder_level: 10 });
    await load();
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || tr("Could not save item.", "পণ্য সংরক্ষণ করা যায়নি।");
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
    toast.success(tr(`Stock adjusted for ${adjustingItem.value.name}.`, `${adjustingItem.value.name}-এর স্টক সমন্বয় হয়েছে।`));
    adjustingItem.value = null;
    await load();
  } catch {
    toast.error(tr("Could not adjust stock.", "স্টক সমন্বয় করা যায়নি।"));
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
        <p class="eyebrow">{{ tr("FABRIC & STOCK", "কাপড় ও স্টক") }}</p>
        <h1>{{ tr("Inventory", "ইনভেন্টরি") }}</h1>
        <p>{{ tr("Track fabric, thread, buttons, lining and accessories with a full movement history.", "কাপড়, সুতা, বোতাম, লাইনিং ও এক্সেসরিজ — প্রতিটি আসা-যাওয়ার হিসাবসহ।") }}</p>
      </div>
    </header>

    <div class="workspace-grid">
      <!-- Add Item Form -->
      <form class="panel form" @submit.prevent="save">
        <h2>{{ tr("Add stock item", "নতুন পণ্য যোগ করুন") }}</h2>
        <label>{{ tr("SKU / barcode", "SKU / বারকোড") }}
          <input v-model="form.sku" :placeholder="tr('e.g. FAB-COT-01', 'যেমন: FAB-COT-01')" required />
        </label>
        <label>{{ tr("Item name *", "পণ্যের নাম *") }}
          <input v-model="form.name" :placeholder="tr('e.g. Cotton voile white 58 inch', 'যেমন: সাদা সুতি ভয়েল ৫৮ ইঞ্চি')" required />
        </label>
        <div class="form-grid-2">
          <label>{{ tr("Unit *", "একক *") }}
            <select v-model="form.unit">
              <option value="yard">{{ tr("Yard", "গজ") }}</option>
              <option value="meter">{{ tr("Meter", "মিটার") }}</option>
              <option value="piece">{{ tr("Piece", "পিস / থান") }}</option>
            </select>
          </label>
          <label>{{ tr("Low-stock alert level", "কম স্টকের সতর্কসীমা") }}
            <input v-model.number="form.reorder_level" type="number" min="0" />
          </label>
        </div>
        <p v-if="error" class="error">{{ error }}</p>
        <button class="primary">{{ tr("Add item", "পণ্য যোগ করুন") }}</button>
      </form>

      <!-- Inventory Catalog Table / List -->
      <section class="panel">
        <div class="toolbar">
          <input v-model="query" :placeholder="tr('Search by name, SKU or barcode…', 'নাম, SKU বা বারকোড দিয়ে খুঁজুন…')" />
        </div>

        <p v-if="loading">{{ tr("Loading inventory…", "ইনভেন্টরি লোড হচ্ছে…") }}</p>
        <p v-else-if="!filteredItems.length" class="empty">{{ tr("No items found.", "কোনো পণ্য পাওয়া যায়নি।") }}</p>

        <div class="inventory-list">
          <div v-for="item in filteredItems" :key="item.public_id" class="record record--stock">
            <div>
              <strong>{{ item.name }}</strong>
              <small style="display:block">SKU: {{ item.sku || '—' }} · {{ tr("Reorder alert", "পুনঃক্রয় সতর্কতা") }}: {{ item.reorder_level || 0 }} {{ item.unit }}</small>
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
              {{ tr("Adjust stock", "স্টক সমন্বয়") }}
            </button>
          </div>
        </div>
      </section>
    </div>

    <!-- ADJUST STOCK MODAL -->
    <div v-if="adjustingItem" class="modal-backdrop" @click.self="adjustingItem = null">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <h2>{{ tr("Adjust stock", "স্টক সমন্বয়") }} — {{ adjustingItem.name }}</h2>
          <button class="modal-close-btn" @click="adjustingItem = null">{{ tr("×", "×") }}</button>
        </div>
        <form class="modal-body" @submit.prevent="confirmAdjust">
          <p><strong>{{ tr("Current stock:", "বর্তমান স্টক:") }}</strong> {{ adjustingItem.movements_sum_quantity || 0 }} {{ adjustingItem.unit }}</p>
          <label>{{ tr("Quantity change (+ add, − remove)", "পরিমাণ পরিবর্তন (+ যোগ, − বাদ)") }}
            <input v-model.number="adjustQuantity" type="number" step="0.5" required />
          </label>
          <label>{{ tr("Reason", "কারণ") }}
            <input v-model="adjustReason" :placeholder="tr('e.g. stock count correction, damaged roll', 'যেমন: গণনা সংশোধন, নষ্ট থান')" required />
          </label>
          <button class="primary" style="margin-top:0.75rem">{{ tr("Save adjustment", "সমন্বয় সংরক্ষণ") }}</button>
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
