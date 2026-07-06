#!/bin/bash
set -e

# Ensure .env exists
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Persist app key across container restarts
KEY_FILE=/var/www/html/database/.app_key

if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    if [ -f "$KEY_FILE" ]; then
        APP_KEY=$(cat "$KEY_FILE")
    else
        php artisan key:generate --force --no-interaction
        APP_KEY=$(grep '^APP_KEY=' .env | cut -d= -f2-)
        echo "$APP_KEY" > "$KEY_FILE"
    fi
    # Export so child processes (including config:cache) see it
    export APP_KEY
    sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
fi

# Ensure SQLite database exists and is writable
touch /var/www/html/database/database.sqlite
chown -R www-data:www-data /var/www/html/database
chmod -R 775 /var/www/html/database

# Run migrations
php artisan migrate --force --no-interaction || true

# Production optimizations
if [ "${APP_ENV}" != "local" ]; then
    php artisan filament:optimize --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan view:cache --no-interaction || true
fi

# Storage link
php artisan storage:link --force --no-interaction 2>/dev/null || true

# Fix permissions for web server
chown -R www-data:www-data /var/www/html/database /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
