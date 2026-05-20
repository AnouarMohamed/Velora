#!/usr/bin/env sh
set -eu

cd /var/www/html

export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
mkdir -p "$COMPOSER_HOME"
git config --global --add safe.directory /var/www/html 2>/dev/null || true

if [ ! -f .env ]; then
    cp .env.example .env
fi

set_env_value() {
    key="$1"
    value="$2"

    awk -v key="$key" -v value="$value" '
        BEGIN { updated = 0 }
        $0 ~ "^" key "=" {
            print key "=" value
            updated = 1
            next
        }
        { print }
        END {
            if (updated == 0) {
                print key "=" value
            }
        }
    ' .env > .env.tmp
    mv .env.tmp .env
}

sync_env_value() {
    key="$1"
    eval "value=\${$key:-}"

    if [ -n "$value" ]; then
        set_env_value "$key" "$value"
    fi
}

for key in \
    APP_NAME \
    APP_ENV \
    APP_KEY \
    APP_DEBUG \
    APP_URL \
    APP_LOCALE \
    APP_FALLBACK_LOCALE \
    DB_CONNECTION \
    DB_DSN \
    DB_DATABASE \
    SESSION_DRIVER \
    QUEUE_CONNECTION \
    CACHE_STORE \
    REDIS_CLIENT \
    REDIS_HOST \
    REDIS_PASSWORD \
    REDIS_PORT \
    FILESYSTEM_DISK \
    LOG_CHANNEL \
    SEED_DEMO_DATA
do
    sync_env_value "$key"
done

mkdir -p \
    database \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan config:clear --no-interaction

if [ "${FILESYSTEM_DISK:-local}" = "local" ] && [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "${RESET_DATABASE:-0}" = "1" ]; then
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force

    if [ "${SEED_DEMO_DATA:-0}" = "1" ]; then
        php artisan db:seed --force
    else
        USER_COUNT="$(php -r 'require __DIR__."/vendor/autoload.php"; $app = require __DIR__."/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\User::query()->count();')"
        if [ "$USER_COUNT" = "0" ]; then
            php artisan db:seed --force
        fi
    fi
fi

exec "$@"
