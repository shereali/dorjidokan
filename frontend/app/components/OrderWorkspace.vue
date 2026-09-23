<script setup lang="ts">
import type { PrintData } from "./PrintTemplates.vue";

interface Part {
  id: string;
  public_id?: string;
  name: string;
  slug?: string;
  unit: string;
  display_order: number;
  required?: boolean;
  svg_asset_ref?: string;
}

interface Measurement {
  part_id: string;
  value: number;
  unit: string;
}

interface Customer {
  id?: string;
  public_id?: string;
  name: string;
  mobile_number?: string;
}

export interface GarmentDesignValue {
  id?: string;
  public_id?: string;
  name: string;
  extra_price_minor: number;
  is_default: boolean;
  display_order: number;
}

export interface GarmentDesignOption {
  id?: string;
  public_id?: string;
  name: string;
  type: "select" | "checkbox" | "radio";
  display_order: number;
  values: GarmentDesignValue[];
}

interface Garment {
  id?: string;
  public_id?: string;
  name: string;
  slug?: string;
  category?: "gents" | "ladies" | "kids" | "unisex";
  group_name?: string;
  base_making_minor?: number;
  master_rate_minor?: number;
  karigar_rate_minor?: number;
  active?: boolean;
  parts?: Part[];
  design_options?: GarmentDesignOption[];
}

interface Employee {
  id: string;
  name: string;
  active: boolean;
  employee_type: string;
}

interface Timeline {
  status: string;
  from_status?: string;
  note?: string;
  created_at: string;
}

interface Order {
  id: string;
  number?: string;
  order_number?: string;
  barcode?: string;
  status: string;
  customer: { name: string; mobile_number?: string; public_id?: string };
  garment: { name: string; parts?: Part[] };
  karigar?: { id: string; name: string };
  measurements?: Measurement[];
  timeline?: Timeline[];
  items?: Array<{ id: string; garment: { name: string }; quantity: number; making_cost_minor: number; design_cost_minor: number; group_name?: string }>;
  total_minor: number;
  paid_minor: number;
  promised_at?: string;
  archived_at?: string;
}

const props = defineProps<{
  initialQuery?: string;
  autoOpenNewModal?: boolean;
}>();

const emit = defineEmits<{
  clearModalFlag: [];
}>();

const api = useTailorsApi();
const toast = useToast();
const realtime = useOrderChannel();
const statusLabelFn = statusLabel;

// Voice measurement composable integration with multi-language engine
const {
  selectedLang: selectedVoiceLang,
  supportedLanguages: supportedVoiceLanguages,
  isSupported: voiceSupported,
  isListening: isVoiceListening,
  activePartId: voiceActivePartId,
  liveTranscript: voiceLiveTranscript,
  lastRecognized: voiceLastRecognized,
  setLanguage: setVoiceLanguage,
  startListening: startVoiceListening,
  stopListening: stopVoiceListening,
  getLanguagePack,
} = useVoiceMeasurement();

const isContinuousVoiceActive = ref(false);
const recentlyUpdatedPartId = ref<string | null>(null);
const voiceConfirmationMessage = ref<string>("");
let voiceConfirmTimer: any = null;

function showVoiceConfirmation(msg: string) {
  voiceConfirmationMessage.value = msg;
  clearTimeout(voiceConfirmTimer);
  voiceConfirmTimer = setTimeout(() => {
    voiceConfirmationMessage.value = "";
  }, 4000);
}

