import { ref, readonly } from "vue";

export interface PartReference {
  id: string;
  public_id?: string;
  name: string;
  slug?: string;
  unit?: string;
}

export interface RecognizedMeasurement {
  partId: string;
  partName: string;
  value: number;
}

export interface TailoringLanguagePack {
  code: string;
  name: string;
  nativeName: string;
  flag: string;
  digitMap: Record<string, string>;
  spokenNumberWords: Record<string, number>;
  decimalWords: string[];
  fractions: {
    half: string[];
    quarter: string[];
    threeQuarter: string[];
  };
  partSynonyms: Record<string, string[]>;
  formatConfirmation: (partName: string, value: number, unit?: string) => string;
}

// ----------------------------------------------------------------------
// 1. BANGLA LANGUAGE PACK (bn-BD)
// ----------------------------------------------------------------------
export const banglaLanguagePack: TailoringLanguagePack = {
  code: "bn-BD",
  name: "Bangla",
  nativeName: "বাংলা",
  flag: "🇧🇩",
  digitMap: {
    "০": "0", "১": "1", "২": "2", "৩": "3", "৪": "4",
    "৫": "5", "৬": "6", "৭": "7", "৮": "8", "৯": "9",
  },
  spokenNumberWords: {
    "এক": 1, "দুই": 2, "তিন": 3, "চার": 4, "পাঁচ": 5, "পাচ": 5,
    "ছয়": 6, "ছয়": 6, "সাত": 7, "আট": 8, "নয়": 9, "নয়": 9, "দশ": 10,
    "এগারো": 11, "এগার": 11, "বারো": 12, "বার": 12, "তেরো": 13, "তের": 13,
    "চৌদ্দ": 14, "পনেরো": 15, "পনের": 15, "ষোল": 16, "সতেরো": 17, "সতের": 17,
    "আঠারো": 18, "আঠার": 18, "উনিশ": 19, "বিশ": 20, "কুড়ি": 20, "কুড়ি": 20,
    "একুশ": 21, "বাইশ": 22, "তেইশ": 23, "চব্বিশ": 24, "পঁচিশ": 25, "পচিশ": 25,
    "ছাব্বিশ": 26, "সাতাশ": 27, "আঠাশ": 28, "উনত্রিশ": 29, "ত্রিশ": 30,
    "একত্রিশ": 31, "বত্রিশ": 32, "তেত্রিশ": 33, "চৌত্রিশ": 34, "পঁয়ত্রিশ": 35, "পয়ত্রিশ": 35,
    "ছত্রিশ": 36, "সাঁইত্রিশ": 37, "সাইত্রিশ": 37, "আটত্রিশ": 38, "উনচল্লিশ": 39, "চল্লিশ": 40,
    "একচল্লিশ": 41, "বিয়াল্লিশ": 42, "বয়াল্লিশ": 42, "তেতাল্লিশ": 43, "চুয়াল্লিশ": 44,
    "পঁয়তাল্লিশ": 45, "পয়তাল্লিশ": 45, "ছেচল্লিশ": 46, "সাতচল্লিশ": 47, "আটচল্লিশ": 48,
    "উনপঞ্চাশ": 49, "পঞ্চাশ": 50,
    "একান্ন": 51, "বায়ান্ন": 52, "বয়ান্ন": 52, "তিপ্পান্ন": 53, "চুয়ান্ন": 54, "পঞ্চান্ন": 55,
    "ছাপ্পান্ন": 56, "সাতান্ন": 57, "আটান্ন": 58, "উনষাট": 59, "ষাট": 60,
    "একষট্টি": 61, "বাষট্টি": 62, "তেষট্টি": 63, "চৌষট্টি": 64, "পঁয়ষট্টি": 65, "পয়ষট্টি": 65,
    "ছেষট্টি": 66, "সাতষট্টি": 67, "আটষট্টি": 68, "উনসত্তর": 69, "সত্তর": 70,
    "একাত্তর": 71, "বাহাত্তর": 72, "তিয়াত্তর": 73, "তিয়াত্তর": 73, "চুয়াত্তর": 74,
    "পঁচাত্তর": 75, "পচাত্তর": 75, "ছিয়াত্তর": 76, "ছিয়াত্তর": 76, "সাতাত্তর": 77,
    "আটাত্তর": 78, "উনআশি": 79, "আশি": 80,
    "একাশি": 81, "বিরাশি": 82, "তিরাশি": 83, "চুরাশি": 84, "পঁচাশি": 85, "পচাশি": 85,
    "ছিয়াশি": 86, "ছিয়াসি": 86, "সাতাশি": 87, "আটাশি": 88, "উননব্বই": 89, "নব্বই": 90,
    "একানব্বই": 91, "বিরানব্বই": 92, "তিরানব্বই": 93, "চুরানব্বই": 94, "পঁচানব্বই": 95,
    "ছিয়ানব্বই": 96, "সাতানব্বই": 97, "আটানব্বই": 98, "নিরানব্বই": 99, "একশত": 100, "একশ": 100,
  },
  decimalWords: ["দশমিক", "পয়েন্ট", "পয়েন্ট", "ডট"],
  fractions: {
    half: ["সাড়ে", "সাড়ের"],
    quarter: ["সোয়া", "সোয়া"],
    threeQuarter: ["পৌনে"],
  },
  partSynonyms: {
    length: ["লম্বা", "লং", "ঝুল", "দৈর্ঘ্য", "দৈর্ঘ", "উচ্চতা"],
    chest: ["বুক", "ছাতি", "বডি", "সিনা"],
    waist: ["কোমর", "কমর", "বেলি"],
    hip: ["হিপ", "পাছা"],
    sleeve: ["হাতা", "হাত", "বাজু"],
    shoulder: ["তীরা", "তিরা", "পুট", "পুঠ", "শোল্ডার", "কাধ", "কাঁধ"],
    collar: ["কলার", "গলা", "নেক"],
    crotch: ["হাই", "ক্রচ"],
    thigh: ["থাই", "রান"],
    bottom: ["মহরী", "মোহরী", "মুহুরি", "মুহরি", "বটম", "পায়", "মুরি"],
    loose: ["লুজ", "লুজিং", "ঢিল"],
    pocket: ["পকেট"],
  },
  formatConfirmation: (name, val, unit = "ইঞ্চি") => `✓ ${name}: ${val} ${unit} ইনপুট সম্পন্ন হয়েছে`,
};

