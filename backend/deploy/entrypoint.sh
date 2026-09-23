#!/bin/sh
# Laravel backend container entrypoint. Runs on every container start.
set -e

# Volume mounts can reset ownership — reapply on every boot, not just at build time
chown -R www-data:www-data storage bootstrap/cache

# Refresh the package manifest for production packages (build used --no-scripts)
php artisan package:discover --ansi || true

# `migrate --force` is intentionally the only migration command run automatically.
# It only applies migrations not yet recorded as run — safe on a database that
# already has data. NEVER change this to `migrate:fresh` or `migrate:refresh`
# here — both drop/rebuild every table and would wipe production data on every
# container restart.
php artisan migrate --force

# Cache config/routes/views for production speed. Re-run on every deploy so a
# stale cache is never served after new code ships.
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

php-fpm -D
nginx -g 'daemon off;'
