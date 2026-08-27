# Legacy Feature Inventory

Status: discovery baseline, 2026-08-19. The legacy application is read-only at `F:\all-projects\laragon\www\btmsav1`.

## Classification policy

- **Keep as-is** means preserve the business outcome, not the legacy implementation.
- **Improve** means preserve and redesign the workflow.
- **Simplify/Merge** means preserve the capability inside a smaller, clearer module.
- **Remove** means intentionally omit it. Removal still requires stakeholder approval before production cutover.

## Inventory

| Legacy feature | Evidence | Classification | Modern destination and rationale |
|---|---|---|---|
| Authentication and sign-out | `index.php`, `login_code.php`, `signout.php` | Improve | Sanctum SPA auth, password hashing, rate limits, optional 2FA, tenant-aware sessions. Plain/MD5-style credential handling must not migrate. |
| Company/shop context | `company`, `com_id` throughout `sql.php`/`ajax.php` | Improve | `tenants` plus automatic tenant scope. Existing company becomes Tenant #1. |
| User creation and staff access | `user.php`, settings in `all.php` | Improve | Users, tenant memberships, roles and permissions; separate employee profile from login identity. |
| Customer registry | `customer_list.php`, `saveCustomer`, `delCustomer` | Improve | Searchable customer profiles, unique normalized mobile per tenant, addresses, history, soft deletion. |
| New tailoring order | `sms_send.php`, `sms_send_barcode.php`, `add_order` in `sql.php` | Improve | Guided order workspace combining customer, garments, measurements, pricing and delivery promise. |
| Measurement/property setup | `savePropertices`, `saveProperticesExtra`, `product`, `property`-style records | Improve | Data-driven garment templates and ordered parts; typed values and units replace delimiter-packed fields. |
| Order list/search | `customer_list.php`, mobile/order searches in `ajax.php` | Improve | Cursor-paginated filters; exact mobile/order match first, fuzzy customer-name fallback. |
| Order editing/archive/restore | archive tab and `delOrderFnc`/`activeOrderFnc` | Simplify/Merge | Order detail timeline with guarded edit, cancel, archive and restore actions. No generic table mutation endpoint. |
| Master/karigar assignment | `assign_receive.php`, `saveMasterKarigor` | Improve | Explicit assignments and production status transitions with audit events. |
| Factory receive and customer delivery | `factory_status`, `factory_receive_date`, `phy_dv_date` | Improve | Order-item workflow states with transition validation and timestamps. |
| Order history/status audit | `history.php`, scattered status columns | Improve | Append-only `order_status_events` displayed as a timeline. |
| Barcode scanning/printing | `barcode.php`, `print_barcode.php`, scan actions | Improve | Stable barcode per order/item, browser print templates and scanner-friendly lookup. |
| Direct fabric sales | `direct_sales.php`, `direct_sale*` tables | Improve | Point-of-sale sale/invoice module sharing customers, inventory and payments. |
| Fabric-only sales | `only_fabrics.php` | Simplify/Merge | A sale type inside the unified POS flow. |
| Stock/catalog setup | `stock_setup.php`, `product`, stock actions | Improve | Garment/service catalog separated from inventory SKUs; stock ledger rather than mutable totals alone. |
| Stock receive/adjustment | `saveFabricsInStock`, `saveStockAdjustment` | Improve | Transactional inventory movements with reason, actor and immutable audit trail. |
| Purchase and suppliers | `purchase.php`, supplier fields in stock flow | Improve | Purchase orders/receipts and supplier directory. |
| Rental workflow | `rent.php` | Improve | Dedicated rental contracts, items, deposits, due/return state and payments. Preserve only after business validation. |
| Expenses and expense types | `only_expense.php`, `expenses`, `expense_type` | Improve | Expense records/categories with attachments, approvals and reports. |
| Customer accounting, due and payment | `accounting`, `transaction`, `update_due` | Improve | Invoice/payment allocation and ledger entries; balances derived from entries. |
| Karigar wages and memo | `wages_head`, `wages_details` | Improve | Piece-rate rules, work entries, payout batches and printable statements. |
| Attendance/staff status | attendance actions in `sql.php` | Improve | Attendance module linked to employees, not authentication users. |
| Daily/monthly/previous-month summaries | `todays.php`, `monthly.php`, `premonthly.php` | Simplify/Merge | One dashboard/reporting module with date presets and consistent metrics. |
| Operational reports and printing | `all.php`, `report_ord.php` | Improve | Filterable reports, asynchronous exports and accessible print views. |
| Decision dashboard | `decision.php` | Simplify/Merge | KPI dashboard; each metric must be reconciled against new ledger/order definitions. |
| SMS templates and delivery reminders | `set_text`, `dsms*`, SMS actions | Improve | Notification templates and queued provider adapter. Calling/telephony remains external. |
| Occasion/bulk SMS | `dsms_occasion`, occasion files | Improve | Consent-aware segmented campaigns; feature-gated and queued. |
| Email report sending | `mail.php`, PHPMailer, `sendEmail` | Simplify/Merge | Queued Laravel notification/export delivery. |
| Setup toggles | `company` columns and “All Settings” | Improve | Typed tenant settings and feature flags; no behavior encoded as unexplained integers. |
| Database backup button | `all.php`, batch files | Remove | Infrastructure-managed encrypted backups with restore drills; never a web-triggered shell script. |
| Billing verification/security-code flow | home modal and `verification` action | Remove | Replaced by Cashier subscription lifecycle and provider-hosted billing portal. |
| Tutorials | `tutorials.php` | Simplify/Merge | Contextual onboarding checklist and help center. |
| Generic arbitrary-column editor | `updateAnyColumn`, `editAllformation` | Remove | Severe authorization/integrity risk; replace with explicit validated endpoints. |
| External server synchronization | `sendToServerOrder`, `sendToServerReceivedOrder` | Improve | Documented idempotent integrations/webhooks with retry logs. |
| Voice-agent customer/order workflow | no legacy equivalent | Improve | New service-token API, tenant scope, idempotency, localized confirmations and rate limits. |
| Live garment prototype | no legacy equivalent | Improve | Reverb order channel plus reusable SVG-layer Nuxt component. |
| SaaS onboarding, plans and super admin | no legacy equivalent | Improve | Tenant provisioning, seeded catalog, feature gates, billing and audited support impersonation. |