// ----------------------------------------------------------------------
// 2. ARABIC LANGUAGE PACK (ar-SA)
// ----------------------------------------------------------------------
const arabicBaseNumbers: Record<string, number> = {
  "واحد": 1, "اثنين": 2, "إثنين": 2, "اثنان": 2, "إثنان": 2,
  "ثلاثة": 3, "ثلاثه": 3, "ثلاث": 3,
  "أربعة": 4, "اربعة": 4, "أربعه": 4, "اربع": 4, "أربع": 4,
  "خمسة": 5, "خمسه": 5, "خمس": 5,
  "ستة": 6, "سته": 6, "ست": 6,
  "سبعة": 7, "سبعه": 7, "سبع": 7,
  "ثمانية": 8, "ثمانيه": 8, "ثماني": 8, "ثمان": 8,
  "تسعة": 9, "تسعه": 9, "تسع": 9,
  "عشرة": 10, "عشره": 10, "عشر": 10,
  "أحد عشر": 11, "احد عشر": 11, "إحدى عشر": 11, "احدى عشر": 11,
  "اثنا عشر": 12, "اثني عشر": 12, "إثنا عشر": 12, "إثني عشر": 12,
  "ثلاثة عشر": 13, "ثلاثه عشر": 13, "أربعة عشر": 14, "اربعة عشر": 14,
  "خمسة عشر": 15, "خمسه عشر": 15, "ستة عشر": 16, "سته عشر": 16,
  "سبعة عشر": 17, "سبعه عشر": 17, "ثمانية عشر": 18, "ثمانيه عشر": 18,
  "تسعة عشر": 19, "تسعه عشر": 19,
  "عشرون": 20, "عشرين": 20,
  "ثلاثون": 30, "ثلاثين": 30,
  "أربعون": 40, "اربعون": 40, "أربعين": 40, "اربعين": 40,
  "خمسون": 50, "خمسين": 50,
  "ستون": 60, "ستين": 60,
  "سبعون": 70, "سبعين": 70,
  "ثمانون": 80, "ثمانين": 80,
  "تسعون": 90, "تسعين": 90,
  "مائة": 100, "مئة": 100, "مية": 100,
};

