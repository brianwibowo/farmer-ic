# Use the same PHP version and extensions for dependency resolution and runtime.
FROM php:8.5-apache-bookworm AS php-base

ENV APP_ENV=production \
    APP_DEBUG=false \
    APACHE_DOCUMENT_ROOT=/var/www/html/public

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# The phpredis archive omits the liblzf submodule required by package.xml.
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl git \
    && git clone --depth 1 --single-branch --branch 6.3.0 --recurse-submodules \
        https://github.com/phpredis/phpredis.git /tmp/phpredis \
    && install-php-extensions \
        bcmath \
        intl \
        mbstring \
        opcache \
        pdo_pgsql \
        pdo_sqlite \
        pgsql \
        /tmp/phpredis \
        zip \
    && rm -rf /tmp/phpredis \
    && a2enmod expires headers rewrite \
    && rm -rf /var/lib/apt/lists/*

# pcntl is required for Laravel's queue worker timeouts and graceful shutdown.
# Keep it in a separate layer so an unchanged PHP extension layer can be reused.
RUN docker-php-ext-install -j2 pcntl

FROM php-base AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

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

# Wayfinder files are gitignored and the Vite config does not generate them.
# This key exists only while generating build-time artifacts, never at runtime.
RUN composer dump-autoload --no-dev --optimize --no-interaction \
    && APP_KEY=base64:MDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDAwMDA= \
        php artisan wayfinder:generate --with-form --no-interaction

FROM node:22-bookworm-slim AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund

COPY --from=dependencies /app ./
RUN npm run build

FROM php-base AS runtime

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
