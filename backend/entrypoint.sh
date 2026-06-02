#!/usr/bin/env sh
set -eu

cd /var/www/html

command="${1:-web}"
needs_laravel_bootstrap=0

if [ "$command" = "web" ] || { [ "$command" = "php" ] && [ "${2:-}" = "artisan" ]; } || [ "$command" = "artisan" ]; then
    needs_laravel_bootstrap=1
fi

if [ "$needs_laravel_bootstrap" = "1" ]; then
    rm -f bootstrap/cache/*.php

    if [ ! -f .env ] && [ "${APP_ENV:-local}" != "production" ] && [ -f .env.example ]; then
        cp .env.example .env
    fi

    app_key="${APP_KEY:-}"
    if [ -z "$app_key" ] && [ -f .env ]; then
        app_key="$(awk '/^APP_KEY=/{print substr($0, 9); exit}' .env || true)"
    fi

    if [ -z "$app_key" ] && [ "${APP_ENV:-local}" = "production" ]; then
        echo "APP_KEY must be set in production." >&2
        exit 1
    fi

    if [ -z "$app_key" ] && [ "${APP_ENV:-local}" != "production" ]; then
        php artisan key:generate --force --no-interaction
    fi

    mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
    php artisan storage:link >/dev/null 2>&1 || true

    if [ "${APP_ENV:-local}" = "production" ]; then
        php artisan config:cache --ansi
        php artisan route:cache --ansi
    else
        php artisan config:clear --ansi
        php artisan route:clear --ansi
    fi
fi

if [ "$command" != "web" ]; then
    exec "$@"
fi

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    php artisan migrate --force --ansi
fi

if [ "${SEED_DEMO_DATA:-0}" = "1" ]; then
    php artisan db:seed --force --ansi
fi

listen_port="${PORT:-8080}"
sed -i "s/listen 8080;/listen ${listen_port};/" /etc/nginx/http.d/default.conf

php-fpm -D
exec nginx -g "daemon off;"
