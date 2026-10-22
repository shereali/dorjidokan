<script setup lang="ts">
import type { Garment } from "~/types/garments";

const props = defineProps<{ garments: Garment[] }>();
const emit = defineEmits<{ saved: [] }>();

const { t } = useTailorsI18n();
const api = useTailorsApi();
const toast = useToast();
// Copy-design source list and presets read the parent's garments.
const items = toRef(props, "garments");

const saving = ref(false);

// 3-Column Studio Modal State
const showStudioModal = ref(false);
const editingGarment = ref<Garment | null>(null);
const activeTabMobile = ref<"general" | "measurements" | "designs">("general");

// Studio Form Reactive State
const studioForm = reactive({
  name: "",
  slug: "",
  category: "gents" as "gents" | "ladies" | "kids" | "unisex",
  group_name: "Panjabi / Jubbah",
  base_making: 450,
  master_rate: 70,
  karigar_rate: 250,
  description: "",
  active: true,
  illustration_url: "",
  parts: [] as { name: string; unit: "inch" | "cm"; required: boolean }[],
  looseAllowances: [] as { part_name: string; allowance_value: string }[],
  designOptions: [] as {
    name: string;
    type: "select" | "checkbox" | "radio";
    values: { name: string; extra_price: number; is_default: boolean }[];
  }[],
});

// Custom Part Adder State
const newCustomPartName = ref("");
const newCustomPartUnit = ref<"inch" | "cm">("inch");
const newCustomPartRequired = ref(true);

// Copy Design Modal State
const showCopyDesignModal = ref(false);
const selectedSourceGarmentId = ref("");

// Categorized measurement bank for instant ergonomics

const selectedBankCategory = ref<"all" | "top" | "bottom" | "ladies">("all");
const bankSearchQuery = ref("");

const filteredBankParts = computed(() => {
  const bank = categorizedMeasurementBank[selectedBankCategory.value] || categorizedMeasurementBank.all;
  if (!bankSearchQuery.value.trim()) return bank;
  const q = bankSearchQuery.value.trim().toLowerCase();
  return bank.filter((p) => p.toLowerCase().includes(q));
});

const quickLooseAllowancePresets = [
  "+১.৫ ইঞ্চি লুজ",
  "+২ ইঞ্চি লুজ",
  "+২.৫ ইঞ্চি লুজ",
  "+৩ ইঞ্চি লুজ",
  "+৩.৫ ইঞ্চি লুজ",
  "+৪ ইঞ্চি লুজ (বোরকা/আবায়া)",
];

// Live Profit Margin Calculation
const liveShopMargin = computed(() => {
  const base = Number(studioForm.base_making) || 0;
  const master = Number(studioForm.master_rate) || 0;
  const karigar = Number(studioForm.karigar_rate) || 0;
  const net = base - master - karigar;
  const pct = base > 0 ? Math.round((net / base) * 100) : 0;
  return { net, pct };
});

function movePartUp(index: number) {
  if (index <= 0) return;
  const item = studioForm.parts.splice(index, 1)[0];
  studioForm.parts.splice(index - 1, 0, item);
}

function movePartDown(index: number) {
  if (index >= studioForm.parts.length - 1) return;
  const item = studioForm.parts.splice(index, 1)[0];
  studioForm.parts.splice(index + 1, 0, item);
}

function setQuickLooseValue(looseIndex: number, val: string) {
  if (studioForm.looseAllowances[looseIndex]) {
    studioForm.looseAllowances[looseIndex].allowance_value = val;
  }
}


function loadPreset(presetKey: string) {
  const p = industryPresets[presetKey as keyof typeof industryPresets];
  if (!p) return;
  studioForm.name = p.name;
  studioForm.category = p.category as any;
  studioForm.group_name = p.group_name;
  studioForm.base_making = p.base_making;
  studioForm.master_rate = p.master_rate;
  studioForm.karigar_rate = p.karigar_rate;
  studioForm.description = p.description;
  studioForm.parts = p.parts.map((part) => ({
    name: part.name,
    unit: (part.unit === "cm" ? "cm" : "inch") as "inch" | "cm",
    required: part.required,
  }));
  studioForm.looseAllowances = p.loose.map((l) => ({ ...l }));
  studioForm.designOptions = p.designs.map((d) => ({
    name: d.name,
    type: d.type as any,
    values: d.values.map((v) => ({ ...v })),
  }));
  toast.success(t('app.garment.p0_template_loaded', { p0: p.name }));
}

