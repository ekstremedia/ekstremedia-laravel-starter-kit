#!/bin/bash
set -e

# Set permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Generate app key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    php artisan key:generate --force
fi

# Run migrations and seed on first boot
php artisan migrate --force
php artisan db:seed --force

# Clear and cache config
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec "$@"
