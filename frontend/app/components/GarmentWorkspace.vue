<script setup lang="ts">
export interface Part {
  public_id: string;
  id?: string;
  name: string;
  slug: string;
  unit: "inch" | "cm";
  display_order: number;
  svg_asset_ref?: string;
  required: boolean;
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

export interface LooseAllowanceItem {
  part_name: string;
  allowance_value: string;
  notes?: string;
}

export interface Garment {
  public_id: string;
  id: string;
  name: string;
  slug: string;
  category: "gents" | "ladies" | "kids" | "unisex";
  group_name: string | null;
  base_making_minor: number;
  master_rate_minor: number;
  karigar_rate_minor: number;
  description?: string | null;
  loose_allowances?: LooseAllowanceItem[] | Record<string, string> | null;
  display_order?: number;
  illustration_url?: string | null;
  active: boolean;
  parts: Part[];
  design_options?: GarmentDesignOption[];
}

const api = useTailorsApi();
const toast = useToast();
const { confirm: confirmAction } = useConfirmDialog();

const items = ref<Garment[]>([]);
const loading = ref(true);
const saving = ref(false);
const error = ref("");
const searchQuery = ref("");
const selectedCategory = ref<"all" | "gents" | "ladies" | "kids" | "unisex">("all");
const selectedGroup = ref<string>("all");

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
const categorizedMeasurementBank = {
  all: [
    "লম্বা (Length)",
    "বুক (Chest)",
    "কোমর / পেট (Waist)",
    "হিপ / ছিট (Hip)",
    "হাতা (Sleeve)",
    "কলার / ব্যান্ড (Collar)",
    "তীরা / পুট (Shoulder)",
    "কফ / মোহরি (Cuff)",
    "মোহরা (Armhole)",
    "ফাড়া (Side Slit)",
    "নিচ ঘের (Bottom Gher)",
    "হাই (Rise/High)",
    "রান (Thigh)",
    "হাঁটু (Knee)",
    "বেল্ট (Belt)",
    "গলা (Neck)",
    "ক্রস ব্যাক (Cross Back)",
    "হাফ বডি",
    "বাহু (Bicep)",
    "কনুই (Elbow)",
    "সামনে গলা",
    "পিছনে গলা",
  ],
  top: [
    "লম্বা (Length)",
    "বুক (Chest)",
    "কোমর / পেট (Waist)",
    "হাতা (Sleeve)",
    "কলার / ব্যান্ড (Collar)",
    "তীরা / পুট (Shoulder)",
    "কফ / মোহরি (Cuff)",
    "মোহরা (Armhole)",
    "গলা (Neck)",
    "ক্রস ব্যাক (Cross Back)",
    "বাহু (Bicep)",
  ],
  bottom: [
    "লম্বা (Length)",
    "কোমর / পেট (Waist)",
    "হিপ / ছিট (Hip)",
    "হাই (Rise/High)",
    "রান (Thigh)",
    "হাঁটু (Knee)",
    "বেল্ট (Belt)",
    "কফ / মোহরি (Cuff)",
    "নিচ ঘের (Bottom Gher)",
  ],
  ladies: [
    "লম্বা (Length)",
    "বুক (Chest)",
    "কোমর / পেট (Waist)",
    "হিপ / ছিট (Hip)",
    "তীরা / পুট (Shoulder)",
    "হাতা (Sleeve)",
    "কফ / মোহরি (Cuff)",
    "ফাড়া (Side Slit)",
    "নিচ ঘের (Bottom Gher)",
    "সামনে গলা",
    "পিছনে গলা",
    "হাফ বডি",
  ],
};

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

const standardGroups = [
  "Panjabi / Jubbah",
  "Coat / Suit / Sherwani",
  "Shirt / Fatua / Safari",
  "Pant / Pajama / Kabli",
  "Ladies Group-1 (Kamiz/Salwar)",
  "Ladies Group-2 (Blouse)",
  "Ladies Group-3 (Lehenga/Gown)",
  "Ladies Group-4 (Burqa/Abaya)",
  "Ladies Group-5 (Maxi/Nighty)",
  "Ladies Group-6 (Frock/Skirt)",
  "Custom Production Group",
];

async function load() {
  loading.value = true;
  try {
    const res = await api.request<{ garments: Garment[] }>("/garments");
    items.value = res.data.garments;
  } catch (e: any) {
    error.value = e?.data?.errors?.[0]?.message || "পোশাকের তালিকা লোড করা যায়নি।";
  } finally {
    loading.value = false;
  }
}

const availableGroups = computed(() => {
  const source = selectedCategory.value === "all"
    ? items.value
    : items.value.filter((g) => g.category === selectedCategory.value);
  return Array.from(new Set(source.map((g) => g.group_name).filter(Boolean))) as string[];
});

const filteredGarments = computed(() => {
  return items.value.filter((g) => {
    const matchCat = selectedCategory.value === "all" || g.category === selectedCategory.value;
    const matchGroup = selectedGroup.value === "all" || g.group_name === selectedGroup.value;
    const matchSearch =
      !searchQuery.value ||
      g.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
      (g.group_name && g.group_name.toLowerCase().includes(searchQuery.value.toLowerCase()));
    return matchCat && matchGroup && matchSearch;
  });
});

const industryPresets = {
  shirt: {
    name: "Shirt (ফরমাল শার্ট)",
    category: "gents",
    group_name: "Shirt / Fatua / Safari",
    base_making: 500,
    master_rate: 60,
    karigar_rate: 100,
    description: "স্ট্যান্ডার্ড ফরমাল শার্ট কাটিং",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "পেট / কোমর (Waist)", unit: "inch", required: true },
      { name: "হিপ (Hip)", unit: "inch", required: false },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "তীরা / পুট (Shoulder)", unit: "inch", required: true },
      { name: "কলার / ব্যান্ড (Collar)", unit: "inch", required: true },
      { name: "কফ / মোহরি (Cuff)", unit: "inch", required: true },
      { name: "মোহরা (Armhole)", unit: "inch", required: false },
    ],
    loose: [
      { part_name: "বুক (Chest)", allowance_value: "+২.৫ ইঞ্চি লুজ" },
      { part_name: "পেট / কোমর (Waist)", allowance_value: "+২ ইঞ্চি লুজ" },
      { part_name: "হিপ (Hip)", allowance_value: "+২ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "প্যাটার্ন / ডিজাইন",
        type: "select",
        values: [
          { name: "Full Regular (ফুল হাতা রেগুলার)", extra_price: 0, is_default: true },
          { name: "Half Regular (হাফ হাতা)", extra_price: 0, is_default: false },
          { name: "Full Chinese (ফুল চাইনিজ)", extra_price: 0, is_default: false },
          { name: "Half Chinese (হাফ চাইনিজ)", extra_price: 0, is_default: false },
          { name: "Half Hawaiian, Side Slit", extra_price: 0, is_default: false },
          { name: "Half Hawaiian, No Side Slit", extra_price: 0, is_default: false },
          { name: "Full Hawaiian, Side Slit", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "কলার স্টাইল",
        type: "select",
        values: [
          { name: "শার্ট কলার (Spread Collar)", extra_price: 0, is_default: true },
          { name: "ব্যান / চাইনিজ কলার", extra_price: 0, is_default: false },
          { name: "বাটন ডাউন কলার", extra_price: 0, is_default: false },
          { name: "ওয়াইড স্প্রেড কলার", extra_price: 0, is_default: false },
          { name: "ম্যান্ডারিন কলার", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "প্লিট (Pleat)",
        type: "select",
        values: [
          { name: "Plain (প্লিট ছাড়া)", extra_price: 0, is_default: true },
          { name: "Box Pleat (মাঝখানে বক্স প্লিট)", extra_price: 0, is_default: false },
          { name: "Side Pleat (দুই পাশে প্লিট)", extra_price: 0, is_default: false },
          { name: "Center Inverted Pleat", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "কফ ডিজাইন",
        type: "select",
        values: [
          { name: "কাট কফ (Cut Cuff)", extra_price: 0, is_default: true },
          { name: "গোল কফ (Round Cuff)", extra_price: 0, is_default: false },
          { name: "সোজা কফ (Square Cuff)", extra_price: 0, is_default: false },
          { name: "ফ্রেঞ্চ কফ (ডাবল কফ)", extra_price: 50, is_default: false },
        ],
      },
      {
        name: "পকেট স্টাইল",
        type: "select",
        values: [
          { name: "১ বুক পকেট (Plain)", extra_price: 0, is_default: true },
          { name: "১ পকেট (V সেলাইসহ)", extra_price: 0, is_default: false },
          { name: "২ ফ্ল্যাপ পকেট (Safari Style)", extra_price: 30, is_default: false },
          { name: "১.৫০ পকেট (পেন স্লটসহ)", extra_price: 20, is_default: false },
          { name: "পকেট ছাড়া (No Pocket)", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "সোল্ডার / তীরা",
        type: "select",
        values: [
          { name: "রেগুলার সিঙ্গেল তীরা", extra_price: 0, is_default: true },
          { name: "স্প্লিট তীরা (Split Yoke)", extra_price: 0, is_default: false },
          { name: "সিমলেস তীরা (Seamless)", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "অতিরিক্ত সেলাই ও স্টাইল",
        type: "checkbox",
        values: [
          { name: "হাতা+পকেটে ফোল্ডিং হবে", extra_price: 100, is_default: false },
          { name: "সেম্পল ডিজাইন", extra_price: 100, is_default: false },
          { name: "প্লেট+কপ+কলার ১ পয়েন্ট চওড়া সেলাই", extra_price: 50, is_default: false },
          { name: "পকেটে V সেলাই", extra_price: 0, is_default: false },
          { name: "হাতাই কুচি হবে না", extra_price: 0, is_default: false },
          { name: "ছবি অনুযায়ী স্পেশাল ডিজাইন", extra_price: 150, is_default: false },
        ],
      },
    ],
  },
  panjabi: {
    name: "Panjabi (পাঞ্জাবী)",
    category: "gents",
    group_name: "Panjabi / Jubbah",
    base_making: 450,
    master_rate: 50,
    karigar_rate: 90,
    description: "রেগুলার / সেমি-ফিটিংস সুতি পাঞ্জাবী",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "কোমর / পেট (Waist)", unit: "inch", required: true },
      { name: "হিপ (Hip)", unit: "inch", required: true },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "তীরা / পুট (Shoulder)", unit: "inch", required: true },
      { name: "কলার / ব্যান্ড (Collar)", unit: "inch", required: true },
      { name: "নিচ ঘের (Bottom Gher)", unit: "inch", required: true },
    ],
    loose: [
      { part_name: "বুক (Chest)", allowance_value: "+৩ ইঞ্চি লুজ" },
      { part_name: "কোমর / পেট (Waist)", allowance_value: "+২.৫ ইঞ্চি লুজ" },
      { part_name: "হিপ (Hip)", allowance_value: "+৩ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "কলার স্টাইল",
        type: "select",
        values: [
          { name: "ব্যান কলার", extra_price: 0, is_default: true },
          { name: "শেরওয়ানি হাই কলার", extra_price: 0, is_default: false },
          { name: "ওপেন চাইনিজ কলার", extra_price: 0, is_default: false },
          { name: "শার্ট কলার", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "প্লেট ডিজাইন",
        type: "select",
        values: [
          { name: "লুকানো বোতাম প্লেট (Hidden Placket)", extra_price: 0, is_default: true },
          { name: "ওপেন ফ্ল্যাট প্লেট", extra_price: 0, is_default: false },
          { name: "জামদানি পাইপিন প্লেট", extra_price: 50, is_default: false },
        ],
      },
      {
        name: "পকেট স্টাইল",
        type: "select",
        values: [
          { name: "২ সাইড পকেট", extra_price: 0, is_default: true },
          { name: "১ সাইড পকেট", extra_price: 0, is_default: false },
          { name: "বুক পকেট + ২ সাইড পকেট", extra_price: 30, is_default: false },
          { name: "চেইন ওয়ালেট পকেট", extra_price: 20, is_default: false },
        ],
      },
      {
        name: "হাতা ও মোহরি",
        type: "select",
        values: [
          { name: "সাধারণ খোলা হাতা", extra_price: 0, is_default: true },
          { name: "শার্ট কাপ হাতা", extra_price: 30, is_default: false },
          { name: "কাবলি ফোল্ডিং হাতা", extra_price: 50, is_default: false },
        ],
      },
      {
        name: "অতিরিক্ত সেলাই ও ডিজাইন",
        type: "checkbox",
        values: [
          { name: "হাতা+পকেটে ফোল্ডিং ডিজাইন", extra_price: 100, is_default: false },
          { name: "পাইপিন / সেম্পল ডিজাইন", extra_price: 100, is_default: false },
          { name: "১ পয়েন্ট ডাবল সেলাই", extra_price: 50, is_default: false },
          { name: "এমব্রয়ডারি / কারচুপি কাজ", extra_price: 250, is_default: false },
        ],
      },
    ],
  },
  pant: {
    name: "Pant (ফরমাল প্যান্ট)",
    category: "gents",
    group_name: "Pant / Pajama / Kabli",
    base_making: 400,
    master_rate: 40,
    karigar_rate: 80,
    description: "ফরমাল ট্রাউজার / গ্যাবার্ডিন প্যান্ট",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "কোমর / পেট (Waist)", unit: "inch", required: true },
      { name: "হিপ / ছিট (Hip)", unit: "inch", required: true },
      { name: "হাই (Rise/High)", unit: "inch", required: true },
      { name: "রান (Thigh)", unit: "inch", required: true },
      { name: "হাঁটু (Knee)", unit: "inch", required: false },
      { name: "কফ / মোহরি (Cuff)", unit: "inch", required: true },
    ],
    loose: [
      { part_name: "রান (Thigh)", allowance_value: "+২ ইঞ্চি লুজ" },
      { part_name: "হিপ / ছিট (Hip)", allowance_value: "+১.৫ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "প্লিট স্টাইল",
        type: "select",
        values: [
          { name: "প্লিট ছাড়া (Flat Front)", extra_price: 0, is_default: true },
          { name: "১ প্লিট (Single Pleat)", extra_price: 0, is_default: false },
          { name: "২ প্লিট (Double Pleat)", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "পকেট স্টাইল",
        type: "select",
        values: [
          { name: "ক্রস পকেট (Cross Pocket)", extra_price: 0, is_default: true },
          { name: "স্ট্রেইট পকেট (Straight)", extra_price: 0, is_default: false },
          { name: "ব্যাক ডাবল পকেট", extra_price: 20, is_default: false },
          { name: "কয়েন / সিক্রেট পকেট", extra_price: 10, is_default: false },
        ],
      },
      {
        name: "বেল্ট ও লুপ",
        type: "select",
        values: [
          { name: "স্ট্যান্ডার্ড বেল্ট লুপ", extra_price: 0, is_default: true },
          { name: "গ্রিপার ওয়েস্টব্যান্ড", extra_price: 30, is_default: false },
          { name: "এক্সটেন্ডেড ট্যাব বাটন", extra_price: 20, is_default: false },
        ],
      },
      {
        name: "বটম ফিনিশিং",
        type: "select",
        values: [
          { name: "প্লেন বটম (Plain Hem)", extra_price: 0, is_default: true },
          { name: "টার্ন-আপ ফোল্ডিং (Cuff Hem)", extra_price: 30, is_default: false },
        ],
      },
    ],
  },
  blazer: {
    name: "Blazer / Suit (ব্লেজার / স্যুট)",
    category: "gents",
    group_name: "Coat / Suit / Sherwani",
    base_making: 2500,
    master_rate: 350,
    karigar_rate: 800,
    description: "টেইলার্ড প্রিমিয়াম স্যুট ও ব্লেজার",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "কোমর / পেট (Waist)", unit: "inch", required: true },
      { name: "হিপ (Hip)", unit: "inch", required: true },
      { name: "তীরা / পুট (Shoulder)", unit: "inch", required: true },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "গলা (Neck)", unit: "inch", required: true },
      { name: "ক্রস ব্যাক (Cross Back)", unit: "inch", required: false },
    ],
    loose: [
      { part_name: "বুক (Chest)", allowance_value: "+৩.৫ ইঞ্চি লুজ" },
      { part_name: "কোমর / পেট (Waist)", allowance_value: "+২.৫ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "ল্যাপেল স্টাইল",
        type: "select",
        values: [
          { name: "নচ ল্যাপেল (Notch Lapel)", extra_price: 0, is_default: true },
          { name: "পিক ল্যাপেল (Peak Lapel)", extra_price: 0, is_default: false },
          { name: "শাল ল্যাপেল (Shawl Lapel)", extra_price: 50, is_default: false },
        ],
      },
      {
        name: "বাটন কনফিগারেশন",
        type: "select",
        values: [
          { name: "২ বাটন সিঙ্গেল ব্রেস্টেড", extra_price: 0, is_default: true },
          { name: "১ বাটন পার্টি ব্লেজার", extra_price: 0, is_default: false },
          { name: "ডাবল ব্রেস্টেড (Double Breasted)", extra_price: 150, is_default: false },
        ],
      },
      {
        name: "ভেন্ট / ব্যাক কাট",
        type: "select",
        values: [
          { name: "ডাবল সাইড ভেন্ট", extra_price: 0, is_default: true },
          { name: "সিঙ্গেল সেন্টার ভেন্ট", extra_price: 0, is_default: false },
          { name: "নো ভেন্ট (ইটালিয়ান কাট)", extra_price: 0, is_default: false },
        ],
      },
      {
        name: "পকেট স্টাইল",
        type: "select",
        values: [
          { name: "ফ্ল্যাপ পকেট", extra_price: 0, is_default: true },
          { name: "টিকিট পকেটসহ ফ্ল্যাপ", extra_price: 50, is_default: false },
          { name: "প্যাচ পকেট (ক্যাজুয়াল)", extra_price: 0, is_default: false },
        ],
      },
    ],
  },
  kamiz: {
    name: "Kamiz (লেডিস কামিজ)",
    category: "ladies",
    group_name: "Ladies Group-1 (Kamiz/Salwar)",
    base_making: 350,
    master_rate: 50,
    karigar_rate: 80,
    description: "থ্রি-পিস কামিজ কাটিং",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "কোমর / পেট (Waist)", unit: "inch", required: true },
      { name: "হিপ / ছিট (Hip)", unit: "inch", required: true },
      { name: "তীরা / পুট (Shoulder)", unit: "inch", required: true },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "কফ / মোহরি (Cuff)", unit: "inch", required: true },
      { name: "ফাড়া (Side Slit)", unit: "inch", required: true },
      { name: "নিচ ঘের (Bottom Gher)", unit: "inch", required: true },
    ],
    loose: [
      { part_name: "বুক (Chest)", allowance_value: "+২ ইঞ্চি লুজ" },
      { part_name: "কোমর / পেট (Waist)", allowance_value: "+১.৫ ইঞ্চি লুজ" },
      { part_name: "হিপ / ছিট (Hip)", allowance_value: "+২ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "গলার ডিজাইন",
        type: "select",
        values: [
          { name: "পান গলা (V Neck)", extra_price: 0, is_default: true },
          { name: "গোল গলা (Round Neck)", extra_price: 0, is_default: false },
          { name: "ব্যান গলা (High Neck)", extra_price: 0, is_default: false },
          { name: "বোট নেক (Boat Neck)", extra_price: 0, is_default: false },
          { name: "স্টার গলা", extra_price: 30, is_default: false },
          { name: "আংরাখা ওভারল্যাপ", extra_price: 100, is_default: false },
        ],
      },
      {
        name: "হাতার ডিজাইন",
        type: "select",
        values: [
          { name: "সাধারণ হাতা", extra_price: 0, is_default: true },
          { name: "থ্রি-কোয়ার্টার হাতা", extra_price: 0, is_default: false },
          { name: "বেল হাতা / ঘটি হাতা", extra_price: 50, is_default: false },
          { name: "কাট হাতা (Sleeveless)", extra_price: 0, is_default: false },
          { name: "বাটারফ্লাই হাতা", extra_price: 50, is_default: false },
        ],
      },
      {
        name: "ঘের ও কাটিং",
        type: "select",
        values: [
          { name: "স্ট্রেইট সাইড ফাড়া (Regular)", extra_price: 0, is_default: true },
          { name: "এ-লাইন ফ্রক কাট", extra_price: 50, is_default: false },
          { name: "আনারকলি গাউন কাট", extra_price: 150, is_default: false },
        ],
      },
      {
        name: "অতিরিক্ত পাইপিন ও ডিজাইন",
        type: "checkbox",
        values: [
          { name: "গলা ও হাতায় পাইপিন", extra_price: 50, is_default: false },
          { name: "সাইড ফাড়ে পাইপিন", extra_price: 50, is_default: false },
          { name: "প্রিমিয়াম লেইস ফিটিংস", extra_price: 100, is_default: false },
          { name: "সম্পূর্ণ ইনার আস্তর", extra_price: 150, is_default: false },
        ],
      },
    ],
  },
  burqa: {
    name: "Burqa / Abaya (বোরকা)",
    category: "ladies",
    group_name: "Ladies Group-4 (Burqa/Abaya)",
    base_making: 650,
    master_rate: 80,
    karigar_rate: 150,
    description: "দুবাই স্টাইল আবায়া / বোরকা",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "তীরা / পুট (Shoulder)", unit: "inch", required: true },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "কফ / মোহরি (Cuff)", unit: "inch", required: true },
      { name: "নিচ ঘের (Bottom Gher)", unit: "inch", required: true },
    ],
    loose: [
      { part_name: "বুক (Chest)", allowance_value: "+৪ ইঞ্চি লুজ" },
    ],
    designs: [
      {
        name: "বোরকা স্টাইল",
        type: "select",
        values: [
          { name: "ফ্রন্ট ওপেন (বাটনসহ)", extra_price: 0, is_default: true },
          { name: "ক্লোজড গাউন স্টাইল", extra_price: 0, is_default: false },
          { name: "কিমোনো বাটারফ্লাই", extra_price: 100, is_default: false },
          { name: "কাফতান আবায়া", extra_price: 100, is_default: false },
        ],
      },
      {
        name: "হাতা ও কফ",
        type: "select",
        values: [
          { name: "ইলাস্টিক কফ হাতা", extra_price: 0, is_default: true },
          { name: "লুপ বাটন কফ", extra_price: 0, is_default: false },
          { name: "লেইস এমব্রয়ডারি হাতা", extra_price: 80, is_default: false },
        ],
      },
    ],
  },
  blouse: {
    name: "Blouse (লেডিস ব্লাউজ)",
    category: "ladies",
    group_name: "Ladies Group-2 (Blouse)",
    base_making: 300,
    master_rate: 40,
    karigar_rate: 80,
    description: "শাড়ির ব্লাউজ",
    parts: [
      { name: "লম্বা (Length)", unit: "inch", required: true },
      { name: "বুক (Chest)", unit: "inch", required: true },
      { name: "কোমর / পেট (Waist)", unit: "inch", required: true },
      { name: "সামনে গলা", unit: "inch", required: true },
      { name: "পিছনে গলা", unit: "inch", required: true },
      { name: "হাতা (Sleeve)", unit: "inch", required: true },
      { name: "কফ / মোহরি (Cuff)", unit: "inch", required: true },
    ],
    loose: [],
    designs: [
      {
        name: "কাটিং প্যাটার্ন",
        type: "select",
        values: [
          { name: "প্রিন্সেস কাট (Princess Cut)", extra_price: 50, is_default: true },
          { name: "৪ ডাট কাট (Plain 4 Dart)", extra_price: 0, is_default: false },
          { name: "ক্রস কাট (Katori Cut)", extra_price: 50, is_default: false },
          { name: "সব্যসাচী ডিপ প্লাঞ্জ", extra_price: 100, is_default: false },
        ],
      },
      {
        name: "ওপেনিং ও হুক",
        type: "select",
        values: [
          { name: "ব্যাক ওপেন হুক", extra_price: 0, is_default: true },
          { name: "ফ্রন্ট ওপেন হুক", extra_price: 0, is_default: false },
          { name: "সাইড চেইন জিপার", extra_price: 30, is_default: false },
        ],
      },
      {
        name: "ব্যাক নেক স্টাইল",
        type: "select",
        values: [
          { name: "ডিপ রাউন্ড উইথ ডোরি (লটকনসহ)", extra_price: 30, is_default: true },
          { name: "বোট নেক ব্যাক", extra_price: 0, is_default: false },
          { name: "কিহোল ব্যাক", extra_price: 20, is_default: false },
        ],
      },
    ],
  },
};

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
  toast.success(`✓ "${p.name}" টেমপ্লেট সফলভাবে লোড হয়েছে!`);
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
    toast.success(`✓ "${p.name}"-এর ডিজাইন অপশনসমূহ বিদ্যমান পোশাকের সাথে যুক্ত হয়েছে!`);
  } else {
    studioForm.designOptions = newDesigns;
    toast.success(`✓ "${p.name}"-এর সম্পূর্ণ ডিজাইন টেমপ্লেট লোড হয়েছে!`);
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
    toast.warning("এই মাপটি ইতিমধ্যে যুক্ত রয়েছে।");
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
    toast.warning("উৎস পোশাকে কোনো ডিজাইন অপশন পাওয়া যায়নি।");
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
  toast.success(`✓ "${source.name}" থেকে ডিজাইন অপশন সফলভাবে কপি করা হয়েছে!`);
  showCopyDesignModal.value = false;
}

