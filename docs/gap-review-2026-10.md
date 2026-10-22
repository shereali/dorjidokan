# Tailors — গ্যাপ রিভিউ (Production Readiness Audit অনুযায়ী)

পরিধি: `backend/` (Laravel 12) + `frontend/` (Nuxt 3)। এই রিভিউ কোড পড়ে করা; অ্যাপ চালিয়ে UI যাচাই এখনো হয়নি (নিচে "যাচাই বাকি" দেখুন)।

## ১. রোল ও পারমিশন (সবচেয়ে বড় গ্যাপ)

| # | সমস্যা | প্রমাণ | ঝুঁকি |
|---|---|---|---|
| R1 | রোল মাত্র ৩টা স্ট্রিং (`admin/manager/staff`), কোনো permission টেবিল নেই। Master/Karigar/Cutter/Receptionist আলাদা রোল নেই। | `PlatformMutationRequest` `in:admin,manager,staff` | CRITICAL |
| R2 | বেশিরভাগ API রুটে রোল চেক নেই। `staff` ইউজার `DELETE orders`, `reports/summary`, `expenses`, `attendance`, `work-entries`, `payouts (GET)`, `customers delete`, `inventory` সবই করতে পারে। | `routes/api.php` — শুধু garments/employees/settings/members ইত্যাদিতে `tenant.role` আছে | CRITICAL |
| R3 | সাইডবার শুধু `adminOnly` (admin/manager) বনাম বাকি সবাই — রোল অনুযায়ী মেনু নেই। Billing, Reports, Operations সবাই দেখে। | `layouts/default.vue` `visibleNav` | HIGH |
| R4 | ফ্রন্টএন্ড রোল কুকি (`tailors_role`) থেকে আসে; ব্যাকএন্ড আলাদা `permissions` পাঠায় না, তাই UI গেটিং ও API গেটিং দুটো আলাদা সত্য। | `stores/session.ts` | HIGH |
| R5 | শেষ admin কে ডিমোট/ডিলিট করা আটকানো নেই (শুধু নিজেকে আটকায়)। অন্য admin কে নামালে ওয়ার্কশপ admin-হীন হতে পারে। | `PlatformController::updateMember/deleteMember` | MEDIUM |
| R6 | একই ইমেইল দুই ওয়ার্কশপে যোগ করা যায় না (`unique:users,email`) — এক মাস্টার দুই দোকানে কাজ করলে সমস্যা। | `saveMember` rule | MEDIUM |

**প্রস্তাবিত সমাধান:** `roles` + `permissions` (per-tenant, ডিফল্ট সিড: Owner, Manager, Master/Cutter, Karigar, Receptionist, Accountant) → `tenant_memberships.role_id`। লগইনে `permissions[]` ব্যাকএন্ড থেকে ফেরত; একটাই `can('orders.delete')` সত্য — রাউট মিডলওয়্যার `permission:orders.delete`, ফ্রন্টএন্ডে মেনু ও বাটন লুকানো সেই তালিকা থেকে। Settings এ রোল ম্যাট্রিক্স এডিটর।

## ২. Super Admin (প্ল্যাটফর্ম কন্ট্রোল)

