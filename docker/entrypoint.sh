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

# storage:link maps /public/storage -> /storage/app/public, which is how the
# Medialibrary-managed avatar / chat / file URLs resolve on a fresh clone.
# Re-run unconditionally: the command is a no-op when the symlink already
# points at the right target.
php artisan storage:link --force || true

# Clear and cache config
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec "$@"
