# Tailors — Tailoring Workshop Management Platform

A modern SaaS platform for tailoring shops: order management, measurements with a live visual garment prototype, karigar (craftsman) work assignment, fabric/inventory, purchases, rentals, expenses, wages/payouts, attendance, loyalty points, SMS campaigns and delivery reminders, subscription billing, and a voice-agent API.

**Stack:** Laravel 12 API (`backend/`) + Nuxt 3 SSR SPA (`frontend/`) + custom CSS design system. Migrated from the legacy PHP app at `F:\all-projects\laragon\www\btmsav1` (read-only reference).

## Repo layout

| Path | Purpose |
|---|---|
| `backend/` | Laravel API (`/api/v1`), tenancy, Sanctum auth, queues, Reverb, Cashier billing |
| `frontend/` | Nuxt 3 app (`app/` srcDir), Pinia, i18n (en/bn), custom CSS design system |
| `docs/` | Feature inventory, parity audit, garment design catalog, OpenAPI contracts, cutover guide |
| `tests/load/` | k6 smoke contract |
| `docker-compose.yml` | Local infra: MySQL, Redis, Mailpit, Reverb, queue worker |

## Quick start (local)

### 1. Infrastructure (optional but recommended)

```bash
docker compose up -d
```

This starts MySQL (`tailors` DB, user `tailors`/`tailors`), Redis, Mailpit (UI at `http://localhost:8025`), Reverb (WebSockets on `:8080`), and a queue worker.

### 2. Backend

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
# edit .env: DB_* for MySQL (or keep sqlite for a quick start), BROADCAST_CONNECTION=reverb,
# REVERB_*, STRIPE_* when billing is enabled
php artisan migrate --seed
php artisan serve --port 8000
```

Optional workers (run each in its own terminal):

```bash
php artisan queue:work            # queued jobs (exports, SMS, webhooks, notifications)
php artisan reverb:start          # WebSocket server
php artisan schedule:work         # scheduler (delivery reminders, campaign dispatch)
```

### 3. Frontend

```bash
cd frontend
npm ci
NUXT_PUBLIC_API_BASE=http://localhost:8000/api/v1 npm run dev
```

Open `http://localhost:3000`. Seeded tenant: slug `heritage-tailors`, `admin@tailors.test` / `ChangeMe123!`.

## Key environment variables

| Var | Purpose |
|---|---|
| `FRONTEND_URL` | Frontend origin used for billing redirects |
| `STRIPE_KEY` / `STRIPE_SECRET` / `STRIPE_WEBHOOK_SECRET` | Cashier billing; map plans via `STRIPE_PRICE_STARTER` / `STRIPE_PRICE_GROWTH` |
| `REVERB_*` | Realtime channels (garment prototype builder) |
| `SMS_PROVIDER` / `SMS_GATEWAY` / `SMS_API_URL` / `SMS_API_KEY` / `SMS_API_SECRET` | SMS gateway (`log` in dev; `http`/`twilio`/`clicksend`/`custom` in prod) |
| `LEGACY_DB_*` | Legacy `btmsav1` connection for the import commands |

## Notable modules

- **Voice-agent API** (`/api/v1/voice/*`) — service-token auth (`POST /api/v1/voice-tokens`), idempotent writes, localized confirmations, fuzzy search.
- **Live garment prototype** — `GarmentPrototypeBuilder` assembles per-part SVG guides as measurements arrive over Reverb.
- **Loyalty points** — accrual on orders, redemption as payment credit.
- **Occasion campaigns & delivery reminders** — consent-aware, scheduled, queued.
- **Super admin** — tenant management, impersonation (2FA-gated), audit log.
- **Legacy import** — `php artisan legacy:import <slug> --company-id=1` (idempotent; see `docs/legacy-cutover.md`).

## Testing

```bash
cd backend && php artisan test && vendor/bin/pint --test
cd frontend && npm run verify && npm run test:e2e
```

CI (`.github/workflows/ci.yml`) runs backend tests + Pint + audits, frontend verify/histoire/redocly, Playwright e2e, and a k6 load check. Deploy (`.github/workflows/deploy.yml`) ships via a `DEPLOY_WEBHOOK_URL` gateway.