function triggerFieldVoice(partId: string) {
  if (isVoiceListening.value && voiceActivePartId.value === partId) {
    stopVoiceListening();
    return;
  }
  const targetPart = selectedGarmentObj.value?.parts?.find((p) => p.id === partId);
  const activeLang = selectedVoiceLang.value || "bn-BD";
  const pack = getLanguagePack(activeLang);

  startVoiceListening({
    lang: activeLang,
    targetPartId: partId,
    continuous: false,
    onResult: (pId, val, pName) => {
      wizardForm.measurements[pId] = val;
      recentlyUpdatedPartId.value = pId;
      const partDisplayName = pName || targetPart?.name || "পরিমাপ";
      const confirmText = pack.formatConfirmation(partDisplayName, val, targetPart?.unit || "inch");
      showVoiceConfirmation(confirmText);
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

function toggleContinuousVoice() {
  if (isVoiceListening.value) {
    stopVoiceListening();
    isContinuousVoiceActive.value = false;
    return;
  }
  isContinuousVoiceActive.value = true;
  const activeLang = selectedVoiceLang.value || "bn-BD";
  const pack = getLanguagePack(activeLang);

  startVoiceListening({
    lang: activeLang,
    continuous: true,
    parts: selectedGarmentObj.value?.parts || [],
    onResult: (pId, val, pName) => {
      wizardForm.measurements[pId] = val;
      recentlyUpdatedPartId.value = pId;
      const targetPart = selectedGarmentObj.value?.parts?.find((p) => p.id === pId);
      const confirmText = pack.formatConfirmation(pName || targetPart?.name || "পরিমাপ", val, targetPart?.unit || "inch");
      showVoiceConfirmation(confirmText);
      toast.success(confirmText);
      setTimeout(() => {
        if (recentlyUpdatedPartId.value === pId) recentlyUpdatedPartId.value = null;
      }, 2500);
    },
    onError: (err) => {
      toast.warning(err);
      isContinuousVoiceActive.value = false;
    },
  });
}

const items = ref<Order[]>([]);
const customers = ref<Customer[]>([]);
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

// Guided Order Booking Wizard State
const showWizard = ref(false);
const wizardStep = ref(1);
const wizardError = ref("");
const wizardLoading = ref(false);
const isUrgent = ref(false);

const wizardForm = reactive({
  customer_id: "",
  customer_name: "",
  customer_mobile: "",
  garment_id: "",
  karigar_id: "",
  promised_at: "",
  notes: "",
  total_minor: 0,
  paid_minor: 0,
  payment_method: "cash",
  measurements: {} as Record<string, number | null>,
  selectedDesignOptions: {} as Record<string, string | string[]>,
});

const measurementValues = reactive<Record<string, number | null>>({});
const assignment = ref("");
const payment = reactive({ amount_minor: 0, method: "cash", reference: "" });

// Auto-suggested customers during wizard typing
const filteredCustomerSuggestions = computed(() => {
  if (!wizardForm.customer_mobile.trim()) return [];
  const term = wizardForm.customer_mobile.trim().toLowerCase();
  return customers.value.filter(
    (c) =>
      c.mobile_number?.toLowerCase().includes(term) ||
      c.name.toLowerCase().includes(term)
  ).slice(0, 5);
});

const selectedGarmentObj = computed(() =>
  garments.value.find(
    (g) => (g.id || g.public_id) === wizardForm.garment_id
  )
);

const groupedGarments = computed(() => {
  const groups: Record<string, Garment[]> = {
    gents: [],
    ladies: [],
    kids: [],
    unisex: [],
  };
  for (const g of garments.value) {
    const cat = g.category || "gents";
    if (!groups[cat]) groups[cat] = [];
    groups[cat].push(g);
  }
  return groups;
});

function calculateGarmentTotalMinor(): number {
  const g = selectedGarmentObj.value;
  if (!g) return wizardForm.total_minor;
  let total = g.base_making_minor || 0;

  if (g.design_options?.length) {
    for (const opt of g.design_options) {
      const selected = wizardForm.selectedDesignOptions[opt.name];
      if (Array.isArray(selected)) {
        for (const sName of selected) {
          const v = opt.values.find((val) => val.name === sName);
          if (v) total += (v.extra_price_minor || 0);
        }
      } else if (typeof selected === "string" && selected) {
        const v = opt.values.find((val) => val.name === selected);
        if (v) total += (v.extra_price_minor || 0);
      }
    }
  }
  return total;
}

function onGarmentSelectChange() {
  const g = selectedGarmentObj.value;
  wizardForm.selectedDesignOptions = {};
  if (g) {
    if (g.design_options?.length) {
      for (const opt of g.design_options) {
        if (opt.type === "checkbox") {
          wizardForm.selectedDesignOptions[opt.name] = [];
        } else {
          const defVal = opt.values.find((v) => v.is_default) || opt.values[0];
          wizardForm.selectedDesignOptions[opt.name] = defVal ? defVal.name : "";
        }
      }
    }
    const tot = calculateGarmentTotalMinor();
    wizardForm.total_minor = tot;
    wizardForm.paid_minor = Math.round(tot * 0.4);
  }
}

function updateDesignOptionSelection(optName: string, valName: string, isCheckbox: boolean = false) {
  if (isCheckbox) {
    const current = (wizardForm.selectedDesignOptions[optName] as string[]) || [];
    const idx = current.indexOf(valName);
    if (idx !== -1) {
      current.splice(idx, 1);
    } else {
      current.push(valName);
    }
    wizardForm.selectedDesignOptions[optName] = [...current];
  } else {
    wizardForm.selectedDesignOptions[optName] = valName;
  }
  const tot = calculateGarmentTotalMinor();
  wizardForm.total_minor = tot;
}

// Money computed helpers
const wizardTotalTaka = computed({
  get: () => wizardForm.total_minor / 100,
  set: (v: number) => { wizardForm.total_minor = Math.round(v * 100); },
});

const wizardAdvanceTaka = computed({
  get: () => wizardForm.paid_minor / 100,
  set: (v: number) => { wizardForm.paid_minor = Math.round(v * 100); },
});

const wizardDueTaka = computed(() =>
  Math.max(0, wizardTotalTaka.value - wizardAdvanceTaka.value)
);

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
  { key: "measuring", label: "Measuring", sub: "মাপ গ্রহণ", color: "var(--status-measuring)" },
  { key: "pending_assignment", label: "Cutting Queue", sub: "কাটিং অপেক্ষমান", color: "var(--status-pending)" },
  { key: "in_progress", label: "In Stitching", sub: "সেলাই চলছে", color: "var(--status-inprogress)" },
  { key: "ready", label: "Ready for Pickup", sub: "ডেলিভারি প্রস্তুত", color: "var(--status-ready)" },
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

    const [ordersRes, customerList, garmentList, employeeList] = await Promise.all([
      api.request<{ items: Order[] }>(`/orders?${params.toString()}`),
      api.request<{ items: Customer[] }>("/customers"),
      api.request<{ garments: Garment[] }>("/garments"),
      api.request<{ employees: Employee[] }>("/employees"),
    ]);

    items.value = ordersRes.data.items;
    customers.value = customerList.data.items;
    garments.value = garmentList.data.garments.filter((g) => g.active !== false);
    employees.value = employeeList.data.employees.filter(
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
    toast.info(name === "order.ready" ? "Order marked ready." : "New measurement received.");
    await refreshSelected();
    await load();
  });
}

const maxStepReached = ref(1);

const recordedMeasurementsCount = computed(() => {
  return Object.values(wizardForm.measurements).filter(
    (v) => v !== null && v !== undefined && !Number.isNaN(v) && v > 0
  ).length;
});

function validateCurrentStep(): boolean {
  wizardError.value = "";
  if (wizardStep.value === 1) {
    if (!wizardForm.customer_mobile.trim() && !wizardForm.customer_name.trim()) {
      wizardError.value = "অনুগ্রহ করে গ্রাহকের মোবাইল নম্বর বা নাম লিখুন।";
      return false;
    }
  } else if (wizardStep.value === 2) {
    if (!wizardForm.garment_id) {
      wizardError.value = "অনুগ্রহ করে একটি পোশাক নির্বাচন করুন।";
      return false;
    }
    if (!wizardForm.promised_at) {
      wizardError.value = "অনুগ্রহ করে ডেলিভারির তারিখ নির্ধারণ করুন।";
      return false;
    }
  } else if (wizardStep.value === 3) {
    const requiredParts = (selectedGarmentObj.value?.parts || []).filter(
      (p) => p.required !== false
    );
    const missingReq = requiredParts.filter((p) => {
      const val = wizardForm.measurements[p.id];
      return val === null || val === undefined || Number.isNaN(val) || val <= 0;
    });
    if (missingReq.length > 0) {
      toast.info(
        `পরামর্শ: ${missingReq.map((p) => p.name).join(", ")} মাপ ফাঁকা রয়েছে।`
      );
    }
  }
  return true;
}

function advanceWizardStep() {
  if (!validateCurrentStep()) return;
  if (wizardStep.value < 4) {
    wizardStep.value++;
    if (wizardStep.value > maxStepReached.value) {
      maxStepReached.value = wizardStep.value;
    }
  }
}

function goToStep(target: number) {
  if (target === wizardStep.value) return;
  if (target > wizardStep.value) {
    if (!validateCurrentStep()) return;
  }
  wizardError.value = "";
  wizardStep.value = target;
  if (target > maxStepReached.value) {
    maxStepReached.value = target;
  }
}

function openWizard() {
  showWizard.value = true;
  wizardStep.value = 1;
  maxStepReached.value = 1;
  wizardError.value = "";
  isUrgent.value = false;

  const firstGarment = garments.value[0];
  const initialGarmentId = firstGarment?.public_id || firstGarment?.id || "";
  const initialBaseMaking = firstGarment?.base_making_minor || 45000;
  const initialAdvance = Math.round(initialBaseMaking * 0.4);

  Object.assign(wizardForm, {
    customer_id: "",
    customer_name: "",
    customer_mobile: "",
    garment_id: initialGarmentId,
    karigar_id: "",
    promised_at: new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 16),
    notes: "",
    total_minor: initialBaseMaking,
    paid_minor: initialAdvance,
    payment_method: "cash",
    measurements: {},
    selectedDesignOptions: {},
  });

  onGarmentSelectChange();
}

function selectExistingCustomer(c: Customer) {
  wizardForm.customer_id = c.id || c.public_id || "";
  wizardForm.customer_name = c.name;
  wizardForm.customer_mobile = c.mobile_number || "";
}

function setDeliveryDays(days: number) {
  const date = new Date(Date.now() + days * 86400000);
  wizardForm.promised_at = date.toISOString().slice(0, 16);
}

async function finishWizard() {
  wizardError.value = "";
  wizardLoading.value = true;
  try {
    let custId = wizardForm.customer_id;
    if (!custId && wizardForm.customer_name && wizardForm.customer_mobile) {
      const res = await api.request<{ customer: { public_id: string; id: string } }>("/customers", {
        method: "POST",
        body: {
          name: wizardForm.customer_name,
          mobile_number: wizardForm.customer_mobile,
          marketing_consent: true,
        },
      });
      custId = res.data.customer.public_id || res.data.customer.id;
    }

    // Assemble design style summary with custom notes
    const selectedStyleSummary: string[] = [];
    if (selectedGarmentObj.value?.design_options?.length) {
      for (const opt of selectedGarmentObj.value.design_options) {
        const val = wizardForm.selectedDesignOptions[opt.name];
        if (Array.isArray(val) && val.length) {
          selectedStyleSummary.push(`${opt.name}: ${val.join(", ")}`);
        } else if (typeof val === "string" && val) {
          selectedStyleSummary.push(`${opt.name}: ${val}`);
        }
      }
    }

    const combinedNotes = [
      selectedStyleSummary.length ? `[ডিজাইন ও স্টাইল] ${selectedStyleSummary.join(" | ")}` : "",
      wizardForm.notes.trim(),
    ].filter(Boolean).join("\n");

    const orderRes = await api.request<{ order: Order }>("/orders", {
      method: "POST",
      body: {
        customer_id: custId,
        garment_id: wizardForm.garment_id,
        promised_at: wizardForm.promised_at,
        total_minor: wizardForm.total_minor,
        paid_minor: wizardForm.paid_minor,
      },
    });

    const newOrder = orderRes.data.order;

    if (combinedNotes) {
      try {
        await api.request(`/orders/${newOrder.id}/status`, {
          method: "PATCH",
          body: {
            status: "measuring",
            note: combinedNotes,
          },
        });
      } catch {}
    }

    for (const [partId, val] of Object.entries(wizardForm.measurements)) {
      if (val !== null && val !== undefined && !Number.isNaN(val)) {
        const partObj = selectedGarmentObj.value?.parts?.find(
          (p) => (p.id || p.public_id) === partId
        );
        await api.request(`/orders/${newOrder.id}/measurements`, {
          method: "POST",
          body: {
            garment_part_id: partId,
            value: Number(val),
            unit: partObj?.unit || "inch",
          },
        });
      }
    }

    if (wizardForm.karigar_id) {
      await api.request(`/orders/${newOrder.id}/assign`, {
        method: "POST",
        body: { karigar_id: wizardForm.karigar_id },
      });
    }

    toast.success(`Order #${newOrder.order_number || newOrder.number} booked successfully!`);
    showWizard.value = false;
    await load();
    await select(newOrder);
    printOrderReceipt(newOrder);
  } catch (e: any) {
    wizardError.value = e?.data?.errors?.[0]?.message || "Could not complete order creation.";
  } finally {
    wizardLoading.value = false;
  }
}

async function saveMeasurement(part: Part) {
  const value = measurementValues[part.id];
  if (!selected.value || !value) return;
  await api.request(`/orders/${selected.value.id}/measurements`, {
    method: "POST",
    body: { garment_part_id: part.id, value, unit: part.unit },
  });
  toast.success(`${part.name} saved.`);
  await refreshSelected();
}

async function assignKarigar() {
  if (!selected.value || !assignment.value) return;
  await api.request(`/orders/${selected.value.id}/assign`, {
    method: "POST",
    body: { karigar_id: assignment.value },
  });
  toast.success("Karigar assigned.");
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
  toast.success(`Order moved to ${statusLabelFn(next)}.`);
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
    toast.success(`Payment of ৳ ${(quickPay.amount_minor / 100).toFixed(2)} recorded.`);
    showDueModal.value = false;
    await load();
    if (selected.value?.id === dueOrder.value.id) await refreshSelected();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "Could not record payment.");
  }
}

