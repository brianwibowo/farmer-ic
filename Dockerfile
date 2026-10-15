# Install PHP dependencies and generate Wayfinder types without carrying
# Composer or Node.js into the runtime image.
FROM composer:2 AS dependencies

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

COPY . .

# Wayfinder is invoked by the Vite plugin. Generate its files in a PHP stage
# so the Node-only frontend stage does not need a PHP runtime.
RUN touch database/database.sqlite \
    && cp .env.example .env \
    && composer dump-autoload --no-dev --optimize \
    && php artisan key:generate --force --no-interaction \
    && php artisan wayfinder:generate --with-form \
    && rm -f .env database/database.sqlite

# Build the frontend independently so the runtime image contains no Node.js
# toolchain or development dependencies.
FROM node:22-bookworm-slim AS frontend

# Wayfinder output is already generated in the Composer stage. Avoid running
# the PHP hook a second time in this Node-only stage.
ENV WAYFINDER_COMMAND=true

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=dependencies /app ./
RUN npm run build

# Apache provides a single, self-contained web process for the Laravel app.
# This keeps local and VPS deployments identical while retaining a small,
# repeatable build pipeline.
FROM php:8.5-apache-bookworm AS runtime

ENV APP_ENV=production \
    APP_DEBUG=false \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN apt-get update \
    && apt-get install -y --no-install-recommends curl \
    && install-php-extensions \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_sqlite \
        zip \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

COPY --from=dependencies /app ./
COPY --from=frontend /app/public/build ./public/build

COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/99-production.ini /usr/local/etc/php/conf.d/99-production.ini
COPY docker/entrypoint.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/docker-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl --fail --silent http://localhost/up || exit 1

ENTRYPOINT ["docker-entrypoint"]
CMD ["apache2-foreground"]