// Save Garment (Create or Update)
async function saveGarmentStudio() {
  if (!studioForm.name.trim()) {
    toast.warning("পোশাকের নাম লিখুন।");
    activeTabMobile.value = "general";
    return;
  }
  if (!studioForm.parts.length) {
    toast.warning("কমপক্ষে একটি মাপের পয়েন্ট যুক্ত করুন।");
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

      toast.success(`✓ "${studioForm.name}" পোশাকের তথ্য ও ডিজাইন সফলভাবে আপডেট হয়েছে!`);
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

      toast.success(`✓ "${studioForm.name}" নতুন পোশাক সফলভাবে তৈরি হয়েছে!`);
    }

    showStudioModal.value = false;
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "সংরক্ষণ করতে ব্যর্থ হয়েছে।");
  } finally {
    saving.value = false;
  }
}

// 1-Click Clone Service
async function cloneGarment(garment: Garment) {
  const confirmed = await confirmAction({
    title: "পোশাকের ধরন ক্লোন (কপি) করবেন?",
    message: `"${garment.name}"-এর সব মাপসমূহ, লুজ ম্যাপিং এবং ডিজাইন অপশন কপি করে একটি নতুন পোশাক তৈরি হবে।`,
    confirmText: "হ্যাঁ, ক্লোন করুন",
    cancelText: "বাতিল",
  });
  if (!confirmed) return;

  try {
    await api.request(`/garments/${garment.public_id}/clone`, {
      method: "POST",
    });
    toast.success(`✓ "${garment.name}" সফলভাবে ক্লোন করা হয়েছে!`);
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "ক্লোন করতে ব্যর্থ হয়েছে।");
  }
}

