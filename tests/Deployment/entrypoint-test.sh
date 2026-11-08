#!/usr/bin/env bash

# Exercise the entrypoint guards without a Docker daemon or a real database.
set -eu

root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
entrypoint="$root/docker/entrypoint.sh"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

export ENTRYPOINT_TEST_DIR="$tmp"
export ENTRYPOINT_TRACE="$tmp/commands"
export APP_KEY='test-only-key'
export DB_CONNECTION=pgsql
export QUEUE_CONNECTION=redis
export NOTIFICATION_QUEUE_CONNECTION=database
export DB_QUEUE_RETRY_AFTER=240
export REDIS_QUEUE_RETRY_AFTER=240

# The production script uses an absolute workdir and changes volume ownership.
# Replace those operations and artisan with isolated test doubles.
cd() { builtin cd "$ENTRYPOINT_TEST_DIR"; }
chown() { :; }
php() { printf '%s\n' "$*" >> "$ENTRYPOINT_TRACE"; }
export -f cd chown php

expect_failure() {
    expected=$1
    shift
    if env "$@" bash "$entrypoint" true > "$tmp/output" 2>&1; then
        echo "Expected entrypoint failure: $expected" >&2
        exit 1
    fi
    if ! grep -Fq "$expected" "$tmp/output"; then
        cat "$tmp/output" >&2
        echo "Missing error: $expected" >&2
        exit 1
    fi
}

expect_failure 'APP_KEY must be supplied' APP_KEY=
expect_failure 'requires DB_CONNECTION=pgsql' DB_CONNECTION=sqlite
expect_failure 'must be database or redis' QUEUE_CONNECTION=sync
expect_failure 'must be database or redis' NOTIFICATION_QUEUE_CONNECTION=sync
expect_failure 'must be greater than 180' DB_QUEUE_RETRY_AFTER=180
expect_failure 'must be integers greater than 180' REDIS_QUEUE_RETRY_AFTER=invalid

bash "$entrypoint" true
for command in 'artisan config:cache' 'artisan route:cache' 'artisan view:cache'; do
    if ! grep -Fxq "$command" "$ENTRYPOINT_TRACE"; then
        cat "$ENTRYPOINT_TRACE" >&2
        echo "Missing artisan call: $command" >&2
        exit 1
    fi
done
if grep -Eq 'migrate|key:generate' "$ENTRYPOINT_TRACE"; then
    echo 'Entrypoint must not migrate or generate application keys.' >&2
    exit 1
fi

echo 'Entrypoint guard checks passed.'
