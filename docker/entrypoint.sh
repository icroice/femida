#!/bin/sh
# First run: prepare .env, the key and migrations; then start FrankenPHP.
set -e
cd /app

mkdir -p storage/app/screenshots storage/logs bootstrap/cache \
         storage/framework/cache/data storage/framework/cache/locks \
         storage/framework/sessions storage/framework/views storage/framework/testing

if [ ! -f .env ]; then
    cp .env.example .env
fi

# APP_KEY can be passed from outside (FEMIDA_APP_KEY in the .env next to compose),
# so encrypted cookies (session, remember) survive container rebuilds.
if [ -n "${APP_KEY:-}" ] && ! grep -q '^APP_KEY=base64:' .env; then
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
    grep -q '^APP_KEY=' .env || echo "APP_KEY=$APP_KEY" >> .env
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# an empty variable from compose must not clobber the key from .env
if [ -z "${APP_KEY:-}" ]; then
    unset APP_KEY
fi

php artisan migrate --force

exec frankenphp run --config /etc/caddy/Caddyfile