// Toggle Quick Status (Active / Inactive)
async function toggleActiveStatus(garment: Garment) {
  try {
    const nextState = !garment.active;
    await api.request(`/garments/${garment.public_id}`, {
      method: "PATCH",
      body: { active: nextState },
    });
    garment.active = nextState;
    toast.success(`✓ "${garment.name}" ${nextState ? "সক্রিয় (Active)" : "নিষ্ক্রিয় (Inactive)"} করা হয়েছে`);
  } catch (e: any) {
    toast.error("স্ট্যাটাস পরিবর্তন ব্যর্থ হয়েছে।");
  }
}

// Delete Garment
async function deleteGarment(garment: Garment) {
  const confirmed = await confirmAction({
    title: "পোশাকের ধরন মুছে ফেলবেন?",
    message: `আপনি কি নিশ্চিত যে "${garment.name}" মুছে ফেলতে চান? এটি পুনরায় ফিরিয়ে আনা যাবে না।`,
    confirmText: "হ্যাঁ, মুছে ফেলুন",
    cancelText: "বাতিল",
  });
  if (!confirmed) return;

  try {
    await api.request(`/garments/${garment.public_id}`, {
      method: "DELETE",
    });
    toast.success(`✓ "${garment.name}" মুছে ফেলা হয়েছে।`);
    await load();
  } catch (e: any) {
    toast.error(e?.data?.errors?.[0]?.message || "মুছে ফেলতে ব্যর্থ হয়েছে।");
  }
}

