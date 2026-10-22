# Roles and permissions

Workshop (tenant) access is permission-based. A role is a named set of permissions; the API enforces them and the SPA only mirrors them to hide menus and buttons.

## Where things live

| What | Where |
|---|---|
| Permission catalog, default role matrix, owner-only list, legacy aliases | `backend/config/permissions.php` |
| Per-workshop overrides | `tenant_roles` table, cached per tenant, edited in **Settings → Roles** (`/api/v1/roles`) |
| Resolution | `App\Services\PermissionService` (`roleOf`, `permissionsFor`, `normalize`) |
| Route enforcement | `permission:<a>,<b>` middleware (any-of), declared per route in `backend/routes/api.php` |
| Effective permissions for the SPA | `/api/v1/auth/me` and the login response |
| Menu / route gating in the SPA | `frontend/app/composables/useRouteAccess.ts` |

## Default roles

| Role | Intent |
|---|---|
| Owner | Everything; the only role that can hold billing, member, role, webhook, voice and workshop-deletion permissions. |
| Manager | Everything except the owner-only permissions. |
| Master / Cutter | Orders (create, edit, assign, status, measure), customers (view), attendance and work logs, stock (view). |
| Karigar | Dashboard, orders (view, status), garments (view). |
| Receptionist | Orders (create, edit, payments, measure), customers, sales and rentals, stock (view). |
| Accountant | Payments, payouts, expenses and approvals, accounting, reports and exports, billing and settings (view). |

The legacy keys `admin` and `staff` still resolve (to Owner and Receptionist).

## Guarantees (enforced by tests)

- Every route behind the `tenant` middleware carries a `permission:` check, except an explicit list of own-account routes (`RoutePermissionAuditTest`). **Adding a tenant route without one fails CI.**
- Every permission named on a route exists in the catalog.
- Every `super-admin` route requires the `super.admin` middleware.
- No default role other than Owner holds an owner-only permission, and the matrix editor refuses to grant one.
- A workshop must keep at least one owner; members cannot demote or remove themselves.

## Adding a permission

1. Add it to a group in `config/permissions.php` (and to the owner-only list if it must never be delegated).
2. Put it on the route: `->middleware('permission:thing.manage')`.
3. Add it to the default roles that should have it.
4. Add the labels to `frontend/i18n/locales/{en,bn}.json` (matrix editor) and, for a new page, to `useRouteAccess.ts`.
5. Run `php artisan test`; the audit test tells you if a step was missed.