async function payRecord() {
  if (!selected.value || payment.amount_minor < 1) return;
  await api.request(`/orders/${selected.value.id}/payments`, {
    method: "POST",
    body: payment,
  });
  toast.success("Payment recorded.");
  Object.assign(payment, { amount_minor: 0, method: "cash", reference: "" });
  await refreshSelected();
  await load();
}

function printOrderReceipt(order: Order) {
  printData.value = {
    type: "order_receipt",
    shopName: "দর্জিদোকান",
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
    shopName: "দর্জিদোকান",
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
  toast.info("Order archived.");
  await load();
}

async function restoreOrder(order: Order) {
  await api.request(`/orders/${order.id}/restore`, { method: "POST" });
  toast.success("Order restored.");
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
        <span class="eyebrow">HIGH-VOLUME PRODUCTION SYSTEM</span>
        <h1>Orders Pipeline (অর্ডার ম্যানেজমেন্ট)</h1>
        <p class="subtitle">Search, filter, assign craftsmen, and manage tens of thousands of bespoke tailoring orders in real time.</p>
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
            <span>Kanban Board</span>
          </button>
          <button
            class="view-pill-btn"
            :class="{ active: viewMode === 'list' && !archived }"
            @click="viewMode = 'list'; archived = false; page = 1; load()"
          >
            <NavIcon name="List" />
            <span>Table & Detail</span>
          </button>
          <button
            class="view-pill-btn"
            :class="{ active: archived }"
            @click="archived = true; selected = null; page = 1; load()"
          >
            <NavIcon name="Settings" />
            <span>Archive</span>
          </button>
        </div>

        <button class="primary" @click="openWizard">
          <NavIcon name="Plus" />
          <span>New Order (নতুন অর্ডার)</span>
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
          <small>Total Orders In Database</small>
          <strong>{{ (pipelineCounts.total || totalOrders).toLocaleString() }} Orders</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': statusFilter === 'in_progress' }"
        @click="statusFilter = statusFilter === 'in_progress' ? '' : 'in_progress'; dueOnlyFilter = false; page = 1; load()"
      >
        <span class="metric-icon metric-icon--blue">🪡</span>
        <div class="metric-info">
          <small>In Stitching</small>
          <strong>{{ (pipelineCounts.in_progress || 0).toLocaleString() }} In Sewing</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': statusFilter === 'ready' }"
        @click="statusFilter = statusFilter === 'ready' ? '' : 'ready'; dueOnlyFilter = false; page = 1; load()"
      >
        <span class="metric-icon metric-icon--emerald">✨</span>
        <div class="metric-info">
          <small>Ready for Pickup</small>
          <strong>{{ (pipelineCounts.ready || 0).toLocaleString() }} Completed</strong>
        </div>
      </div>

      <div
        class="metric-pill"
        :class="{ 'metric-pill--active': dueOnlyFilter }"
        @click="dueOnlyFilter = !dueOnlyFilter; statusFilter = ''; page = 1; load()"
      >
        <span class="metric-icon metric-icon--coral">💰</span>
        <div class="metric-info">
          <small>Uncollected Dues ({{ pipelineCounts.due_count || 0 }})</small>
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
          placeholder="Search by Order #, Customer Name, Mobile Number, or Barcode..."
          inputmode="search"
          @keyup.enter="page = 1; load()"
        />
        <button v-if="query" class="clear-search-btn" @click="query = ''; page = 1; load()">&times;</button>
      </div>

      <!-- Status Filter Dropdown -->
      <select v-if="!archived" v-model="statusFilter" @change="page = 1; load()">
        <option value="">All Status Pipeline ({{ pipelineCounts.total || totalOrders }})</option>
        <option v-for="col in kanbanColumns" :key="col.key" :value="col.key">
          {{ col.label }} ({{ (pipelineCounts as any)[col.key] || 0 }})
        </option>
      </select>

      <!-- Date Presets -->
      <select v-if="!archived" v-model="dateFilter" @change="page = 1; load()">
        <option value="">All Delivery Dates</option>
        <option value="today">Promised Today (আজকের ডেলিভারি)</option>
        <option value="overdue">Overdue / Delayed (দেরি হওয়া অর্ডার)</option>
        <option value="this_week">This Week (চলতি সপ্তাহ)</option>
      </select>

      <!-- Sort Options -->
      <select v-model="sortBy" @change="load()">
        <option value="id">Sort: Order ID / Newest</option>
        <option value="promised_at">Sort: Delivery Date</option>
        <option value="total_minor">Sort: Total Amount</option>
        <option value="due_minor">Sort: Highest Due</option>
      </select>

      <button class="secondary search-submit-btn" @click="page = 1; load()">
        <span>Search</span>
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
                <strong>{{ col.label }}</strong>
                <small>{{ col.sub }}</small>
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
                    title="Collect Due Payment"
                    @click="openDueModal(order)"
                  >
                    Due ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
                  </button>

                  <button
                    v-if="col.key !== 'delivered'"
                    class="mini-btn mini-btn--forward"
                    title="Advance to next workflow step"
                    @click="advanceStatus(order)"
                  >
                    ➔ Next
                  </button>

                  <button
                    class="mini-btn"
                    title="Print Receipt Slip"
                    @click="printOrderReceipt(order)"
                  >
                    <NavIcon name="Printer" />
                  </button>
                </div>
              </div>
            </div>

            <div v-if="!ordersInStatus(col.key).length" class="empty-kanban-slot">
              <span>No orders in this phase.</span>
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
            <span class="deck-eyebrow">ORDER RECORDS</span>
            <h2 class="deck-title">{{ archived ? 'Archived Orders' : 'Active Order Queue' }}</h2>
          </div>
          <span class="badge-count">{{ totalOrders.toLocaleString() }}</span>
        </div>

        <p v-if="loading" class="loading-state">Loading orders…</p>
        <p v-else-if="!items.length" class="empty-state">No matching orders found.</p>

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
                Due ৳ {{ ((order.total_minor - order.paid_minor) / 100).toFixed(0) }}
              </span>
            </div>

            <div class="record-actions-col" @click.stop>
              <button v-if="!archived" class="mini-btn" title="Print Slip" @click="printOrderReceipt(order)">
                <NavIcon name="Printer" />
              </button>
              <button
                v-if="!archived && order.total_minor > order.paid_minor"
                class="mini-btn mini-btn--due"
                title="Pay Due"
                @click="openDueModal(order)"
              >
                Pay
              </button>
            </div>
          </div>
        </div>

        <!-- High-Volume Server-Side Pagination Bar -->
        <div v-if="totalOrders > 0" class="table-pagination-footer">
          <div class="pagination-summary">
            <span><strong>{{ fromItem }}–{{ toItem }}</strong> of <strong>{{ totalOrders.toLocaleString() }}</strong></span>
          </div>

          <div class="pagination-actions">
            <select v-model.number="perPage" class="pagination-perpage" @change="page = 1; load()">
              <option :value="25">25 / page</option>
              <option :value="50">50 / page</option>
              <option :value="100">100 / page</option>
              <option :value="200">200 / page</option>
            </select>

            <div class="pagination-nav-btns">
              <button class="page-nav-btn" :disabled="page <= 1" title="First Page" @click="goToPage(1)">«</button>
              <button class="page-nav-btn" :disabled="page <= 1" title="Previous Page" @click="goToPage(page - 1)">‹</button>
              <span class="page-current-indicator">{{ page }} / {{ lastPage }}</span>
              <button class="page-nav-btn" :disabled="page >= lastPage" title="Next Page" @click="goToPage(page + 1)">›</button>
              <button class="page-nav-btn" :disabled="page >= lastPage" title="Last Page" @click="goToPage(lastPage)">»</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Selected Order Detail Panel -->
      <section class="panel order-detail">
        <template v-if="selected">
          <div class="order-detail-header">
            <div>
              <span class="eyebrow">ORDER INSPECTION</span>
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
                title="Send WhatsApp Notification"
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
            <h3 class="section-title">Measurements (মাপসমূহ)</h3>
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
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="23"/>
                        <line x1="8" y1="23" x2="16" y2="23"/>
                      </svg>
                    </button>
                  </div>
                </label>
                <button class="mini-btn">Save</button>
              </form>
            </div>
          </div>

          <!-- Karigar Assignment -->
          <div class="assignment-section">
            <h3 class="section-title">Craftsman Assignment (কারিগর বরাদ্দ)</h3>
            <form class="assignment-form" @submit.prevent="assignKarigar">
              <select v-model="assignment">
                <option value="">Unassigned (কোনো কারিগর নির্ধারণ করা নেই)</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">
                  {{ e.name }}
                </option>
              </select>
              <button class="primary">Assign Karigar</button>
            </form>
          </div>

          <!-- Payments & Dues Summary -->
          <div class="payment-card">
            <div class="payment-card-row">
              <div class="payment-cell">
                <small>Total Making Cost</small>
                <strong>৳ {{ (selected.total_minor / 100).toFixed(2) }}</strong>
              </div>
              <div class="payment-cell">
                <small>Advance Received</small>
                <strong style="color:var(--status-ready)">৳ {{ (selected.paid_minor / 100).toFixed(2) }}</strong>
              </div>
              <div class="payment-cell">
                <small>Balance Due</small>
                <strong style="color:var(--status-cancelled)">৳ {{ ((selected.total_minor - selected.paid_minor) / 100).toFixed(2) }}</strong>
              </div>
            </div>

            <form v-if="selected.total_minor > selected.paid_minor" class="payment-inline-form" @submit.prevent="payRecord">
              <label>Collect Due (৳):
                <input
                  v-model.number="paymentTaka"
                  type="number"
                  min="0.01"
                  step="0.01"
                  :max="(selected.total_minor - selected.paid_minor) / 100"
                  placeholder="Amount"
                />
              </label>
              <select v-model="payment.method">
                <option value="cash">Cash (নগদ)</option>
                <option value="mobile_banking">bKash / Nagad</option>
                <option value="card">Card</option>
                <option value="bank_transfer">Bank</option>
              </select>
              <button class="primary">Record Payment</button>
            </form>
          </div>

          <!-- Action Buttons Deck -->
          <div class="order-actions-bar">
            <button v-if="nextStatus" class="primary advance-action-btn" @click="advanceStatus()">
              <span>Advance to {{ nextStatusLabel }}</span>
              <span class="btn-arrow">➔</span>
            </button>
            <button class="secondary" @click="printOrderReceipt(selected)">
              <NavIcon name="Printer" /> Print Receipt
            </button>
            <button class="secondary" @click="printJobTicket(selected)">
              <NavIcon name="Scissors" /> Job Card
            </button>
            <button class="secondary" @click="printBarcode(selected)">
              <NavIcon name="Barcode" /> Barcode
            </button>
            <button v-if="!['ready', 'delivered'].includes(selected.status)" class="danger-btn" @click="archiveSelected">
              Archive
            </button>
          </div>

          <!-- Order Timeline History -->
          <div class="timeline-box">
            <h4 class="timeline-title">Order Lifecycle Trail</h4>
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
          <strong>No Order Selected</strong>
          <p>Click on any order card from the Kanban board or list to view live measurements, assign karigars, and manage payments.</p>
        </div>
      </section>
    </div>

    <!-- GUIDED NEW ORDER WIZARD MODAL -->
    <div v-if="showWizard" class="modal-backdrop" @click.self="showWizard = false">
      <div class="modal-window order-wizard-window">
        <div class="modal-header">
          <div>
            <span class="deck-eyebrow">ORDER CREATION</span>
            <h2>New Bespoke Order (নতুন অর্ডার তৈরি)</h2>
          </div>
          <button class="modal-close-btn" aria-label="Close modal" @click="showWizard = false">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>

        <div class="modal-steps-bar">
          <div
            v-for="step in [
              { num: 1, label: 'Customer Info', bn: 'গ্রাহকের তথ্য' },
              { num: 2, label: 'Garment & Date', bn: 'পোশাক ও তারিখ' },
              { num: 3, label: 'Measurements', bn: 'মাপ গ্রহণ' },
              { num: 4, label: 'Advance & Price', bn: 'মজুরি ও অগ্রিম' },
            ]"
            :key="step.num"
            class="step-item"
            :class="{
              'step-item--active': wizardStep === step.num,
              'step-item--completed': wizardStep > step.num,
              'step-item--clickable': maxStepReached >= step.num || wizardStep > step.num,
            }"
            @click="goToStep(step.num)"
          >
            <span class="step-num">
              <template v-if="wizardStep > step.num">✓</template>
              <template v-else>{{ step.num }}</template>
            </span>
            <div class="step-texts">
              <span class="step-lbl-en">{{ step.label }}</span>
              <span class="step-lbl-bn">{{ step.bn }}</span>
            </div>
          </div>
        </div>

        <div class="modal-body">
          <p v-if="wizardError" class="error">{{ wizardError }}</p>

          <!-- Step 1: Customer Information -->
          <div v-if="wizardStep === 1" class="form wizard-form">
            <label>Customer Mobile Number (মোবাইল নম্বর) *
              <input
                v-model="wizardForm.customer_mobile"
                placeholder="01XXXXXXXXX"
                autocomplete="off"
                required
                @keydown.enter.prevent="advanceWizardStep"
              />
            </label>

            <!-- Auto-suggested existing customers -->
            <div v-if="filteredCustomerSuggestions.length" class="suggestions-box">
              <small>Matching existing profiles (ক্লিক করে নির্বাচন করুন):</small>
              <div
                v-for="c in filteredCustomerSuggestions"
                :key="c.id || c.public_id"
                class="suggestion-pill"
                @click="selectExistingCustomer(c)"
              >
                <strong>{{ c.name }}</strong> <span>({{ c.mobile_number }})</span>
              </div>
            </div>

            <label>Customer Full Name (গ্রাহকের নাম) *
              <input
                v-model="wizardForm.customer_name"
                placeholder="e.g. Al-Amin Chowdhury"
                required
                @keydown.enter.prevent="advanceWizardStep"
              />
            </label>
          </div>

          <!-- Step 2: Garment & Delivery Promise -->
          <div v-else-if="wizardStep === 2" class="form wizard-form">
            <label>Garment Style (পোশাকের ধরন) *
              <select v-model="wizardForm.garment_id" required @change="onGarmentSelectChange">
                <optgroup v-if="groupedGarments.gents?.length" label="👔 পুরুষ (Gents)">
                  <option v-for="g in groupedGarments.gents" :key="g.id || g.public_id" :value="g.id || g.public_id">
                    {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                  </option>
                </optgroup>
                <optgroup v-if="groupedGarments.ladies?.length" label="🥻 মহিলা (Ladies)">
                  <option v-for="g in groupedGarments.ladies" :key="g.id || g.public_id" :value="g.id || g.public_id">
                    {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                  </option>
                </optgroup>
                <optgroup v-if="groupedGarments.kids?.length" label="🧒 বাচ্চা (Kids)">
                  <option v-for="g in groupedGarments.kids" :key="g.id || g.public_id" :value="g.id || g.public_id">
                    {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                  </option>
                </optgroup>
                <optgroup v-if="groupedGarments.unisex?.length" label="👥 উভলিঙ্গ (Unisex)">
                  <option v-for="g in groupedGarments.unisex" :key="g.id || g.public_id" :value="g.id || g.public_id">
                    {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                  </option>
                </optgroup>
              </select>
            </label>

            <!-- Garment Live Specs Preview Card -->
            <div v-if="selectedGarmentObj" class="wizard-garment-preview-box">
              <div class="preview-box-head">
                <span class="preview-group-tag">{{ selectedGarmentObj.group_name || 'Standard Group' }}</span>
                <span class="preview-rate-tag">মজুরি: ৳ {{ ((selectedGarmentObj.base_making_minor || 0) / 100).toLocaleString() }}</span>
              </div>
              <div class="preview-parts-list">
                <small>📏 পরিমাপ পয়েন্ট ({{ selectedGarmentObj.parts?.length || 0 }}টি):</small>
                <div class="preview-chips">
                  <span v-for="p in selectedGarmentObj.parts || []" :key="p.id || p.public_id" class="preview-chip" :class="{ 'is-req': p.required !== false }">
                    {{ p.name }} <span class="unit-sub">({{ p.unit || 'in' }})</span><strong v-if="p.required !== false" class="req-star">*</strong>
                  </span>
                </div>
              </div>
            </div>

            <div class="delivery-presets">
              <small>Quick Delivery Presets (দ্রুত দিন নির্বাচন):</small>
              <div class="preset-buttons">
                <button type="button" class="mini-btn" @click="setDeliveryDays(3)">+3 Days</button>
                <button type="button" class="mini-btn" @click="setDeliveryDays(7)">+7 Days</button>
                <button type="button" class="mini-btn" @click="setDeliveryDays(14)">+14 Days</button>
                <button
                  type="button"
                  class="mini-btn"
                  :class="{ 'mini-btn--urgent': isUrgent }"
                  @click="isUrgent = !isUrgent"
                >
                  ★ {{ isUrgent ? 'Urgent Express' : 'Mark Urgent' }}
                </button>
              </div>
            </div>

            <label>Delivery Promised Date & Time *
              <input v-model="wizardForm.promised_at" type="datetime-local" required />
            </label>

            <label>Assign Karigar / Master Cutter (ঐচ্ছিক)
              <select v-model="wizardForm.karigar_id">
                <option value="">Unassigned (পরে অ্যাসাইন করুন)</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">
                  {{ e.name }}
                </option>
              </select>
            </label>
          </div>

          <!-- Step 3: Measurements & Cutting Notes with Voice Input -->
          <div v-else-if="wizardStep === 3" class="form wizard-form">
            <p v-if="!selectedGarmentObj?.parts?.length" class="empty">
              No measurement parts configured for this garment type.
            </p>
            <template v-else>
              <!-- Hands-Free Voice Assistant Dictation Banner -->
              <div class="voice-assistant-panel" :class="{ 'is-listening': isContinuousVoiceActive }">
                <div class="voice-panel-left">
                  <div class="voice-mic-icon-wrapper" :class="{ 'is-pulse': isContinuousVoiceActive }">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                      <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                      <line x1="12" y1="19" x2="12" y2="23"/>
                      <line x1="8" y1="23" x2="16" y2="23"/>
                    </svg>
                  </div>
                  <div class="voice-panel-texts">
                    <div class="voice-title-row">
                      <strong>🎙️ স্মার্ট ভয়েস সহকারী (Voice Assistant)</strong>
                      <span v-if="isContinuousVoiceActive" class="badge-live-rec">● রেকর্ডিং চলছে</span>

                      <!-- Language Switcher Pill Selector -->
                      <div class="voice-lang-selector">
                        <button
                          v-for="(pack, code) in supportedVoiceLanguages"
                          :key="code"
                          type="button"
                          class="lang-pill-btn"
                          :class="{ 'is-active-lang': selectedVoiceLang === code }"
                          :title="`${pack.name} (${pack.nativeName})`"
                          @click="setVoiceLanguage(code)"
                        >
                          {{ pack.flag }} {{ pack.nativeName }}
                        </button>
                      </div>
                    </div>
                    <div v-if="voiceConfirmationMessage" class="voice-confirmed-banner">
                      {{ voiceConfirmationMessage }}
                    </div>
                    <span v-else-if="isContinuousVoiceActive && voiceLiveTranscript" class="live-transcript-txt">
                      শুনছি: <em>"{{ voiceLiveTranscript }}"</em>
                    </span>
                    <span v-else-if="isContinuousVoiceActive" class="live-status-txt">
                      {{ selectedVoiceLang === 'ar-SA' ? 'تحدث الآن (مثال: "الطول ٣٢"، "الصدر ٣٨.٥")...' : selectedVoiceLang === 'en-US' ? 'Speak now (e.g. "Length 32", "Chest 38.5")...' : 'মুখে বলুন (যেমন: "লম্বা ৩২", "বুক ৩৮.৫", "হাতা ২৪")...' }}
                    </span>
                    <span v-else class="voice-help-txt">
                      {{ selectedVoiceLang === 'ar-SA' ? 'اضغط لتشغيل الإملاء الصوتي باللغة العربية' : selectedVoiceLang === 'en-US' ? 'Click to start voice dictation in English' : 'টেপ দিয়ে মাপ নেওয়ার সময় হ্যান্ডস-ফ্রি চালু করুন অথবা ফিল্ডের মাইকে ক্লিক করুন।' }}
                    </span>
                  </div>
                </div>
                <button
                  type="button"
                  class="btn-voice-toggle"
                  :class="{ 'is-active': isContinuousVoiceActive }"
                  @click="toggleContinuousVoice"
                >
                  {{ isContinuousVoiceActive ? '⏹ ভয়েস বন্ধ করুন' : '🎙️ হ্যান্ডস-ফ্রি মোড' }}
                </button>
              </div>

              <!-- Measurement Inputs Grid -->
              <div class="measurement-grid">
                <label v-for="part in selectedGarmentObj.parts" :key="part.id || part.public_id" class="measurement-cell-label">
                  <div class="lbl-top">
                    <span>{{ part.name }}</span>
                    <span v-if="part.required !== false" class="badge-req" title="আবশ্যক মাপ">* আবশ্যক</span>
                  </div>
                  <div class="input-with-unit-voice">
                    <input
                      v-model.number="wizardForm.measurements[part.id]"
                      type="number"
                      step="0.01"
                      :placeholder="part.unit || 'inch'"
                      :class="{ 'flash-success': recentlyUpdatedPartId === part.id }"
                    />
                    <span class="unit-badge">{{ part.unit || 'in' }}</span>
                    <button
                      type="button"
                      class="btn-field-mic"
                      :class="{ 'is-field-listening': isVoiceListening && voiceActivePartId === part.id }"
                      :title="`${part.name}-এর মাপ মুখে বলুন (ভয়েস ইনপুট)`"
                      @click="triggerFieldVoice(part.id)"
                    >
                      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                        <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                        <line x1="12" y1="19" x2="12" y2="23"/>
                        <line x1="8" y1="23" x2="16" y2="23"/>
                      </svg>
                    </button>
                  </div>
                </label>
              </div>

              <!-- Design & Style Options Selector -->
              <div v-if="selectedGarmentObj.design_options?.length" class="design-options-picker-box">
                <div class="design-box-title">
                  <strong>🎨 পোশাকের ডিজাইন ও স্টাইল নির্বাচন (Style & Customization)</strong>
                  <small>পছন্দমতো কলার, পকেট ও বিশেষ সেলাই স্টাইল নির্বাচন করুন (অতিরিক্ত চার্জ স্বয়ংক্রিয়ভাবে যোগ হবে)</small>
                </div>

                <div class="design-options-grid">
                  <div
                    v-for="opt in selectedGarmentObj.design_options"
                    :key="opt.name"
                    class="design-opt-field"
                  >
                    <label class="opt-label">{{ opt.name }}</label>

                    <!-- Checkbox Multi-Select -->
                    <div v-if="opt.type === 'checkbox'" class="chk-options-cloud">
                      <label
                        v-for="val in opt.values"
                        :key="val.name"
                        class="chk-card-opt"
                        :class="{ 'is-checked': (wizardForm.selectedDesignOptions[opt.name] as string[])?.includes(val.name) }"
                      >
                        <input
                          type="checkbox"
                          :checked="(wizardForm.selectedDesignOptions[opt.name] as string[])?.includes(val.name)"
                          @change="updateDesignOptionSelection(opt.name, val.name, true)"
                        />
                        <span class="chk-val-name">{{ val.name }}</span>
                        <span v-if="val.extra_price_minor" class="chk-extra-badge">
                          +৳{{ Math.round(val.extra_price_minor / 100) }}
                        </span>
                      </label>
                    </div>

                    <!-- Radio / Select Single Option -->
                    <div v-else class="design-opt-pills">
                      <button
                        v-for="val in opt.values"
                        :key="val.name"
                        type="button"
                        class="opt-pill-choice"
                        :class="{ 'is-active-choice': wizardForm.selectedDesignOptions[opt.name] === val.name }"
                        @click="updateDesignOptionSelection(opt.name, val.name, false)"
                      >
                        {{ val.name }}
                        <span v-if="val.extra_price_minor" class="pill-extra-tag">
                          +৳{{ Math.round(val.extra_price_minor / 100) }}
                        </span>
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </template>

            <label>Cutting & Stitching Instructions (বিশেষ নির্দেশনা)
              <textarea v-model="wizardForm.notes" placeholder="যেমন: শেরওয়ানি কলার, বুক পকেট, ডাবল স্টিচিং..."></textarea>
            </label>
          </div>

          <!-- Step 4: Pricing, Advance & Payment with Review Summary -->
          <div v-else class="form wizard-form">
            <!-- Compact Order Review Summary Card before Booking -->
            <div class="order-summary-review-card">
              <div class="summary-review-head">
                <span class="review-title">📋 অর্ডার চূড়ান্ত বিবরণী (Order Summary):</span>
                <span class="review-status-badge">যাচাইকৃত</span>
              </div>
              <div class="summary-grid-3">
                <div class="summary-meta-item">
                  <small>গ্রাহকের নাম ও ফোন</small>
                  <strong>{{ wizardForm.customer_name || 'নতুন গ্রাহক' }}</strong>
                  <span class="sub-val">{{ wizardForm.customer_mobile }}</span>
                </div>
                <div class="summary-meta-item">
                  <small>পোশাক ও গ্রুপ</small>
                  <strong>{{ selectedGarmentObj?.name }}</strong>
                  <span class="sub-val">{{ selectedGarmentObj?.group_name || 'Standard' }}</span>
                </div>
                <div class="summary-meta-item">
                  <small>ডেলিভারি তারিখ</small>
                  <strong v-if="wizardForm.promised_at">{{ new Date(wizardForm.promised_at).toLocaleDateString() }}</strong>
                  <span v-if="isUrgent" class="badge-urgent-tag">★ এক্সপ্রেস</span>
                </div>
              </div>

              <!-- Selected Design Options summary chip -->
              <div v-if="Object.keys(wizardForm.selectedDesignOptions).some(k => Boolean(wizardForm.selectedDesignOptions[k]))" class="summary-design-styles-chip">
                🎨 <strong>নির্বাচিত স্টাইল:</strong>
                <template v-for="(val, optName) in wizardForm.selectedDesignOptions" :key="optName">
                  <span v-if="Array.isArray(val) ? val.length : val" class="style-crumb">
                    {{ optName }}: {{ Array.isArray(val) ? val.join(', ') : val }}
                  </span>
                </template>
              </div>
              <div class="summary-foot-row">
                <span>📏 মাপ সংরক্ষণ: <strong>{{ recordedMeasurementsCount }}টি পয়েন্ট রেকর্ড হয়েছে</strong></span>
                <span v-if="wizardForm.karigar_id">✂️ কাটিং মাস্টার/কারিগর: <strong>{{ employees.find(e => e.id === wizardForm.karigar_id)?.name }}</strong></span>
              </div>
            </div>

            <label>Total Making Charge (মোট মজুরি ৳) *
              <input v-model.number="wizardTotalTaka" type="number" min="0" step="10" required />
            </label>

            <label>Advance Payment Received (অগ্রিম গ্রহণ ৳) *
              <input v-model.number="wizardAdvanceTaka" type="number" min="0" :max="wizardTotalTaka" step="10" required />
            </label>

            <!-- Quick Advance Presets -->
            <div class="advance-shortcuts">
              <small>দ্রুত অগ্রিম নির্বাচন:</small>
              <div class="preset-buttons">
                <button type="button" class="mini-btn" @click="wizardForm.paid_minor = wizardForm.total_minor">পূর্ণ পরিশোধ (100%)</button>
                <button type="button" class="mini-btn" @click="wizardForm.paid_minor = Math.round(wizardForm.total_minor * 0.5)">৫০% অগ্রিম</button>
                <button type="button" class="mini-btn" @click="wizardForm.paid_minor = Math.round(wizardForm.total_minor * 0.3)">৩০% অগ্রিম</button>
                <button type="button" class="mini-btn" @click="wizardForm.paid_minor = 0">বকেয়া (Zero)</button>
              </div>
            </div>

            <div class="summary-due-box">
              <span>Remaining Balance Due (অবশিষ্ট বকেয়া):</span>
              <strong style="font-size:1.25rem;color:var(--status-cancelled)">৳ {{ wizardDueTaka.toFixed(2) }}</strong>
            </div>

            <label>Advance Payment Method
              <select v-model="wizardForm.payment_method">
                <option value="cash">Cash (নগদ)</option>
                <option value="mobile_banking">bKash / Nagad</option>
                <option value="card">Card</option>
              </select>
            </label>
          </div>
        </div>

        <div class="modal-footer">
          <button v-if="wizardStep > 1" type="button" class="secondary" @click="goToStep(wizardStep - 1)">
            ← Back
          </button>
          <div v-else></div>

          <button v-if="wizardStep < 4" type="button" class="primary" @click="advanceWizardStep">
            Next Step →
          </button>
          <button v-else type="button" class="primary" :disabled="wizardLoading" @click="finishWizard">
            {{ wizardLoading ? 'Creating Order…' : 'Complete & Print Order Receipt ✓' }}
          </button>
        </div>
      </div>
    </div>

    <!-- QUICK DUE COLLECTION MODAL -->
    <div v-if="showDueModal && dueOrder" class="modal-backdrop" @click.self="showDueModal = false">
      <div class="modal-window" style="max-width:28rem">
        <div class="modal-header">
          <div>
            <span class="deck-eyebrow">PAYMENT RECORDING</span>
            <h2>Collect Due Payment (বকেয়া পরিশোধ)</h2>
          </div>
          <button class="modal-close-btn" aria-label="Close modal" @click="showDueModal = false">
            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <line x1="18" y1="6" x2="6" y2="18" />
              <line x1="6" y1="6" x2="18" y2="18" />
            </svg>
          </button>
        </div>
        <form class="modal-body" @submit.prevent="recordQuickPayment">
          <div class="quick-due-meta">
            <p><strong>Customer:</strong> {{ dueOrder.customer.name }}</p>
            <p><strong>Order:</strong> #{{ dueOrder.order_number || dueOrder.number }}</p>
            <p><strong>Total Due:</strong> <span style="color:var(--status-cancelled);font-weight:700">৳ {{ ((dueOrder.total_minor - dueOrder.paid_minor) / 100).toFixed(2) }}</span></p>
          </div>

          <label>Payment Received (৳)
            <input
              v-model.number="quickPayTaka"
              type="number"
              min="1"
              :max="(dueOrder.total_minor - dueOrder.paid_minor) / 100"
              step="1"
              required
            />
          </label>
          <label>Payment Method
            <select v-model="quickPay.method">
              <option value="cash">Cash (নগদ)</option>
              <option value="mobile_banking">bKash / Nagad</option>
              <option value="card">Card</option>
            </select>
          </label>
          <button class="primary" style="margin-top:0.75rem">
            Record Payment & Issue Receipt
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
  .orders-metrics-strip {
    grid-template-columns: 1fr;
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
   Kanban Board
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

.design-options-picker-box {
  background: rgba(10, 61, 49, 0.03);
  border: 1px solid rgba(10, 61, 49, 0.12);
  border-radius: 10px;
  padding: 0.85rem;
  margin-bottom: 1rem;
}

.design-box-title strong {
  display: block;
  font-size: 0.85rem;
  color: var(--primary);
}

.design-box-title small {
  font-size: 0.72rem;
  color: var(--muted);
}

.design-options-grid {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
  margin-top: 0.65rem;
}

.design-opt-field .opt-label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 0.35rem;
}

.design-opt-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.opt-pill-choice {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.3rem 0.65rem;
  font-size: 0.78rem;
  font-weight: 600;
  color: var(--ink);
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.3rem;
  transition: all 0.15s ease;
}

.opt-pill-choice:hover {
  border-color: var(--primary);
}

.opt-pill-choice.is-active-choice {
  background: var(--primary);
  border-color: var(--primary);
  color: #ffffff;
  font-weight: 700;
}

.pill-extra-tag {
  background: rgba(245, 158, 11, 0.2);
  color: #b45309;
  font-size: 0.68rem;
  padding: 1px 4px;
  border-radius: 4px;
}

.is-active-choice .pill-extra-tag {
  background: rgba(255, 255, 255, 0.25);
  color: #ffffff;
}

.chk-options-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
}

.chk-card-opt {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.65rem;
  font-size: 0.78rem;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.chk-card-opt.is-checked {
  background: rgba(10, 61, 49, 0.08);
  border-color: var(--primary);
  color: var(--primary);
  font-weight: 700;
}

.chk-extra-badge {
  font-size: 0.7rem;
  font-weight: 800;
  color: #b45309;
  background: #fef3c7;
  padding: 1px 4px;
  border-radius: 4px;
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
   Wizard Modal
   ============================================================ */
.order-wizard-window {
  max-width: 44rem;
}

.modal-steps-bar {
  display: flex;
  background: var(--paper);
  border-bottom: 1px solid var(--line);
}

.step-item {
  flex: 1;
  padding: 0.75rem 0.5rem;
  text-align: center;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--muted);
  border-bottom: 2px solid transparent;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  transition: all 0.18s ease;
  user-select: none;
}

.step-item--clickable {
  cursor: pointer;
}

.step-item--clickable:hover {
  background: rgba(10, 61, 49, 0.04);
  color: var(--ink);
}

.step-item--completed {
  color: var(--ink);
}

.step-item--completed .step-num {
  background: #059669;
  color: #ffffff;
  font-weight: 800;
}

.step-item--active {
  color: var(--primary);
  border-bottom-color: var(--primary);
  background: var(--surface);
  font-weight: 700;
}

.step-item--active .step-num {
  background: var(--primary);
  color: #ffffff;
  box-shadow: 0 0 0 3px var(--primary-soft);
}

.step-num {
  width: 1.45rem;
  height: 1.45rem;
  border-radius: 50%;
  background: var(--line);
  color: var(--muted);
  display: grid;
  place-items: center;
  font-size: 0.725rem;
  font-weight: 700;
  transition: all 0.18s ease;
  flex-shrink: 0;
}

.step-texts {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.15;
}

.step-lbl-en {
  font-size: 0.78rem;
  font-weight: 700;
}

.step-lbl-bn {
  font-size: 0.68rem;
  color: var(--muted);
  font-weight: 500;
}

.step-item--active .step-lbl-bn {
  color: var(--primary);
}

/* ============================================================
   Order Final Review Summary Card (Step 4)
   ============================================================ */
.order-summary-review-card {
  background: var(--paper);
  border: 1.5px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.85rem 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.65rem;
  margin-bottom: 0.4rem;
}

.summary-review-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px dashed var(--line);
  padding-bottom: 0.45rem;
}

.review-title {
  font-size: 0.825rem;
  font-weight: 700;
  color: var(--ink);
}

.review-status-badge {
  font-size: 0.7rem;
  font-weight: 700;
  background: #dcfce7;
  color: #15803d;
  padding: 0.1rem 0.45rem;
  border-radius: 4px;
}

.summary-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 0.6rem;
}

.summary-meta-item {
  display: flex;
  flex-direction: column;
  gap: 0.1rem;
}

.summary-meta-item small {
  font-size: 0.7rem;
  color: var(--muted);
  font-weight: 600;
}

.summary-meta-item strong {
  font-size: 0.85rem;
  color: var(--ink);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.summary-meta-item .sub-val {
  font-size: 0.725rem;
  color: var(--muted);
}

.badge-urgent-tag {
  font-size: 0.68rem;
  font-weight: 700;
  color: #dc2626;
  background: #fee2e2;
  padding: 0.05rem 0.35rem;
  border-radius: 3px;
  display: inline-block;
  margin-top: 0.15rem;
}

.summary-foot-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
  font-size: 0.75rem;
  color: var(--muted);
  border-top: 1px dashed var(--line);
  padding-top: 0.45rem;
}

.summary-foot-row strong {
  color: var(--ink);
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

/* ============================================================
   Garment Specs Preview & Measurement Chips in Wizard
   ============================================================ */
.wizard-garment-preview-box {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.75rem 0.95rem;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
  margin-top: 0.25rem;
}

.preview-box-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.preview-group-tag {
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted);
}

.preview-rate-tag {
  font-size: 0.85rem;
  font-weight: 800;
  color: var(--primary);
  background: var(--primary-soft);
  padding: 0.15rem 0.5rem;
  border-radius: 4px;
}

.preview-parts-list small {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
  display: block;
  margin-bottom: 0.35rem;
}

.preview-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.preview-chip {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 0.2rem 0.45rem;
  font-size: 0.75rem;
  color: var(--ink);
  display: inline-flex;
  align-items: center;
  gap: 0.2rem;
}

.preview-chip .unit-sub {
  font-size: 0.7rem;
  color: var(--muted);
}

.preview-chip.is-req {
  border-color: #fca5a5;
  background: #fff5f5;
}

.preview-chip .req-star {
  color: #dc2626;
  font-weight: 800;
}

.measurement-cell-label {
  display: flex;
  flex-direction: column;
  gap: 0.3rem;
}

.measurement-cell-label .lbl-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.badge-req {
  font-size: 0.65rem;
  font-weight: 700;
  color: #dc2626;
  background: #fee2e2;
  padding: 0.05rem 0.3rem;
  border-radius: 3px;
}

.advance-shortcuts {
  margin-top: 0.2rem;
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.advance-shortcuts small {
  font-size: 0.75rem;
  color: var(--muted);
  font-weight: 600;
}

/* ============================================================
   Voice Input & Speech Recognition UI
   ============================================================ */
.voice-assistant-panel {
  background: linear-gradient(135deg, var(--paper) 0%, var(--surface) 100%);
  border: 1.5px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.85rem 1rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  transition: all 0.2s ease;
}

.voice-assistant-panel.is-listening {
  border-color: #ef4444;
  background: #fef2f2;
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.12);
}

.voice-panel-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex: 1;
}

.voice-mic-icon-wrapper {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: var(--surface);
  border: 1px solid var(--line);
  color: var(--primary);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.2s ease;
}

.voice-mic-icon-wrapper.is-pulse {
  background: #ef4444;
  border-color: #ef4444;
  color: #ffffff;
  animation: pulse-mic 1.2s infinite;
}

@keyframes pulse-mic {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
  70% { transform: scale(1.06); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

.voice-panel-texts {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}

.voice-title-row {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.voice-title-row strong {
  font-size: 0.85rem;
  color: var(--ink);
}

.voice-lang-selector {
  display: inline-flex;
  align-items: center;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 2px;
  gap: 2px;
  margin-left: auto;
}

.lang-pill-btn {
  background: transparent;
  border: none;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--muted);
  padding: 0.15rem 0.45rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  transition: all 0.15s ease;
  user-select: none;
}

.lang-pill-btn:hover {
  background: var(--paper);
  color: var(--ink);
}

.lang-pill-btn.is-active-lang {
  background: var(--primary);
  color: #ffffff;
  box-shadow: 0 1px 4px rgba(10, 61, 49, 0.2);
}

.badge-live-rec {
  font-size: 0.68rem;
  font-weight: 700;
  color: #dc2626;
  background: #fee2e2;
  padding: 0.1rem 0.4rem;
  border-radius: 4px;
  animation: blink-rec 1s infinite alternate;
}

@keyframes blink-rec {
  from { opacity: 0.6; }
  to { opacity: 1; }
}

.live-transcript-txt {
  font-size: 0.8rem;
  color: #b91c1c;
  font-weight: 600;
}

.voice-confirmed-banner {
  background: #dcfce7;
  color: #166534;
  border: 1px solid #86efac;
  font-size: 0.8rem;
  font-weight: 700;
  padding: 0.2rem 0.5rem;
  border-radius: 4px;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  animation: pulse-green 1.2s ease;
}

@keyframes pulse-green {
  0% { transform: scale(0.97); opacity: 0.8; }
  50% { transform: scale(1.02); opacity: 1; }
  100% { transform: scale(1); }
}

.live-status-txt {
  font-size: 0.78rem;
  color: #dc2626;
  font-weight: 500;
}

.voice-help-txt {
  font-size: 0.75rem;
  color: var(--muted);
}

.btn-voice-toggle {
  background: var(--surface);
  border: 1.5px solid var(--line);
  border-radius: var(--radius-sm);
  padding: 0.55rem 0.95rem;
  font-size: 0.825rem;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
  white-space: nowrap;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.btn-voice-toggle:hover {
  background: var(--paper);
  border-color: var(--primary);
  color: var(--primary);
}

.btn-voice-toggle.is-active {
  background: #ef4444;
  border-color: #dc2626;
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Input with integrated unit & voice mic button */
.input-with-unit-voice {
  display: flex;
  align-items: stretch;
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

.input-with-unit-voice input {
  flex: 1;
  border: none;
  background: transparent;
  padding: 0.55rem 0.65rem;
  font-size: 0.925rem;
  font-weight: 600;
  color: var(--ink);
  width: 100%;
}

.input-with-unit-voice input:focus {
  outline: none;
  box-shadow: none;
}

.input-with-unit-voice .unit-badge {
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

.summary-design-styles-chip {
  margin-top: 0.65rem;
  padding-top: 0.65rem;
  border-top: 1px dashed rgba(10, 61, 49, 0.15);
  font-size: 0.78rem;
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.35rem;
  color: var(--ink);
}

.summary-design-styles-chip .style-crumb {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 1px 6px;
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--primary);
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
