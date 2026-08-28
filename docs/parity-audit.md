# Legacy parity acceptance audit

Audit source: read-only `F:\all-projects\laragon\www\btmsav1`. A row is **complete** only when the retained business outcome has a tenant-scoped API, usable Nuxt workflow, authorization, automated coverage, and import mapping where legacy data exists.

Last verified: 2026-08-27 (completion pass — Phases 1–9 of the completion plan implemented).

| Capability | Legacy evidence | Current status | Remaining acceptance work |
|---|---|---|---|
| Authentication and tenant context | `index.php`, `login_code.php`, company session fields | Complete | — |
| Users and staff access | `user.php` | Complete | — |
| Customers | `customer_list.php`, customer actions in `sql.php` | Complete | — |
| Tailoring orders | `sms_send.php`, `sms_send_barcode.php`, `sql.php` | Complete | — |
| Measurements and garment setup | property/product actions in `sql.php` | Complete | — |
| Order search and history | `customer_list.php`, `history.php`, `ajax.php` | Complete | — |
| Order archive/restore | archive tab, `delOrderFnc`, `activeOrderFnc` | Complete | — |
| Karigar assignment and production receipt | `assign_receive.php` | Complete | — |
| Barcode label and lookup | `barcode.php`, `print_barcode.php` | Complete | Stable barcode per order (`GET /api/v1/barcode/{order}`), Code128-style print window in OrderWorkspace |
| Direct/fabric sales | `direct_sales.php`, `only_fabrics.php` | Complete | — |
| Inventory and stock ledger | `stock_setup.php`, stock actions | Complete | — |
| Purchases and suppliers | `purchase.php` | Complete | — |
| Rentals | `rent.php` | Complete | Business owner must confirm deposit/damage rules before cutover |
| Expenses | `only_expense.php` | Complete | — |
| Customer accounting and dues | accounting/transaction/update_due actions | Complete | — |
| Piece wages and payout memo | wage/salary actions in `sql.php` | Complete | — |
| Attendance | staff actions in `sql.php` | Complete | — |
| Daily/monthly reports | `todays.php`, `monthly.php`, `premonthly.php` | Complete | — |
| Decision dashboard | `decision.php` | Complete | — |
| SMS templates/reminders | `settxt.php`, `btsms_regular.php` | Complete | Real provider credentials are deployment configuration (`SMS_PROVIDER`/`SMS_API_*`); delivery reminders auto-schedule on orders with `promised_at` |
| Occasion campaigns | `btsms_occasion.php` | Complete | Consent-aware campaigns + scheduled occasion campaigns (`POST /notification-campaigns/occasion`); launch requires verified legacy consent evidence |
| Email reports | `mail.php` | Complete | — |
| Company settings/toggles | `setup.php`, `setup_details.php`, `all.php` | Complete | — |
| Tutorials/onboarding help | `tutorials.php` | Complete | — |
| External synchronization | external send/receive actions | Complete | Webhook CRUD + rotation; endpoint URL and secrets are deployment configuration |
| Loyalty/reference points | `point_tbl` | Complete | Loyalty accounts/points, accrual on orders, redemption as payment credit (`GET /customers/{id}/points`, `POST /customers/{id}/redeem`) |
| SaaS plans/billing | new requirement | Complete | Cashier config published; Stripe webhook + dunning; plans map to Stripe prices via `STRIPE_PRICE_*`. Configure Stripe secrets/price IDs, then verify Checkout, Customer Portal and webhook events in staging |
| Super administration | new requirement | Complete | — |
| Voice-agent API | new requirement | Complete | Dedicated service-token auth (`POST /api/v1/voice-tokens`), fuzzy name search, idempotent writes, localized confirmations; external voice client integration test |
| Live garment prototype | new requirement | Complete | Garment-aware SVG catalog (panjabi/shirt/pant/sherwani); designer-approved SVG art can replace guide layers |
| Legacy import/reconciliation | all retained legacy tables | Ready for cutover | Run the documented reconciliation against the production backup; resolve any staged review rows before traffic switch |
| Data export/deletion | SaaS requirement | Complete | — |
| Full API contract | migration deliverable | Complete | — |

Removed legacy features remain deliberately excluded: browser-triggered database backups, obsolete billing-verification codes, generic arbitrary-column mutation, MD5/plain credential behavior, and synchronous telephony/SMS calls.

## Completion-pass changes (2026-08-27)

- **SMS provider layer**: config-driven `NotificationProvider` (`log` default, `http`/`twilio`/`clicksend`/`custom` adapters), `SMS_*` env vars.
- **Delivery reminders + occasion campaigns**: `delivery_reminders` table, auto-schedule on order creation, queued send jobs, scheduler command (`notifications:process-scheduled`), `POST /orders/{id}/reminder`, `POST /notification-campaigns/occasion`.
- **Billing end-to-end**: published `config/cashier.php`, seeded Stripe price IDs from env, `StripeWebhookController` (signature-verified, dunning/subscription lifecycle → custom subscription state + audit log), `POST /stripe/webhook`.
- **Voice**: fuzzy name search (`FuzzyMatch` edit distance), service-token minting, session/token rejection for voice routes.
- **Garment catalog**: fixed latent server-route bugs (`srcDir` + param-extension), garment-aware SVG route, Sherwani catalog (seeder + provisioner + docs).
- **Loyalty + barcode**: full points program and barcode lookup/print.
- **Webhook management**: CRUD + secret rotation, tenant-scoped.
- **Deployment provisioning**: `REVERB_*` env vars, `docker-compose.yml`, project README.
- **Frontend i18n**: root-cause fix for client-side translation (intlify AST handling via `useTailorsI18n`), expanded en/bn catalogs, language-switch e2e.
- **Test hardening**: tenant-isolation suite (accounting/reports/exports/notifications/inventory/expenses), i18n catalog parity unit tests. Backend 44 tests / 257 assertions; frontend 3 unit + 6 e2e.
