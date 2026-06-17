#!/bin/sh
set -e

# Rebuild the cached config/routes/views from the runtime environment. These
# are safe and idempotent; database migrations are intentionally NOT run here
# (run them as an explicit deploy step: `php artisan migrate --force`).
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Point the Telegram bot at this deployment's public URL. Idempotent and
# safe to re-run on every start; no-ops if the bot isn't configured yet.
php artisan telegram:set-webhook || true

exec docker-php-entrypoint "$@"
