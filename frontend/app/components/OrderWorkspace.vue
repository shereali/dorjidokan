<script setup lang="ts">
import type { PrintData } from "./PrintTemplates.vue";

import type { Employee, Garment, Order, Part } from "~/types/orders";

const props = defineProps<{
  initialQuery?: string;
  autoOpenNewModal?: boolean;
}>();

const emit = defineEmits<{
  clearModalFlag: [];
}>();

const api = useTailorsApi();
const toast = useToast();
const { t } = useTailorsI18n();
const realtime = useOrderChannel();
const statusLabelFn = statusLabel;

// Voice dictation for the saved-order inspector (the wizard has its own instance).
const {
  selectedLang: selectedVoiceLang,
  isListening: isVoiceListening,
  activePartId: voiceActivePartId,
  startListening: startVoiceListening,
  stopListening: stopVoiceListening,
  getLanguagePack,
} = useVoiceMeasurement();

const recentlyUpdatedPartId = ref<string | null>(null);

function triggerInspectorFieldVoice(part: Part) {
  if (isVoiceListening.value && voiceActivePartId.value === part.id) {
    stopVoiceListening();
    return;
  }
  const activeLang = selectedVoiceLang.value || "bn-BD";
  const pack = getLanguagePack(activeLang);

  startVoiceListening({
    lang: activeLang,
    targetPartId: part.id,
    continuous: false,
    onResult: async (pId, val) => {
      measurementValues[pId] = val;
      recentlyUpdatedPartId.value = pId;
      await saveMeasurement(part);
      const confirmText = pack.formatConfirmation(part.name, val, part.unit || "inch");
      toast.success(confirmText);
      setTimeout(() => {
        if (recentlyUpdatedPartId.value === pId) recentlyUpdatedPartId.value = null;
      }, 2500);
    },
    onError: (err) => {
      toast.warning(err);
    },
  });
}

const items = ref<Order[]>([]);
const garments = ref<Garment[]>([]);
const employees = ref<Employee[]>([]);
const selected = ref<Order | null>(null);

const viewMode = ref<"kanban" | "list">("kanban");
const statusFilter = ref("");
const dueOnlyFilter = ref(false);
const dateFilter = ref("");
const karigarFilter = ref("");
const sortBy = ref("id");
const sortDir = ref<"desc" | "asc">("desc");
const query = ref(props.initialQuery || "");
const archived = ref(false);
const loading = ref(true);
// Counters show a dash until the first response so a slow connection never reads as "0 orders".
const loadedOnce = ref(false);
const shown = (value: string) => (loadedOnce.value ? value : "—");
const live = ref(false);
const error = ref("");

// High-volume pagination metadata
const page = ref(1);
const perPage = ref(25);
const totalOrders = ref(0);
const lastPage = ref(1);
const fromItem = ref(0);
const toItem = ref(0);

// Multi-select for bulk actions
const selectedOrderIds = ref<string[]>([]);

// Server-side aggregated pipeline counts across database
const pipelineCounts = reactive({
  measuring: 0,
  pending_assignment: 0,
  in_progress: 0,
  ready: 0,
  delivered: 0,
  total: 0,
  due_count: 0,
  total_due_minor: 0,
});

// Print template modal state
const printData = ref<PrintData | null>(null);

// Quick Due Collection Modal
const showDueModal = ref(false);
const dueOrder = ref<Order | null>(null);
const quickPay = reactive({ amount_minor: 0, method: "cash", reference: "" });

// New-order wizard is its own component; this flag opens it.
const showWizard = ref(false);

const measurementValues = reactive<Record<string, number | null>>({});
const assignment = ref("");
const payment = reactive({ amount_minor: 0, method: "cash", reference: "" });

const quickPayTaka = computed({
  get: () => quickPay.amount_minor / 100,
  set: (v: number) => { quickPay.amount_minor = Math.round(v * 100); },
});

const paymentTaka = computed({
  get: () => payment.amount_minor / 100,
  set: (v: number) => { payment.amount_minor = Math.round(v * 100); },
});

// Kanban status columns
const kanbanColumns = [
  { key: "measuring", label: "Measuring", sub: "মাপ নেওয়া হচ্ছে", color: "var(--status-measuring)" },
  { key: "pending_assignment", label: "Cutting queue", sub: "কাটিংয়ের অপেক্ষায়", color: "var(--status-pending)" },
  { key: "in_progress", label: "In stitching", sub: "সেলাই চলছে", color: "var(--status-inprogress)" },
  { key: "ready", label: "Ready for pickup", sub: "ডেলিভারির জন্য প্রস্তুত", color: "var(--status-ready)" },
  { key: "delivered", label: "Delivered", sub: "সম্পন্ন", color: "var(--status-delivered)" },
];

function ordersInStatus(s: string) {
  return items.value.filter((o) => o.status === s);
}

const nextStatus = computed(() =>
  selected.value
    ? (
        {
          measuring: "pending_assignment",
          pending_assignment: "in_progress",
          in_progress: "ready",
          ready: "delivered",
        } as Record<string, string>
      )[selected.value.status]
    : undefined
);

const nextStatusLabel = computed(() =>
  nextStatus.value ? statusLabelFn(nextStatus.value) : ""
);

function getWhatsAppUrl(mobile?: string, name?: string, orderNum?: string) {
  if (!mobile) return "#";
  const digits = mobile.replace(/\D/g, "");
  const num = digits.startsWith("88") ? digits : digits.startsWith("0") ? "88" + digits : digits;
  const msg = `Hello ${name || 'Customer'}, your bespoke tailoring order #${orderNum} is updated at Dorjidokan Atelier. Thank you!`;
  return `https://wa.me/${num}?text=${encodeURIComponent(msg)}`;
}