onMounted(() => {
  load();
});
</script>

<template>
  <div class="garment-studio-page">
    <!-- Header Section -->
    <header class="page-topbar">
      <div class="topbar-title-block">
        <div class="badge-atelier">ATELIER STUDIO</div>
        <h1>👔 পোশাক ও সার্ভিস ব্যবস্থাপনা (Services & Designs)</h1>
        <p class="subtitle">পোশাকের ধরন, মেকিং রেট, কারিগর মজুরি, মাপসমূহ এবং কলার/পকেট ডিজাইন অপশন পরিচালনা করুন</p>
      </div>

      <div class="topbar-actions">
        <button type="button" class="btn-atelier-primary" @click="openCreateModal">
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="5" x2="12" y2="19"/>
            <line x1="5" y1="12" x2="19" y2="12"/>
          </svg>
          + নতুন পোশাক তৈরি করুন
        </button>
      </div>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="toolbar-card">
      <div class="category-tabs">
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'all' }"
          @click="selectedCategory = 'all'"
        >
          সব পোশাক ({{ items.length }})
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'gents' }"
          @click="selectedCategory = 'gents'"
        >
          👨 পুরুষ (Gents)
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'ladies' }"
          @click="selectedCategory = 'ladies'"
        >
          👩 মহিলা (Ladies)
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'kids' }"
          @click="selectedCategory = 'kids'"
        >
          🧒 বাচ্চা (Kids)
        </button>
        <button
          type="button"
          class="tab-btn"
          :class="{ 'is-active': selectedCategory === 'unisex' }"
          @click="selectedCategory = 'unisex'"
        >
          👥 ইউনিসেক্স
        </button>
      </div>

      <div class="toolbar-right">
        <div class="search-box">
          <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="11" cy="11" r="8"/>
            <line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input
            v-model="searchQuery"
            type="text"
            placeholder="পোশাকের নাম বা গ্রুপ খুঁজুন..."
          />
        </div>

        <select v-if="availableGroups.length" v-model="selectedGroup" class="select-group-filter">
          <option value="all">সব গ্রুপ (All Groups)</option>
          <option v-for="g in availableGroups" :key="g" :value="g">{{ g }}</option>
        </select>
      </div>
    </div>

    <!-- Main Garments Table Card -->
    <div class="main-table-card">
      <div v-if="loading" class="state-loading">
        <div class="atelier-spinner"></div>
        <span>পোশাকের ডাটা লোড হচ্ছে...</span>
      </div>

      <div v-else-if="error" class="state-error">
        <span>⚠️ {{ error }}</span>
        <button type="button" class="btn-retry" @click="load">পুনরায় চেষ্টা করুন</button>
      </div>

      <div v-else-if="!filteredGarments.length" class="state-empty">
        <div class="empty-icon">✂️</div>
        <h3>কোনো পোশাক পাওয়া যায়নি</h3>
        <p>নতুন পোশাক যুক্ত করতে ওপরের "+ নতুন পোশাক তৈরি করুন" বাটনে ক্লিক করুন।</p>
      </div>

      <div v-else class="table-responsive">
        <table class="atelier-table">
          <thead>
            <tr>
              <th style="width: 50px;">ছবি</th>
              <th>পোশাকের নাম ও গ্রুপ</th>
              <th>পরিমাপের পয়েন্টসমূহ (Parts)</th>
              <th>ডিজাইন অপশনসমূহ</th>
              <th class="text-right">কাস্টমারের মূল্য</th>
              <th class="text-right">মাস্টার ফি</th>
              <th class="text-right">কারিগর ফি</th>
              <th class="text-center" style="width: 110px;">স্ট্যাটাস</th>
              <th class="text-right" style="width: 180px;">অ্যাকশন</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="garment in filteredGarments" :key="garment.public_id" :class="{ 'is-inactive-row': !garment.active }">
              <td class="col-img">
                <div class="garment-avatar">
                  {{ garment.category === 'ladies' ? '🥻' : garment.category === 'kids' ? '👶' : '👔' }}
                </div>
              </td>
              <td class="col-name">
                <strong class="garment-title">{{ garment.name }}</strong>
                <span class="garment-group-sub">{{ garment.group_name || 'সাধারণ ক্যাটাগরি' }}</span>
              </td>
              <td class="col-parts">
                <div class="parts-badges-wrap">
                  <span v-for="p in garment.parts.slice(0, 4)" :key="p.slug" class="badge-part-chip">
                    {{ p.name }}
                  </span>
                  <span v-if="garment.parts.length > 4" class="badge-part-more">
                    +{{ garment.parts.length - 4 }}টি আরও
                  </span>
                </div>
              </td>
              <td class="col-designs">
                <div
                  v-if="garment.design_options?.length"
                  class="design-summary-chip is-clickable"
                  :title="garment.design_options.map(d => d.name).join(', ') + ' (ক্লিক করে ডিজাইন এডিট করুন)'"
                  @click="openEditModal(garment, 'designs')"
                >
                  🎨 {{ garment.design_options.length }}টি অপশন ({{ garment.design_options.reduce((acc, curr) => acc + (curr.values?.length || 0), 0) }}টি ভ্যালু)
                </div>
                <button
                  v-else
                  type="button"
                  class="btn-add-design-pill"
                  title="এই পোশাকে কলার, পকেট ও অন্যান্য ডিজাইন স্টাইল যুক্ত করুন"
                  @click="openEditModal(garment, 'designs')"
                >
                  + ডিজাইন যোগ করুন
                </button>
              </td>
              <td class="col-price text-right">
                <span class="price-val">৳ {{ Math.round((garment.base_making_minor || 0) / 100) }}</span>
              </td>
              <td class="col-master text-right">
                <span class="fee-val">৳ {{ Math.round((garment.master_rate_minor || 0) / 100) }}</span>
              </td>
              <td class="col-karigar text-right">
                <span class="fee-val">৳ {{ Math.round((garment.karigar_rate_minor || 0) / 100) }}</span>
              </td>
              <td class="col-status text-center">
                <button
                  type="button"
                  class="toggle-status-pill"
                  :class="{ 'is-active': garment.active, 'is-inactive': !garment.active }"
                  :title="garment.active ? 'অর্ডারে সক্রিয় (ক্লিক করে বন্ধ করুন)' : 'অর্ডারে বন্ধ (ক্লিক করে চালু করুন)'"
                  @click="toggleActiveStatus(garment)"
                >
                  {{ garment.active ? '● সক্রিয়' : '○ বন্ধ' }}
                </button>
              </td>
              <td class="col-actions text-right">
                <div class="action-btn-group">
                  <button
                    type="button"
                    class="btn-act btn-act-edit"
                    title="সম্পাদনা করুন (Edit 3-Column Studio)"
                    @click="openEditModal(garment)"
                  >
                    ✏️ এডিট
                  </button>
                  <button
                    type="button"
                    class="btn-act btn-act-clone"
                    title="ক্লোন / কপি করুন (Clone)"
                    @click="cloneGarment(garment)"
                  >
                    📋 ক্লোন
                  </button>
                  <button
                    type="button"
                    class="btn-act btn-act-del"
                    title="মুছে ফেলুন (Delete)"
                    @click="deleteGarment(garment)"
                  >
                    🗑️
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ================================================================= -->
    <!-- 3-COLUMN ATELIER GARMENT STUDIO MODAL                             -->
    <!-- ================================================================= -->
    <div v-if="showStudioModal" class="studio-modal-backdrop" @click.self="showStudioModal = false">
      <div class="studio-modal-dialog">
        <!-- Modal Topbar Header -->
        <div class="studio-modal-header">
          <div class="header-left">
            <span class="modal-badge-atelier">GARMENTS STUDIO</span>
            <h2>{{ editingGarment ? `পোশাকের ধরন এডিট করুন: ${editingGarment.name}` : '+ নতুন পোশাক তৈরি ও ডিজাইন কনফিগারেশন' }}</h2>
          </div>

          <div class="header-actions">
            <button
              v-if="items.length > 1"
              type="button"
              class="btn-copy-design-header"
              title="অন্য যেকোনো পোশাক থেকে ডিজাইন স্টাইল ও অপশন ১-ক্লিকে কপি করুন"
              @click="openCopyDesignModal"
            >
              📋 Copy Design
            </button>
            <button
              type="button"
              class="btn-close-modal"
              title="বন্ধ করুন"
              @click="showStudioModal = false"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- 1-Click Industry Presets Banner for Instant Setup -->
        <div v-if="!editingGarment" class="modal-presets-banner">
          <span class="preset-title">⚡ ১-ক্লিকে রেডিমেড টেমপ্লেট লোড করুন:</span>
          <div class="preset-pills-row">
            <button type="button" class="preset-pill-btn" @click="loadPreset('shirt')">👔 ফরমাল শার্ট</button>
            <button type="button" class="preset-pill-btn" @click="loadPreset('panjabi')">🥻 পাঞ্জাবী</button>
            <button type="button" class="preset-pill-btn" @click="loadPreset('pant')">👖 ফরমাল প্যান্ট</button>
            <button type="button" class="preset-pill-btn" @click="loadPreset('kamiz')">🥻 লেডিস কামিজ</button>
            <button type="button" class="preset-pill-btn" @click="loadPreset('burqa')">🧕 বোরকা / আবায়া</button>
            <button type="button" class="preset-pill-btn" @click="loadPreset('blouse')">🥻 লেডিস ব্লাউজ</button>
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
            📌 ১. সাধারণ ও মজুরি
          </button>
          <button
            type="button"
            class="m-tab-btn"
            :class="{ 'is-m-active': activeTabMobile === 'measurements' }"
            @click="activeTabMobile = 'measurements'"
          >
            📏 ২. মাপ ও লুজ ম্যাপিং
          </button>
          <button
            type="button"
            class="m-tab-btn"
            :class="{ 'is-m-active': activeTabMobile === 'designs' }"
            @click="activeTabMobile = 'designs'"
          >
            🎨 ৩. ডিজাইন ও স্টাইল
          </button>
        </div>

        <!-- Studio Modal 3-Column Body -->
        <div class="studio-modal-body">
          <!-- ========================================================= -->
          <!-- COLUMN 1: GENERAL INFORMATION & PRICING                   -->
          <!-- ========================================================= -->
          <div class="studio-col col-general" :class="{ 'm-show': activeTabMobile === 'general' }">
            <div class="col-header">
              <span class="col-num-badge">১</span>
              <h3>📌 সাধারণ ও মজুরি তথ্য (General & Rates)</h3>
            </div>

            <div class="col-content-scroll">
              <div class="form-group">
                <label>পোশাকের নাম (Garment Name) *</label>
                <input
                  v-model="studioForm.name"
                  type="text"
                  placeholder="যেমন: Shirt, পাঞ্জাবী, প্যান্ট, ব্লেজার"
                  required
                />
              </div>

              <div class="form-row-2">
                <div class="form-group">
                  <label>ক্যাটাগরি *</label>
                  <select v-model="studioForm.category">
                    <option value="gents">👨 পুরুষ (Gents)</option>
                    <option value="ladies">👩 মহিলা (Ladies)</option>
                    <option value="kids">🧒 বাচ্চা (Kids)</option>
                    <option value="unisex">👥 ইউনিসেক্স</option>
                  </select>
                </div>

                <div class="form-group">
                  <label>প্রোডাকশন গ্রুপ</label>
                  <select v-model="studioForm.group_name">
                    <option v-for="g in standardGroups" :key="g" :value="g">{{ g }}</option>
                  </select>
                </div>
              </div>

              <div class="rates-box-card">
                <div class="rates-box-title">
                  <span>💰 পারিশ্রমিক ও রেট কনফিগারেশন</span>
                  <div class="live-profit-pill" :class="{ 'is-negative': liveShopMargin.net < 0 }">
                    দোকানের নিট লাভ: <strong>৳{{ liveShopMargin.net }}</strong> ({{ liveShopMargin.pct }}%)
                  </div>
                </div>

                <div class="form-group">
                  <label>কাস্টমারের মূল্য / বেস মেকিং রেট (৳) *</label>
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
                    <label>মাস্টার ফি (কাটিং) ৳</label>
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
                    <label>কারিগর ফি (সেলাই) ৳</label>
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
                <label>বিবরণ ও কাটিং সংক্রান্ত নোট (Description)</label>
                <textarea
                  v-model="studioForm.description"
                  rows="3"
                  placeholder="কাটিং বা সেলাই সংক্রান্ত বিশেষ সাধারণ নির্দেশনা লিখুন..."
                ></textarea>
              </div>

              <div class="active-toggle-card">
                <label class="switch-lbl">
                  <input v-model="studioForm.active" type="checkbox" />
                  <span class="slider"></span>
                </label>
                <div class="switch-texts">
                  <strong>অর্ডারে দৃশ্যমান (Active Status)</strong>
                  <small>চালু রাখলে নিউ অর্ডার ফর্মে এই পোশাকটি শো করবে।</small>
                </div>
              </div>
            </div>
          </div>

          <!-- ========================================================= -->
          <!-- COLUMN 2: MEASUREMENTS & LOOSE MAPPING                    -->
          <!-- ========================================================= -->
          <div class="studio-col col-measurements" :class="{ 'm-show': activeTabMobile === 'measurements' }">
            <div class="col-header">
              <span class="col-num-badge">২</span>
              <h3>📏 মাপ ও লুজ ম্যাপিং (Measurements & Loose)</h3>
            </div>

            <div class="col-content-scroll">
              <!-- Available Measurement Points Bank with Category Filters & Search -->
              <div class="parts-bank-section">
                <div class="section-sub-title">
                  <span>উপলব্ধ মাপের ব্যাংক (Measurement Bank)</span>
                  <small>ক্লিক করে যুক্ত বা বাদ দিন</small>
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
                      সব মাপ
                    </button>
                    <button
                      type="button"
                      class="bank-cat-pill"
                      :class="{ 'is-active-cat': selectedBankCategory === 'top' }"
                      @click="selectedBankCategory = 'top'"
                    >
                      👔 টপস
                    </button>
                    <button
                      type="button"
                      class="bank-cat-pill"
                      :class="{ 'is-active-cat': selectedBankCategory === 'bottom' }"
                      @click="selectedBankCategory = 'bottom'"
                    >
                      👖 বটমস
                    </button>
                    <button
                      type="button"
                      class="bank-cat-pill"
                      :class="{ 'is-active-cat': selectedBankCategory === 'ladies' }"
                      @click="selectedBankCategory = 'ladies'"
                    >
                      🥻 লেডিস
                    </button>
                  </div>
                  <input
                    v-model="bankSearchQuery"
                    type="text"
                    class="bank-search-input"
                    placeholder="পিল খুঁজুন..."
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
                  placeholder="নতুন কাস্টম মাপের নাম..."
                  @keydown.enter.prevent="addCustomPart"
                />
                <select v-model="newCustomPartUnit">
                  <option value="inch">ইঞ্চি (inch)</option>
                  <option value="cm">সেমি (cm)</option>
                </select>
                <button type="button" class="btn-add-custom-part" @click="addCustomPart">
                  + যুক্ত করুন
                </button>
              </div>

              <!-- Selected Parts List -->
              <div class="selected-parts-section">
                <div class="section-sub-title">
                  <span>নির্বাচিত মাপসমূহ (Selected {{ studioForm.parts.length }} Parts) *</span>
                </div>

                <div v-if="!studioForm.parts.length" class="empty-parts-notice">
                  ⚠️ কোনো মাপের পয়েন্ট নির্বাচিত নেই! ওপরের ব্যাংক থেকে সিলেক্ট করুন।
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
                      <option value="inch">inch</option>
                      <option value="cm">cm</option>
                    </select>
                    <label class="part-req-chk" title="আবশ্যক মাপ">
                      <input v-model="part.required" type="checkbox" />
                      <span>আবশ্যক</span>
                    </label>
                    <button
                      type="button"
                      class="btn-remove-part"
                      title="বাদ দিন"
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
                    <strong>🔀 লুজ ম্যাপিং ইঞ্জিন (Loose Allowance Mapping)</strong>
                    <small>ফিটিংস অনুযায়ী প্রতিটি পয়েন্টে কতটুকু লুজ যোগ হবে তা নির্ধারণ করুন</small>
                  </div>
                  <button type="button" class="btn-add-loose-row" @click="addLooseAllowanceRow">
                    + লুজ রুল যোগ করুন
                  </button>
                </div>

                <div v-if="!studioForm.looseAllowances.length" class="empty-loose-notice">
                  লুজ ম্যাপিং কনফিগার করা নেই (ডিফল্ট ফিটিংস ব্যবহৃত হবে)।
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
                        placeholder="যেমন: +২.৫ ইঞ্চি লুজ"
                      />
                      <button
                        type="button"
                        class="btn-remove-loose"
                        title="বাদ দিন"
                        @click="removeLooseAllowanceRow(lIdx)"
                      >
                        ✕
                      </button>
                    </div>

                    <!-- 1-Click Quick Loose Chips -->
                    <div class="quick-loose-chips">
                      <span class="loose-chips-lbl">দ্রুত বসান:</span>
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
              <span class="col-num-badge">৩</span>
              <div class="col-header-texts">
                <h3>🎨 ডিজাইন ও স্টাইল অপশন (Design Options)</h3>
                <small>কলার, পকেট, কাপ, প্লিট এবং অতিরিক্ত চার্জযুক্ত সেলাই স্টাইল</small>
              </div>
              <button
                type="button"
                class="btn-add-design-opt"
                @click="addDesignOptionGroup"
              >
                + নতুন ডিজাইন
              </button>
            </div>

            <div class="col-content-scroll">
              <!-- 1-Click Design Template Loader for Any Existing or New Dress -->
              <div class="col-design-presets-bar">
                <span class="preset-sub-lbl">⚡ রেডিমেড ডিজাইন লোড করুন:</span>
                <div class="preset-chips-row">
                  <button type="button" class="preset-chip-btn" title="শার্টের কলার, পকেট, কফ ও প্লিট ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('shirt')">👔 শার্ট</button>
                  <button type="button" class="preset-chip-btn" title="পাঞ্জাবীর কলার, প্লেট ও পকেট ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('panjabi')">🥻 পাঞ্জাবী</button>
                  <button type="button" class="preset-chip-btn" title="প্যান্টের প্লিট, পকেট ও বেল্ট ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('pant')">👖 প্যান্ট</button>
                  <button type="button" class="preset-chip-btn" title="ব্লেজারের ল্যাপেল, বাটন ও ভেন্ট ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('blazer')">🧥 ব্লেজার</button>
                  <button type="button" class="preset-chip-btn" title="কামিজের গলা, হাতা ও ঘের ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('kamiz')">🥻 কামিজ</button>
                  <button type="button" class="preset-chip-btn" title="বোরকার প্যাটার্ন ও কফ ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('burqa')">🧕 বোরকা</button>
                  <button type="button" class="preset-chip-btn" title="ব্লাউজের কাট ও ব্যাক নেক ডিজাইন যোগ করুন" @click="applyPresetDesignsToCurrentGarment('blouse')">🥻 ব্লাউজ</button>
                </div>
              </div>

              <div v-if="!studioForm.designOptions.length" class="empty-design-notice">
                <div class="design-empty-icon">🎨</div>
                <h4>কোনো ডিজাইন অপশন যোগ করা নেই</h4>
                <p>কলার স্টাইল, পকেট স্টাইল বা বিশেষ সেলাই চার্জ যুক্ত করতে ওপরের বাটন চাপুন অথবা অন্য পোশাক থেকে কপি করুন।</p>
                <div class="empty-design-actions">
                  <button
                    v-if="items.length > 1"
                    type="button"
                    class="btn-inline-copy-design"
                    @click="openCopyDesignModal"
                  >
                    📋 অন্য পোশাক থেকে ডিজাইন কপি করুন
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
                      placeholder="অপশন নাম (যেমন: কলার স্টাইল, পকেট স্টাইল)"
                      required
                    />
                    <select v-model="optGroup.type" class="design-type-select">
                      <option value="select">সিঙ্গেল সিলেক্ট (Dropdown)</option>
                      <option value="checkbox">মাল্টি সিলেক্ট (Checkbox)</option>
                      <option value="radio">রেডিও বাটন (Radio)</option>
                    </select>
                    <button
                      type="button"
                      class="btn-remove-group"
                      title="এই ডিজাইন অপশনটি বাদ দিন"
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
                        placeholder="ভ্যালু নাম (যেমন: ব্যান কলার, গোল কাপ)"
                        required
                        @keydown.enter.prevent="addDesignOptionValue(gIdx)"
                      />
                      <div class="val-price-wrap" title="অতিরিক্ত মূল্য (মেকিং চার্জের সাথে যোগ হবে)">
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
                      <label v-if="optGroup.type === 'select'" class="val-default-lbl" title="ডিফল্ট সিলেক্টেড থাকবে">
                        <input
                          type="radio"
                          :name="`default_${gIdx}`"
                          :checked="val.is_default"
                          @change="optGroup.values.forEach((v, idx) => v.is_default = idx === vIdx)"
                        />
                        <span>ডিফল্ট</span>
                      </label>
                      <button
                        type="button"
                        class="btn-remove-val"
                        title="বাদ দিন"
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
                      + ভ্যালু যোগ করুন
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
              📏 নির্বাচিত মাপ: <strong>{{ studioForm.parts.length }}টি</strong>
            </span>
            <span class="summary-designs-count">
              🎨 ডিজাইন অপশন: <strong>{{ studioForm.designOptions.length }}টি</strong>
            </span>
          </div>

          <div class="footer-buttons">
            <button
              type="button"
              class="btn-cancel"
              :disabled="saving"
              @click="showStudioModal = false"
            >
              বাতিল
            </button>
            <button
              type="button"
              class="btn-save-studio"
              :disabled="saving"
              @click="saveGarmentStudio"
            >
              <span v-if="saving" class="atelier-btn-spinner"></span>
              {{ saving ? 'সংরক্ষণ করা হচ্ছে...' : '✓ সম্পন্ন করুন ও সংরক্ষণ করুন' }}
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
          <h3>📋 অন্য পোশাক থেকে ডিজাইন কপি করুন (Copy Design)</h3>
          <button type="button" class="btn-close-modal" @click="showCopyDesignModal = false">✕</button>
        </div>

        <div class="copy-modal-body">
          <label>কোন পোশাক থেকে ডিজাইন অপশন কপি করতে চান?
            <select v-model="selectedSourceGarmentId" class="copy-source-select">
              <option
                v-for="g in items.filter(x => !editingGarment || x.public_id !== editingGarment.public_id)"
                :key="g.public_id"
                :value="g.public_id"
              >
                {{ g.name }} ({{ g.design_options?.length || 0 }}টি ডিজাইন অপশন)
              </option>
            </select>
          </label>
        </div>

        <div class="copy-modal-footer">
          <button type="button" class="btn-cancel" @click="showCopyDesignModal = false">বাতিল</button>
          <button type="button" class="btn-atelier-primary" @click="applyCopiedDesign">
            ✓ কপি প্রয়োগ করুন
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.garment-studio-page {
  padding: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
  font-family: inherit;
  color: var(--ink);
}

