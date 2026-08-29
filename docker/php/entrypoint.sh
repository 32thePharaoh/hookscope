#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app bootstrap/cache

APP_KEY_FILE=storage/app/.appkey

if [ -z "${APP_KEY:-}" ]; then
    unset APP_KEY
    if [ -s "$APP_KEY_FILE" ]; then
        APP_KEY="$(cat "$APP_KEY_FILE")"
    else
        APP_KEY="base64:$(head -c 32 /dev/urandom | base64 -w0 2>/dev/null || head -c 32 /dev/urandom | base64)"
        printf '%s' "$APP_KEY" > "$APP_KEY_FILE"
        chmod 600 "$APP_KEY_FILE"
    fi
    export APP_KEY
    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
        echo "APP_KEY=${APP_KEY}" >> .env
    fi
fi

chown -R www-data:www-data storage bootstrap/cache

php artisan package:discover --ansi --no-interaction >/dev/null
php artisan config:clear --no-interaction >/dev/null

if [ "$#" -eq 0 ] || [ "$1" = "php-fpm" ]; then
    php artisan migrate --force --no-interaction
    exec php-fpm -F
fi

exec "$@"