| # | সমস্যা | প্রমাণ |
|---|---|---|
| S1 | UI ও API মেলে না: UI `plan`, `orders_this_month`, audit `id` চায়; API এগুলো পাঠায় না → `undefined` দেখায়। | `SuperAdminWorkspace.vue` বনাম `PlatformController::tenants/audits` |
| S2 | স্ট্যাটাস শুধু `active/suspended`। চাওয়া: Active, Inactive, Hold, Suspended (+ কারণ, কে/কখন)। | `PlatformMutationRequest` `in:active,suspended` |
| S3 | সাবস্ক্রিপশন/পেমেন্ট ম্যানেজমেন্ট নেই: প্ল্যান বদল, মেয়াদ বাড়ানো, ম্যানুয়াল পেমেন্ট এন্ট্রি, ইনভয়েস, ট্রায়াল এক্সটেনশন — কিছুই নেই। | super-admin গ্রুপে মাত্র ৪টা রুট |
| S4 | প্ল্যান/প্যাকেজ CRUD নেই (শুধু সিডার)। | `PlanSeeder`, কোনো Plan কন্ট্রোলার নেই |
| S5 | রিপোর্ট নেই: মোট সাবস্ক্রিপশন, MRR, আজকের নতুন সাবস্ক্রিপশন, churn, মেয়াদ শেষ হতে যাওয়া, এক্সপোর্ট (PDF/CSV)। | — |
| S6 | শুধু Stripe। বাংলাদেশে bKash/Nagad/SSLCommerz/ম্যানুয়াল ব্যাংক পেমেন্ট দরকার। | `PlatformController::checkout` |
| S7 | সাবস্ক্রিপশন মেয়াদ শেষ হলে লক হয় না — `FeatureGateService` fallback করে starter limit এ চালিয়ে যায়; `grace_ends_at` কোথাও enforce হয় না। | `FeatureGateService::limits`, `ResolveTenant` |
| S8 | Impersonation: ফ্রন্টএন্ড `token` আশা করে কিন্তু ব্যাকএন্ড সেশন লগইন করায়; সুপার অ্যাডমিন সেশন হারায়, "ফিরে যান" ব্যানার নেই। | `impersonate()` ও UI `impersonate()` |
| S9 | সুপার অ্যাডমিন আলাদা ইউজার নয় — সিডে `admin@tailors.test` একই সাথে সুপার অ্যাডমিন ও ওয়ার্কশপ অ্যাডমিন। আলাদা লগইন/ডোমেইন নেই। | `UserSeeder` |
| S10 | টেন্যান্ট তালিকায় সার্চ/ফিল্টার/পেজিনেশন UI নেই (API তে cursor আছে, UI ব্যবহার করে না)। অডিট শুধু ১০০টা, ফিল্টার নেই। | `audits()` `limit(100)` |
| S11 | suspend করলে ওই টেন্যান্টের চলমান টোকেন/সেশন বাতিল হয় না (শুধু পরের রিকোয়েস্টে `ResolveTenant` আটকায়, ভালো; তবে ব্যবহারকারীকে কারণ বার্তা নেই — জেনেরিক 404)। | `ResolveTenant` |

## ৩. স্কেলেবিলিটি / পারফরম্যান্স

- **SC1** ড্যাশবোর্ড ও রিপোর্টে লো-স্টক হিসাব সব `InventoryItem` মেমরিতে লোড করে ফিল্টার করে (`->get()->filter`)। বড় টেন্যান্টে ধীর। → SQL `HAVING` / ক্যাশড ব্যালেন্স কলাম।
- **SC2** ড্যাশবোর্ডে ৫+ আলাদা `count()` কোয়েরি, কোনো ক্যাশ নেই। → একটি `GROUP BY status` + `Cache::remember` (৩০–৬০ সেকেন্ড, টেন্যান্ট কী)।
- **SC3** `AppController` ৮০৬ লাইন, `OperationsController` ২৭৬ — একটাতে সব (customers, orders, garments, employees, loyalty)। Services/Repositories ভাগ দরকার (audit code_1)।
- **SC4** `garments`, `employees` লিস্ট অপেজিনেটেড `->get()`।
- **SC5** ফ্রন্টএন্ডে `OrderWorkspace.vue` (৩৫০৭ লাইন) ও `GarmentWorkspace.vue` (৩৪৪৬ লাইন) মনোলিথ — মেইনটেইন ও লোড দুটোতেই ঝুঁকি। → কম্পোনেন্ট ভাগ + lazy import।
- **SC6** `usage_counters` ও `orders_per_month` প্রতি রিকোয়েস্টে লাইভ কাউন্ট; ঠিক আছে এখনকার স্কেলে, পরে কাউন্টার ক্যাশ।
- ভালো দিক: tenant-scoped কম্পোজিট ইনডেক্স আছে, `BelongsToTenant` গ্লোবাল স্কোপ আছে, idempotency, rate limit, queue, Reverb আছে।

