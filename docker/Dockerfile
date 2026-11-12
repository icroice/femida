# ── Stage 1: frontend assets (Vite build) ───────────────────────────────
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . ./
RUN npm run build

# ── Stage 2: composer dependencies (prod) ───────────────────────────────
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . ./
RUN mkdir -p storage/framework/cache/data storage/framework/sessions \
       storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --optimize --no-dev

# ── PHP runtime without app code ──────────────────────────────────────────
# Base for the compose artisan/test services (target: php): code and vendor
# are mounted from the host; no reason to build node/vendor stages for them.
FROM dunglas/frankenphp:1-php8.4-alpine AS php

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN install-php-extensions pdo_sqlite sqlite3 bcmath gd intl opcache

WORKDIR /app

# ── Application runtime: FrankenPHP (Caddy + PHP 8.4) ─────────────────────
FROM php

COPY --from=vendor /app/vendor ./vendor
COPY . ./
COPY --from=assets /app/public/build ./public/build

COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/app/screenshots storage/framework/cache/data storage/framework/cache/locks \
       storage/framework/sessions storage/framework/testing storage/framework/views \
       storage/logs bootstrap/cache \
    && chmod -R ugo+rwX storage bootstrap/cache \
    && rm -rf docker

ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/app/storage/app/database.sqlite

EXPOSE 80

# M10: alive container = /up responds; on a full disk/hung PHP — restart
# (restart: unless-stopped in compose picks up unhealthy after retries)
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD wget -qO /dev/null http://127.0.0.1/up || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
