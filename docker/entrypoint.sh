#!/bin/sh
set -e

echo "==> Waiting for MySQL..."
until php artisan migrate:status > /dev/null 2>&1; do
    sleep 2
done

echo "==> Running migrations..."
php artisan migrate --force

echo "==> Caching config/routes/views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Fixing storage permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

echo "==> Starting PHP-FPM..."
exec "$@"