## ৪. UI/UX (কোড থেকে যা ধরা পড়েছে)

- **U1** টপবারের "Live" পিল সবসময় সবুজ হার্ডকোড — আসল WebSocket অবস্থা দেখায় না।
- **U2** সাইডবারের "Workshop/Manage", "Dark/Light", প্লেসহোল্ডার, টুলটিপ ইংরেজি হার্ডকোড — বাংলা মোডে মিশ্র ভাষা।
- **U3** গ্লোবাল সার্চ শুধু `/orders?query=` এ যায়; কাস্টমার/মোবাইল/বারকোড স্ক্যান আলাদা করে না, যদিও প্লেসহোল্ডারে তাই বলা।
- **U4** `keydown` লিসেনার `onMounted` এ যোগ হয়, `onUnmounted` এ সরানো হয় না (লিক)।
- **U5** মাস্টারের জন্য "দ্রুত মাপ নেওয়া" ফ্লো: `OrderWorkspace` এ পরিমাপ আছে, তবে ৩.৫k লাইনের একটাই ভিউ — মোবাইলে ধাপে-ধাপ (wizard) ও বড় ইনপুট/ইউনিট টগল (ইঞ্চি/সেমি) যাচাই দরকার।
- **U6** `session.clear()` ডিফল্ট ইমেইল `admin@tailors.test` রেখে দেয়; ডিফল্ট টেন্যান্ট `heritage-tailors` কুকিতে হার্ডকোড।
- **U7** CSS: ৯৩৪ লাইনের `main.scss` + `components/*` ফাইল; `settings/tools/utilities` ফোল্ডার খালি — ডিজাইন টোকেন কাঠামো অসম্পূর্ণ।

## ৫. সিকিউরিটি / অপারেশন

- **X1** Login: যে ইউজার কোনো টেন্যান্টের সদস্য নয়, সে `Tenant::where('status','active')->first()` এ পড়ে যায় (নিরীহ, তবে অপ্রয়োজনীয় ও বিভ্রান্তিকর)।
- **X2** `PlatformController::suspend` `$tenant->update($d)` — রিজন/অডিট আছে, তবে বাতিল-সেশন নেই।
- **X3** ইমেইল ভেরিফিকেশন বন্ধ (`MustVerifyEmail` কমেন্টেড)।
- **X4** Sentry/Prometheus মনিটরিং কনফিগ পাইনি (audit func_4)।
- **X5** সাম্প্রতিক কমিট সবই "stabilize e2e/CI" — টেস্ট ফ্লেকি ছিল; RBAC ও সুপার অ্যাডমিনের জন্য নতুন টেস্ট লাগবে।

## ৬. প্রস্তাবিত ধাপ (অগ্রাধিকার অনুযায়ী)

1. **ফেজ ১ — RBAC:** roles/permissions মাইগ্রেশন+সিড, `permission` মিডলওয়্যার, সব রুটে প্রয়োগ, লগইনে `permissions[]`, সাইডবার/বাটন গেটিং, Settings এ রোল ম্যাট্রিক্স, টেস্ট।
2. **ফেজ ২ — Super Admin:** স্ট্যাটাস (active/inactive/hold/suspended + কারণ), প্ল্যান CRUD, ম্যানুয়াল পেমেন্ট/ইনভয়েস, সাবস্ক্রিপশন মেয়াদ ও লক, ড্যাশবোর্ড (মোট/আজকের সাবস্ক্রিপশন, MRR, মেয়াদোত্তীর্ণ), CSV/PDF রিপোর্ট, impersonation ফিক্স।
3. **ফেজ ৩ — স্কেল:** SC1–SC4, ক্যাশ, কন্ট্রোলার ভাগ।
4. **ফেজ ৪ — UI/UX:** অ্যাপ চালিয়ে প্রতিটি পেজ স্ক্রিনশট-রিভিউ, মাস্টারের মাপ-ফ্লো সহজ করা, OrderWorkspace/GarmentWorkspace ভাগ, i18n সম্পূর্ণ, ডিজাইন টোকেন।
5. **ফেজ ৫ — অডিট চেকলিস্ট ক্লোজ:** মনিটরিং, ভেরিফিকেশন, ডক, টেস্ট কভারেজ।