function openCreateModal() {
  editingGarment.value = null;
  loadPreset("panjabi");
  activeTabMobile.value = "general";
  showStudioModal.value = true;
}

function openEditModal(garment: Garment, initialTab: "general" | "measurements" | "designs" = "general") {
  editingGarment.value = garment;
  studioForm.name = garment.name;
  studioForm.slug = garment.slug;
  studioForm.category = garment.category || "gents";
  studioForm.group_name = garment.group_name || standardGroups[0];
  studioForm.base_making = Math.round((garment.base_making_minor || 0) / 100);
  studioForm.master_rate = Math.round((garment.master_rate_minor || 0) / 100);
  studioForm.karigar_rate = Math.round((garment.karigar_rate_minor || 0) / 100);
  studioForm.description = garment.description || "";
  studioForm.active = garment.active ?? true;
  studioForm.illustration_url = garment.illustration_url || "";
  studioForm.parts = (garment.parts || []).map((p) => ({
    name: p.name,
    unit: p.unit || "inch",
    required: p.required !== false,
  }));

  // Map loose allowances
  if (Array.isArray(garment.loose_allowances)) {
    studioForm.looseAllowances = garment.loose_allowances.map((item: any) => ({
      part_name: item.part_name || "",
      allowance_value: item.allowance_value || "",
    }));
  } else if (garment.loose_allowances && typeof garment.loose_allowances === "object") {
    studioForm.looseAllowances = Object.entries(garment.loose_allowances).map(([k, v]) => ({
      part_name: k,
      allowance_value: String(v),
    }));
  } else {
    studioForm.looseAllowances = [];
  }

  // Map design options
  studioForm.designOptions = (garment.design_options || []).map((opt) => ({
    name: opt.name,
    type: opt.type || "select",
    values: (opt.values || []).map((val) => ({
      name: val.name,
      extra_price: Math.round((val.extra_price_minor || 0) / 100),
      is_default: !!val.is_default,
    })),
  }));

  activeTabMobile.value = initialTab;
  showStudioModal.value = true;
}

function applyPresetDesignsToCurrentGarment(presetKey: keyof typeof industryPresets) {
  const p = industryPresets[presetKey];
  if (!p) return;
  const newDesigns = p.designs.map((d) => ({
    name: d.name,
    type: d.type as any,
    values: d.values.map((v) => ({ ...v })),
  }));

  if (studioForm.designOptions.length > 0) {
    studioForm.designOptions = [...studioForm.designOptions, ...newDesigns];
    toast.success(t('app.garment.p0_design_options_added_to_this', { p0: p.name }));
  } else {
    studioForm.designOptions = newDesigns;
    toast.success(t('app.garment.p0_full_design_template_loaded', { p0: p.name }));
  }
}

// Measurement Point Controls
function isPartSelected(name: string): boolean {
  return studioForm.parts.some((p) => p.name.toLowerCase() === name.toLowerCase());
}

function toggleBankPart(name: string) {
  const index = studioForm.parts.findIndex((p) => p.name.toLowerCase() === name.toLowerCase());
  if (index !== -1) {
    studioForm.parts.splice(index, 1);
    // Also remove from loose allowances if present
    studioForm.looseAllowances = studioForm.looseAllowances.filter((l) => l.part_name !== name);
  } else {
    studioForm.parts.push({ name, unit: "inch", required: true });
  }
}

function removeSelectedPart(index: number) {
  const removed = studioForm.parts[index];
  studioForm.parts.splice(index, 1);
  if (removed) {
    studioForm.looseAllowances = studioForm.looseAllowances.filter((l) => l.part_name !== removed.name);
  }
}

function addCustomPart() {
  const trimmed = newCustomPartName.value.trim();
  if (!trimmed) return;
  if (isPartSelected(trimmed)) {
    toast.warning(t('app.garment.this_measurement_is_already_added'));
    return;
  }
  studioForm.parts.push({
    name: trimmed,
    unit: newCustomPartUnit.value,
    required: newCustomPartRequired.value,
  });
  newCustomPartName.value = "";
}

// Loose Allowance Controls
function addLooseAllowanceRow() {
  const unmappedPart = studioForm.parts.find(
    (p) => !studioForm.looseAllowances.some((l) => l.part_name === p.name)
  );
  studioForm.looseAllowances.push({
    part_name: unmappedPart ? unmappedPart.name : (studioForm.parts[0]?.name || ""),
    allowance_value: "+২ ইঞ্চি লুজ",
  });
}

