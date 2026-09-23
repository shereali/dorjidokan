# Laravel 12 backend (PHP-FPM + nginx), multi-stage build.
# Stage 1: composer dependencies only (no source needed)
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
# ^ --no-scripts: skips post-autoload-dump package:discover during the build
# (needs a bootable app/DB). Artisan package:discover is run by the entrypoint.

# Stage 2: runtime
FROM php:8.3-fpm-alpine
RUN apk add --no-cache nginx mysql-client icu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo_mysql opcache intl bcmath pcntl

WORKDIR /var/www/html
COPY backend/. .
COPY --from=vendor /app/vendor ./vendor
COPY backend/deploy/nginx.conf /etc/nginx/http.d/default.conf
COPY backend/deploy/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
