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
    if (newGarments.length > 0 && !wizardForm.garment_id) {
      const first = newGarments[0];
      wizardForm.garment_id = first.public_id || first.id || "";
      wizardForm.total_minor = first.base_making_minor || 45000;
      wizardForm.paid_minor = Math.round(wizardForm.total_minor * 0.4);
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

        <button v-if="wizardStep < 4" type="button" class="primary" @click="advanceWizardStep">
          {{ t('app.order_wizard.next_step') }}
        </button>
        <button v-else type="button" class="primary" :disabled="wizardLoading" @click="finishWizard">
          {{ wizardLoading ? 'Creating Order…' : 'Complete & Print Order Receipt ✓' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>


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

.unit-badge {
  position: absolute;
  right: 0.5rem;
  font-size: 0.725rem;
  color: var(--muted);
  font-weight: 600;
  pointer-events: none;
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

.measurement-grid {
  grid-template-columns: repeat(auto-fill, minmax(11rem, 1fr));
  gap: 0.9rem 0.75rem;
  align-items: end;
}

.measurement-cell-label {
  display: flex !important;
  flex-direction: column;
  gap: 0.3rem;
  min-width: 0;
}

.req-mark {
  color: #dc2626;
  margin-left: 0.2rem;
}

.suggestions-box {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 0.4rem;
  padding: 0.6rem 0.75rem;
  border: 1px dashed var(--line-strong, var(--line));
  border-radius: var(--radius-sm);
  background: var(--paper);
}

.suggestions-box small {
  flex-basis: 100%;
  color: var(--muted);
}

.suggestion-pill {
  cursor: pointer;
  padding: 0.4rem 0.75rem;
  border: 1px solid var(--primary);
  border-radius: 99px;
  background: var(--surface);
  font-size: 0.85rem;
  min-height: 2.25rem;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.suggestion-pill:hover {
  background: var(--primary-soft);
}

.measurement-cell-label .lbl-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
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

@keyframes pulse-mic {
  0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); }
  70% { transform: scale(1.06); box-shadow: 0 0 0 8px rgba(239, 68, 68, 0); }
  100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); }
}

@keyframes blink-rec {
  from { opacity: 0.6; }
  to { opacity: 1; }
}

@keyframes pulse-green {
  0% { transform: scale(0.97); opacity: 0.8; }
  50% { transform: scale(1.02); opacity: 1; }
  100% { transform: scale(1); }
}
</style>
