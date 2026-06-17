#!/bin/sh
set -e

# Bind-mounted source (especially from a Windows host) may not be writable
# by the www-data user php-fpm workers run as. Ensure Laravel's writable
# dirs are usable on every container start.
chmod -R a+rwX storage bootstrap/cache 2>/dev/null || true

# Dev runs against live bind-mounted source, so never use a cached config —
# a stale bootstrap/cache (e.g. left behind by a production build) would
# otherwise override the live .env and the testing environment.
php artisan optimize:clear 2>/dev/null || true

exec docker-php-entrypoint "$@"