async function load() {
  loading.value = true;
  try {
    const params = new URLSearchParams({
      archived: archived.value ? "1" : "0",
      page: String(page.value),
      per_page: String(perPage.value),
      sort_by: sortBy.value,
      sort_dir: sortDir.value,
    });
    if (statusFilter.value) params.set("status", statusFilter.value);
    if (dueOnlyFilter.value) params.set("due_only", "1");
    if (dateFilter.value) params.set("date_filter", dateFilter.value);
    if (karigarFilter.value) params.set("karigar_id", karigarFilter.value);
    if (query.value.trim()) params.set("query", query.value.trim());

    // Lookup lists are only requested when the role may read them; the order list itself is required.
    const optional = <T,>(allowed: boolean, path: string) =>
      allowed ? api.request<T>(path).catch(() => null) : Promise.resolve(null);
    const [ordersRes, garmentList, employeeList] = await Promise.all([
      api.request<{ items: Order[] }>(`/orders?${params.toString()}`),
      optional<{ garments: Garment[] }>(api.can("garments.view", "garments.manage"), "/garments"),
      optional<{ employees: Employee[] }>(api.can("employees.view", "orders.assign", "employees.manage"), "/employees"),
    ]);

    items.value = ordersRes.data.items;
    garments.value = (garmentList?.data.garments ?? []).filter((g) => g.active !== false);
    employees.value = (employeeList?.data.employees ?? []).filter(
      (e) => e.active && e.employee_type === "karigar"
    );

    // Update pagination and server counts
    const meta = (ordersRes as any).meta || {};
    if (meta.total !== undefined) totalOrders.value = meta.total;
    if (meta.last_page !== undefined) lastPage.value = meta.last_page;
    if (meta.from !== undefined) fromItem.value = meta.from || 0;
    if (meta.to !== undefined) toItem.value = meta.to || 0;
    if (meta.pipeline_counts) {
      Object.assign(pipelineCounts, meta.pipeline_counts);
    }
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "Could not load orders.";
  } finally {
    loading.value = false;
    loadedOnce.value = true;
  }
}

function goToPage(p: number) {
  if (p < 1 || p > lastPage.value || p === page.value) return;
  page.value = p;
  load();
}

function setQuickDateFilter(df: string) {
  dateFilter.value = dateFilter.value === df ? "" : df;
  page.value = 1;
  load();
}

function toggleSelectAll(event: Event) {
  const checked = (event.target as HTMLInputElement).checked;
  if (checked) {
    selectedOrderIds.value = items.value.map((o) => o.id);
  } else {
    selectedOrderIds.value = [];
  }
}

function toggleSelectOrder(id: string) {
  const index = selectedOrderIds.value.indexOf(id);
  if (index >= 0) selectedOrderIds.value.splice(index, 1);
  else selectedOrderIds.value.push(id);
}

// Debounced search
let searchTimer: any = null;
watch(query, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    page.value = 1;
    load();
  }, 250);
});

async function refreshSelected() {
  if (!selected.value) return;
  selected.value = (
    await api.request<{ order: Order }>(`/orders/${selected.value.id}`)
  ).data.order;
  for (const measurement of selected.value.measurements || [])
    measurementValues[measurement.part_id] = measurement.value;
}

async function select(order: Order) {
  selected.value = (
    await api.request<{ order: Order }>(`/orders/${order.id}`)
  ).data.order;
  for (const measurement of selected.value.measurements || [])
    measurementValues[measurement.part_id] = measurement.value;
  assignment.value = selected.value.karigar?.id || "";
  live.value = await realtime.join(order.id, async (name) => {
    toast.info(name === "order.ready" ? t('app.order.order_marked_ready') : t('app.order.new_measurement_received'));
    await refreshSelected();
    await load();
  });
}

async function saveMeasurement(part: Part) {
  const value = measurementValues[part.id];
  if (!selected.value || !value) return;
  await api.request(`/orders/${selected.value.id}/measurements`, {
    method: "POST",
    body: { garment_part_id: part.id, value, unit: part.unit },
  });
  toast.success(t('app.order.p0_saved', { p0: part.name }));
  await refreshSelected();
}

async function assignKarigar() {
  if (!selected.value || !assignment.value) return;
  await api.request(`/orders/${selected.value.id}/assign`, {
    method: "POST",
    body: { karigar_id: assignment.value },
  });
  toast.success(t('app.order.karigar_assigned'));
  await refreshSelected();
  await load();
}

async function advanceStatus(order?: Order) {
  const target = order || selected.value;
  if (!target) return;
  const next = {
    measuring: "pending_assignment",
    pending_assignment: "in_progress",
    in_progress: "ready",
    ready: "delivered",
  }[target.status];
  if (!next) return;

  await api.request(`/orders/${target.id}/status`, {
    method: "PATCH",
    body: { status: next },
  });
  toast.success(t('app.order.order_moved_to_p0', { p0: statusLabelFn(next) }));
  if (selected.value?.id === target.id) await refreshSelected();
  await load();
}

function openDueModal(order: Order) {
  dueOrder.value = order;
  quickPay.amount_minor = order.total_minor - order.paid_minor;
  quickPay.method = "cash";
  quickPay.reference = "";
  showDueModal.value = true;
}

async function recordQuickPayment() {
  if (!dueOrder.value || quickPay.amount_minor <= 0) return;
  try {
    await api.request(`/orders/${dueOrder.value.id}/payments`, {
      method: "POST",
      body: quickPay,
    });
    toast.success(t('app.order.payment_of_p0_recorded', { p0: (quickPay.amount_minor / 100).toFixed(2) }));
    showDueModal.value = false;
    await load();
    if (selected.value?.id === dueOrder.value.id) await refreshSelected();
  } catch (e: any) {
    toast.error(apiErrorMessage(e, t('app.order.could_not_record_payment')));
  }
}

async function payRecord() {
  if (!selected.value || payment.amount_minor < 1) return;
  await api.request(`/orders/${selected.value.id}/payments`, {
    method: "POST",
    body: payment,
  });
  toast.success(t('app.order.payment_recorded'));
  Object.assign(payment, { amount_minor: 0, method: "cash", reference: "" });
  await refreshSelected();
  await load();
}

function printOrderReceipt(order: Order) {
  printData.value = {
    type: "order_receipt",
    shopName: api.tenantName.value || undefined,
    orderNumber: order.order_number || order.number,
    barcode: order.barcode || order.order_number || order.number,
    customerName: order.customer.name,
    customerPhone: order.customer.mobile_number,
    promisedAt: order.promised_at,
    totalAmount: order.total_minor / 100,
    advancePaid: order.paid_minor / 100,
    dueAmount: (order.total_minor - order.paid_minor) / 100,
    items: [
      {
        name: order.garment.name,
        quantity: 1,
        price: order.total_minor / 100,
      },
    ],
  };
}

function printJobTicket(order: Order) {
  printData.value = {
    type: "job_ticket",
    shopName: api.tenantName.value || undefined,
    orderNumber: order.order_number || order.number,
    barcode: order.barcode || order.order_number || order.number,
    customerName: order.customer.name,
    customerPhone: order.customer.mobile_number,
    garmentName: order.garment.name,
    karigarName: order.karigar?.name,
    promisedAt: order.promised_at,
    measurements: (order.measurements || []).map((m) => {
      const part = order.garment.parts?.find((p) => p.id === m.part_id);
      return {
        name: part?.name || m.part_id,
        value: m.value,
        unit: m.unit,
      };
    }),
  };
}