// Generate full compound Arabic numbers (21-99)
const arabicCompoundNumbers: Record<string, number> = { ...arabicBaseNumbers };
const arabicUnits: [string, number][] = [
  ["واحد", 1], ["اثنين", 2], ["اثنان", 2], ["ثلاثة", 3], ["ثلاثه", 3],
  ["أربعة", 4], ["اربعة", 4], ["خمسة", 5], ["خمسه", 5],
  ["ستة", 6], ["سته", 6], ["سبعة", 7], ["سبعه", 7],
  ["ثمانية", 8], ["ثمانيه", 8], ["تسعة", 9], ["تسعه", 9],
];
const arabicTens: [string, number][] = [
  ["عشرون", 20], ["عشرين", 20],
  ["ثلاثون", 30], ["ثلاثين", 30],
  ["أربعون", 40], ["اربعون", 40], ["أربعين", 40], ["اربعين", 40],
  ["خمسون", 50], ["خمسين", 50],
  ["ستون", 60], ["ستين", 60],
  ["سبعون", 70], ["سبعين", 70],
  ["ثمانون", 80], ["ثمانين", 80],
  ["تسعون", 90], ["تسعين", 90],
];

for (const [uStr, uVal] of arabicUnits) {
  for (const [tStr, tVal] of arabicTens) {
    arabicCompoundNumbers[`${uStr} و${tStr}`] = uVal + tVal;
    arabicCompoundNumbers[`${uStr} و ${tStr}`] = uVal + tVal;
  }
}

export const arabicLanguagePack: TailoringLanguagePack = {
  code: "ar-SA",
  name: "Arabic",
  nativeName: "العربية",
  flag: "🇸🇦",
  digitMap: {
    "٠": "0", "١": "1", "٢": "2", "٣": "3", "٤": "4",
    "٥": "5", "٦": "6", "٧": "7", "٨": "8", "٩": "9",
  },
  spokenNumberWords: arabicCompoundNumbers,
  decimalWords: ["فاصلة", "فاصله", "نقطة", "نقطه", "بوينت", "دوت"],
  fractions: {
    half: ["ونصف", "ونص", "نصف", "نص"],
    quarter: ["وربع", "ربع"],
    threeQuarter: ["إلا ربع", "الا ربع", "وثلاثة أرباع", "وثلاثه ارباع"],
  },
  partSynonyms: {
    length: ["طول", "الطول", "طويل", "قياس الطول", "length"],
    chest: ["صدر", "الصدر", "محيط الصدر", "الصدرية", "بست", "chest"],
    waist: ["خصر", "الخصر", "وسط", "الوسط", "محيط الخصر", "waist"],
    hip: ["ورك", "الورك", "أرداف", "الأرداف", "محيط الورك", "الهيب", "hip"],
    sleeve: ["كم", "الكم", "يد", "اليد", "ذراع", "الذراع", "أكمام", "الأكمام", "sleeve"],
    shoulder: ["كتف", "الكتف", "أكتاف", "الأكتاف", "شولدر", "shoulder"],
    collar: ["رقبة", "الرقبة", "ياقة", "الياقة", "طوق", "الطوق", "collar"],
    crotch: ["حجر", "الحجر", "قعدة", "القعدة", "سروال", "بنطلون", "crotch"],
    thigh: ["فخذ", "الفخذ", "رجل", "الرجل", "thigh"],
    bottom: ["وسع", "الوسع", "فتحة", "الفتحة", "أسفل", "الأسفل", "محيط القدم", "bottom"],
    loose: ["وسع", "فضفاض", "توسيع", "loose"],
    pocket: ["جيب", "الجيب", "pocket"],
  },
  formatConfirmation: (name, val, unit = "بوصة") => `✓ ${name}: ${val} ${unit} تم التسجيل بنجاح`,
};

// ----------------------------------------------------------------------
// 3. ENGLISH LANGUAGE PACK (en-US)
// ----------------------------------------------------------------------
const englishBaseNumbers: Record<string, number> = {
  "one": 1, "two": 2, "three": 3, "four": 4, "five": 5,
  "six": 6, "seven": 7, "eight": 8, "nine": 9, "ten": 10,
  "eleven": 11, "twelve": 12, "thirteen": 13, "fourteen": 14, "fifteen": 15,
  "sixteen": 16, "seventeen": 17, "eighteen": 18, "nineteen": 19,
  "twenty": 20, "thirty": 30, "forty": 40, "fifty": 50,
  "sixty": 60, "seventy": 70, "eighty": 80, "ninety": 90,
  "one hundred": 100, "hundred": 100,
};

