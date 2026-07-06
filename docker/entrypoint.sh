#!/bin/bash
set -e

# Ensure .env exists (env vars from docker-compose are used at runtime)
test -f .env || cp .env.example .env

# Ensure SQLite database exists
touch /var/www/html/database/database.sqlite

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
fi

# Run migrations (safe to retry)
php artisan migrate --force --no-interaction || true

# Storage link (may already exist)
php artisan storage:link --force --no-interaction 2>/dev/null || true

exec "$@"