function printBarcode(order: Order) {
  printData.value = {
    type: "barcode_tag",
    orderNumber: order.order_number || order.number,
    barcode: order.barcode || order.order_number || order.number,
    customerName: order.customer.name,
    garmentName: order.garment.name,
    promisedAt: order.promised_at,
  };
}

async function archiveSelected() {
  if (!selected.value) return;
  await api.request(`/orders/${selected.value.id}`, { method: "DELETE" });
  selected.value = null;
  toast.info(t('app.order.order_archived'));
  await load();
}

async function restoreOrder(order: Order) {
  await api.request(`/orders/${order.id}/restore`, { method: "POST" });
  toast.success(t('app.order.order_restored'));
  await load();
}

watch(
  () => props.autoOpenNewModal,
  (val) => {
    if (val) {
      openWizard();
      emit("clearModalFlag");
    }
  },
  { immediate: true }
);

function openWizard() {
  showWizard.value = true;
}

// A booked order: refresh the board, open it and print the receipt.
async function onWizardCreated(order: Order) {
  await load();
  await select(order);
  printOrderReceipt(order);
}

watch(
  () => props.initialQuery,
  (val) => {
    query.value = val || "";
    page.value = 1;
    load();
  }
);

onMounted(load);
</script>

<template>
  <div class="orders-root">
    <!-- ATELIER ORDERS EXECUTIVE HEADER -->
    <header class="orders-header">
      <div>
        <span class="eyebrow">{{ t('app.order.production') }}</span>
        <h1>{{ t('app.order.orders') }}</h1>
        <p class="subtitle">{{ t('app.order.search_filter_assign_karigars_and_track') }}</p>
      </div>

      <div class="header-actions">
        <!-- View Mode Segmented Controls -->
        <div class="view-mode-pill-box">
          <button
            class="view-pill-btn"
            :class="{ active: viewMode === 'kanban' && !archived }"
            @click="viewMode = 'kanban'; archived = false; page = 1; load()"
          >
            <NavIcon name="Kanban" />
            <span>{{ t('app.order.board') }}</span>
          </button>
          <button
            class="view-pill-btn"
            :class="{ active: viewMode === 'list' && !archived }"
            @click="viewMode = 'list'; archived = false; page = 1; load()"
          >
            <NavIcon name="List" />
            <span>{{ t('app.order.list_details') }}</span>
          </button>
          <button
            class="view-pill-btn"
            :class="{ active: archived }"
            @click="archived = true; selected = null; page = 1; load()"
          >
            <NavIcon name="Settings" />
            <span>{{ t('app.order.archive') }}</span>
          </button>
        </div>

        <button class="primary" @click="openWizard">
          <NavIcon name="Plus" />
          <span>{{ t('app.common.new_order') }}</span>
          <span class="kbd-pill">F2</span>
        </button>
      </div>
    </header>

    <!-- LIVE DATABASE AGGREGATED METRICS STRIP -->
    <section class="orders-metrics-strip">
      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': !statusFilter && !dueOnlyFilter && !dateFilter }"
        @click="statusFilter = ''; dueOnlyFilter = false; dateFilter = ''; page = 1; load()"
      >
        <span class="metric-icon">📋</span>
        <div class="metric-info">
          <small>{{ t('app.order.all_orders') }}</small>
          <strong>{{ shown((pipelineCounts.total || totalOrders).toLocaleString()) }} {{ t('app.common.orders') }}</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': statusFilter === 'in_progress' }"
        @click="statusFilter = statusFilter === 'in_progress' ? '' : 'in_progress'; dueOnlyFilter = false; page = 1; load()"
      >
        <span class="metric-icon metric-icon--blue">🪡</span>
        <div class="metric-info">
          <small>{{ t('app.common.in_stitching') }}</small>
          <strong>{{ shown((pipelineCounts.in_progress || 0).toLocaleString()) }} {{ t('app.order.in_sewing') }}</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': statusFilter === 'ready' }"
        @click="statusFilter = statusFilter === 'ready' ? '' : 'ready'; dueOnlyFilter = false; page = 1; load()"
      >
        <span class="metric-icon metric-icon--emerald">✨</span>
        <div class="metric-info">
          <small>{{ t('app.order.ready_for_pickup') }}</small>
          <strong>{{ shown((pipelineCounts.ready || 0).toLocaleString()) }} {{ t('app.order.ready') }}</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': dueOnlyFilter }"
        @click="dueOnlyFilter = !dueOnlyFilter; statusFilter = ''; page = 1; load()"
      >
        <span class="metric-icon metric-icon--coral">💰</span>
        <div class="metric-info">
          <small>{{ t('app.order.uncollected_dues') }} ({{ pipelineCounts.due_count || 0 }})</small>
          <strong style="color:var(--status-cancelled)">৳ {{ (((pipelineCounts.total_due_minor || 0)) / 100).toLocaleString() }}</strong>
        </div>
      </div>
    </section>

    <!-- UNIFIED INLINE SEARCH, FILTER, AND SORT TOOLBAR -->
    <div class="toolbar orders-toolbar">
      <div class="search-input-wrapper">
        <span class="search-icon"><NavIcon name="Search" /></span>
        <input
          v-model="query"
          :placeholder="t('app.order.search_by_order_no_customer_mobile')"
          inputmode="search"
          @keyup.enter="page = 1; load()"
        />
        <button v-if="query" class="clear-search-btn" @click="query = ''; page = 1; load()">&times;</button>
      </div>

      <!-- Status Filter Dropdown -->
      <select v-if="!archived" v-model="statusFilter" @change="page = 1; load()">
        <option value="">{{ t('app.order.all_stages') }} ({{ pipelineCounts.total || totalOrders }})</option>
        <option v-for="col in kanbanColumns" :key="col.key" :value="col.key">
          {{ t(`app.orders.stage_${col.key}`) }} ({{ (pipelineCounts as any)[col.key] || 0 }})
        </option>
      </select>

      <!-- Date Presets -->
      <select v-if="!archived" v-model="dateFilter" @change="page = 1; load()">
        <option value="">{{ t('app.order.all_delivery_dates') }}</option>
        <option value="today">{{ t('app.order.due_today') }}</option>
        <option value="overdue">{{ t('app.order.overdue') }}</option>
        <option value="this_week">{{ t('app.order.this_week') }}</option>
      </select>

      <!-- Sort Options -->
      <select v-model="sortBy" @change="load()">
        <option value="id">{{ t('app.order.sort_newest_first') }}</option>
        <option value="promised_at">{{ t('app.order.sort_delivery_date') }}</option>
        <option value="total_minor">{{ t('app.order.sort_amount') }}</option>
        <option value="due_minor">{{ t('app.order.sort_highest_due') }}</option>
      </select>

      <button class="secondary search-submit-btn" @click="page = 1; load()">
        <span>{{ t('app.order.search') }}</span>
      </button>
    </div>

    <!-- KANBAN BOARD VIEW -->
    <div v-if="viewMode === 'kanban' && !archived" class="kanban-board-container">
      <div class="kanban-board">
        <div v-for="col in kanbanColumns" :key="col.key" class="kanban-column">
          <!-- Column Header -->
          <div class="kanban-header">
            <div class="kanban-title-group">
              <span class="status-indicator-dot" :style="{ background: col.color }"></span>
              <div>
                <strong>{{ t(`app.orders.stage_${col.key}`) }}</strong>
              </div>
            </div>
            <span class="kanban-count">{{ ordersInStatus(col.key).length }}</span>
          </div>

          <!-- Cards in Column -->
          <div class="kanban-cards">
            <div
              v-for="order in ordersInStatus(col.key)"
              :key="order.id"
              class="kanban-card"
              :class="{ 'kanban-card--active': selected?.id === order.id }"
              @click="select(order)"
            >
              <div class="kanban-card-top">
                <div class="customer-block">
                  <strong class="customer-name">{{ order.customer.name }}</strong>
                  <small v-if="order.customer.mobile_number" class="customer-mobile">
                    {{ order.customer.mobile_number }}
                  </small>
                </div>
                <span class="kanban-order-num">#{{ order.order_number || order.number }}</span>
              </div>

              <div class="kanban-meta-row">
                <span class="garment-tag">{{ order.garment.name }}</span>
                <span v-if="order.karigar" class="karigar-tag">
                  ✂ {{ order.karigar.name }}
                </span>
              </div>

              <!-- Card Delivery & Due Bar -->
              <div class="kanban-footer">
                <span
                  class="due-date-pill"
                  :class="{
                    'due-date-pill--today': order.promised_at && new Date(order.promised_at).toDateString() === new Date().toDateString(),
                    'due-date-pill--urgent': order.promised_at && new Date(order.promised_at) < new Date(),
                  }"
                  :title="order.promised_at ? new Date(order.promised_at).toLocaleString() : ''"
                >
                  📅 {{ order.promised_at ? new Date(order.promised_at).toLocaleDateString() : 'No date' }}
                </span>

                <div class="card-mini-actions" @click.stop>
                  <button
                    v-if="order.total_minor > order.paid_minor"
                    class="mini-btn mini-btn--due"
                    :title="t('app.order.collect_due_payment')"
                    @click="openDueModal(order)"
                  >
                    {{ t('app.common.due') }} ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
                  </button>

                  <button
                    v-if="col.key !== 'delivered'"
                    class="mini-btn mini-btn--forward"
                    :title="t('app.order.move_to_the_next_stage')"
                    @click="advanceStatus(order)"
                  >
                    {{ t('app.order.next') }}
                  </button>

                  <button
                    class="mini-btn"
                    :title="t('app.order.print_receipt')"
                    @click="printOrderReceipt(order)"
                  >
                    <NavIcon name="Printer" />
                  </button>
                </div>
              </div>
            </div>

            <div v-if="!ordersInStatus(col.key).length" class="empty-kanban-slot">
              <span>{{ loading ? t('app.common.loading') : t('app.order.no_orders_in_this_stage') }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- LIST & DETAIL DUAL-PANEL VIEW -->
    <div v-else class="workspace-grid orders-layout">
      <!-- Order List Panel -->
      <section class="panel order-list">
        <div class="deck-header">
          <div>
            <span class="deck-eyebrow">{{ t('app.order.order_list') }}</span>
            <h2 class="deck-title">{{ archived ? 'Archived Orders' : 'Active Order Queue' }}</h2>
          </div>
          <span class="badge-count">{{ totalOrders.toLocaleString() }}</span>
        </div>

        <p v-if="loading" class="loading-state">{{ t('app.order.loading_orders') }}</p>
        <p v-else-if="!items.length" class="empty-state">{{ t('app.order.no_matching_orders_found') }}</p>

        <div v-else class="order-records-scroll">
          <div
            v-for="order in items"
            :key="order.id"
            class="record order-select-item"
            :class="{ active: selected?.id === order.id }"
            @click="archived ? restoreOrder(order) : select(order)"
          >
            <div class="record-meta">
              <div class="record-top-line">
                <strong class="record-customer">{{ order.customer.name }}</strong>
                <span class="order-num-pill">#{{ order.order_number || order.number }}</span>
              </div>
              <div class="record-sub-line">
                <span>{{ order.garment.name }}</span>
                <span v-if="order.karigar" class="karigar-inline">· ✂ {{ order.karigar.name }}</span>
                <span v-if="order.promised_at" class="date-inline">· 📅 {{ new Date(order.promised_at).toLocaleDateString() }}</span>
              </div>
            </div>

            <div class="record-status-col">
              <span class="status" :class="`status--${order.status}`">
                {{ statusLabelFn(order.status) }}
              </span>
              <span v-if="order.total_minor > order.paid_minor" class="due-tag">
                {{ t('app.common.due') }} ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
              </span>
            </div>

            <div class="record-actions-col" @click.stop>
              <button v-if="!archived" class="mini-btn" :title="t('app.order.print_slip')" @click="printOrderReceipt(order)">
                <NavIcon name="Printer" />
              </button>
              <button
                v-if="!archived && order.total_minor > order.paid_minor"
                class="mini-btn mini-btn--due"
                :title="t('app.order.pay_due')"
                @click="openDueModal(order)"
              >
                {{ t('app.order.pay') }}
              </button>
            </div>
          </div>
        </div>

        <!-- High-Volume Server-Side Pagination Bar -->
        <div v-if="totalOrders > 0" class="table-pagination-footer">
          <div class="pagination-summary">
            <span><strong>{{ fromItem }}–{{ toItem }}</strong> {{ t('app.order.of') }} <strong>{{ totalOrders.toLocaleString() }}</strong></span>
          </div>

          <div class="pagination-actions">
            <select v-model.number="perPage" class="pagination-perpage" @change="page = 1; load()">
              <option :value="25">{{ t('app.order.25_page') }}</option>
              <option :value="50">{{ t('app.order.50_page') }}</option>
              <option :value="100">{{ t('app.order.100_page') }}</option>
              <option :value="200">{{ t('app.order.200_page') }}</option>
            </select>

            <div class="pagination-nav-btns">
              <button class="page-nav-btn" :disabled="page <= 1" :title="t('app.order.first_page')" @click="goToPage(1)">«</button>
              <button class="page-nav-btn" :disabled="page <= 1" :title="t('app.order.previous_page')" @click="goToPage(page - 1)">‹</button>
              <span class="page-current-indicator">{{ page }} / {{ lastPage }}</span>
              <button class="page-nav-btn" :disabled="page >= lastPage" :title="t('app.order.next_page')" @click="goToPage(page + 1)">›</button>
              <button class="page-nav-btn" :disabled="page >= lastPage" :title="t('app.order.last_page')" @click="goToPage(lastPage)">»</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Selected Order Detail Panel -->
      <section class="panel order-detail">
        <template v-if="selected">
          <div class="order-detail-header">
            <div>
              <span class="eyebrow">{{ t('app.order.order_details') }}</span>
              <h2>{{ selected.customer.name }}</h2>
              <div class="detail-subtitle">
                <span>#{{ selected.order_number || selected.number }}</span>
                <span>· {{ selected.garment.name }}</span>
                <span v-if="selected.customer.mobile_number">· 📞 {{ selected.customer.mobile_number }}</span>
              </div>
            </div>

            <div class="order-detail-badges">
              <span class="status" :class="`status--${selected.status}`">{{ statusLabelFn(selected.status) }}</span>
              <span v-if="live" class="live-pill"><span class="live-dot"></span> Live</span>
              <a
                v-if="selected.customer.mobile_number"
                :href="getWhatsAppUrl(selected.customer.mobile_number, selected.customer.name, selected.order_number || selected.number)"
                target="_blank"
                class="whatsapp-action-btn"
                :title="t('app.common.send_whatsapp_message')"
              >
                <NavIcon name="WhatsApp" />
              </a>
            </div>
          </div>

          <!-- Garment Visual Prototype -->
          <div class="detail-blueprint-box">
            <GarmentPrototypeBuilder
              :garment-name="selected.garment.name"
              :parts="selected.garment.parts || []"
              :measurements="selected.measurements || []"
            />
          </div>

          <!-- Measurement inputs -->
          <div class="measurements-section">
            <h3 class="section-title">{{ t('app.order.measurements') }}</h3>
            <div class="measurement-form-grid">
              <form
                v-for="part in selected.garment.parts || []"
                :key="part.id"
                class="measurement-input-row"
                @submit.prevent="saveMeasurement(part)"
              >
                <label>{{ part.name }}
                  <div class="input-with-unit-voice">
                    <input
                      v-model.number="measurementValues[part.id]"
                      type="number"
                      step="0.01"
                      min="0.01"
                      :placeholder="part.unit"
                      :aria-label="part.name"
                      :class="{ 'flash-success': recentlyUpdatedPartId === part.id }"
                    />
                    <span class="unit-badge">{{ part.unit }}</span>
                    <button
                      type="button"
                      class="btn-field-mic"
                      :class="{ 'is-field-listening': isVoiceListening && voiceActivePartId === part.id }"
                      :title="`${part.name}-এর মাপ মুখে বলুন (ভয়েস ইনপুট)`"
                      @click="triggerInspectorFieldVoice(part)"
                    >
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                        <line x1="12" y1="19" x2="12" y2="23" />
                        <line x1="8" y1="23" x2="16" y2="23" />
                      </svg>
                    </button>
                  </div>
                </label>
                <button class="mini-btn">{{ t('app.order.save') }}</button>
              </form>
            </div>
          </div>

          <!-- Karigar Assignment -->
          <div v-if="api.can('orders.assign')" class="assignment-section">
            <h3 class="section-title">{{ t('app.order.karigar_assignment') }}</h3>
            <form class="assignment-form" @submit.prevent="assignKarigar">
              <select v-model="assignment">
                <option value="">{{ t('app.order.unassigned') }}</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">
                  {{ e.name }}
                </option>
              </select>
              <button class="primary">{{ t('app.order.assign_karigar') }}</button>
            </form>
          </div>

          <!-- Payments & Dues Summary -->
          <div class="payment-card">
            <div class="payment-card-row">
              <div class="payment-cell">
                <small>{{ t('app.order.total_making_cost') }}</small>
                <strong>৳ {{ (selected.total_minor / 100).toFixed(2) }}</strong>
              </div>
              <div class="payment-cell">
                <small>{{ t('app.order.advance_received') }}</small>
                <strong style="color:var(--status-ready)">৳ {{ (selected.paid_minor / 100).toFixed(2) }}</strong>
              </div>
              <div class="payment-cell">
                <small>{{ t('app.order.balance_due') }}</small>
                <strong style="color:var(--status-cancelled)">৳ {{ ((selected.total_minor - selected.paid_minor) / 100).toFixed(2) }}</strong>
              </div>
            </div>

            <form v-if="selected.total_minor > selected.paid_minor && api.can('orders.payments')" class="payment-inline-form" @submit.prevent="payRecord">
              <label>{{ t('app.order.collect_due') }}
                <input
                  v-model.number="paymentTaka"
                  type="number"
                  min="0.01"
                  step="0.01"
                  :max="(selected.total_minor - selected.paid_minor) / 100"
                  :placeholder="t('app.common.amount_2')"
                />
              </label>
              <select v-model="payment.method">
                <option value="cash">{{ t('app.common.cash') }}</option>
                <option value="mobile_banking">bKash / Nagad</option>
                <option value="card">{{ t('app.common.card') }}</option>
                <option value="bank_transfer">{{ t('app.common.bank') }}</option>
              </select>
              <button class="primary">{{ t('app.common.record_payment') }}</button>
            </form>
          </div>

          <!-- Action Buttons Deck -->
          <div class="order-actions-bar">
            <button v-if="nextStatus && api.can('orders.status')" class="primary advance-action-btn" @click="advanceStatus()">
              <span>{{ t('app.order.move_to') }} {{ nextStatusLabel }}</span>
              <span class="btn-arrow">➔</span>
            </button>
            <button class="secondary" @click="printOrderReceipt(selected)">
              <NavIcon name="Printer" /> {{ t('app.order.print_receipt') }}
            </button>
            <button class="secondary" @click="printJobTicket(selected)">
              <NavIcon name="Scissors" /> {{ t('app.order.job_card') }}
            </button>
            <button class="secondary" @click="printBarcode(selected)">
              <NavIcon name="Barcode" /> {{ t('app.order.barcode') }}
            </button>
            <button v-if="!['ready', 'delivered'].includes(selected.status) && api.can('orders.delete')" class="danger-btn" @click="archiveSelected">
              {{ t('app.order.archive') }}
            </button>
          </div>

          <!-- Order Timeline History -->
          <div class="timeline-box">
            <h4 class="timeline-title">{{ t('app.order.order_history') }}</h4>
            <ol class="timeline-list">
              <li v-for="event in selected.timeline || []" :key="event.created_at" class="timeline-item">
                <div class="timeline-dot"></div>
                <div class="timeline-content">
                  <div class="timeline-top">
                    <strong>{{ statusLabelFn(event.status) }}</strong>
                    <small>{{ new Date(event.created_at).toLocaleString() }}</small>
                  </div>
                  <span v-if="event.note" class="timeline-note">{{ event.note }}</span>
                </div>
              </li>
            </ol>
          </div>
        </template>

        <div v-else class="empty-detail-state">
          <div class="empty-icon-art">📐</div>
          <strong>{{ t('app.order.no_order_selected') }}</strong>
          <p>{{ t('app.order.pick_an_order_from_the_board') }}</p>
        </div>
      </section>
    </div>

    <OrderWizard v-model="showWizard" :garments="garments" :employees="employees" @created="onWizardCreated" />

    <!-- QUICK DUE COLLECTION MODAL -->
    <div v-if="showDueModal && dueOrder" class="modal-backdrop" @click.self="showDueModal = false">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <div>
            <span class="deck-eyebrow">{{ t('app.order.payment') }}</span>
            <h2>{{ t('app.order.collect_due_payment') }}</h2>
          </div>
          <button class="modal-close-btn" :aria-label="t('app.common.close')" @click="showDueModal = false">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
        <form class="modal-body" @submit.prevent="recordQuickPayment">
          <div class="quick-due-meta">
            <p><strong>{{ t('app.common.customer') }}</strong> {{ dueOrder.customer.name }}</p>
            <p><strong>{{ t('app.order.order') }}</strong> #{{ dueOrder.order_number || dueOrder.number }}</p>
            <p><strong>{{ t('app.order.total_due') }}</strong> <span style="color:var(--status-cancelled);font-weight:700">৳ {{ ((dueOrder.total_minor - dueOrder.paid_minor) / 100).toFixed(2) }}</span></p>
          </div>

          <label>{{ t('app.order.payment_received') }}
            <input
              v-model.number="quickPayTaka"
              type="number"
              min="1"
              :max="(dueOrder.total_minor - dueOrder.paid_minor) / 100"
              step="1"
              required
            />
          </label>
          <label>{{ t('app.common.payment_method') }}
            <select v-model="quickPay.method">
              <option value="cash">{{ t('app.common.cash') }}</option>
              <option value="mobile_banking">bKash / Nagad</option>
              <option value="card">{{ t('app.common.card') }}</option>
            </select>
          </label>
          <button class="primary" style="margin-top:0.75rem">
            {{ t('app.order.record_payment_issue_receipt') }}
          </button>
        </form>
      </div>
    </div>

    <!-- GLOBAL PRINT MODAL -->
    <PrintTemplates :data="printData" @close="printData = null" />
  </div>
</template>


<style scoped>
.orders-root {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.orders-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 1.5rem;
  flex-wrap: wrap;
}

.header-actions {
  display: flex;
  gap: 0.85rem;
  align-items: center;
  flex-wrap: wrap;
}

.view-mode-pill-box {
  display: inline-flex;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 0.25rem;
  gap: 0.2rem;
}

.view-pill-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  padding: 0.5rem 0.85rem;
  border-radius: var(--radius-xs);
  border: 1px solid transparent;
  background: transparent;
  color: var(--muted);
  font-size: 0.825rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 150ms ease;
}