## যাচাই বাকি
- অ্যাপ চালিয়ে ব্রাউজারে UI/UX ও মোবাইল ভিউ দেখা হয়নি।
- ব্যাকএন্ড টেস্ট স্যুট চালিয়ে বর্তমান অবস্থা (পাস/ফেল) দেখা হয়নি।
- `OperationsController`, `OrderWorkspace` এর ভেতরের লজিক সম্পূর্ণ পড়া হয়নি।

## ফেজ ১ — স্ট্যাটাস (RBAC): সম্পন্ন
- ৬ রোল: Owner, Manager, Master/Cutter, Karigar, Receptionist, Accountant (`backend/config/permissions.php`); পুরনো `admin→owner`, `staff→receptionist` মাইগ্রেশনে রূপান্তরিত।
- প্রতিটি টেন্যান্ট রুটে `permission:` মিডলওয়্যার; `GET /auth/me` ও লগইন `permissions[]` ফেরত দেয়; `GET/PUT/DELETE /roles` ম্যাট্রিক্স API (অডিটেড, owner-only পারমিশন কখনো অন্য রোলকে দেওয়া যায় না)।
- ফ্রন্টএন্ড: সাইডবার/রুট গার্ড/বাটন পারমিশন অনুযায়ী; Settings এ রোল ম্যাট্রিক্স এডিটর; শেষ Owner ডিমোট/ডিলিট আটকানো।
- বাকি (ফেজ ৪): Operations পেজের ভেতরের ট্যাব, ও Karigar কে শুধু নিজের অর্ডার দেখানো (ইউজার↔কারিগর লিংক লাগবে)।

## ফেজ ২ — স্ট্যাটাস (Super Admin): সম্পন্ন
- স্ট্যাটাস: Active / Inactive / Hold (শুধু দেখা, ৪২৩) / Suspended, বাধ্যতামূলক কারণ, লগইন ও API দুটোতেই কারণসহ বার্তা (S2, S11)।
- সাবস্ক্রিপশন: মেয়াদোত্তীর্ণ হলে ওয়ার্কশপ read-only (৪০২ `subscription_expired`), বিলিং রুট খোলা (S7); `SubscriptionState` সার্ভিস।
- ম্যানুয়াল পেমেন্ট (bKash/Nagad/Rocket/ব্যাংক/নগদ) → সাবস্ক্রিপশন সক্রিয় ও মেয়াদ বৃদ্ধি; প্ল্যান বদল/ট্রায়াল (S3, S6)।
- প্ল্যান CRUD (S4); ওভারভিউ: মোট/আজকের/এ মাসের সাবস্ক্রিপশন, MRR/ARR, আদায়, ৬ মাসের সিরিজ, ৭ দিনে মেয়াদ শেষ; CSV এক্সপোর্ট ও প্রিন্ট/PDF (S5)।
- টেন্যান্ট তালিকা: সার্চ, স্ট্যাটাস/সাবস্ক্রিপশন ফিল্টার, পেজিনেশন, বিস্তারিত মোডাল (S1, S10); impersonation এর UI ঠিক ও কনফার্ম (S8)।
- বাকি: SSLCommerz/bKash অনলাইন গেটওয়ে ইন্টিগ্রেশন, আলাদা সুপার অ্যাডমিন লগইন/ডোমেইন (S9), ইনভয়েস PDF।

