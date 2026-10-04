#!/bin/sh
set -eu

if [ "${1:-}" = "apache2-foreground" ]; then
    if [ -z "${APP_KEY:-}" ]; then
        echo "APP_KEY is required before starting the web container" >&2
        echo "Generate one with: docker compose run --rm --no-deps app php artisan key:generate --show" >&2
        exit 1
    fi

    # Cache only configuration/routes that are present in this image. Migrations
    # and seeders remain explicit operator actions and are never run at startup.
    php artisan package:discover --ansi
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec "$@"