const englishCompoundNumbers: Record<string, number> = { ...englishBaseNumbers };
const engUnits: [string, number][] = [
  ["one", 1], ["two", 2], ["three", 3], ["four", 4], ["five", 5],
  ["six", 6], ["seven", 7], ["eight", 8], ["nine", 9],
];
const engTens: [string, number][] = [
  ["twenty", 20], ["thirty", 30], ["forty", 40], ["fifty", 50],
  ["sixty", 60], ["seventy", 70], ["eighty", 80], ["ninety", 90],
];

for (const [tStr, tVal] of engTens) {
  for (const [uStr, uVal] of engUnits) {
    englishCompoundNumbers[`${tStr} ${uStr}`] = tVal + uVal;
    englishCompoundNumbers[`${tStr}-${uStr}`] = tVal + uVal;
  }
}

export const englishLanguagePack: TailoringLanguagePack = {
  code: "en-US",
  name: "English",
  nativeName: "English",
  flag: "🇬🇧",
  digitMap: {},
  spokenNumberWords: englishCompoundNumbers,
  decimalWords: ["point", "dot"],
  fractions: {
    half: ["and a half", "half"],
    quarter: ["and a quarter", "quarter"],
    threeQuarter: ["three quarters", "and three quarters"],
  },
  partSynonyms: {
    length: ["length", "long", "height", "total length", "body length"],
    chest: ["chest", "bust", "body", "chest width"],
    waist: ["waist", "belly", "waistline"],
    hip: ["hip", "hips", "seat"],
    sleeve: ["sleeve", "arm", "hand", "sleeve length"],
    shoulder: ["shoulder", "cross back", "across shoulder"],
    collar: ["collar", "neck", "neckline"],
    crotch: ["crotch", "rise", "front rise"],
    thigh: ["thigh", "upper leg"],
    bottom: ["bottom", "hem", "leg opening", "cuff", "opening"],
    loose: ["loose", "easing", "fitting"],
    pocket: ["pocket"],
  },
  formatConfirmation: (name, val, unit = "inch") => `✓ ${name}: ${val} ${unit} recorded successfully`,
};

// ----------------------------------------------------------------------
// 4. LANGUAGE REGISTRY & RESOLVER
// ----------------------------------------------------------------------
export const supportedLanguagePacks: Record<string, TailoringLanguagePack> = {
  "bn-BD": banglaLanguagePack,
  "ar-SA": arabicLanguagePack,
  "en-US": englishLanguagePack,
};

export function getLanguagePack(code: string): TailoringLanguagePack {
  return supportedLanguagePacks[code] || banglaLanguagePack;
}

export function registerLanguagePack(pack: TailoringLanguagePack) {
  supportedLanguagePacks[pack.code] = pack;
}

export function normalizeSpokenDigits(text: string, pack?: TailoringLanguagePack): string {
  let res = text;
  const packs = pack ? [pack] : Object.values(supportedLanguagePacks);
  for (const p of packs) {
    for (const [localDigit, engDigit] of Object.entries(p.digitMap)) {
      res = res.replaceAll(localDigit, engDigit);
    }
  }
  return res;
}

/**
 * High-accuracy multi-language spoken number & fraction parser
 */
