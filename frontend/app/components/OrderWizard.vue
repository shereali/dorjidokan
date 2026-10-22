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


// Enter jumps to the next measurement box (and to the next step after the last one) so a master can key in a whole set without the mouse.
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

// Existing-customer suggestions come from the server, so shops with thousands of customers still match.
const filteredCustomerSuggestions = ref<Customer[]>([]);
let suggestionTimer: ReturnType<typeof setTimeout> | undefined;
let suggestionTicket = 0;
let pickedMobile = "";

watch(
  () => wizardForm.customer_mobile,
  (value) => {
    // Editing the number after picking a customer means a different person: forget the picked profile.
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

const maxStepReached = ref(1);

const recordedMeasurementsCount = computed(() => {
  return Object.values(wizardForm.measurements).filter(
    (v) => v !== null && v !== undefined && !Number.isNaN(v) && v > 0
  ).length;
});

function validateCurrentStep(): boolean {
  wizardError.value = "";
  if (wizardStep.value === 1) {
    // An existing customer is already complete; a new one needs both a mobile number and a name.
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
      selectedStyleSummary.length ? `[${t('app.order_wizard.design_style')}] ${selectedStyleSummary.join(" | ")}` : "",
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

    toast.success(t('app.order_wizard.order_p0_booked', { p0: newOrder.order_number || newOrder.number || "" }));
    showWizard.value = false;
    emit("created", newOrder);
  } catch (e: any) {
    wizardError.value = apiErrorMessage(e, t('app.order_wizard.could_not_complete_order_creation'));
  } finally {
    wizardLoading.value = false;
  }
}

// Re-initialise the form every time the wizard is opened, and release the microphone when it closes.
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
    // The garment list can arrive after the wizard opened (e.g. /orders?new=1): select the first one
    // and initialise its design options and price exactly like picking it by hand.
    if (newGarments.length > 0 && !wizardForm.garment_id) {
      const first = newGarments[0]!;
      wizardForm.garment_id = first.public_id || first.id || "";
      onGarmentSelectChange();
    }
  },
  { immediate: true }
);
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
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18" />
            <line x1="6" y1="6" x2="18" y2="18" />
          </svg>
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
            <template v-if="wizardStep > step.num">✓</template>
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
        </div>

        <!-- Step 2: Garment & Delivery Promise -->
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
              <span class="preview-rate-tag">{{ t('app.order_wizard.making_charge') }}: ৳ {{ ((selectedGarmentObj.base_making_minor || 0) / 100).toLocaleString() }}</span>
            </div>
            <div class="preview-parts-list">
              <small>📏 {{ t('app.order_wizard.measurement_points') }} ({{ selectedGarmentObj.parts?.length || 0 }}):</small>
              <div class="preview-chips">
                <span v-for="p in selectedGarmentObj.parts || []" :key="p.id || p.public_id" class="preview-chip" :class="{ 'is-req': p.required !== false }">
                  {{ p.name }} <span class="unit-sub">({{ p.unit || 'in' }})</span><strong v-if="p.required !== false" class="req-star">*</strong>
                </span>
              </div>
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
                ★ {{ isUrgent ? 'Urgent Express' : 'Mark Urgent' }}
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
            <!-- Hands-Free Voice Assistant Dictation Banner -->
            <div class="voice-assistant-panel" :class="{ 'is-listening': isContinuousVoiceActive }">
              <div class="voice-panel-left">
                <div class="voice-mic-icon-wrapper" :class="{ 'is-pulse': isContinuousVoiceActive }">
                  <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                    <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                    <line x1="12" y1="19" x2="12" y2="23" />
                    <line x1="8" y1="23" x2="16" y2="23" />
                  </svg>
                </div>
                <div class="voice-panel-texts">
                  <div class="voice-title-row">
                    <strong>🎙️ {{ t('app.order_wizard.voice_assistant') }}</strong>
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
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z" />
                      <path d="M19 10v2a7 7 0 0 1-14 0v-2" />
                      <line x1="12" y1="19" x2="12" y2="23" />
                      <line x1="8" y1="23" x2="16" y2="23" />
                    </svg>
                  </button>
                </div>
              </label>
            </div>

            <!-- Design & Style Options Selector -->
            <div v-if="selectedGarmentObj.design_options?.length" class="design-options-picker-box">
              <div class="design-box-title">
                <strong>🎨 {{ t('app.order_wizard.design_style') }}</strong>
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
              <span class="review-title">📋 {{ t('app.order_wizard.order_summary') }}:</span>
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
            </div>

            <!-- Selected Design Options summary chip -->
            <div v-if="Object.keys(wizardForm.selectedDesignOptions).some(k => Boolean(wizardForm.selectedDesignOptions[k]))" class="summary-design-styles-chip">
              🎨 <strong>{{ t('app.order_wizard.selected_styles') }}</strong>
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

          <label>{{ t('app.order_wizard.total_making_charge') }}
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
          {{ wizardLoading ? 'Creating Order…' : 'Complete & Print Order Receipt ✓' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped src="../../assets/css/components/_order-wizard.scss"></style>
