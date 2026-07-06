#!/bin/bash
set -e

# Ensure .env exists
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Persist app key across container restarts (stored alongside SQLite database)
KEY_FILE=/var/www/html/database/.app_key

if [ -z "$APP_KEY" ]; then
    if [ -f "$KEY_FILE" ]; then
        # Restore key from persisted file
        APP_KEY=$(cat "$KEY_FILE")
        sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
    else
        # Generate new key and persist it
        php artisan key:generate --force --no-interaction
        grep '^APP_KEY=' .env | cut -d= -f2 > "$KEY_FILE"
    fi
fi

# Ensure SQLite database exists
touch /var/www/html/database/database.sqlite

# Run migrations
php artisan migrate --force --no-interaction || true

# Production optimizations
if [ "${APP_ENV}" != "local" ]; then
    php artisan filament:optimize --no-interaction || true
    php artisan optimize --no-interaction || true
fi

# Storage link
php artisan storage:link --force --no-interaction 2>/dev/null || true

exec "$@"
