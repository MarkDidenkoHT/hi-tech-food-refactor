#!/bin/sh
set -e

# Rebuild the cached config/routes/views from the runtime environment. These
# are safe and idempotent; database migrations are intentionally NOT run here
# (run them as an explicit deploy step: `php artisan migrate --force`).
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec docker-php-entrypoint "$@"
