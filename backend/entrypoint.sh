#!/usr/bin/env sh
set -eu

cd /var/www/html

export COMPOSER_HOME="${COMPOSER_HOME:-/tmp/composer}"
mkdir -p "$COMPOSER_HOME"
git config --global --add safe.directory /var/www/html 2>/dev/null || true

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p \
    database \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/testing \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

touch "${DB_DATABASE:-database/database.sqlite}"

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
fi

if ! grep -Eq '^APP_KEY=base64:.+' .env; then
    php artisan key:generate --force
fi

php artisan config:clear --no-interaction

if [ ! -e public/storage ]; then
    php artisan storage:link --no-interaction
fi

if [ "${RESET_DATABASE:-0}" = "1" ]; then
    php artisan migrate:fresh --seed --force
else
    php artisan migrate --force

    USER_COUNT="$(php -r 'require __DIR__."/vendor/autoload.php"; $app = require __DIR__."/bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo App\Models\User::query()->count();')"
    if [ "$USER_COUNT" = "0" ]; then
        php artisan db:seed --force
    fi
fi

exec "$@"
