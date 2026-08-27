# Legacy parity acceptance audit

Audit source: read-only `F:\all-projects\laragon\www\btmsav1`. A row is **complete** only when the retained business outcome has a tenant-scoped API, usable Nuxt workflow, authorization, automated coverage, and import mapping where legacy data exists.

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
| Barcode label and lookup | `barcode.php`, `print_barcode.php` | Complete | — |
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
| SMS templates/reminders | `settxt.php`, `btsms_regular.php` | Complete | Real provider credentials are deployment configuration |
| Occasion campaigns | `btsms_occasion.php` | Complete | Launch requires verified legacy consent evidence |
| Email reports | `mail.php` | Complete | — |
| Company settings/toggles | `setup.php`, `setup_details.php`, `all.php` | Complete | — |
| Tutorials/onboarding help | `tutorials.php` | Complete | — |
| External synchronization | external send/receive actions | Complete | Endpoint URL and secrets are deployment configuration |
| SaaS plans/billing | new requirement | Ready for deployment | Configure Stripe secrets and each plan's `stripe_price_id`, then verify Checkout, Customer Portal and webhook/dunning events in staging |
| Super administration | new requirement | Complete | — |
| Voice-agent API | new requirement | Complete | External voice client integration test |
| Live garment prototype | new requirement | Complete | Final designer-approved SVG catalog can replace generated layers |
| Legacy import/reconciliation | all retained legacy tables | Ready for cutover | Run the documented reconciliation against the production backup; resolve any staged review rows before traffic switch |
| Data export/deletion | SaaS requirement | Complete | — |
| Full API contract | migration deliverable | Complete | — |

Removed legacy features remain deliberately excluded: browser-triggered database backups, obsolete billing-verification codes, generic arbitrary-column mutation, MD5/plain credential behavior, and synchronous telephony/SMS calls.
