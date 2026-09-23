import { describe, it, expect } from "vitest";
import {
  normalizeSpokenDigits,
  parseSpokenNumber,
  parseSpeechToMeasurement,
  parseAllMeasurementsFromSpeech,
  getLanguagePack,
} from "../../app/composables/useVoiceMeasurement";

describe("Multi-Language Voice Measurement Engine (Bangla, Arabic, English)", () => {
  // ----------------------------------------------------------------
  // 1. BANGLA TESTS
  // ----------------------------------------------------------------
  describe("Bangla (bn-BD)", () => {
    const parts = [
      { id: "p1", name: "লম্বা (Length)", slug: "length" },
      { id: "p2", name: "বুক (Chest)", slug: "chest" },
      { id: "p3", name: "হাতা (Sleeve)", slug: "sleeve" },
    ];

    it("parses Bangla spoken numbers and fractions", () => {
      expect(parseSpokenNumber("৩২", "bn-BD")).toBe(32);
      expect(parseSpokenNumber("বত্রিশ", "bn-BD")).toBe(32);
      expect(parseSpokenNumber("সাড়ে ৩২", "bn-BD")).toBe(32.5);
      expect(parseSpokenNumber("সোয়া ৩৮", "bn-BD")).toBe(38.25);
      expect(parseSpokenNumber("পৌনে ৪০", "bn-BD")).toBe(39.75);
    });

    it("matches Bangla keywords and assigns to correct part", () => {
      const res = parseSpeechToMeasurement("লম্বা ৩২", parts, "bn-BD");
      expect(res).not.toBeNull();
      expect(res?.partId).toBe("p1");
      expect(res?.value).toBe(32);

      const res2 = parseSpeechToMeasurement("বুক ৩৮.৫", parts, "bn-BD");
      expect(res2?.partId).toBe("p2");
      expect(res2?.value).toBe(38.5);
    });
  });

  // ----------------------------------------------------------------
  // 2. ARABIC TESTS
  // ----------------------------------------------------------------
  describe("Arabic (ar-SA)", () => {
    const parts = [
      { id: "p1", name: "الطول (Length)", slug: "length" },
      { id: "p2", name: "الصدر (Chest)", slug: "chest" },
      { id: "p3", name: "الكم (Sleeve)", slug: "sleeve" },
      { id: "p4", name: "الكتف (Shoulder)", slug: "shoulder" },
    ];

    it("normalizes Arabic-Indic digits correctly", () => {
      expect(normalizeSpokenDigits("٣٢.٥")).toBe("32.5");
      expect(normalizeSpokenDigits("٤٢")).toBe("42");
    });

    it("parses Arabic spoken numbers and fractions", () => {
      expect(parseSpokenNumber("٣٢", "ar-SA")).toBe(32);
      expect(parseSpokenNumber("اثنان وثلاثون", "ar-SA")).toBe(32);
      expect(parseSpokenNumber("٣٢ ونصف", "ar-SA")).toBe(32.5);
      expect(parseSpokenNumber("٣٢ وربع", "ar-SA")).toBe(32.25);
      expect(parseSpokenNumber("٣٣ إلا ربع", "ar-SA")).toBe(32.75);
      expect(parseSpokenNumber("ثمانية وأربعون", "ar-SA")).toBe(48);
    });

    it("matches Arabic keywords and extracts measurement", () => {
      // "الطول ٣٢" -> Length 32
      const res1 = parseSpeechToMeasurement("الطول ٣٢", parts, "ar-SA");
      expect(res1).not.toBeNull();
      expect(res1?.partId).toBe("p1");
      expect(res1?.value).toBe(32);

      // "الصدر ٣٨ فاصلة ٥" -> Chest 38.5
      const res2 = parseSpeechToMeasurement("الصدر ٣٨ فاصلة ٥", parts, "ar-SA");
      expect(res2).not.toBeNull();
      expect(res2?.partId).toBe("p2");
      expect(res2?.value).toBe(38.5);

      // "الكم ٢٤" -> Sleeve 24
      const res3 = parseSpeechToMeasurement("الكم ٢٤", parts, "ar-SA");
      expect(res3?.partId).toBe("p3");
      expect(res3?.value).toBe(24);
    });

    it("extracts multiple Arabic measurements from continuous speech", () => {
      const matches = parseAllMeasurementsFromSpeech("الطول ٣٢ الصدر ٣٨.٥ الكم ٢٤", parts, "ar-SA");
      expect(matches.length).toBe(3);
      expect(matches.find((m) => m.partId === "p1")?.value).toBe(32);
      expect(matches.find((m) => m.partId === "p2")?.value).toBe(38.5);
      expect(matches.find((m) => m.partId === "p3")?.value).toBe(24);
    });
  });

  // ----------------------------------------------------------------
  // 3. ENGLISH TESTS
  // ----------------------------------------------------------------
  describe("English (en-US)", () => {
    const parts = [
      { id: "p1", name: "Length", slug: "length" },
      { id: "p2", name: "Chest", slug: "chest" },
      { id: "p3", name: "Sleeve", slug: "sleeve" },
    ];

    it("parses English spoken numbers and fractions", () => {
      expect(parseSpokenNumber("32", "en-US")).toBe(32);
      expect(parseSpokenNumber("thirty two", "en-US")).toBe(32);
      expect(parseSpokenNumber("32 and a half", "en-US")).toBe(32.5);
      expect(parseSpokenNumber("38 point 5", "en-US")).toBe(38.5);
      expect(parseSpokenNumber("forty eight", "en-US")).toBe(48);
    });

    it("matches English keywords and extracts measurement", () => {
      const res1 = parseSpeechToMeasurement("length 32", parts, "en-US");
      expect(res1).not.toBeNull();
      expect(res1?.partId).toBe("p1");
      expect(res1?.value).toBe(32);

      const res2 = parseSpeechToMeasurement("chest 38.5", parts, "en-US");
      expect(res2?.partId).toBe("p2");
      expect(res2?.value).toBe(38.5);
    });

    it("extracts multiple English measurements from continuous speech", () => {
      const matches = parseAllMeasurementsFromSpeech("length 32 chest 38.5 sleeve 24", parts, "en-US");
      expect(matches.length).toBe(3);
      expect(matches.find((m) => m.partId === "p1")?.value).toBe(32);
      expect(matches.find((m) => m.partId === "p2")?.value).toBe(38.5);
      expect(matches.find((m) => m.partId === "p3")?.value).toBe(24);
    });
  });
});
