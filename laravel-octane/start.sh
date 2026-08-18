#!/bin/bash
set -e

export APP_KEY="${APP_KEY:-base64:$(head -c 32 /dev/urandom | base64)}"

php artisan config:cache
php artisan route:cache
php artisan view:cache

WORKERS="${OCTANE_WORKERS:-128}"

exec php artisan octane:start --server=frankenphp --host=0.0.0.0 --port=8080 \
    --workers="$WORKERS" --max-requests=1000
