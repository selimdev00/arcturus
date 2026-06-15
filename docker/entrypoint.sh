#!/bin/sh
set -e

# Ensure the SQLite database exists on the mounted volume.
if [ ! -f /app/database/database.sqlite ]; then
    touch /app/database/database.sqlite
fi
chown -R www-data:www-data /app/database /app/storage 2>/dev/null || true

# App key on first boot.
if ! grep -q '^APP_KEY=base64:' /app/.env 2>/dev/null; then
    php artisan key:generate --force
fi

# Migrate + seed the single user (idempotent).
php artisan migrate --force
php artisan db:seed --force || true

php artisan config:cache
php artisan route:cache

exec "$@"