export function parseSpokenNumber(phrase: string, langCode: string = "bn-BD"): number | null {
  if (!phrase) return null;
  const pack = getLanguagePack(langCode);
  let clean = phrase.toLowerCase().trim();

  // Normalize digits (Bangla, Arabic-Indic)
  clean = normalizeSpokenDigits(clean, pack);

  // Normalize decimal words
  const allDecimalWords = [
    ...pack.decimalWords,
    "point", "dot", "দশমিক", "পয়েন্ট", "পয়েন্ট", "ডট", "فاصلة", "فاصله", "نقطة",
  ];
  for (const dw of allDecimalWords) {
    clean = clean.replaceAll(dw, ".");
  }
  clean = clean.replace(/,/g, ".").replace(/\s*\.\s*/g, ".");

  // Check fraction prefixes/suffixes with precise boundaries
  let isHalf = false;
  let isQuarter = false;
  let isThreeQuarter = false;

  for (const f of pack.fractions.threeQuarter) {
    if (clean.includes(f)) {
      isThreeQuarter = true;
      break;
    }
  }

  if (!isThreeQuarter) {
    for (const f of pack.fractions.half) {
      if (clean.includes(f)) {
        isHalf = true;
        break;
      }
    }

    if (!isHalf) {
      for (const f of pack.fractions.quarter) {
        if (clean.includes(f) && !clean.includes("أربع") && !clean.includes("اربع")) {
          isQuarter = true;
          break;
        }
      }
    }
  }

  // Check word dictionary FIRST before stripping to preserve compound phrases like "اثنان وثلاثون" or "thirty two"
  let baseNum: number | null = null;
  const sortedWords = Object.entries(pack.spokenNumberWords).sort(
    (a, b) => b[0].length - a[0].length
  );

  for (const [word, num] of sortedWords) {
    if (clean.includes(word.toLowerCase())) {
      baseNum = num;
      break;
    }
  }

  // If not matched directly in dictionary, strip fraction keywords and unit noise
  if (baseNum === null) {
    let stripped = clean;
    const allFractionTokens = [
      ...pack.fractions.threeQuarter,
      ...pack.fractions.half,
      ...pack.fractions.quarter,
      "and a half", "and a quarter", "and", "ইঞ্চি", "inch", "بوصة", "سم", "cm",
    ];
    for (const token of allFractionTokens) {
      stripped = stripped.replaceAll(token, " ");
    }
    stripped = stripped.trim();

    for (const [word, num] of sortedWords) {
      if (stripped.includes(word.toLowerCase())) {
        baseNum = num;
        break;
      }
    }

    if (baseNum === null) {
      const numMatch = stripped.match(/\d+(\.\d+)?/);
      if (numMatch) {
        baseNum = parseFloat(numMatch[0]);
      }
    }
  }

  if (baseNum === null || isNaN(baseNum)) return null;

  if (isHalf) return baseNum + 0.5;
  if (isQuarter) return baseNum + 0.25;
  if (isThreeQuarter) {
    return baseNum > 1 ? baseNum - 0.25 : 0.75;
  }

  return baseNum;
}

export function getAliasesForPart(part: PartReference, langCode: string = "bn-BD"): string[] {
  const pack = getLanguagePack(langCode);
  const pName = part.name.toLowerCase();
  const pSlug = (part.slug || "").toLowerCase();

  const aliases = new Set<string>();
  aliases.add(pName);
  if (pSlug) aliases.add(pSlug);

  pName.replace(/[()\/,-]/g, " ").split(/\s+/).forEach((w) => {
    if (w.length >= 2) aliases.add(w);
  });

  for (const [categoryKey, synonymList] of Object.entries(pack.partSynonyms)) {
    const matchesCategory =
      pName.includes(categoryKey) ||
      pSlug.includes(categoryKey) ||
      synonymList.some((s) => pName.includes(s) || pSlug.includes(s));

    if (matchesCategory) {
      synonymList.forEach((s) => aliases.add(s));
    }
  }

  return Array.from(aliases).sort((a, b) => b.length - a.length);
}

export function parseAllMeasurementsFromSpeech(
  transcript: string,
  parts: PartReference[] = [],
  langCode: string = "bn-BD"
): RecognizedMeasurement[] {
  if (!transcript || !parts.length) return [];
  const normalized = transcript.toLowerCase();
  const results: RecognizedMeasurement[] = [];
  const processedPartIds = new Set<string>();

  for (const part of parts) {
    const partId = part.id || (part.public_id as string);
    if (processedPartIds.has(partId)) continue;

    const aliases = getAliasesForPart(part, langCode);

    for (const alias of aliases) {
      const aliasIndex = normalized.indexOf(alias);
      if (aliasIndex !== -1) {
        const afterText = normalized.slice(aliasIndex + alias.length, aliasIndex + alias.length + 35);
        const beforeText = normalized.slice(Math.max(0, aliasIndex - 30), aliasIndex);

        let val = parseSpokenNumber(afterText, langCode);
        if (val === null) {
          val = parseSpokenNumber(beforeText, langCode);
        }

        if (val !== null) {
          results.push({
            partId,
            partName: part.name,
            value: val,
          });
          processedPartIds.add(partId);
          break;
        }
      }
    }
  }

  return results;
}

export function parseSpeechToMeasurement(
  text: string,
  parts: PartReference[] = [],
  langCode: string = "bn-BD"
): RecognizedMeasurement | null {
  const matches = parseAllMeasurementsFromSpeech(text, parts, langCode);
  return matches.length > 0 ? matches[0] : null;
}