function removeLooseAllowanceRow(index: number) {
  studioForm.looseAllowances.splice(index, 1);
}

// Design Options Controls
function addDesignOptionGroup() {
  studioForm.designOptions.push({
    name: "নতুন স্টাইল অপশন",
    type: "select",
    values: [
      { name: "অপশন ১", extra_price: 0, is_default: true },
      { name: "অপশন ২", extra_price: 50, is_default: false },
    ],
  });
}

function removeDesignOptionGroup(index: number) {
  studioForm.designOptions.splice(index, 1);
}

function addDesignOptionValue(optionIndex: number) {
  studioForm.designOptions[optionIndex].values.push({
    name: "",
    extra_price: 0,
    is_default: false,
  });
}

function removeDesignOptionValue(optionIndex: number, valIndex: number) {
  studioForm.designOptions[optionIndex].values.splice(valIndex, 1);
}

// 1-Click Copy Design from Another Garment
function openCopyDesignModal() {
  selectedSourceGarmentId.value = items.value[0]?.public_id || "";
  showCopyDesignModal.value = true;
}

function applyCopiedDesign() {
  const source = items.value.find((g) => g.public_id === selectedSourceGarmentId.value);
  if (!source || !source.design_options?.length) {
    toast.warning(t('app.garment.the_source_garment_has_no_design'));
    showCopyDesignModal.value = false;
    return;
  }

  const copied = source.design_options.map((opt) => ({
    name: opt.name,
    type: opt.type,
    values: opt.values.map((v) => ({
      name: v.name,
      extra_price: Math.round((v.extra_price_minor || 0) / 100),
      is_default: v.is_default,
    })),
  }));

  studioForm.designOptions = [...studioForm.designOptions, ...copied];
  toast.success(t('app.garment.design_options_copied_from_p0', { p0: source.name }));
  showCopyDesignModal.value = false;
}

// Save Garment (Create or Update)
async function saveGarmentStudio() {
  if (!studioForm.name.trim()) {
    toast.warning(t('app.garment.enter_the_garment_name'));
    activeTabMobile.value = "general";
    return;
  }
  if (!studioForm.parts.length) {
    toast.warning(t('app.garment.add_at_least_one_measurement_point'));
    activeTabMobile.value = "measurements";
    return;
  }

  saving.value = true;
  try {
    const payload: any = {
      name: studioForm.name.trim(),
      category: studioForm.category,
      group_name: studioForm.group_name,
      base_making_minor: Math.round((Number(studioForm.base_making) || 0) * 100),
      master_rate_minor: Math.round((Number(studioForm.master_rate) || 0) * 100),
      karigar_rate_minor: Math.round((Number(studioForm.karigar_rate) || 0) * 100),
      description: studioForm.description.trim() || null,
      loose_allowances: studioForm.looseAllowances.filter((l) => l.part_name && l.allowance_value),
      illustration_url: studioForm.illustration_url.trim() || null,
      active: studioForm.active,
      parts: studioForm.parts.map((p) => ({
        name: p.name.trim(),
        unit: p.unit,
        required: p.required,
      })),
    };

    if (editingGarment.value) {
      // Update basic fields & parts
      await api.request(`/garments/${editingGarment.value.public_id}`, {
        method: "PATCH",
        body: payload,
      });

      // Synchronize Design Options
      // 1. Delete old options
      if (editingGarment.value.design_options?.length) {
        for (const opt of editingGarment.value.design_options) {
          try {
            await api.request(`/garments/${editingGarment.value.public_id}/design-options/${opt.public_id || opt.id}`, {
              method: "DELETE",
            });
          } catch {}
        }
      }

      // 2. Create new options
      for (const opt of studioForm.designOptions) {
        if (!opt.name.trim()) continue;
        await api.request(`/garments/${editingGarment.value.public_id}/design-options`, {
          method: "POST",
          body: {
            name: opt.name.trim(),
            type: opt.type,
            values: opt.values
              .filter((v) => v.name.trim())
              .map((v) => ({
                name: v.name.trim(),
                extra_price_minor: Math.round((Number(v.extra_price) || 0) * 100),
                is_default: v.is_default,
              })),
          },
        });
      }

      toast.success(t('app.garment.p0_updated', { p0: studioForm.name }));
    } else {
      // Create new Garment
      const res = await api.request<{ garment: Garment }>("/garments", {
        method: "POST",
        body: payload,
      });

      const newGarmentId = res.data.garment.public_id || res.data.garment.id;

      // Create design options for newly created garment
      for (const opt of studioForm.designOptions) {
        if (!opt.name.trim()) continue;
        await api.request(`/garments/${newGarmentId}/design-options`, {
          method: "POST",
          body: {
            name: opt.name.trim(),
            type: opt.type,
            values: opt.values
              .filter((v) => v.name.trim())
              .map((v) => ({
                name: v.name.trim(),
                extra_price_minor: Math.round((Number(v.extra_price) || 0) * 100),
                is_default: v.is_default,
              })),
          },
        });
      }

      toast.success(t('app.garment.p0_created', { p0: studioForm.name }));
    }

    showStudioModal.value = false;
    emit("saved");
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || t('app.garment.could_not_save'));
  } finally {
    saving.value = false;
  }
}

