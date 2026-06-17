# syntax=docker/dockerfile:1

# ---- Composer dependencies (no dev) ----------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/ ./
# Platform extensions (gd, pdo_pgsql, zip) live in the runtime image, so skip
# the platform check here and validate it at runtime instead.
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# ---- Frontend assets -------------------------------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY backend/package.json backend/package-lock.json ./
RUN npm ci
COPY backend/ ./
RUN npm run build

# ---- PHP-FPM runtime (the app) ---------------------------------------------
FROM php:8.3-fpm AS app
RUN apt-get update && apt-get install -y \
        libpq-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql gd zip opcache \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Application code with production vendor and built assets.
COPY --chown=www-data:www-data backend/ ./
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

USER www-data

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

# ---- Nginx serving the built public dir ------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
