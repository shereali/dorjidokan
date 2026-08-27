# Legacy data cutover

The legacy application remains read-only. Run the migration against a restored copy of its MySQL database, never the live production database.

## Procedure

1. Configure the Laravel `legacy` database connection to the restored `btmsav1` backup.
2. Create or select the destination tenant. If the backup contains more than one company, identify the correct legacy `company.id` and always pass `--company-id`.
3. Inventory the source without writing destination data:

   `php artisan legacy:import tenant-slug --company-id=1 --dry-run`

4. Run the idempotent import:

   `php artisan legacy:import tenant-slug --company-id=1`

5. Re-run it once to prove idempotency, then reconcile every staged row and core normalized mapping:

   `php artisan legacy:validate tenant-slug --company-id=1`

6. Compare the customer receivables, trial balance, order totals, stock balances, approved expenses, and attendance counts in the new reports with signed legacy cutover totals. Resolve every `MISMATCH` before switching traffic.

## Guarantees and review queue

Every available retained legacy table is copied into `legacy_staging_records` with its original payload and SHA-256 checksum before transformation. This prevents malformed or uncommon historical rows from disappearing silently. Core operational transforms are idempotently mapped in `legacy_import_mappings`, including customers, garment catalogs and measurement parts, multi-garment order lines, numeric measurements, employees, inventory movements, customer invoices/payments, direct sales, expenses, and attendance.

Rows that do not have a safe normalized equivalent remain staged for explicit cutover review. Browser-triggered backups, obsolete activation/billing codes, plaintext/MD5 credentials, and synchronous telecom behavior are intentionally not restored as executable features.
