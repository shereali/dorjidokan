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
