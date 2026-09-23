import { describe, it, expect } from "vitest";

describe("Garment Design Studio & Order Calculation Engine", () => {
  it("calculates garment total with selected design option extra charges", () => {
    const baseMakingMinor = 50000; // ৳500

    const designOptions = [
      {
        name: "কলার স্টাইল",
        type: "select",
        values: [
          { name: "ব্যান কলার", extra_price_minor: 0, is_default: true },
          { name: "শেরওয়ানি স্পেশাল কলার", extra_price_minor: 5000, is_default: false }, // +৳50
        ],
      },
      {
        name: "পকেট স্টাইল",
        type: "select",
        values: [
          { name: "১ পকেট", extra_price_minor: 0, is_default: true },
          { name: "বুক পকেট + ২ সাইড পকেট", extra_price_minor: 3000, is_default: false }, // +৳30
        ],
      },
      {
        name: "অতিরিক্ত সেলাই ও স্টাইল",
        type: "checkbox",
        values: [
          { name: "হাতা+পকেটে ফোল্ডিং ডিজাইন", extra_price_minor: 10000, is_default: false }, // +৳100
          { name: "১ পয়েন্ট চওড়া ডাবল সেলাই", extra_price_minor: 5000, is_default: false }, // +৳50
        ],
      },
    ];

    // Scenario 1: Default selection (0 extra charges)
    let total1 = baseMakingMinor;
    const selections1: Record<string, string | string[]> = {
      "কলার স্টাইল": "ব্যান কলার",
      "পকেট স্টাইল": "১ পকেট",
      "অতিরিক্ত সেলাই ও স্টাইল": [],
    };
    for (const opt of designOptions) {
      const sel = selections1[opt.name];
      if (Array.isArray(sel)) {
        for (const sName of sel) {
          const v = opt.values.find((val) => val.name === sName);
          if (v) total1 += v.extra_price_minor;
        }
      } else if (typeof sel === "string") {
        const v = opt.values.find((val) => val.name === sel);
        if (v) total1 += v.extra_price_minor;
      }
    }
    expect(total1).toBe(50000); // ৳500

    // Scenario 2: Special collar (+৳50), Triple pockets (+৳30), and Folding design (+৳100)
    let total2 = baseMakingMinor;
    const selections2: Record<string, string | string[]> = {
      "কলার স্টাইল": "শেরওয়ানি স্পেশাল কলার",
      "পকেট স্টাইল": "বুক পকেট + ২ সাইড পকেট",
      "অতিরিক্ত সেলাই ও স্টাইল": ["হাতা+পকেটে ফোল্ডিং ডিজাইন", "১ পয়েন্ট চওড়া ডাবল সেলাই"],
    };
    for (const opt of designOptions) {
      const sel = selections2[opt.name];
      if (Array.isArray(sel)) {
        for (const sName of sel) {
          const v = opt.values.find((val) => val.name === sName);
          if (v) total2 += v.extra_price_minor;
        }
      } else if (typeof sel === "string") {
        const v = opt.values.find((val) => val.name === sel);
        if (v) total2 += v.extra_price_minor;
      }
    }
    // 50000 + 5000 + 3000 + 10000 + 5000 = 73000 (৳730)
    expect(total2).toBe(73000);
  });

  it("assembles cutting notes correctly from selected design styles", () => {
    const selectedDesignOptions = {
      "কলার স্টাইল": "ব্যান কলার",
      "পকেট স্টাইল": "১ সাইড পকেট",
      "অতিরিক্ত সেলাই": ["হাতা+পকেটে ফোল্ডিং ডিজাইন"],
    };
    const userNote = "ডেলিভারি দ্রুত দিতে হবে";

    const styleSummary: string[] = [];
    for (const [optName, val] of Object.entries(selectedDesignOptions)) {
      if (Array.isArray(val) && val.length) {
        styleSummary.push(`${optName}: ${val.join(", ")}`);
      } else if (typeof val === "string" && val) {
        styleSummary.push(`${optName}: ${val}`);
      }
    }

    const combinedNotes = [
      styleSummary.length ? `[ডিজাইন ও স্টাইল] ${styleSummary.join(" | ")}` : "",
      userNote.trim(),
    ].filter(Boolean).join("\n");

    expect(combinedNotes).toContain("[ডিজাইন ও স্টাইল] কলার স্টাইল: ব্যান কলার | পকেট স্টাইল: ১ সাইড পকেট | অতিরিক্ত সেলাই: হাতা+পকেটে ফোল্ডিং ডিজাইন");
    expect(combinedNotes).toContain("ডেলিভারি দ্রুত দিতে হবে");
  });
});