.page-topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.5rem;
  flex-wrap: wrap;
}

.badge-atelier {
  display: inline-block;
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--primary);
  background: rgba(10, 61, 49, 0.08);
  padding: 2px 8px;
  border-radius: 4px;
  margin-bottom: 0.25rem;
}

.topbar-title-block h1 {
  font-size: 1.45rem;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
}

.subtitle {
  font-size: 0.85rem;
  color: var(--muted);
  margin: 0.2rem 0 0;
}

.btn-atelier-primary {
  background: var(--primary);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0.65rem 1.25rem;
  font-weight: 700;
  font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 12px rgba(10, 61, 49, 0.2);
  transition: all 0.2s ease;
}

.btn-atelier-primary:hover {
  background: #0d4b3d;
  transform: translateY(-1px);
}

/* Toolbar Card */
.toolbar-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  padding: 0.75rem 1rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1.25rem;
  flex-wrap: wrap;
}

.category-tabs {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
}

.tab-btn {
  background: transparent;
  border: 1px solid transparent;
  border-radius: 6px;
  padding: 0.4rem 0.75rem;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.tab-btn:hover {
  background: var(--paper);
  color: var(--ink);
}

.tab-btn.is-active {
  background: var(--paper);
  border-color: var(--line);
  color: var(--primary);
  font-weight: 700;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.toolbar-right {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.search-box {
  display: flex;
  align-items: center;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.75rem;
  gap: 0.5rem;
  color: var(--muted);
}

.search-box input {
  border: none;
  background: transparent;
  outline: none;
  font-size: 0.82rem;
  color: var(--ink);
  width: 180px;
}

.select-group-filter {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.65rem;
  font-size: 0.82rem;
  color: var(--ink);
  outline: none;
}

/* Main Table Card */
.main-table-card {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}

.table-responsive {
  overflow-x: auto;
}

.atelier-table {
  width: 100%;
  border-collapse: collapse;
  text-align: left;
  font-size: 0.88rem;
}

.atelier-table thead th {
  background: var(--paper);
  color: var(--muted);
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.75rem 1rem;
  border-bottom: 1px solid var(--line);
}

.atelier-table tbody tr {
  border-bottom: 1px solid var(--line);
  transition: background 0.15s ease;
}

.atelier-table tbody tr:hover {
  background: rgba(10, 61, 49, 0.02);
}

.atelier-table tbody tr.is-inactive-row {
  opacity: 0.6;
  background: #fdfdfd;
}

.atelier-table td {
  padding: 0.85rem 1rem;
  vertical-align: middle;
}

.text-right {
  text-align: right;
}

.text-center {
  text-align: center;
}

.garment-avatar {
  width: 38px;
  height: 38px;
  border-radius: 8px;
  background: var(--paper);
  border: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.25rem;
}

.garment-title {
  display: block;
  font-size: 0.92rem;
  font-weight: 700;
  color: var(--ink);
}

.garment-group-sub {
  font-size: 0.75rem;
  color: var(--muted);
}

.parts-badges-wrap {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.badge-part-chip {
  background: var(--paper);
  border: 1px solid var(--line);
  font-size: 0.72rem;
  font-weight: 600;
  color: var(--ink);
  padding: 2px 6px;
  border-radius: 4px;
}

.badge-part-more {
  font-size: 0.72rem;
  font-weight: 700;
  color: var(--primary);
  background: rgba(10, 61, 49, 0.08);
  padding: 2px 6px;
  border-radius: 4px;
}

.design-summary-chip {
  display: inline-block;
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  color: #3730a3;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
}

.design-summary-chip.is-clickable {
  cursor: pointer;
  transition: all 0.15s ease;
}

.design-summary-chip.is-clickable:hover {
  background: #e0e7ff;
  border-color: #818cf8;
  transform: translateY(-1px);
}

.btn-add-design-pill {
  background: rgba(99, 102, 241, 0.08);
  border: 1px dashed #6366f1;
  color: #4f46e5;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-add-design-pill:hover {
  background: #4f46e5;
  color: #ffffff;
  border-style: solid;
}

.text-muted-empty {
  font-size: 0.75rem;
  color: var(--muted);
  font-style: italic;
}

.price-val {
  font-size: 0.95rem;
  font-weight: 800;
  color: var(--primary);
}

.fee-val {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--ink);
}

.toggle-status-pill {
  border: none;
  font-size: 0.72rem;
  font-weight: 700;
  border-radius: 12px;
  padding: 3px 10px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.toggle-status-pill.is-active {
  background: #dcfce7;
  color: #166534;
}

.toggle-status-pill.is-inactive {
  background: #fee2e2;
  color: #991b1b;
}

.action-btn-group {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}

.btn-act {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.3rem 0.6rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-act:hover {
  background: #ffffff;
  border-color: var(--primary);
  color: var(--primary);
}

.btn-act-del:hover {
  border-color: #ef4444;
  color: #ef4444;
}

/* ================================================================= */
/* 3-COLUMN ATELIER STUDIO MODAL DIALOG                              */
/* ================================================================= */
.studio-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.studio-modal-dialog {
  background: var(--surface);
  border-radius: 16px;
  width: 100%;
  max-width: 1320px;
  height: 90vh;
  max-height: 860px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 25px 60px rgba(0, 0, 0, 0.35);
  overflow: hidden;
  border: 1px solid var(--line);
}

.studio-modal-header {
  padding: 1rem 1.5rem;
  border-bottom: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  background: var(--paper);
}

.modal-badge-atelier {
  font-size: 0.65rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  color: var(--primary);
  background: rgba(10, 61, 49, 0.08);
  padding: 2px 6px;
  border-radius: 4px;
}

.studio-modal-header h2 {
  font-size: 1.15rem;
  font-weight: 800;
  margin: 0.2rem 0 0;
  color: var(--ink);
}

.header-actions {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-copy-design-header {
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  color: #3730a3;
  border-radius: 6px;
  padding: 0.35rem 0.85rem;
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-copy-design-header:hover {
  background: #e0e7ff;
}

/* Presets Banner */
.modal-presets-banner {
  background: rgba(10, 61, 49, 0.05);
  border-bottom: 1px solid var(--line);
  padding: 0.5rem 1.5rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  flex-wrap: wrap;
}

.preset-title {
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--primary);
  white-space: nowrap;
}

.preset-pills-row {
  display: flex;
  align-items: center;
  gap: 0.35rem;
  flex-wrap: wrap;
}

.preset-pill-btn {
  background: var(--surface);
  border: 1px solid rgba(10, 61, 49, 0.2);
  border-radius: 6px;
  padding: 0.25rem 0.65rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  cursor: pointer;
  transition: all 0.15s ease;
}

.preset-pill-btn:hover {
  background: var(--primary);
  border-color: var(--primary);
  color: #ffffff;
  transform: translateY(-1px);
}

.btn-close-modal {
  background: transparent;
  border: none;
  font-size: 1.2rem;
  color: var(--muted);
  cursor: pointer;
  padding: 0.2rem 0.5rem;
  border-radius: 6px;
}

.btn-close-modal:hover {
  background: var(--paper);
  color: var(--ink);
}

/* Mobile Tabs */
.mobile-tab-nav {
  display: none;
  background: var(--paper);
  border-bottom: 1px solid var(--line);
  padding: 0.4rem;
  gap: 0.4rem;
}

.m-tab-btn {
  flex: 1;
  border: none;
  background: transparent;
  padding: 0.5rem 0.2rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--muted);
  border-radius: 6px;
  cursor: pointer;
}

.m-tab-btn.is-m-active {
  background: var(--surface);
  color: var(--primary);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

/* 3-Column Body Grid */
.studio-modal-body {
  display: grid;
  grid-template-columns: 340px 1fr 1fr;
  flex: 1;
  min-height: 0;
  overflow: hidden;
  divide-x: 1px;
}

.studio-col {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 0;
  background: var(--surface);
}

.col-general {
  border-right: 1px solid var(--line);
}

.col-measurements {
  border-right: 1px solid var(--line);
}

.col-header {
  padding: 0.85rem 1.15rem;
  border-bottom: 1px solid var(--line);
  background: var(--paper);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
}

.col-header h3 {
  font-size: 0.88rem;
  font-weight: 800;
  margin: 0;
  color: var(--ink);
}

.col-header-texts small {
  display: block;
  font-size: 0.72rem;
  color: var(--muted);
}

.col-num-badge {
  width: 22px;
  height: 22px;
  border-radius: 50%;
  background: var(--primary);
  color: #ffffff;
  font-size: 0.75rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.col-content-scroll {
  padding: 1.15rem;
  overflow-y: auto;
  flex: 1;
}

/* Forms & Cards inside columns */
.form-group {
  margin-bottom: 0.85rem;
}

.form-group label {
  display: block;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  margin-bottom: 0.25rem;
}

.form-group input,
.form-group select,
.form-group textarea {
  width: 100%;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.45rem 0.65rem;
  font-size: 0.85rem;
  color: var(--ink);
  outline: none;
  box-sizing: border-box;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
  border-color: var(--primary);
  box-shadow: 0 0 0 2px rgba(10, 61, 49, 0.1);
}

.form-row-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.65rem;
}

.rates-box-card {
  background: rgba(10, 61, 49, 0.03);
  border: 1px solid rgba(10, 61, 49, 0.12);
  border-radius: 8px;
  padding: 0.75rem;
  margin-bottom: 0.85rem;
}

.rates-box-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--primary);
  margin-bottom: 0.65rem;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.live-profit-pill {
  background: rgba(16, 185, 129, 0.12);
  border: 1px solid rgba(16, 185, 129, 0.3);
  color: #065f46;
  font-size: 0.7rem;
  font-weight: 600;
  padding: 2px 8px;
  border-radius: 20px;
}

.live-profit-pill.is-negative {
  background: rgba(239, 68, 68, 0.12);
  border-color: rgba(239, 68, 68, 0.3);
  color: #991b1b;
}

.bank-filters-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  flex-wrap: wrap;
}

.bank-cat-pills {
  display: flex;
  gap: 0.25rem;
  flex-wrap: wrap;
}

.bank-cat-pill {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 4px;
  font-size: 0.7rem;
  font-weight: 600;
  color: var(--muted);
  padding: 2px 6px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.bank-cat-pill:hover,
.bank-cat-pill.is-active-cat {
  background: var(--primary);
  border-color: var(--primary);
  color: #ffffff;
}

.bank-search-input {
  max-width: 110px;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 0.7rem;
  color: var(--ink);
  outline: none;
}

.reorder-btns-col {
  display: flex;
  flex-direction: column;
  gap: 1px;
}

.btn-reorder {
  background: transparent;
  border: none;
  font-size: 0.55rem;
  color: var(--muted);
  cursor: pointer;
  padding: 0 2px;
  line-height: 1;
  border-radius: 2px;
}

.btn-reorder:hover:not(:disabled) {
  background: var(--line);
  color: var(--primary);
}

.btn-reorder:disabled {
  opacity: 0.2;
  cursor: not-allowed;
}

.loose-inputs-line {
  display: flex;
  align-items: center;
  gap: 0.4rem;
}

.quick-loose-chips {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.35rem;
  flex-wrap: wrap;
}

.loose-chips-lbl {
  font-size: 0.65rem;
  color: var(--muted);
  font-weight: 700;
}

.loose-chip-btn {
  background: rgba(245, 158, 11, 0.1);
  border: 1px solid rgba(245, 158, 11, 0.25);
  border-radius: 3px;
  font-size: 0.65rem;
  color: #92400e;
  padding: 1px 5px;
  cursor: pointer;
  transition: all 0.12s ease;
}

.loose-chip-btn:hover {
  background: #f59e0b;
  color: #ffffff;
}

.input-currency-wrap {
  display: flex;
  align-items: center;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  overflow: hidden;
}

.currency-sign {
  padding: 0.4rem 0.6rem;
  background: var(--paper);
  color: var(--muted);
  font-size: 0.85rem;
  font-weight: 700;
  border-right: 1px solid var(--line);
}

.input-currency-wrap input {
  border: none;
  background: transparent;
  padding: 0.4rem 0.65rem;
  width: 100%;
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--ink);
  outline: none;
}

.active-toggle-card {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.65rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 8px;
}

.switch-lbl {
  position: relative;
  display: inline-block;
  width: 38px;
  height: 20px;
  flex-shrink: 0;
}

.switch-lbl input {
  opacity: 0;
  width: 0;
  height: 0;
}

.slider {
  position: absolute;
  cursor: pointer;
  inset: 0;
  background-color: #cbd5e1;
  transition: 0.2s;
  border-radius: 20px;
}

.slider:before {
  position: absolute;
  content: "";
  height: 14px;
  width: 14px;
  left: 3px;
  bottom: 3px;
  background-color: white;
  transition: 0.2s;
  border-radius: 50%;
}

input:checked + .slider {
  background-color: var(--primary);
}

input:checked + .slider:before {
  transform: translateX(18px);
}

.switch-texts strong {
  display: block;
  font-size: 0.8rem;
  color: var(--ink);
}

.switch-texts small {
  font-size: 0.7rem;
  color: var(--muted);
}

/* Measurements Column elements */
.parts-bank-section {
  margin-bottom: 1rem;
}

.section-sub-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 0.78rem;
  font-weight: 800;
  color: var(--ink);
  margin-bottom: 0.45rem;
}

.section-sub-title small {
  font-size: 0.7rem;
  color: var(--muted);
  font-weight: normal;
}

.parts-pill-cloud {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
  max-height: 130px;
  overflow-y: auto;
  padding: 0.4rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 8px;
}

.bank-part-pill {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--ink);
  padding: 3px 8px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.bank-part-pill:hover {
  border-color: var(--primary);
  color: var(--primary);
}

.bank-part-pill.is-selected-pill {
  background: rgba(10, 61, 49, 0.1);
  border-color: var(--primary);
  color: var(--primary);
  font-weight: 700;
}

.custom-part-adder-bar {
  display: flex;
  gap: 0.4rem;
  margin-bottom: 1rem;
}

.custom-part-adder-bar input {
  flex: 1;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.6rem;
  font-size: 0.8rem;
  color: var(--ink);
  outline: none;
}

.custom-part-adder-bar select {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.4rem;
  font-size: 0.8rem;
  color: var(--ink);
  outline: none;
}

.btn-add-custom-part {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.75rem;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--primary);
  cursor: pointer;
  transition: all 0.15s ease;
  white-space: nowrap;
}

.btn-add-custom-part:hover {
  background: var(--primary);
  color: #ffffff;
}

.selected-parts-grid {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  margin-bottom: 1rem;
}

.selected-part-card {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.5rem;
}

.part-order-num {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--muted);
  width: 18px;
  text-align: center;
}

.part-name-input {
  flex: 1;
  border: 1px solid transparent;
  background: transparent;
  font-size: 0.82rem;
  font-weight: 600;
  color: var(--ink);
  outline: none;
  padding: 2px 4px;
  border-radius: 4px;
}

.part-name-input:focus {
  background: #ffffff;
  border-color: var(--line);
}

.part-unit-select {
  border: 1px solid var(--line);
  background: var(--surface);
  border-radius: 4px;
  font-size: 0.75rem;
  padding: 2px 4px;
  color: var(--muted);
  outline: none;
}

.part-req-chk {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  font-size: 0.72rem;
  color: var(--muted);
  cursor: pointer;
}

.btn-remove-part {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 0.85rem;
  cursor: pointer;
  padding: 0 4px;
}

/* Loose Mapping section */
.loose-mapping-section {
  background: rgba(245, 158, 11, 0.05);
  border: 1px dashed rgba(245, 158, 11, 0.3);
  border-radius: 8px;
  padding: 0.75rem;
}

.loose-header-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 0.65rem;
}

