<script setup lang="ts">
const { locale } = useTailorsI18n();

const guidesBn = [
  {
    title: "১. নতুন গ্রাহক ও অর্ডার বুকিং (New Order)",
    desc: "উপরে '+ নতুন অর্ডার' বাটনে চাপ দিন। গ্রাহকের মোবাইল নম্বর লিখুন। পোশাক (যেমন পাঞ্জাবি বা শার্ট) নির্বাচন করে ডেলিভারির তারিখ দিন। এরপর মাপসমূহ লিখে মোট মূল্য ও অগ্রিম টাকা এন্ট্রি করে সংরক্ষণ করুন। তাৎক্ষণিক ক্যাশ রসিদ ও কাটিং স্লিপ প্রিন্ট করুন।",
    icon: "Orders",
  },
  {
    title: "২. কারিগর নিয়োগ ও সেলাই পরিচালনা (Production)",
    desc: "অর্ডার্স ট্যাবে যান। কাটিং বা সেলাই কারিগর নির্বাচন করে 'Assign' করুন। কাজ শেষ হলে 'Move to Ready' চাপুন, গ্রাহকের মোবাইলে স্বয়ংক্রিয়ভাবে তৈরি হওয়ার এসএমএস চলে যাবে।",
    icon: "Karigars",
  },
  {
    title: "৩. কাপড়ের সরাসরি বিক্রয় (Point of Sale)",
    desc: "কার্যক্রম (Operations) থেকে কাপড়ের তালিকায় যান বা সরাসরি পিওএস-এ গিয়ে গজ বা থান হিসেবে কাপড় যোগ করুন। ডিসকাউন্ট ও পেমেন্ট মেথড দিয়ে ক্যাশ মেমো প্রিন্ট করুন।",
    icon: "Cash",
  },
  {
    title: "৪. গ্রাহকের বকেয়া আদায় (Customer Due Collection)",
    desc: "অর্ডার কার্ড বা গ্রাহক তালিকায় 'Pay Due' বাটনে ক্লিক করে আংশিক বা পূর্ণ বকেয়া গ্রহণ করুন এবং তাৎক্ষণিক মানি রিসিট প্রদান করুন।",
    icon: "Accounting",
  },
  {
    title: "৫. কারিগর হাজিরা ও মজুরি পরিশোধ (Wages)",
    desc: "কার্যক্রম থেকে কারিগর হাজিরা দিন এবং পিস রেট সেলাই কাজ এন্ট্রি করুন। সপ্তাহান্তে বা মাসান্তে এক ক্লিকে মজুরি শিট তৈরি করে পরিশোধ করুন।",
    icon: "Scissors",
  },
  {
    title: "৬. দৈনিক হিসাব ও রিপোর্ট (Daily Reports)",
    desc: "রিপোর্টস থেকে আজকের আয়, মোট বিক্রয়, দোকান খরচ, মোট বকেয়া ও আনুমানিক লাভ-ক্ষতির পূর্ণ হিসাব এক নজরে দেখুন ও ডাউনলোড করুন।",
    icon: "Reports",
  },
];

const guidesEn = [
  {
    title: "1. Order Booking & Measurement Wizard",
    desc: "Click '+ New Order' from the top bar. Enter the customer's mobile number, select the garment, pick a delivery date, fill the measurement fields, enter making charge & advance, and immediately print the 80mm thermal receipt and job card.",
    icon: "Orders",
  },
  {
    title: "2. Production & Karigar Assignment",
    desc: "Track orders visually on the Kanban board. Assign active tailors/cutters, advance the state from measuring to stitching to ready. An automated SMS is dispatched when marked ready.",
    icon: "Karigars",
  },
  {
    title: "3. Direct Fabric & Accessory POS",
    desc: "Use Operations -> POS to sell fabric by the yard or ready-made clothes. Add items to cart, enter discounts, collect payments (Cash/bKash/Card), and issue printable receipts.",
    icon: "Cash",
  },
  {
    title: "4. Customer Accounts & Dues Collection",
    desc: "Open customer profiles to view statements and 1-click WhatsApp messaging. Collect due payments instantly on any order.",
    icon: "Accounting",
  },
  {
    title: "5. Craftsman Attendance & Wage Payouts",
    desc: "Log daily worker attendance and record piece-work units. Generate auditable wage payout statements with printable vouchers.",
    icon: "Scissors",
  },
  {
    title: "6. Daily Cash Flow & Intelligence Reports",
    desc: "Access daily and monthly summaries for gross revenue, expenses, net profit, unpaid wage liabilities, and low-stock fabric alerts.",
    icon: "Reports",
  },
];
</script>

<template>
  <div>
    <header>
      <div>
        <p class="eyebrow">WORKSHOP TUTORIALS & HELP</p>
        <h1>User Guide & Knowledge Base</h1>
        <p>Step-by-step instructions designed for workshop receptionists, cutting masters and tailors.</p>
      </div>
    </header>

    <div class="guides-grid">
      <article
        v-for="guide in (locale === 'bn' ? guidesBn : guidesEn)"
        :key="guide.title"
        class="panel guide-card"
      >
        <div class="guide-icon-wrapper">
          <NavIcon :name="guide.icon" />
        </div>
        <div>
          <h2>{{ guide.title }}</h2>
          <p>{{ guide.desc }}</p>
        </div>
      </article>
    </div>
  </div>
</template>

<style scoped>
.guides-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(20rem, 1fr));
  gap: 1.25rem;
  margin-top: 1.5rem;
}

.guide-card {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.guide-icon-wrapper {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-sm);
  background: var(--primary-soft);
  color: var(--primary);
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.guide-card h2 {
  font-size: 1.05rem;
  margin: 0 0 0.4rem;
  color: var(--ink);
}

.guide-card p {
  margin: 0;
  font-size: 0.88rem;
  line-height: 1.5;
  color: var(--muted);
}
</style>