.view-pill-btn:hover {
  color: var(--ink);
}

.view-pill-btn.active {
  background: var(--surface);
  color: var(--primary);
  border-color: var(--line);
  box-shadow: var(--shadow-xs);
}

.kbd-pill {
  font-size: 0.68rem;
  font-weight: 700;
  background: rgb(255 255 255 / 20%);
  padding: 0.1rem 0.35rem;
  border-radius: 3px;
  margin-left: 0.35rem;
}

/* ============================================================
   Metrics Strip
   ============================================================ */
.orders-metrics-strip {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 1rem;
}

@media (max-width: 64rem) {
  .orders-metrics-strip {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (max-width: 36rem) {
  /* Keep all four counters above the fold on a phone: 2x2 and without the decorative icon. */
  .orders-metrics-strip {
    grid-template-columns: repeat(2, 1fr);
    gap: 0.6rem;
  }

  .orders-metrics-strip .metric-pill {
    padding: 0.6rem 0.75rem;
  }

  .orders-metrics-strip .metric-icon {
    display: none;
  }
}

.metric-pill {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 0.85rem 1.15rem;
  display: flex;
  align-items: center;
  gap: 0.85rem;
  cursor: pointer;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
  box-shadow: var(--shadow-xs);
}

.metric-pill:hover,
.metric-pill--active {
  border-color: var(--primary);
  background: var(--paper);
  transform: translateY(-1px);
  box-shadow: var(--shadow-sm);
}

.metric-icon {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: var(--radius-sm);
  background: var(--paper);
  display: grid;
  place-items: center;
  font-size: 1.15rem;
  flex-shrink: 0;
}

.metric-icon--blue { background: var(--status-measuring-soft); }

.metric-icon--emerald { background: var(--status-ready-soft); }

.metric-icon--coral { background: var(--status-cancelled-soft); }

.metric-info small {
  display: block;
  font-size: 0.725rem;
  color: var(--muted);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.metric-info strong {
  display: block;
  font-size: 0.95rem;
  color: var(--ink);
  margin-top: 0.15rem;
}

/* ============================================================
   Toolbar
   ============================================================ */
.orders-toolbar {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  margin-bottom: 1.25rem;
}

.search-input-wrapper {
  position: relative;
  flex: 1 1 auto;
  min-width: 14rem;
  display: flex;
  align-items: center;
}

.search-input-wrapper input {
  padding-left: 2.4rem;
  width: 100%;
  height: 2.75rem;
  min-height: 2.75rem;
  box-sizing: border-box;
  margin: 0;
}

.orders-toolbar select {
  flex: 0 0 auto;
  width: auto;
  min-width: 13rem;
  max-width: 18rem;
  height: 2.75rem;
  min-height: 2.75rem;
  box-sizing: border-box;
  margin: 0;
}

.orders-toolbar .search-submit-btn {
  flex: 0 0 auto;
  height: 2.75rem;
  min-height: 2.75rem;
  padding: 0 1.25rem;
  box-sizing: border-box;
  margin: 0;
  white-space: nowrap;
}

.search-icon {
  position: absolute;
  left: 0.85rem;
  color: var(--muted);
  pointer-events: none;
}

.clear-search-btn {
  position: absolute;
  right: 0.65rem;
  background: transparent;
  border: 0;
  color: var(--muted);
  font-size: 1.25rem;
  cursor: pointer;
  padding: 0.2rem 0.4rem;
}

/* ============================================================
   {{ t('app.order.board') }}
   ============================================================ */
.kanban-board-container {
  width: 100%;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  padding-bottom: 0.5rem;
}

.kanban-board {
  display: grid;
  grid-template-columns: repeat(5, minmax(17.5rem, 1fr));
  gap: 1.15rem;
  align-items: start;
  min-width: 87.5rem;
}

.kanban-column {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-lg);
  display: flex;
  flex-direction: column;
  max-height: calc(100vh - 18rem);
  box-shadow: var(--shadow-xs);
}

.kanban-header {
  padding: 1rem 1.15rem;
  border-bottom: 1px solid var(--line);
  background: var(--paper);
  border-top-left-radius: var(--radius-lg);
  border-top-right-radius: var(--radius-lg);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.kanban-title-group {
  display: flex;
  align-items: center;
  gap: 0.65rem;
}

.status-indicator-dot {
  width: 0.55rem;
  height: 0.55rem;
  border-radius: 50%;
}

.kanban-title-group strong {
  display: block;
  font-size: 0.88rem;
  color: var(--ink);
}

.kanban-title-group small {
  display: block;
  font-size: 0.72rem;
  color: var(--muted);
}

.kanban-count {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-pill);
  padding: 0.15rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
}

.kanban-cards {
  padding: 0.85rem;
  overflow-y: auto;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.kanban-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1rem;
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  transition: all 160ms cubic-bezier(0.16, 1, 0.3, 1);
}

.kanban-card:hover {
  border-color: var(--primary);
  background: var(--surface);
  transform: translateY(-2px);
  box-shadow: var(--shadow-sm);
}

.kanban-card--active {
  border-color: var(--primary) !important;
  background: var(--surface) !important;
  box-shadow: 0 0 0 2px var(--primary-soft), var(--shadow-sm) !important;
}

.kanban-card-top {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 0.5rem;
}

.customer-name {
  display: block;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
}

.customer-mobile {
  display: block;
  font-size: 0.75rem;
  color: var(--muted);
  margin-top: 0.1rem;
}

.kanban-order-num {
  font-size: 0.75rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  color: var(--ink);
}

.kanban-meta-row {
  display: flex;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.garment-tag {
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
  color: var(--ink);
}

.karigar-tag {
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--primary-soft);
  color: var(--primary);
  border: 1px solid rgb(20 92 75 / 15%);
  padding: 0.15rem 0.45rem;
  border-radius: 4px;
}

.kanban-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
  margin-top: 0.25rem;
  padding-top: 0.65rem;
  border-top: 1px dashed var(--line);
}

.due-date-pill {
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--muted);
}