.loose-title strong {
  display: block;
  font-size: 0.8rem;
  color: #92400e;
}

.loose-title small {
  font-size: 0.68rem;
  color: var(--muted);
}

.btn-add-loose-row {
  background: #fef3c7;
  border: 1px solid #fde68a;
  color: #92400e;
  border-radius: 4px;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 8px;
  cursor: pointer;
}

.loose-rows-list {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
}

.loose-row-card {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--surface);
  border: 1px solid #fde68a;
  border-radius: 6px;
  padding: 0.3rem 0.5rem;
}

.loose-part-select {
  flex: 1;
  background: transparent;
  border: none;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  outline: none;
}

.loose-val-input {
  flex: 1;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 0.78rem;
  color: var(--ink);
  outline: none;
}

.btn-remove-loose {
  background: transparent;
  border: none;
  color: #ef4444;
  cursor: pointer;
}

/* Design & Style Options elements */
.col-design-presets-bar {
  background: rgba(99, 102, 241, 0.05);
  border: 1px solid rgba(99, 102, 241, 0.2);
  border-radius: 8px;
  padding: 0.6rem;
  margin-bottom: 0.85rem;
}

.preset-sub-lbl {
  display: block;
  font-size: 0.72rem;
  font-weight: 800;
  color: #4338ca;
  margin-bottom: 0.35rem;
}