## Concrete legacy pain points discovered

1. `sql.php` and `ajax.php` are multi-thousand-line action routers mixing HTTP, SQL, business rules and HTML.
2. User-controlled values are interpolated into SQL in many paths, creating injection and integrity risk.
3. Tenant isolation depends on manually remembering `com_id`; several queries omit it.
4. Authentication uses obsolete credential patterns and exposes overly broad mutation surfaces.
5. Status is spread across flags and nullable date columns (`factory_status`, `phy_dv_date`, `is_active`) without a validated state machine.
6. Measurements/design properties use delimiter-packed request values, making validation and evolution fragile.
7. Repeated scalar subqueries and unbounded/offset queries will create N+1 and scale problems.
8. Navigation exposes overlapping screens for orders, reports, history, fabrics and stock, increasing cognitive load.
9. UI logic, inline styles, jQuery and server-rendered fragments are tightly coupled, with weak loading/error/accessibility states.
10. Backups, billing verification, SMS and external synchronization are mixed into the web process instead of durable jobs/infrastructure.

## Decisions requiring product-owner confirmation

- Confirm whether Rental is actively used and which asset/deposit rules must survive.
- Confirm whether occasion marketing SMS has recorded consent and should launch in Phase 1.
- Supply the authoritative list of garment types; Panjabi, Shirt and Pant are the current baseline only.
- Confirm employee attendance/payroll depth versus simple piece-rate payouts.
- Confirm subdomain tenant identification and Stripe as the billing provider.

