#!/usr/bin/env bash
set -e

cd /app

echo "==> Booting AlertBook for Production on Railway…"

# 1) Ensure storage directories exist (crucial if a Volume is mounted empty)
mkdir -p storage/app/livewire-tmp
mkdir -p storage/app/public/incidents
mkdir -p storage/app/public/case-notes
mkdir -p storage/app/public/referencements
mkdir -p storage/framework/cache/data
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs

# Fix permissions dynamically for storage & cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 2) Clear existing caches to avoid conflicts
php artisan config:clear || true
php artisan cache:clear || true
php artisan route:clear || true
php artisan view:clear || true

# 3) Establish Storage Link if not exists
php artisan storage:link || true

# 4) Run database migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# 5) Optimize configuration caching
echo "⚡ Caching configurations, routes, and views..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# 6) Start PHP-FPM in background
echo "🔄 Starting PHP-FPM..."
php-fpm -D

# 7) Start Nginx in foreground (keeps the container running)
echo "🚀 Starting Nginx..."
nginx -g "daemon off;"