.preset-chips-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.3rem;
}

.preset-chip-btn {
  background: var(--surface);
  border: 1px solid rgba(99, 102, 241, 0.25);
  color: #3730a3;
  font-size: 0.72rem;
  font-weight: 700;
  padding: 2px 7px;
  border-radius: 4px;
  cursor: pointer;
  transition: all 0.15s ease;
}

.preset-chip-btn:hover {
  background: #4f46e5;
  border-color: #4f46e5;
  color: #ffffff;
}

.btn-add-design-opt {
  background: var(--primary);
  color: #ffffff;
  border: none;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 700;
  padding: 0.35rem 0.75rem;
  cursor: pointer;
  white-space: nowrap;
}

.design-groups-list {
  display: flex;
  flex-direction: column;
  gap: 0.85rem;
}

.design-group-card {
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 0.75rem;
}

.design-group-header {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.65rem;
}

.design-group-name-input {
  flex: 1;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.6rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--ink);
  outline: none;
}

.design-type-select {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.35rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--ink);
  outline: none;
}

.btn-remove-group {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 0.9rem;
  cursor: pointer;
  padding: 0 4px;
}

.design-values-container {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding-left: 0.5rem;
  border-left: 2px solid rgba(10, 61, 49, 0.2);
}

.design-value-row {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.3rem 0.5rem;
}

