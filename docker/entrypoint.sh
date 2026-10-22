#!/bin/sh
set -e

# Only the container started with RUN_MIGRATIONS=true (the "app" service) prepares
# the application, so queue and scheduler containers never race on it.
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
    # In development the source is bind-mounted and vendor/ may not exist yet.
    if [ ! -f vendor/autoload.php ]; then
        composer install --no-interaction --prefer-dist
    fi

    php artisan migrate --force
    php artisan app:passport-setup
fi

if [ "${APP_ENV:-local}" = "production" ]; then
    php artisan optimize
fi

exec "$@"