.due-date-pill--today {
  color: var(--accent);
  font-weight: 700;
}

.due-date-pill--urgent {
  color: var(--status-cancelled);
  font-weight: 700;
}

.card-mini-actions {
  display: flex;
  gap: 0.35rem;
  align-items: center;
}

.mini-btn {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 0.3rem 0.55rem;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 140ms ease;
}

.mini-btn:hover {
  background: var(--paper);
  border-color: var(--primary);
  color: var(--primary);
}

.mini-btn--due {
  color: var(--status-cancelled);
  background: var(--status-cancelled-soft);
  border-color: rgb(220 38 38 / 20%);
}

.mini-btn--forward {
  background: var(--primary-soft);
  color: var(--primary);
  border-color: rgb(20 92 75 / 20%);
}

.empty-kanban-slot {
  padding: 3rem 1rem;
  text-align: center;
  color: var(--muted);
  font-size: 0.8rem;
  font-style: italic;
}

/* ============================================================
   List & Split Detail Panel
   ============================================================ */
.orders-layout {
  display: grid;
  grid-template-columns: 1fr 1.3fr;
  gap: 1.5rem;
  align-items: start;
}

@media (max-width: 64rem) {
  .orders-layout {
    grid-template-columns: 1fr;
  }
}

.order-records-scroll {
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  max-height: calc(100vh - 18rem);
  overflow-y: auto;
}