defineExpose({ openCreateModal, openEditModal });
</script>

<template>
  <!-- ================================================================= -->
  <!-- 3-COLUMN ATELIER GARMENT STUDIO MODAL                             -->
  <!-- ================================================================= -->
  <div v-if="showStudioModal" class="studio-modal-backdrop" @click.self="showStudioModal = false">
    <div class="studio-modal-dialog">
      <!-- Modal Topbar Header -->
      <div class="studio-modal-header">
        <div class="header-left">
          <span class="modal-badge-atelier">{{ t('app.garment.garment_studio') }}</span>
          <h2>{{ editingGarment ? t('app.garment.studio_edit_title', { p0: editingGarment.name }) : t('app.garment.studio_create_title') }}</h2>
        </div>

        <div class="header-actions">
          <button
            v-if="items.length > 1"
            type="button"
            class="btn-copy-design-header"
            :title="t('app.garment.copy_design_styles_and_options_from')"
            @click="openCopyDesignModal"
          >
            {{ t('app.garment.copy_design') }}
          </button>
          <button
            type="button"
            class="btn-close-modal"
            :title="t('app.common.close')"
            @click="showStudioModal = false"
          >
            ✕
          </button>
        </div>
      </div>

      <!-- 1-Click Industry Presets Banner for Instant Setup -->
      <div v-if="!editingGarment" class="modal-presets-banner">
        <span class="preset-title">{{ t('app.garment.load_a_ready_made_template') }}</span>
        <div class="preset-pills-row">
          <button type="button" class="preset-pill-btn" @click="loadPreset('shirt')">{{ t('app.garment.formal_shirt') }}</button>
          <button type="button" class="preset-pill-btn" @click="loadPreset('panjabi')">{{ t('app.garment.panjabi') }}</button>
          <button type="button" class="preset-pill-btn" @click="loadPreset('pant')">{{ t('app.garment.formal_pant') }}</button>
          <button type="button" class="preset-pill-btn" @click="loadPreset('kamiz')">{{ t('app.garment.ladies_kameez') }}</button>
          <button type="button" class="preset-pill-btn" @click="loadPreset('burqa')">{{ t('app.garment.burqa_abaya') }}</button>
          <button type="button" class="preset-pill-btn" @click="loadPreset('blouse')">{{ t('app.garment.ladies_blouse') }}</button>
        </div>
      </div>

      <!-- Mobile Navigation Tabs -->
      <div class="mobile-tab-nav">
        <button
          type="button"
          class="m-tab-btn"
          :class="{ 'is-m-active': activeTabMobile === 'general' }"
          @click="activeTabMobile = 'general'"
        >
          {{ t('app.garment.1_general_rates') }}
        </button>
        <button
          type="button"
          class="m-tab-btn"
          :class="{ 'is-m-active': activeTabMobile === 'measurements' }"
          @click="activeTabMobile = 'measurements'"
        >
          {{ t('app.garment.2_measurements_loose') }}
        </button>
        <button
          type="button"
          class="m-tab-btn"
          :class="{ 'is-m-active': activeTabMobile === 'designs' }"
          @click="activeTabMobile = 'designs'"
        >
          {{ t('app.garment.3_design_style') }}
        </button>
      </div>

      <!-- Studio Modal 3-Column Body -->
      <div class="studio-modal-body">
        <!-- ========================================================= -->
        <!-- COLUMN 1: GENERAL INFORMATION & PRICING                   -->
        <!-- ========================================================= -->
        <div class="studio-col col-general" :class="{ 'm-show': activeTabMobile === 'general' }">
          <div class="col-header">
            <span class="col-num-badge">1</span>
            <h3>{{ t('app.garment.general_details_rates') }}</h3>
          </div>

          <div class="col-content-scroll">
            <div class="form-group">
              <label>{{ t('app.garment.garment_name') }}</label>
              <input
                v-model="studioForm.name"
                type="text"
                :placeholder="t('app.garment.e_g_shirt_panjabi_pant_blazer')"
                required
              />
            </div>

            <div class="form-row-2">
              <div class="form-group">
                <label>{{ t('app.garment.category') }}</label>
                <select v-model="studioForm.category">
                  <option value="gents">{{ t('app.garment.gents') }}</option>
                  <option value="ladies">{{ t('app.garment.ladies') }}</option>
                  <option value="kids">{{ t('app.garment.kids') }}</option>
                  <option value="unisex">{{ t('app.garment.unisex') }}</option>
                </select>
              </div>

              <div class="form-group">
                <label>{{ t('app.garment.production_group') }}</label>
                <select v-model="studioForm.group_name">
                  <option v-for="g in standardGroups" :key="g" :value="g">{{ g }}</option>
                </select>
              </div>
            </div>

            <div class="rates-box-card">
              <div class="rates-box-title">
                <span>{{ t('app.garment.rates_wages') }}</span>
                <div class="live-profit-pill" :class="{ 'is-negative': liveShopMargin.net < 0 }">
                  {{ t('app.garment.shop_net_margin') }} <strong>৳{{ liveShopMargin.net }}</strong> ({{ liveShopMargin.pct }}%)
                </div>
              </div>

              <div class="form-group">
                <label>{{ t('app.garment.customer_price_base_making_rate') }}</label>
                <div class="input-currency-wrap">
                  <span class="currency-sign">৳</span>
                  <input
                    v-model.number="studioForm.base_making"
                    type="number"
                    min="0"
                    step="1"
                    placeholder="500"
                    required
                  />
                </div>
              </div>

              <div class="form-row-2">
                <div class="form-group">
                  <label>{{ t('app.garment.master_fee_cutting') }}</label>
                  <div class="input-currency-wrap">
                    <span class="currency-sign">৳</span>
                    <input
                      v-model.number="studioForm.master_rate"
                      type="number"
                      min="0"
                      placeholder="60"
                    />
                  </div>
                </div>

                <div class="form-group">
                  <label>{{ t('app.garment.karigar_fee_stitching') }}</label>
                  <div class="input-currency-wrap">
                    <span class="currency-sign">৳</span>
                    <input
                      v-model.number="studioForm.karigar_rate"
                      type="number"
                      min="0"
                      placeholder="100"
                    />
                  </div>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label>{{ t('app.garment.description_cutting_notes') }}</label>
              <textarea
                v-model="studioForm.description"
                rows="3"
                :placeholder="t('app.garment.general_cutting_or_stitching_instructions')"
              ></textarea>
            </div>

            <div class="active-toggle-card">
              <label class="switch-lbl">
                <input v-model="studioForm.active" type="checkbox" />
                <span class="slider"></span>
              </label>
              <div class="switch-texts">
                <strong>{{ t('app.garment.available_for_orders_active') }}</strong>
                <small>{{ t('app.garment.when_on_this_garment_appears_in') }}</small>
              </div>
            </div>
          </div>
        </div>

        <!-- ========================================================= -->
        <!-- COLUMN 2: MEASUREMENTS & LOOSE MAPPING                    -->
        <!-- ========================================================= -->
        <div class="studio-col col-measurements" :class="{ 'm-show': activeTabMobile === 'measurements' }">
          <div class="col-header">
            <span class="col-num-badge">2</span>
            <h3>{{ t('app.garment.measurements_loose') }}</h3>
          </div>

          <div class="col-content-scroll">
            <!-- Available Measurement Points Bank with Category Filters & Search -->
            <div class="parts-bank-section">
              <div class="section-sub-title">
                <span>{{ t('app.garment.measurement_bank') }}</span>
                <small>{{ t('app.garment.click_to_add_or_remove') }}</small>
              </div>

              <!-- Bank Category Filter & Search Bar -->
              <div class="bank-filters-row">
                <div class="bank-cat-pills">
                  <button
                    type="button"
                    class="bank-cat-pill"
                    :class="{ 'is-active-cat': selectedBankCategory === 'all' }"
                    @click="selectedBankCategory = 'all'"
                  >
                    {{ t('app.garment.all') }}
                  </button>
                  <button
                    type="button"
                    class="bank-cat-pill"
                    :class="{ 'is-active-cat': selectedBankCategory === 'top' }"
                    @click="selectedBankCategory = 'top'"
                  >
                    {{ t('app.garment.tops') }}
                  </button>
                  <button
                    type="button"
                    class="bank-cat-pill"
                    :class="{ 'is-active-cat': selectedBankCategory === 'bottom' }"
                    @click="selectedBankCategory = 'bottom'"
                  >
                    {{ t('app.garment.bottoms') }}
                  </button>
                  <button
                    type="button"
                    class="bank-cat-pill"
                    :class="{ 'is-active-cat': selectedBankCategory === 'ladies' }"
                    @click="selectedBankCategory = 'ladies'"
                  >
                    {{ t('app.garment.ladies_2') }}
                  </button>
                </div>
                <input
                  v-model="bankSearchQuery"
                  type="text"
                  class="bank-search-input"
                  :placeholder="t('app.garment.search_measurements')"
                />
              </div>

              <div class="parts-pill-cloud">
                <button
                  v-for="partName in filteredBankParts"
                  :key="partName"
                  type="button"
                  class="bank-part-pill"
                  :class="{ 'is-selected-pill': isPartSelected(partName) }"
                  @click="toggleBankPart(partName)"
                >
                  {{ isPartSelected(partName) ? '✓' : '+' }} {{ partName }}
                </button>
              </div>
            </div>

            <!-- Quick Add Custom Measurement Part -->
            <div class="custom-part-adder-bar">
              <input
                v-model="newCustomPartName"
                type="text"
                :placeholder="t('app.garment.new_custom_measurement_name')"
                @keydown.enter.prevent="addCustomPart"
              />
              <select v-model="newCustomPartUnit">
                <option value="inch">{{ t('app.garment.inch') }}</option>
                <option value="cm">{{ t('app.garment.cm') }}</option>
              </select>
              <button type="button" class="btn-add-custom-part" @click="addCustomPart">
                {{ t('app.garment.add') }}
              </button>
            </div>

            <!-- Selected Parts List -->
            <div class="selected-parts-section">
              <div class="section-sub-title">
                <span>{{ t('app.garment.selected_measurements_p0', { p0: studioForm.parts.length }) }}</span>
              </div>

              <div v-if="!studioForm.parts.length" class="empty-parts-notice">
                {{ t('app.garment.no_measurement_points_selected_pick_some') }}
              </div>

              <div v-else class="selected-parts-grid">
                <div
                  v-for="(part, idx) in studioForm.parts"
                  :key="idx"
                  class="selected-part-card"
                >
                  <span class="part-order-num">{{ idx + 1 }}</span>
                  <input
                    v-model="part.name"
                    type="text"
                    class="part-name-input"
                    required
                  />
                  <select v-model="part.unit" class="part-unit-select">
                    <option value="inch">{{ t('app.garment.inch') }}</option>
                    <option value="cm">{{ t('app.garment.cm') }}</option>
                  </select>
                  <label class="part-req-chk" :title="t('app.garment.required_measurement')">
                    <input v-model="part.required" type="checkbox" />
                    <span>{{ t('app.garment.required') }}</span>
                  </label>
                  <button
                    type="button"
                    class="btn-remove-part"
                    :title="t('app.garment.remove')"
                    @click="removeSelectedPart(idx)"
                  >
                    ✕
                  </button>
                </div>
              </div>
            </div>

            <!-- Loose Mapping Engine -->
            <div class="loose-mapping-section">
              <div class="loose-header-row">
                <div class="loose-title">
                  <strong>{{ t('app.garment.loose_allowance_rules') }}</strong>
                  <small>{{ t('app.garment.set_how_much_loose_is_added') }}</small>
                </div>
                <button type="button" class="btn-add-loose-row" @click="addLooseAllowanceRow">
                  {{ t('app.garment.add_loose_rule') }}
                </button>
              </div>

              <div v-if="!studioForm.looseAllowances.length" class="empty-loose-notice">
                {{ t('app.garment.no_loose_rules_set_default_fits') }}
              </div>

              <div v-else class="loose-rows-list">
                <div
                  v-for="(loose, lIdx) in studioForm.looseAllowances"
                  :key="lIdx"
                  class="loose-row-card"
                >
                  <div class="loose-inputs-line">
                    <select v-model="loose.part_name" class="loose-part-select">
                      <option v-for="p in studioForm.parts" :key="p.name" :value="p.name">
                        {{ p.name }}
                      </option>
                    </select>
                    <input
                      v-model="loose.allowance_value"
                      type="text"
                      class="loose-val-input"
                      :placeholder="t('app.garment.e_g_2_5_inch_loose')"
                    />
                    <button
                      type="button"
                      class="btn-remove-loose"
                      :title="t('app.garment.remove')"
                      @click="removeLooseAllowanceRow(lIdx)"
                    >
                      ✕
                    </button>
                  </div>

                  <!-- 1-Click Quick Loose Chips -->
                  <div class="quick-loose-chips">
                    <span class="loose-chips-lbl">{{ t('app.garment.quick_fill') }}</span>
                    <button
                      v-for="chip in quickLooseAllowancePresets"
                      :key="chip"
                      type="button"
                      class="loose-chip-btn"
                      @click="setQuickLooseValue(lIdx, chip)"
                    >
                      {{ chip }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ========================================================= -->
        <!-- COLUMN 3: DESIGN & STYLE OPTIONS BUILDER                  -->
        <!-- ========================================================= -->
        <div class="studio-col col-designs" :class="{ 'm-show': activeTabMobile === 'designs' }">
          <div class="col-header">
            <span class="col-num-badge">3</span>
            <div class="col-header-texts">
              <h3>{{ t('app.garment.design_style_options') }}</h3>
              <small>{{ t('app.garment.collar_pocket_cuff_pleat_and_other') }}</small>
            </div>
            <button
              type="button"
              class="btn-add-design-opt"
              @click="addDesignOptionGroup"
            >
              {{ t('app.garment.new_design') }}
            </button>
          </div>

          <div class="col-content-scroll">
            <!-- 1-Click Design Template Loader for Any Existing or New Dress -->
            <div class="col-design-presets-bar">
              <span class="preset-sub-lbl">{{ t('app.garment.load_ready_made_designs') }}</span>
              <div class="preset-chips-row">
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_shirt_collar_pocket_cuff_and')" @click="applyPresetDesignsToCurrentGarment('shirt')">{{ t('app.garment.shirt') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_panjabi_collar_placket_and_pocket')" @click="applyPresetDesignsToCurrentGarment('panjabi')">{{ t('app.garment.panjabi') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_pant_pleat_pocket_and_belt')" @click="applyPresetDesignsToCurrentGarment('pant')">{{ t('app.garment.pant') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_blazer_lapel_button_and_vent')" @click="applyPresetDesignsToCurrentGarment('blazer')">{{ t('app.garment.blazer') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_kameez_neck_sleeve_and_hem')" @click="applyPresetDesignsToCurrentGarment('kamiz')">{{ t('app.garment.kameez') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_burqa_pattern_and_cuff_designs')" @click="applyPresetDesignsToCurrentGarment('burqa')">{{ t('app.garment.burqa') }}</button>
                <button type="button" class="preset-chip-btn" :title="t('app.garment.add_blouse_cut_and_back_neck')" @click="applyPresetDesignsToCurrentGarment('blouse')">{{ t('app.garment.blouse') }}</button>
              </div>
            </div>

            <div v-if="!studioForm.designOptions.length" class="empty-design-notice">
              <div class="design-empty-icon">🎨</div>
              <h4>{{ t('app.garment.no_design_options_yet') }}</h4>
              <p>{{ t('app.garment.use_the_buttons_above_to_add') }}</p>
              <div class="empty-design-actions">
                <button
                  v-if="items.length > 1"
                  type="button"
                  class="btn-inline-copy-design"
                  @click="openCopyDesignModal"
                >
                  {{ t('app.garment.copy_designs_from_another_garment') }}
                </button>
              </div>
            </div>

            <div v-else class="design-groups-list">
              <div
                v-for="(optGroup, gIdx) in studioForm.designOptions"
                :key="gIdx"
                class="design-group-card"
              >
                <div class="design-group-header">
                  <input
                    v-model="optGroup.name"
                    type="text"
                    class="design-group-name-input"
                    :placeholder="t('app.garment.option_name_e_g_collar_style')"
                    required
                  />
                  <select v-model="optGroup.type" class="design-type-select">
                    <option value="select">{{ t('app.garment.single_choice_dropdown') }}</option>
                    <option value="checkbox">{{ t('app.garment.multiple_choice_checkbox') }}</option>
                    <option value="radio">{{ t('app.garment.single_choice_radio') }}</option>
                  </select>
                  <button
                    type="button"
                    class="btn-remove-group"
                    :title="t('app.garment.remove_this_design_option')"
                    @click="removeDesignOptionGroup(gIdx)"
                  >
                    ✕
                  </button>
                </div>

                <!-- Option Values List -->
                <div class="design-values-container">
                  <div
                    v-for="(val, vIdx) in optGroup.values"
                    :key="vIdx"
                    class="design-value-row"
                  >
                    <input
                      v-model="val.name"
                      type="text"
                      class="val-name-input"
                      :placeholder="t('app.garment.value_name_e_g_band_collar')"
                      required
                      @keydown.enter.prevent="addDesignOptionValue(gIdx)"
                    />
                    <div class="val-price-wrap" :title="t('app.garment.extra_price_added_to_the_making')">
                      <span class="price-plus">+৳</span>
                      <input
                        v-model.number="val.extra_price"
                        type="number"
                        min="0"
                        class="val-price-input"
                        placeholder="0"
                        @keydown.enter.prevent="addDesignOptionValue(gIdx)"
                      />
                    </div>
                    <label v-if="optGroup.type === 'select'" class="val-default-lbl" :title="t('app.garment.selected_by_default')">
                      <input
                        type="radio"
                        :name="`default_${gIdx}`"
                        :checked="val.is_default"
                        @change="optGroup.values.forEach((v, idx) => v.is_default = idx === vIdx)"
                      />
                      <span>{{ t('app.garment.default') }}</span>
                    </label>
                    <button
                      type="button"
                      class="btn-remove-val"
                      :title="t('app.garment.remove')"
                      @click="removeDesignOptionValue(gIdx, vIdx)"
                    >
                      ✕
                    </button>
                  </div>

                  <button
                    type="button"
                    class="btn-add-val-row"
                    @click="addDesignOptionValue(gIdx)"
                  >
                    {{ t('app.garment.add_value') }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Studio Modal Footer -->
      <div class="studio-modal-footer">
        <div class="footer-left">
          <span class="summary-parts-count">
            📏 {{ t('app.garment.measurements') }}: <strong>{{ studioForm.parts.length }}</strong>
          </span>
          <span class="summary-designs-count">
            🎨 {{ t('app.garment.design_options_2') }}: <strong>{{ studioForm.designOptions.length }}</strong>
          </span>
        </div>

        <div class="footer-buttons">
          <button
            type="button"
            class="btn-cancel"
            :disabled="saving"
            @click="showStudioModal = false"
          >
            {{ t('app.common.cancel') }}
          </button>
          <button
            type="button"
            class="btn-save-studio"
            :disabled="saving"
            @click="saveGarmentStudio"
          >
            <span v-if="saving" class="atelier-btn-spinner"></span>
            {{ saving ? t('app.garment.saving') : t('app.garment.finish_and_save') }}
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- ================================================================= -->
  <!-- COPY DESIGN MODAL                                                 -->
  <!-- ================================================================= -->
  <div v-if="showCopyDesignModal" class="copy-modal-backdrop" @click.self="showCopyDesignModal = false">
    <div class="copy-modal-dialog">
      <div class="copy-modal-header">
        <h3>{{ t('app.garment.copy_designs_from_another_garment_2') }}</h3>
        <button type="button" class="btn-close-modal" @click="showCopyDesignModal = false">✕</button>
      </div>

      <div class="copy-modal-body">
        <label>{{ t('app.garment.copy_design_options_from_which_garment') }}
          <select v-model="selectedSourceGarmentId" class="copy-source-select">
            <option
              v-for="g in items.filter(x => !editingGarment || x.public_id !== editingGarment.public_id)"
              :key="g.public_id"
              :value="g.public_id"
            >
              {{ g.name }} ({{ t('app.garment.p0_design_options', { p0: g.design_options?.length || 0 }) }})
            </option>
          </select>
        </label>
      </div>

      <div class="copy-modal-footer">
        <button type="button" class="btn-cancel" @click="showCopyDesignModal = false">{{ t('app.common.cancel') }}</button>
        <button type="button" class="btn-atelier-primary" @click="applyCopiedDesign">
          {{ t('app.garment.apply_copy') }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped src="../../assets/css/components/_garment-studio.scss"></style>