.val-name-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 0.8rem;
  color: var(--ink);
  outline: none;
}

.val-price-wrap {
  display: flex;
  align-items: center;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 4px;
  padding: 1px 4px;
}

.price-plus {
  font-size: 0.72rem;
  font-weight: 800;
  color: var(--primary);
}

.val-price-input {
  width: 50px;
  border: none;
  background: transparent;
  font-size: 0.78rem;
  font-weight: 700;
  color: var(--ink);
  outline: none;
  text-align: right;
}

.val-default-lbl {
  display: flex;
  align-items: center;
  gap: 0.2rem;
  font-size: 0.72rem;
  color: var(--muted);
  cursor: pointer;
}

.btn-remove-val {
  background: transparent;
  border: none;
  color: #ef4444;
  font-size: 0.8rem;
  cursor: pointer;
}

.btn-add-val-row {
  background: transparent;
  border: 1px dashed var(--line);
  border-radius: 6px;
  padding: 0.3rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--primary);
  cursor: pointer;
  transition: all 0.15s ease;
}

.btn-add-val-row:hover {
  background: rgba(10, 61, 49, 0.05);
  border-color: var(--primary);
}

.empty-design-notice {
  text-align: center;
  padding: 2rem 1rem;
  color: var(--muted);
}

.design-empty-icon {
  font-size: 2rem;
  margin-bottom: 0.5rem;
}

.empty-design-notice h4 {
  font-size: 0.95rem;
  margin: 0 0 0.25rem;
  color: var(--ink);
}

.empty-design-notice p {
  font-size: 0.78rem;
  margin: 0 0 1rem;
}

.btn-inline-copy-design {
  background: #eef2ff;
  border: 1px solid #c7d2fe;
  color: #3730a3;
  border-radius: 6px;
  padding: 0.45rem 1rem;
  font-size: 0.82rem;
  font-weight: 700;
  cursor: pointer;
}

/* Studio Modal Footer */
.studio-modal-footer {
  padding: 0.85rem 1.5rem;
  border-top: 1px solid var(--line);
  background: var(--paper);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.footer-left {
  display: flex;
  align-items: center;
  gap: 1.25rem;
  font-size: 0.82rem;
  color: var(--muted);
}

.footer-left strong {
  color: var(--ink);
}

.footer-buttons {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.btn-cancel {
  background: var(--surface);
  border: 1px solid var(--line);
  border-radius: 8px;
  padding: 0.55rem 1.25rem;
  font-size: 0.85rem;
  font-weight: 700;
  color: var(--muted);
  cursor: pointer;
}

.btn-cancel:hover {
  background: var(--paper);
  color: var(--ink);
}

.btn-save-studio {
  background: var(--primary);
  color: #ffffff;
  border: none;
  border-radius: 8px;
  padding: 0.55rem 1.5rem;
  font-size: 0.88rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  box-shadow: 0 4px 12px rgba(10, 61, 49, 0.2);
  transition: all 0.2s ease;
}

.btn-save-studio:hover {
  background: #0d4b3d;
}

.btn-save-studio:disabled {
  opacity: 0.65;
  cursor: not-allowed;
}

/* Copy Modal */
.copy-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.6);
  z-index: 10000;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

.copy-modal-dialog {
  background: var(--surface);
  border-radius: 12px;
  width: 100%;
  max-width: 480px;
  border: 1px solid var(--line);
  overflow: hidden;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
}

.copy-modal-header {
  padding: 0.85rem 1.25rem;
  background: var(--paper);
  border-bottom: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.copy-modal-header h3 {
  font-size: 0.95rem;
  font-weight: 800;
  margin: 0;
}

.copy-modal-body {
  padding: 1.25rem;
}

.copy-source-select {
  width: 100%;
  background: var(--paper);
  border: 1px solid var(--line);
  border-radius: 6px;
  padding: 0.5rem 0.75rem;
  font-size: 0.88rem;
  margin-top: 0.4rem;
  color: var(--ink);
  outline: none;
}

.copy-modal-footer {
  padding: 0.75rem 1.25rem;
  background: var(--paper);
  border-top: 1px solid var(--line);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* State views */
.state-loading,
.state-error,
.state-empty {
  padding: 4rem 2rem;
  text-align: center;
  color: var(--muted);
}

.empty-icon {
  font-size: 2.5rem;
  margin-bottom: 0.5rem;
}

.atelier-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid rgba(10, 61, 49, 0.1);
  border-top-color: var(--primary);
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin: 0 auto 0.75rem;
}

.atelier-btn-spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: #ffffff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Responsive Breakpoints */
@media (max-width: 1024px) {
  .studio-modal-body {
    grid-template-columns: 1fr;
  }
  .mobile-tab-nav {
    display: flex;
  }
  .studio-col {
    display: none;
  }
  .studio-col.m-show {
    display: flex;
  }
}
</style>
