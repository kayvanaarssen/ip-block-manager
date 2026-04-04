#!/bin/bash
# Ploi Deploy Script for IP Block Manager
# =========================================
# Copy this into your Ploi site's deployment settings.

cd {SITE_DIRECTORY}

# Reset to match remote exactly (handles force pushes and local changes)
git fetch origin main
git reset --hard origin/main

composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

npm ci --production=false
npx vite build
rm -rf node_modules

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

php artisan queue:restart

chmod -R 775 storage bootstrap/cache

echo "" | sudo -S service php8.4-fpm reload

echo "🚀 Application deployed!"