## ফেজ ৩ — স্ট্যাটাস (স্কেল/পারফরম্যান্স): সম্পন্ন
- SC1: লো-স্টক এখন SQL স্কোপ (`InventoryItem::lowStock()`), মেমরিতে সব আইটেম লোড হয় না; রিপোর্টে সর্বোচ্চ ২০০।
- SC2: ড্যাশবোর্ড ৫+ আলাদা count এর বদলে একটি `GROUP BY status`, `whereDate` এর বদলে রেঞ্জ (ইনডেক্স ব্যবহারযোগ্য), ওয়ার্কশপ-ভিত্তিক ১৫ সেকেন্ড ক্যাশ; অর্ডার লিস্টের ৪টি aggregate একটিতে।
- রোল ম্যাট্রিক্স ওভাররাইড প্রতি রিকোয়েস্টে কোয়েরি না করে ক্যাশ (এডিটে ইনভ্যালিডেট)।
- ইনডেক্স: `orders(tenant_id, created_at)`, `audit_logs(action,id)`, `tenants(created_at)`।
- ফ্রন্টএন্ড: প্রতিটি পেজে সব কাস্টমার লোড বন্ধ (২০টির সীমায় বাকিরা অদৃশ্য হতো); সার্ভার-সাইড সার্চের `CustomerPicker`, অর্ডার উইজার্ডের সাজেশন সার্ভার থেকে; Operations/Accounting রোল-সচেতন লোড (এক ফেল পুরোটা ভাঙে না)।
- বাকি (ফেজ ৪): OrderWorkspace/GarmentWorkspace ভাগ করা, garments/employees পেজিনেশন (ছোট তালিকা), Redis কিউ/ক্যাশ প্রোডাকশন কনফিগ, লোড টেস্ট (k6) বাড়ানো।

## ফেজ ৪ — স্ট্যাটাস (UI/UX): প্রথম ধাপ সম্পন্ন (অ্যাপ চালিয়ে ব্রাউজারে যাচাই)
- মাস্টারের মাপ-ফ্লো: মাপের গ্রিড ভাঙা ছিল (unit লেবেল বাইরে ভেসে যেত, "আবশ্যক" ব্যাজ লেবেলে চাপ); এখন পরিষ্কার গ্রিড, বড় ইনপুট, `inputmode=decimal`, **Enter চাপলে পরের মাপ বক্সে যায়** (শেষে পরের ধাপে) — মাউস ছাড়া পুরো মাপ তোলা যায়।
- আসল বাগ: অর্ডার উইজার্ডের কাস্টমার সাজেশনে স্টাইল ছিল না (ক্লিকযোগ্য মনে হতো না) — ঠিক; নির্বাচনের পর সাজেশন লুকায়।
- লোডিং: অর্ডার পেজ লোডের সময় "0 Orders" দেখাত; এখন "—" ও "Loading…"।
- "Live" পিল সবসময় সবুজ হার্ডকোড ছিল → আসল অনলাইন/অফলাইন; সাইডবার/থিম/সার্চ লেবেল বাংলা-ইংরেজি; কীবোর্ড শর্টকাট লিসেনার লিক বন্ধ (আগেই)।
- ফোনে অর্ডার কাউন্টার ২×২ (আগে ৪টি লম্বা কার্ড)।
- সুপার অ্যাডমিনে 2FA না থাকলে "Could not load" এর বদলে পরিষ্কার নির্দেশনা ও Settings লিংক।
- পাসওয়ার্ড রিসেটে ওয়ার্কশপ স্লাগ এখন ঐচ্ছিক (আগে ডিফল্ট `heritage-tailors` এর ওপর নির্ভর করত); সেশন ডিফল্টে ডেমো টেন্যান্ট/ইমেইল সরানো।
- লগইনে ডেমো ক্রেডেনশিয়াল/রোল শর্টকাট প্রোডাকশনে বন্ধ (`NUXT_PUBLIC_DEMO_MODE`), ভাষা বদল থাকে।
- বাকি: OrderWorkspace/GarmentWorkspace (৩.৫k লাইন) ভাঙা, বাকি পেজে হার্ডকোড ইংরেজি/মিশ্র ভাষা i18n এ আনা, design token ফোল্ডার (settings/tools/utilities) পূরণ, ইঞ্চি/সেমি ইউনিট টগল, Karigar শুধু নিজের অর্ডার।

