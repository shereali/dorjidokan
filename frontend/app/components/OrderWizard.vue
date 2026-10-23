<script setup lang="ts">
import type { Customer, Employee, Garment, Order } from "~/types/orders";

const props = defineProps<{
  modelValue: boolean;
  garments: Garment[];
  employees: Employee[];
}>();

const emit = defineEmits<{
  "update:modelValue": [open: boolean];
  created: [order: Order];
}>();

const api = useTailorsApi();
const toast = useToast();
const { t } = useTailorsI18n();
const garments = toRef(props, "garments");
const employees = toRef(props, "employees");
const showWizard = computed({
  get: () => props.modelValue,
  set: (open: boolean) => emit("update:modelValue", open),
});

// Voice measurement composable integration with multi-language engine
const {
  selectedLang: selectedVoiceLang,
  supportedLanguages: supportedVoiceLanguages,
  isListening: isVoiceListening,
  activePartId: voiceActivePartId,
  liveTranscript: voiceLiveTranscript,
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
      const partDisplayName = pName || targetPart?.name || t("app.order_wizard.measurement_fallback");
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
      const confirmText = pack.formatConfirmation(pName || targetPart?.name || t("app.order_wizard.measurement_fallback"), val, targetPart?.unit || "inch");
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

// Guided Order Booking Wizard State
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

// Customer Previous Order History State & 1-Click Measurement Autofill
const prevOrders = ref<any[]>([]);
const prevOrdersLoading = ref(false);

async function loadCustomerHistory(customerId: string) {
  if (!customerId) {
    prevOrders.value = [];
    return;
  }
  prevOrdersLoading.value = true;
  try {
    const res = await api.request<{ items: any[] }>(`/customers/${customerId}/orders`);
    prevOrders.value = res.data.items || [];
  } catch {
    prevOrders.value = [];
  } finally {
    prevOrdersLoading.value = false;
  }
}

watch(
  () => wizardForm.customer_id,
  (newId) => {
    if (newId) loadCustomerHistory(newId);
    else prevOrders.value = [];
  }
);

function usePreviousOrderMeasurements(order: any) {
  if (!order) return;
  // Match and select garment if available
  if (order.garment_id) {
    const match = garments.value.find((g) => (g.id || g.public_id) === order.garment_id);
    if (match) wizardForm.garment_id = match.id || match.public_id || "";
  } else if (order.garment) {
    const match = garments.value.find((g) => g.name.toLowerCase() === order.garment.toLowerCase());
    if (match) wizardForm.garment_id = match.id || match.public_id || "";
  }

  onGarmentSelectChange();

  // Populate measurements
  if (order.measurements && Array.isArray(order.measurements)) {
    const currentParts = selectedGarmentObj.value?.parts || [];
    for (const m of order.measurements) {
      const targetPart = currentParts.find(
        (p) =>
          (p.id && p.id === m.part_id) ||
          (p.public_id && p.public_id === m.part_id) ||
          (p.slug && m.part_slug && p.slug === m.part_slug) ||
          (p.name && m.part_name && p.name.toLowerCase() === m.part_name.toLowerCase())
      );
      if (targetPart) {
        wizardForm.measurements[targetPart.id] = m.value;
      }
    }
  }

  toast.success(`অর্ডার #${order.order_number || ''}-এর পূর্বের মাপ যুক্ত করা হয়েছে।`);

  // Direct advance to final step (Step 4: Price & Advance) as requested
  maxStepReached.value = 4;
  wizardStep.value = 4;
}

// Fabric Purchase from Shop Inventory State
const fabricSource = ref<"customer" | "shop">("customer");
const selectedFabricId = ref("");
const fabricQuantity = ref<number>(2.5);
const fabricUnitPrice = ref<number>(350);
const availableFabrics = ref<{ public_id: string; name: string; sku: string; unit: string; movements_sum_quantity?: number }[]>([]);

async function loadFabrics() {
  try {
    const res = await api.request<{ items: any[] }>("/inventory");
    availableFabrics.value = res.data.items || [];
    if (availableFabrics.value.length && !selectedFabricId.value) {
      selectedFabricId.value = availableFabrics.value[0].public_id;
    }
  } catch {
    availableFabrics.value = [];
  }
}

const selectedFabricObj = computed(() =>
  availableFabrics.value.find((f) => f.public_id === selectedFabricId.value)
);

const fabricCostTaka = computed(() => {
  if (fabricSource.value !== "shop") return 0;
  return Math.round((fabricQuantity.value || 0) * (fabricUnitPrice.value || 0));
});

// Enter jumps to next measurement box
function focusNextMeasurement(event: KeyboardEvent) {
  const inputs = Array.from(document.querySelectorAll<HTMLInputElement>(".measurement-grid .input-with-unit-voice input"));
  const index = inputs.indexOf(event.target as HTMLInputElement);
  const next = inputs[index + 1];
  if (next) {
    next.focus();
    next.select();
  } else {
    advanceWizardStep();
  }
}

// Existing-customer suggestions
const filteredCustomerSuggestions = ref<Customer[]>([]);
let suggestionTimer: ReturnType<typeof setTimeout> | undefined;
let suggestionTicket = 0;
let pickedMobile = "";

watch(
  () => wizardForm.customer_mobile,
  (value) => {
    if (wizardForm.customer_id && value !== pickedMobile) wizardForm.customer_id = "";
    clearTimeout(suggestionTimer);
    const term = value.trim();
    if (term.length < 2 || !api.can("customers.view", "orders.create")) {
      filteredCustomerSuggestions.value = [];
      return;
    }
    suggestionTimer = setTimeout(async () => {
      const ticket = ++suggestionTicket;
      try {
        const res = await api.request<{ items: Customer[] }>(`/customers?query=${encodeURIComponent(term)}`);
        if (ticket === suggestionTicket) filteredCustomerSuggestions.value = res.data.items.slice(0, 5);
      } catch {
        if (ticket === suggestionTicket) filteredCustomerSuggestions.value = [];
      }
    }, 250);
  }
);

onBeforeUnmount(() => clearTimeout(suggestionTimer));

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
  let total = g ? (g.base_making_minor || 0) : 0;

  if (g?.design_options?.length) {
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
  const fabricMinor = fabricSource.value === "shop" ? Math.round(fabricCostTaka.value * 100) : 0;
  return total + fabricMinor;
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

watch([fabricSource, fabricQuantity, fabricUnitPrice, selectedFabricId], () => {
  const tot = calculateGarmentTotalMinor();
  wizardForm.total_minor = tot;
  wizardForm.paid_minor = Math.round(tot * 0.4);
});

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

const maxStepReached = ref(1);

const recordedMeasurementsCount = computed(() => {
  return Object.values(wizardForm.measurements).filter(
    (v) => v !== null && v !== undefined && !Number.isNaN(v) && v > 0
  ).length;
});

function validateCurrentStep(): boolean {
  wizardError.value = "";
  if (wizardStep.value === 1) {
    if (!wizardForm.customer_id && (!wizardForm.customer_mobile.trim() || !wizardForm.customer_name.trim())) {
      wizardError.value = t('app.order_wizard.for_a_new_customer_enter_both');
      return false;
    }
  } else if (wizardStep.value === 2) {
    if (!garments.value.length) {
      wizardError.value = t('app.order_wizard.garments_loading');
      return false;
    }
    if (!wizardForm.garment_id) {
      wizardError.value = t('app.order_wizard.please_choose_a_garment');
      return false;
    }
    if (!wizardForm.promised_at) {
      wizardError.value = t('app.order_wizard.please_set_the_delivery_date');
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
        t('app.order_wizard.tip_p0_not_measured_yet', { p0: missingReq.map((p) => p.name).join(", ") })
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

function resetWizard() {
  wizardStep.value = 1;
  maxStepReached.value = 1;
  wizardError.value = "";
  isUrgent.value = false;
  fabricSource.value = "customer";
  selectedFabricId.value = availableFabrics.value[0]?.public_id || "";
  fabricQuantity.value = 2.5;
  fabricUnitPrice.value = 350;
  prevOrders.value = [];

  const firstGarment = props.garments[0];
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
  pickedMobile = wizardForm.customer_mobile;
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

    let fabricNote = "";
    if (fabricSource.value === "shop" && selectedFabricObj.value) {
      fabricNote = `[দোকানের কাপড়] ${selectedFabricObj.value.name} (${fabricQuantity.value} ${selectedFabricObj.value.unit || 'গজ'} @ ৳${fabricUnitPrice.value} = ৳${fabricCostTaka.value})`;
    }

    const combinedNotes = [
      selectedStyleSummary.length ? `[${t('app.order_wizard.design_style')}] ${selectedStyleSummary.join(" | ")}` : "",
      fabricNote,
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
        fabric_item_id:
          fabricSource.value === "shop" && selectedFabricObj.value
            ? selectedFabricObj.value.public_id
            : undefined,
        fabric_quantity:
          fabricSource.value === "shop" && selectedFabricObj.value
            ? Number(fabricQuantity.value)
            : undefined,
        fabric_unit_price_minor:
          fabricSource.value === "shop" && selectedFabricObj.value
            ? Math.round(fabricUnitPrice.value * 100)
            : undefined,
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

    toast.success(t('app.order_wizard.order_p0_booked', { p0: newOrder.order_number || newOrder.number || "" }));
    showWizard.value = false;
    emit("created", newOrder);
  } catch (e: any) {
    wizardError.value = apiErrorMessage(e, t('app.order_wizard.could_not_complete_order_creation'));
  } finally {
    wizardLoading.value = false;
  }
}

watch(
  showWizard,
  (open) => {
    if (open) {
      resetWizard();
    } else {
      stopVoiceListening();
      isContinuousVoiceActive.value = false;
    }
  },
  { immediate: true },
);

watch(
  () => props.garments,
  (newGarments) => {
    if (newGarments.length > 0 && !wizardForm.garment_id) {
      const first = newGarments[0]!;
      wizardForm.garment_id = first.public_id || first.id || "";
      onGarmentSelectChange();
    }
  },
  { immediate: true }
);

onMounted(loadFabrics);
</script>

<template>
  <div v-if="showWizard" class="modal-backdrop" @click.self="showWizard = false">
    <div class="modal-window order-wizard-window">
      <div class="modal-header">
        <div>
          <span class="deck-eyebrow">{{ t('app.order_wizard.order_creation') }}</span>
          <h2>{{ t('app.order_wizard.new_bespoke_order') }}</h2>
        </div>
        <button class="modal-close-btn" :aria-label="t('app.common.close')" @click="showWizard = false">
          <NavIcon name="Close" />
        </button>
      </div>

      <div class="modal-steps-bar">
        <div
          v-for="step in [
            { num: 1, label: t('app.order_wizard.customer') },
            { num: 2, label: t('app.order_wizard.garment_date') },
            { num: 3, label: t('app.order_wizard.measurements') },
            { num: 4, label: t('app.order_wizard.price_advance') },
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
            <template v-if="wizardStep > step.num">
              <NavIcon name="Check" style="width:12px;height:12px" />
            </template>
            <template v-else>{{ step.num }}</template>
          </span>
          <div class="step-texts">
            <span class="step-lbl-en">{{ step.label }}</span>
          </div>
        </div>
      </div>

      <div class="modal-body">
        <p v-if="wizardError" class="error">{{ wizardError }}</p>

        <!-- Step 1: Customer Information -->
        <div v-if="wizardStep === 1" class="form wizard-form">
          <label>{{ t('app.order_wizard.customer_mobile_number') }}
            <input
              v-model="wizardForm.customer_mobile"
              :placeholder="t('app.common.01xxxxxxxxx')"
              autocomplete="off"
              required
              @keydown.enter.prevent="advanceWizardStep"
            />
          </label>

          <!-- Auto-suggested existing customers -->
          <div v-if="filteredCustomerSuggestions.length && !wizardForm.customer_id" class="suggestions-box">
            <small>{{ t('app.order_wizard.matching_existing_customers_tap_to_select') }}</small>
            <div
              v-for="c in filteredCustomerSuggestions"
              :key="c.id || c.public_id"
              class="suggestion-pill"
              @click="selectExistingCustomer(c)"
            >
              <strong>{{ c.name }}</strong> <span>({{ c.mobile_number }})</span>
            </div>
          </div>

          <label>{{ t('app.order_wizard.customer_full_name') }}
            <input
              v-model="wizardForm.customer_name"
              :placeholder="t('app.order_wizard.e_g_al_amin_chowdhury')"
              required
              @keydown.enter.prevent="advanceWizardStep"
            />
          </label>

          <!-- Customer Previous Orders with 1-Click Measurement Autofill -->
          <div v-if="wizardForm.customer_id && prevOrders.length" class="prev-orders-section">
            <div class="prev-orders-head">
              <strong>
                <NavIcon name="Clipboard" />
                {{ t('app.customer.col_orders') }}: {{ wizardForm.customer_name }}
              </strong>
              <small class="text-muted">{{ prevOrders.length }} previous orders</small>
            </div>
            <div class="prev-orders-list">
              <div v-for="po in prevOrders" :key="po.id" class="prev-order-item">
                <div class="prev-order-info">
                  <strong>{{ po.garment || 'Garment' }} · #{{ po.order_number }}</strong>
                  <small>{{ po.created_at ? new Date(po.created_at).toLocaleDateString() : '' }} · {{ po.measurements?.length || 0 }} measurements recorded</small>
                </div>
                <button
                  type="button"
                  class="btn-use-measurements"
                  :title="`Use saved measurements from order #${po.order_number}`"
                  @click="usePreviousOrderMeasurements(po)"
                >
                  <NavIcon name="TapeMeasure" />
                  <span>এই মাপ নিয়ে এগিয়ে যান →</span>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Step 2: Garment, Fabric & Delivery Promise -->
        <div v-else-if="wizardStep === 2" class="form wizard-form">
          <label>{{ t('app.order_wizard.garment_style') }}
            <select v-model="wizardForm.garment_id" required @change="onGarmentSelectChange">
              <optgroup v-if="groupedGarments.gents?.length" :label="t('app.order_wizard.group_gents')">
                <option v-for="g in groupedGarments.gents" :key="g.id || g.public_id" :value="g.id || g.public_id">
                  {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                </option>
              </optgroup>
              <optgroup v-if="groupedGarments.ladies?.length" :label="t('app.order_wizard.group_ladies')">
                <option v-for="g in groupedGarments.ladies" :key="g.id || g.public_id" :value="g.id || g.public_id">
                  {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                </option>
              </optgroup>
              <optgroup v-if="groupedGarments.kids?.length" :label="t('app.order_wizard.group_kids')">
                <option v-for="g in groupedGarments.kids" :key="g.id || g.public_id" :value="g.id || g.public_id">
                  {{ g.name }} — ৳{{ ((g.base_making_minor || 0) / 100).toLocaleString() }}
                </option>
              </optgroup>
              <optgroup v-if="groupedGarments.unisex?.length" label="উভলিঙ্গ (Unisex)">
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
              <span class="preview-rate-tag">{{ t('app.order_wizard.making_charge') }}: ৳ {{ ((selectedGarmentObj.base_making_minor || 0) / 100).toLocaleString() }}</span>
            </div>
            <div class="preview-parts-list">
              <small>
                <NavIcon name="TapeMeasure" style="width:14px;height:14px;vertical-align:middle" />
                {{ t('app.order_wizard.measurement_points') }} ({{ selectedGarmentObj.parts?.length || 0 }}):
              </small>
              <div class="preview-chips">
                <span v-for="p in selectedGarmentObj.parts || []" :key="p.id || p.public_id" class="preview-chip" :class="{ 'is-req': p.required !== false }">
                  {{ p.name }} <span class="unit-sub">({{ p.unit || 'in' }})</span><strong v-if="p.required !== false" class="req-star">*</strong>
                </span>
              </div>
            </div>
          </div>

          <!-- Fabric Source Selector: Customer Provided vs Buy from Shop -->
          <div class="fabric-source-selector">
            <div class="fabric-source-title">
              <NavIcon name="Package" />
              <span>কাপড়ের উৎস (Fabric / Material)</span>
            </div>
            <div class="fabric-source-radios">
              <label
                class="fabric-source-choice"
                :class="{ 'is-active': fabricSource === 'customer' }"
              >
                <input v-model="fabricSource" type="radio" value="customer" style="display:none" />
                <span>কাস্টমার কাপড় দিয়েছেন (Customer Provided)</span>
              </label>
              <label
                class="fabric-source-choice"
                :class="{ 'is-active': fabricSource === 'shop' }"
              >
                <input v-model="fabricSource" type="radio" value="shop" style="display:none" />
                <span>দোকান থেকে কাপড় নিচ্ছেন (Buy from Shop)</span>
              </label>
            </div>

            <!-- Shop Fabric Details -->
            <div v-if="fabricSource === 'shop'" class="shop-fabric-fields">
              <label>কাপড় নির্বাচন করুন *
                <select v-model="selectedFabricId" required>
                  <option v-for="f in availableFabrics" :key="f.public_id" :value="f.public_id">
                    {{ f.name }} (মজুত: {{ f.movements_sum_quantity || 0 }} {{ f.unit }})
                  </option>
                </select>
              </label>
              <label>পরিমাণ (গজ / মিটার) *
                <input v-model.number="fabricQuantity" type="number" step="0.25" min="0.25" required />
              </label>
              <label>প্রতি গজের দাম (৳) *
                <input v-model.number="fabricUnitPrice" type="number" min="0" step="10" required />
              </label>
            </div>

            <div v-if="fabricSource === 'shop'" class="fabric-pricing-breakdown">
              <span>কাপড়ের মোট দাম: {{ fabricQuantity }} গজ × ৳{{ fabricUnitPrice }} = <strong>৳ {{ fabricCostTaka }}</strong></span>
              <span>দর্জির মজুরি: <strong>৳ {{ Math.round((selectedGarmentObj?.base_making_minor || 0) / 100) }}</strong></span>
            </div>
          </div>

          <div class="delivery-presets">
            <small>{{ t('app.order_wizard.quick_delivery_presets') }}</small>
            <div class="preset-buttons">
              <button type="button" class="mini-btn" @click="setDeliveryDays(3)">{{ t('app.order_wizard.3_days') }}</button>
              <button type="button" class="mini-btn" @click="setDeliveryDays(7)">{{ t('app.order_wizard.7_days') }}</button>
              <button type="button" class="mini-btn" @click="setDeliveryDays(14)">{{ t('app.order_wizard.14_days') }}</button>
              <button
                type="button"
                class="mini-btn"
                :class="{ 'mini-btn--urgent': isUrgent }"
                @click="isUrgent = !isUrgent"
              >
                <NavIcon name="Ready" style="width:12px;height:12px" />
                {{ isUrgent ? 'Urgent Express' : 'Mark Urgent' }}
              </button>
            </div>
          </div>

          <label>{{ t('app.order_wizard.promised_delivery_date_time') }}
            <input v-model="wizardForm.promised_at" type="datetime-local" required />
          </label>

          <label>{{ t('app.order_wizard.assign_karigar_master_cutter_optional') }}
            <select v-model="wizardForm.karigar_id">
              <option value="">{{ t('app.order_wizard.unassigned_assign_later') }}</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">
                {{ e.name }}
              </option>
            </select>
          </label>
        </div>

        <!-- Step 3: Measurements & Cutting Notes with Voice Input -->
        <div v-else-if="wizardStep === 3" class="form wizard-form">
          <p v-if="!selectedGarmentObj?.parts?.length" class="empty">
            {{ t('app.order_wizard.no_measurement_points_are_set_up') }}
          </p>
          <template v-else>
            <!-- Voice Assistant Dictation Banner -->
            <div class="voice-assistant-panel" :class="{ 'is-listening': isContinuousVoiceActive }">
              <div class="voice-panel-left">
                <div class="voice-mic-icon-wrapper" :class="{ 'is-pulse': isContinuousVoiceActive }">
                  <NavIcon name="Mic" />
                </div>
                <div class="voice-panel-texts">
                  <div class="voice-title-row">
                    <strong>
                      <NavIcon name="Mic" style="width:14px;height:14px;vertical-align:middle" />
                      {{ t('app.order_wizard.voice_assistant') }}
                    </strong>
                    <span v-if="isContinuousVoiceActive" class="badge-live-rec">{{ t('app.order_wizard.recording') }}</span>

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
                    {{ t('app.order_wizard.listening') }} <em>"{{ voiceLiveTranscript }}"</em>
                  </span>
                  <span v-else-if="isContinuousVoiceActive" class="live-status-txt">
                    {{ selectedVoiceLang === 'ar-SA' ? 'تحدث الآن (مثال: "الطول ٣٢"، "الصدر ٣٨.৫")...' : selectedVoiceLang === 'en-US' ? 'Speak now (e.g. "Length 32", "Chest 38.5")...' : 'মুখে বলুন (যেমন: "লম্বা ৩২", "বুক ৩৮.৫", "হাতা ২৪")...' }}
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
                <NavIcon name="Mic" />
                <span>{{ isContinuousVoiceActive ? 'ভয়েস বন্ধ করুন' : 'হ্যান্ডস-ফ্রি মোড' }}</span>
              </button>
            </div>

            <!-- Measurement Inputs Grid -->
            <div class="measurement-grid">
              <label v-for="part in selectedGarmentObj.parts" :key="part.id || part.public_id" class="measurement-cell-label">
                <div class="lbl-top">
                  <span>{{ part.name }}<b v-if="part.required !== false" class="req-mark" :title="t('app.order_wizard.required')">*</b></span>
                </div>
                <div class="input-with-unit-voice">
                  <input
                    v-model.number="wizardForm.measurements[part.id]"
                    type="number"
                    inputmode="decimal"
                    step="0.01"
                    min="0"
                    enterkeyhint="next"
                    :placeholder="part.unit || 'inch'"
                    :class="{ 'flash-success': recentlyUpdatedPartId === part.id }"
                    @keydown.enter.prevent="focusNextMeasurement($event)"
                  />
                  <span class="unit-badge">{{ part.unit || 'in' }}</span>
                  <button
                    type="button"
                    class="btn-field-mic"
                    :class="{ 'is-field-listening': isVoiceListening && voiceActivePartId === part.id }"
                    :title="`${part.name}-এর মাপ মুখে বলুন (ভয়েস ইনপুট)`"
                    @click="triggerFieldVoice(part.id)"
                  >
                    <NavIcon name="Mic" style="width:12px;height:12px" />
                  </button>
                </div>
              </label>
            </div>

            <!-- Design & Style Options Selector -->
            <div v-if="selectedGarmentObj.design_options?.length" class="design-options-picker-box">
              <div class="design-box-title">
                <strong>
                  <NavIcon name="Palette" style="width:14px;height:14px;vertical-align:middle" />
                  {{ t('app.order_wizard.design_style') }}
                </strong>
                <small>{{ t('app.order_wizard.choose_collar_pocket_and_stitching_styles') }}</small>
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

          <label>{{ t('app.order_wizard.cutting_stitching_instructions') }}
            <textarea v-model="wizardForm.notes" :placeholder="t('app.order_wizard.e_g_sherwani_collar_chest_pocket')"></textarea>
          </label>
        </div>

        <!-- Step 4: Pricing, Advance & Payment with Review Summary -->
        <div v-else class="form wizard-form">
          <!-- Compact Order Review Summary Card before Booking -->
          <div class="order-summary-review-card">
            <div class="summary-review-head">
              <span class="review-title">
                <NavIcon name="Clipboard" style="width:16px;height:16px;vertical-align:middle" />
                {{ t('app.order_wizard.order_summary') }}:
              </span>
              <span class="review-status-badge">{{ t('app.order_wizard.verified') }}</span>
            </div>
            <div class="summary-grid-3">
              <div class="summary-meta-item">
                <small>{{ t('app.order_wizard.customer_name_phone') }}</small>
                <strong>{{ wizardForm.customer_name || 'নতুন গ্রাহক' }}</strong>
                <span class="sub-val">{{ wizardForm.customer_mobile }}</span>
              </div>
              <div class="summary-meta-item">
                <small>{{ t('app.order_wizard.garment_group') }}</small>
                <strong>{{ selectedGarmentObj?.name }}</strong>
                <span class="sub-val">{{ selectedGarmentObj?.group_name || 'Standard' }}</span>
              </div>
              <div class="summary-meta-item">
                <small>{{ t('app.order_wizard.delivery_date') }}</small>
                <strong v-if="wizardForm.promised_at">{{ new Date(wizardForm.promised_at).toLocaleDateString() }}</strong>
                <span v-if="isUrgent" class="badge-urgent-tag">{{ t('app.order_wizard.express') }}</span>
              </div>
              <div v-if="fabricSource === 'shop'" class="summary-meta-item">
                <small>দোকানের কাপড়</small>
                <strong>{{ selectedFabricObj?.name || 'Fabric' }}</strong>
                <span class="sub-val">{{ fabricQuantity }} গজ @ ৳{{ fabricUnitPrice }} = ৳{{ fabricCostTaka }}</span>
              </div>
            </div>

            <!-- Selected Design Options summary chip -->
            <div v-if="Object.keys(wizardForm.selectedDesignOptions).some(k => Boolean(wizardForm.selectedDesignOptions[k]))" class="summary-design-styles-chip">
              <NavIcon name="Palette" style="width:14px;height:14px;vertical-align:middle" />
              <strong>{{ t('app.order_wizard.selected_styles') }}</strong>
              <template v-for="(val, optName) in wizardForm.selectedDesignOptions" :key="optName">
                <span v-if="Array.isArray(val) ? val.length : val" class="style-crumb">
                  {{ optName }}: {{ Array.isArray(val) ? val.join(', ') : val }}
                </span>
              </template>
            </div>
            <div class="summary-foot-row">
              <span>{{ t('app.order_wizard.measurements_2') }} <strong>{{ t('app.order_wizard.p0_points_recorded', { p0: recordedMeasurementsCount }) }}</strong></span>
              <span v-if="wizardForm.karigar_id">{{ t('app.order_wizard.master_karigar') }} <strong>{{ employees.find(e => e.id === wizardForm.karigar_id)?.name }}</strong></span>
            </div>
          </div>

          <!-- Price Breakdown: Tailoring Charge + Fabric Price = Total -->
          <div v-if="fabricSource === 'shop'" class="fabric-pricing-breakdown" style="margin-bottom:1rem">
            <span>দর্জির মজুরি: <strong>৳ {{ Math.round((wizardForm.total_minor - (fabricCostTaka * 100)) / 100) }}</strong></span>
            <span>+ কাপড়ের দাম: <strong>৳ {{ fabricCostTaka }}</strong></span>
            <span>= মোট বিল: <strong>৳ {{ wizardTotalTaka }}</strong></span>
          </div>

          <label>{{ t('app.order_wizard.total_making_charge') }} (সর্বমোট মূল্য)
            <input v-model.number="wizardTotalTaka" type="number" min="0" step="10" required />
          </label>

          <label>{{ t('app.order_wizard.advance_received') }}
            <input v-model.number="wizardAdvanceTaka" type="number" min="0" :max="wizardTotalTaka" step="10" required />
          </label>

          <!-- Quick Advance Presets -->
          <div class="advance-shortcuts">
            <small>{{ t('app.order_wizard.quick_advance') }}</small>
            <div class="preset-buttons">
              <button type="button" class="mini-btn" @click="wizardForm.paid_minor = wizardForm.total_minor">{{ t('app.order_wizard.full_payment_100') }}</button>
              <button type="button" class="mini-btn" @click="wizardForm.paid_minor = Math.round(wizardForm.total_minor * 0.5)">{{ t('app.order_wizard.50_advance') }}</button>
              <button type="button" class="mini-btn" @click="wizardForm.paid_minor = Math.round(wizardForm.total_minor * 0.3)">{{ t('app.order_wizard.30_advance') }}</button>
              <button type="button" class="mini-btn" @click="wizardForm.paid_minor = 0">{{ t('app.order_wizard.no_advance') }}</button>
            </div>
          </div>

          <div class="summary-due-box">
            <span>{{ t('app.order_wizard.balance_due') }}</span>
            <strong style="font-size:1.25rem;color:var(--status-cancelled)">৳ {{ wizardDueTaka.toFixed(2) }}</strong>
          </div>

          <label>{{ t('app.common.payment_method') }}
            <select v-model="wizardForm.payment_method">
              <option value="cash">{{ t('app.common.cash') }}</option>
              <option value="mobile_banking">bKash / Nagad</option>
              <option value="card">{{ t('app.common.card') }}</option>
            </select>
          </label>
        </div>
      </div>

      <div class="modal-footer">
        <button v-if="wizardStep > 1" type="button" class="secondary" @click="goToStep(wizardStep - 1)">
          {{ t('app.order_wizard.back') }}
        </button>
        <div v-else></div>

        <button v-if="wizardStep < 4" type="button" class="primary" :disabled="wizardStep === 2 && !garments.length" @click="advanceWizardStep">
          {{ t('app.order_wizard.next_step') }}
        </button>
        <button v-else type="button" class="primary" :disabled="wizardLoading" @click="finishWizard">
          {{ wizardLoading ? 'Creating Order…' : 'Complete & Print Order Receipt' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped src="../../assets/css/components/_order-wizard.scss"></style>
