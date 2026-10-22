# Operations, Migration, and Security

## Environments

Local uses SQLite and synchronous queues. Staging and production use MySQL 8, Redis cache/queues, Horizon workers, S3-compatible storage, TLS, and separate secrets. Production deploys require approval and a database backup. Additive migrations ship before code that consumes them; destructive cleanup ships separately.

## Legacy import

1. Take and verify a legacy backup; leave the source read-only.
2. Provision Tenant #1 and normalize mobile numbers/dates.
3. Import company, users/employees, customers, catalog/properties, orders/items, measurements, accounting, inventory, wages, and notification history in that order.
4. Record each source table/id mapping and checksum; reruns update or skip and never delete.
5. Reconcile source/target counts, financial totals, orphan counts, and a sampled order timeline.
6. Run both systems behind a feature flag, freeze legacy writes, run the final delta, validate, then cut over.

## Security sign-off

- Eloquent/query builder only; validation is server-side.
- Tenant context is established after Sanctum authentication and applied globally to tenant models.
- Service tokens must be revocable, tenant-bound and restricted to voice abilities before production issuance.
- Voice writes use persisted idempotency keys; order-ready webhooks are signed, queued, retried and logged. Super-admin actions require a confirmed second factor and are appended to the audit log. Security headers include CSP, HSTS on TLS, clickjacking protection and MIME sniffing protection; dependency audits run in CI.
- Before accepting customer uploads, configure object storage, MIME/size validation and malware scanning. Sensitive-field encryption and append-only external audit retention remain deployment controls and must be enabled for the production environment.
- Generic table/column mutation, web-triggered backups, MD5/plain passwords and synchronous external calls from the legacy system are prohibited.

## Runtime

Run `php artisan migrate --force`, `php artisan horizon`, the scheduler, and Reverb as supervised processes. Health checks cover HTTP, database, Redis, queue lag and storage. Tag structured logs with tenant/order identifiers; never log tokens or customer measurement payloads unnecessarily.

## Monitoring

What the application exposes out of the box, and where to plug in a hosted tool.

| Signal | Where | Notes |
|---|---|---|
| Liveness / dependencies | `GET /api/v1/health` | `ok` / `degraded` (queue backlog above `HEALTH_QUEUE_BACKLOG_WARNING`) with 200; **503** when the database, cache or storage check fails. No hostnames or driver names in the body, so it is safe for a public load balancer probe. |
| Metrics | `GET /api/v1/metrics` | Prometheus text. **Disabled (404) until `METRICS_TOKEN` is set**; scrape with `Authorization: Bearer <token>`. Gauges: `tailors_workshops{status}`, `tailors_orders_created_24h`, `tailors_queue_pending_jobs`, `tailors_failed_jobs`. |
| Request correlation | `X-Request-Id` header | Every response carries one; a client-supplied id (8-64 chars of `A-Za-z0-9._-`) is kept, anything else is replaced by a ULID. It is added to the log context, so one id finds every log line of a request. |
| Slow requests | log `slow_request` (warning) | Requests at or above `LOG_SLOW_REQUEST_MS` (default 1500, 0 disables) with method, route, status, `duration_ms`, `tenant_id`, `user_id`. |
| Browser errors | log `client_error` (error) | The SPA posts uncaught Vue errors, `window.onerror`, unhandled rejections and failed resource loads to `POST /api/v1/client-errors` (one per distinct error per page load; message capped at 500 and stack at 4000 chars). |

### Alerts worth setting up

- `/health` non-200 for 2 consecutive minutes.
- `tailors_failed_jobs` increasing, or `tailors_queue_pending_jobs` above the warning threshold for 10 minutes.
- Rate of `client_error` log lines more than doubling after a deploy.
- `slow_request` count per hour, per route.

### Plugging in Sentry (or similar)

No SDK is bundled, so nothing is sent to a third party by default.

- **Backend:** `composer require sentry/sentry-laravel`, then `php artisan sentry:publish --dsn=...`. The request id is already in the log context; add it to the Sentry scope in `RequestContext` if wanted.
- **Frontend:** install `@sentry/vue` and call `Sentry.captureException` from `report()` in `frontend/app/plugins/error-reporting.client.ts` (keep or drop the `/client-errors` post).

### Email verification

Verification is deliberately soft: unverified users can work, the layout shows a banner with a resend button, and a completed password reset also verifies the address. Links are signed, expire after 3 days and are bound to the email hash, so a changed address invalidates old links. Resending is limited to 3 per minute. Mail needs a working `MAIL_*` configuration and a running queue worker (the notification is queued).
