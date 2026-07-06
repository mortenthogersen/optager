#!/bin/bash
set -e

# Ensure .env exists
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force --no-interaction
elif [ -z "$APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
fi

# Ensure SQLite database exists
touch /var/www/html/database/database.sqlite

# Run migrations
php artisan migrate --force --no-interaction || true

# Storage link
php artisan storage:link --force --no-interaction 2>/dev/null || true

exec "$@"
