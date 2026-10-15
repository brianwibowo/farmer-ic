#!/bin/sh

set -eu

cd /var/www/html

# A secret APP_KEY can be injected by the platform. For local Docker runs,
# generate one once and keep it in the container's .env file.
if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ -z "${APP_KEY:-}" ]; then
    php artisan key:generate --force --no-interaction
fi

database_path="${DB_DATABASE:-/var/lib/farmer-ic/database.sqlite}"
mkdir -p "$(dirname "$database_path")"
if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ] && [ ! -f "$database_path" ]; then
    touch "$database_path"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chown -R www-data:www-data "$(dirname "$database_path")" 2>/dev/null || true

php artisan migrate --force --graceful --no-interaction
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