.order-select-item {
  display: grid;
  grid-template-columns: 1fr auto auto;
  gap: 1rem;
  align-items: center;
  padding: 1rem 1.15rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all 150ms ease;
}

.order-select-item:hover,
.order-select-item.active {
  border-color: var(--primary);
  background: var(--surface);
  transform: translateX(2px);
}

.record-top-line {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.record-customer {
  font-size: 0.92rem;
  color: var(--ink);
}

.order-num-pill {
  font-size: 0.725rem;
  font-weight: 700;
  background: var(--surface);
  border: 1px solid var(--line);
  padding: 0.1rem 0.35rem;
  border-radius: 4px;
}

.record-sub-line {
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.record-status-col {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.25rem;
}

.due-tag {
  font-size: 0.725rem;
  font-weight: 700;
  color: var(--status-cancelled);
}

/* Detail Panel */
.order-detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 1.25rem;
  border-bottom: 1px solid var(--line);
  margin-bottom: 1.25rem;
}

.detail-subtitle {
  display: flex;
  gap: 0.4rem;
  font-size: 0.85rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.order-detail-badges {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.whatsapp-action-btn {
  color: #25d366;
  padding: 0.4rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--radius-xs);
  background: rgb(37 211 102 / 12%);
  transition: all 150ms ease;
}

.whatsapp-action-btn:hover {
  background: #25d366;
  color: #fff;
}

.detail-blueprint-box {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.section-title {
  font: 700 0.95rem var(--sans);
  color: var(--ink);
  margin: 0 0 0.85rem;
}

.measurement-form-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0.85rem;
  margin-bottom: 1.5rem;
}

@media (max-width: 40rem) {
  .measurement-form-grid {
    grid-template-columns: 1fr;
  }
}

.measurement-input-row {
  display: flex;
  align-items: flex-end;
  gap: 0.5rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.65rem 0.85rem;
}

.measurement-input-row label {
  flex: 1;
  font-size: 0.8rem;
  font-weight: 600;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.input-with-unit {
  position: relative;
  display: flex;
  align-items: center;
}

.input-with-unit input {
  padding-right: 2.25rem;
  font-weight: 700;
}

.unit-badge {
  position: absolute;
  right: 0.5rem;
  font-size: 0.725rem;
  color: var(--muted);
  font-weight: 600;
  pointer-events: none;
}

.assignment-section {
  margin-bottom: 1.5rem;
}

.assignment-form {
  display: flex;
  gap: 0.75rem;
}

.assignment-form select {
  flex: 1;
}

.payment-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.payment-card-row {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1rem;
  margin-bottom: 1rem;
}

.payment-cell small {
  display: block;
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 600;
  text-transform: uppercase;
}

.payment-cell strong {
  display: block;
  font-size: 1.15rem;
  margin-top: 0.2rem;
  color: var(--ink);
}

.payment-inline-form {
  display: flex;
  gap: 0.75rem;
  align-items: flex-end;
  padding-top: 1rem;
  border-top: 1px dashed var(--line);
}

.order-actions-bar {
  display: flex;
  gap: 0.65rem;
  flex-wrap: wrap;
  align-items: center;
  margin-bottom: 1.5rem;
}

.advance-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-arrow {
  transition: transform 150ms ease;
}

.advance-action-btn:hover .btn-arrow {
  transform: translateX(3px);
}

.danger-btn {
  background: transparent;
  border: 1px solid var(--color-danger);
  color: var(--color-danger);
  padding: 0.65rem 1.15rem;
  border-radius: var(--radius);
  font-weight: 600;
  font-size: 0.88rem;
  cursor: pointer;
  transition: all 150ms ease;
}

.danger-btn:hover {
  background: var(--color-danger-soft);
}

.timeline-box {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius);
  padding: 1.25rem;
}

.timeline-title {
  margin: 0 0 1rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--muted);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.timeline-list {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.timeline-item {
  display: flex;
  gap: 0.85rem;
  position: relative;
}

.timeline-dot {
  width: 0.65rem;
  height: 0.65rem;
  border-radius: 50%;
  background: var(--primary);
  margin-top: 0.35rem;
  flex-shrink: 0;
}

.timeline-content {
  flex: 1;
}

.timeline-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.timeline-top strong {
  font-size: 0.88rem;
  color: var(--ink);
}

.timeline-top small {
  font-size: 0.75rem;
  color: var(--muted);
}

.timeline-note {
  display: block;
  font-size: 0.78rem;
  color: var(--muted);
  margin-top: 0.2rem;
}

.empty-detail-state {
  padding: 4rem 1.5rem;
  text-align: center;
  color: var(--muted);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.5rem;
}

.empty-icon-art {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

/* ============================================================
   Server-Side Pagination Footer
   ============================================================ */
.table-pagination-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  padding: 0.85rem 1rem;
  background: var(--paper);
  border-top: 1px solid var(--line);
  border-bottom-left-radius: var(--radius-lg);
  border-bottom-right-radius: var(--radius-lg);
  flex-wrap: wrap;
  font-size: 0.85rem;
  margin-top: auto;
}

.pagination-summary {
  color: var(--muted);
}

.pagination-summary strong {
  color: var(--ink);
}

.pagination-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.pagination-perpage {
  height: 2rem;
  padding: 0 0.5rem;
  font-size: 0.8rem;
  border-radius: var(--radius-sm);
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--ink);
}

.pagination-nav-btns {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.15rem;
}

.page-nav-btn {
  width: 1.85rem;
  height: 1.85rem;
  display: grid;
  place-items: center;
  background: transparent;
  border: 0;
  border-radius: var(--radius-xs);
  color: var(--ink);
  font-weight: 600;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 120ms ease;
}

.page-nav-btn:hover:not(:disabled) {
  background: var(--paper);
  color: var(--primary);
}

.page-nav-btn:disabled {
  opacity: 0.35;
  cursor: not-allowed;
}

.page-current-indicator {
  padding: 0 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--ink);
  white-space: nowrap;
}

.badge-req {
  font-size: 0.65rem;
  font-weight: 700;
  color: #dc2626;
  background: #fee2e2;
  padding: 0.05rem 0.3rem;
  border-radius: 3px;
}

@keyframes pulse-mic {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
  70% { transform: scale(1.06); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

/* Input with integrated unit & voice mic button */
.input-with-unit-voice {
  display: flex;
  align-items: stretch;
  min-height: 3rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  overflow: hidden;
  transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.input-with-unit-voice:focus-within {
  border-color: var(--primary);
  box-shadow: var(--focus-ring);
}

.form .input-with-unit-voice input {
  flex: 1;
  min-width: 0;
  border: none;
  border-radius: 0;
  background: transparent;
  padding: 0.55rem 0.65rem;
  font-size: 1.05rem;
  font-weight: 700;
  color: var(--ink);
  width: 100%;
}

.form .input-with-unit-voice input:focus {
  outline: none;
  box-shadow: none;
}

.input-with-unit-voice .unit-badge {
  position: static;
  flex-shrink: 0;
  background: var(--paper);
  border-left: 1px solid var(--line);
  border-right: 1px solid var(--line);
  color: var(--muted);
  font-size: 0.725rem;
  font-weight: 700;
  padding: 0 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  user-select: none;
}

.btn-field-mic {
  background: transparent;
  border: none;
  color: var(--muted);
  width: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  padding: 0;
  transition: all 0.15s ease;
}

.btn-field-mic:hover {
  background: var(--paper);
  color: var(--primary);
}

.btn-field-mic.is-field-listening {
  background: #ef4444;
  color: #ffffff;
  animation: pulse-mic 1s infinite;
}

.flash-success {
  background-color: #dcfce7 !important;
  transition: background-color 0.3s ease;
}

@media (max-width: 48rem) {
  .voice-assistant-panel {
    flex-direction: column;
    align-items: stretch;
  }
  .modal-steps-bar {
    overflow-x: auto;
  }
  .step-item {
    font-size: 0.725rem;
    padding: 0.65rem 0.25rem;
  }
  .payment-card-row {
    flex-direction: column;
    gap: 0.5rem;
  }
  .table-pagination-footer {
    flex-direction: column;
    align-items: stretch;
    gap: 0.5rem;
  }
}
</style>