export function playChimeSuccess() {
  if (typeof window === "undefined") return;
  try {
    const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();
    osc.type = "sine";
    osc.frequency.setValueAtTime(587.33, ctx.currentTime);
    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.08);
    gain.gain.setValueAtTime(0.12, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.18);
    osc.connect(gain);
    gain.connect(ctx.destination);
    osc.start();
    osc.stop(ctx.currentTime + 0.2);
  } catch {}
}

export function useVoiceMeasurement() {
  const selectedLang = ref<string>(
    (typeof window !== "undefined" && localStorage.getItem("tailors_voice_lang")) || "bn-BD"
  );
  const isListening = ref(false);
  const activePartId = ref<string | null>(null);
  const liveTranscript = ref("");
  const lastRecognized = ref<RecognizedMeasurement | null>(null);
  const isSupported = ref(
    typeof window !== "undefined" &&
      ("SpeechRecognition" in window || "webkitSpeechRecognition" in window)
  );

  let recognitionInstance: any = null;

  function setLanguage(code: string) {
    selectedLang.value = code;
    if (typeof window !== "undefined") {
      localStorage.setItem("tailors_voice_lang", code);
    }
    if (isListening.value) {
      stopListening();
    }
  }

  function stopListening() {
    if (recognitionInstance) {
      try {
        recognitionInstance.stop();
      } catch {}
      recognitionInstance = null;
    }
    isListening.value = false;
    activePartId.value = null;
  }

  function startListening(options: {
    lang?: string;
    targetPartId?: string;
    parts?: PartReference[];
    continuous?: boolean;
    onResult: (partId: string, value: number, partName?: string) => void;
    onError?: (err: string) => void;
  }) {
    if (typeof window === "undefined") return;

    const SpeechRecognition =
      (window as any).SpeechRecognition || (window as any).webkitSpeechRecognition;

    if (!SpeechRecognition) {
      options.onError?.("ভয়েস ইনপুট সমর্থিত নয় / Voice input not supported.");
      return;
    }

    stopListening();

    const activeLanguage = options.lang || selectedLang.value || "bn-BD";

    try {
      const recognition = new SpeechRecognition();
      recognition.lang = activeLanguage;
      recognition.continuous = options.continuous ?? false;
      recognition.interimResults = true;
      recognition.maxAlternatives = 1;

      isListening.value = true;
      activePartId.value = options.targetPartId || null;
      liveTranscript.value = "";

      recognition.onstart = () => {
        isListening.value = true;
      };

      recognition.onresult = (event: any) => {
        let currentTranscript = "";
        for (let i = event.resultIndex; i < event.results.length; ++i) {
          currentTranscript += event.results[i][0].transcript;
        }

        liveTranscript.value = currentTranscript;

        if (options.targetPartId) {
          const num = parseSpokenNumber(currentTranscript, activeLanguage);
          if (num !== null) {
            lastRecognized.value = { partId: options.targetPartId, partName: "", value: num };
            playChimeSuccess();
            options.onResult(options.targetPartId, num);
          }
        } else if (options.parts && options.parts.length) {
          const matches = parseAllMeasurementsFromSpeech(currentTranscript, options.parts, activeLanguage);
          for (const match of matches) {
            lastRecognized.value = match;
            playChimeSuccess();
            options.onResult(match.partId, match.value, match.partName);
          }
        }
      };

      recognition.onerror = (event: any) => {
        if (event.error !== "no-speech") {
          options.onError?.(`ভয়েস ত্রুটি / Voice error: ${event.error}`);
        }
        if (!options.continuous) {
          stopListening();
        }
      };

      recognition.onend = () => {
        if (!options.continuous) {
          isListening.value = false;
          activePartId.value = null;
        }
      };

      recognitionInstance = recognition;
      recognition.start();
    } catch (err: any) {
      options.onError?.("মাইক্রোফোন চালু করা যায়নি। / Microphone access error.");
      isListening.value = false;
      activePartId.value = null;
    }
  }

  return {
    selectedLang: readonly(selectedLang),
    supportedLanguages: supportedLanguagePacks,
    isSupported: readonly(isSupported),
    isListening: readonly(isListening),
    activePartId: readonly(activePartId),
    liveTranscript: readonly(liveTranscript),
    lastRecognized: readonly(lastRecognized),
    setLanguage,
    startListening,
    stopListening,
    getLanguagePack,
  };
}
