#!/bin/bash
set -e

# Wait for SQLite database
touch /var/www/html/database/database.sqlite

# Generate app key if not set
if [ -z "$APP_KEY" ]; then
    php artisan key:generate --force --no-interaction
fi

# Run migrations
php artisan migrate --force --no-interaction

# Storage link
php artisan storage:link --force --no-interaction 2>/dev/null || true

# Cache config for production
if [ "${APP_ENV}" = "production" ]; then
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
    php artisan view:cache --no-interaction
fi

exec "$@"
