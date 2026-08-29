#!/bin/sh
set -eu

cd /var/www/html

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

if [ ! -f .env ]; then
    cp .env.example .env
fi

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app bootstrap/cache

# env_file passes APP_KEY= as an empty string, which wins over a generated .env
# file. Unset it and write a real key so artisan and FPM both see it.
#
# The key is cached under storage/, which compose.yaml backs with a named volume.
# Without that, .env lives only in the container's writable layer and every
# `docker compose down && up` mints a new key, silently invalidating sessions
# (and, once anything is encrypted at rest, making it undecryptable).
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
php artisan migrate --force --no-interaction

exec php-fpm -F
