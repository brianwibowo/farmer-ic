#!/bin/sh

set -eu

cd /var/www/html

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY must be supplied and shared by all application containers.' >&2
    exit 1
fi

if [ "${DB_CONNECTION:-}" != 'pgsql' ]; then
    echo 'The current schema requires DB_CONNECTION=pgsql; no database was changed.' >&2
    exit 1
fi

for connection in "${QUEUE_CONNECTION:-}" "${NOTIFICATION_QUEUE_CONNECTION:-}"; do
    case "$connection" in
        database|redis) ;;
        *) echo 'QUEUE_CONNECTION and NOTIFICATION_QUEUE_CONNECTION must be database or redis.' >&2; exit 1 ;;
    esac
done

# The general worker has a 180 second timeout. A shorter reservation can
# cause a second worker to execute a job that is still running.
for retry_after in "${DB_QUEUE_RETRY_AFTER:-240}" "${REDIS_QUEUE_RETRY_AFTER:-240}"; do
    case "$retry_after" in
        ''|*[!0-9]*) echo 'Queue retry_after values must be integers greater than 180 seconds.' >&2; exit 1 ;;
    esac
    if [ "$retry_after" -le 180 ]; then
        echo 'Queue retry_after values must be greater than 180 seconds.' >&2
        exit 1
    fi
done

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Migrations are an explicit, reviewed operation, never a side effect of starting
# the web server, a worker, the scheduler, or a one-off artisan command.
php artisan storage:link --force 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