## ফেজ ৪ — দ্বিতীয় ধাপ: OrderWorkspace ভাগ ও i18n (ব্রাউজারে যাচাই)
- `OrderWorkspace.vue` ৩৬০৪ → ২০২৩ লাইন; নতুন `OrderWizard.vue` (অর্ডার তৈরির ৪-ধাপ উইজার্ড: স্টেট, ভয়েস, দাম হিসাব, ভ্যালিডেশন, নিজস্ব CSS) এবং শেয়ারড টাইপ `app/types/orders.ts`। ডেটা ফ্লো: `v-model` (খোলা/বন্ধ), props `garments/employees`, ইভেন্ট `created` → বোর্ড রিফ্রেশ, অর্ডার সিলেক্ট, রসিদ প্রিন্ট।
- উইজার্ড বন্ধ হলে মাইক্রোফোন ছেড়ে দেয়; ফর্ম প্রতিবার খোলার সময় রিসেট।
- আসল বাগ (ব্রাউজারে ধরা): ধাপ ১ এ শুধু নাম দিয়ে এগোনো যেত, শেষে অস্পষ্ট "Could not complete order creation" আসত। এখন নতুন গ্রাহকের জন্য মোবাইল+নাম বাধ্যতামূলক, নির্বাচিত গ্রাহকের নম্বর বদলালে প্রোফাইল ভুলে যায়, ও Laravel ভ্যালিডেশন বার্তা (`apiErrorMessage`) দেখায়।
- i18n: অর্ডার পেজ ও উইজার্ডে "English (বাংলা)" মিশ্র লেবেল বাদ — সক্রিয় ভাষাতেই সব (হেডার, ফিল্টার, কাউন্টার, কানবান কলাম, ধাপ, বাটন, টোস্ট, ভ্যালিডেশন)। ইংরেজি ও বাংলা দুই মোডে যাচাই।
- বাকি: Inspector প্যানেল ও কানবান আলাদা কম্পোনেন্ট; GarmentWorkspace (৩.৪k), Customer/Operations/Inventory/Reports/Billing/Notifications/Help পেজের মিশ্র ভাষা; JSON ক্যাটালগে স্থানান্তর (এখন `tr(en, bn)`)।

## ফেজ ৪ — তৃতীয় ধাপ: বাকি পেজের i18n (ব্রাউজারে দুই ভাষায় যাচাই)
- গ্রাহক, Operations, Inventory, Reports, Billing, Notifications, Help, Garments — সক্রিয় ভাষাতেই সব লেবেল, প্লেসহোল্ডার, টোস্ট, কনফার্ম ডায়ালগ। Garments আগে শুধু বাংলা ছিল, এখন ইংরেজিও।
- যাচাই: ইংরেজি মোডে বাংলা অক্ষর কেবল ডেটায় (সিডের নাম) ও ৳ চিহ্নে; বাংলা মোডে ল্যাটিন কেবল ডেটা (গ্রাহক/পণ্যের নাম, একক) ও WhatsApp/JSON এ।
- `statusLabel` এখন দুই ভাষায় (নোটিফিকেশন কিউ আগে কাঁচা `scheduled` দেখাত)।
- Billing: প্ল্যান লিমিট কাঁচা কী (`STAFF SEATS … true`) এর বদলে পাঠযোগ্য সারি; **Stripe price id টেন্যান্টের কাছে প্রকাশ হতো** (`/billing` ও `/plans`) — এখন বাদ, টেস্টসহ।
- বাকি: Dashboard, Settings, Accounting, Karigars (Workforce) পেজ ও PrintTemplates (রসিদ/জব কার্ড) এখনো মিশ্র/একভাষী; `tr(en, bn)` থেকে JSON ক্যাটালগে স্থানান্তর; seed ডেটার নাম দ্বিভাষিক ("Waist (কোমর)") — এগুলো ডেটা, UI নয়।
