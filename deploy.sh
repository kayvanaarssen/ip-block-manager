#!/bin/bash
# Ploi Deploy Script for IP Block Manager
# =========================================
# Add this script to your Ploi site's deployment settings.
# Ensure your server has PHP 8.2+, Composer, Node 20+, and npm installed.

set -euo pipefail

cd {SITE_DIRECTORY}

echo "🔒 IP Block Manager - Deploying..."

# Pull latest changes
git pull origin main

# Ensure SQLite database file exists before composer/artisan runs
if [ ! -f database/database.sqlite ]; then
    echo "📦 Creating SQLite database..."
    touch database/database.sqlite
fi

# Install PHP dependencies (no dev on production)
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# Install Node dependencies and build frontend
npm ci --production=false
npx vite build
rm -rf node_modules

# Run migrations
php artisan migrate --force

# blockip.sh is bundled in the repo at storage/app/blockip.sh
# It's automatically updated via git pull above
chmod +x storage/app/blockip.sh
echo "📥 blockip.sh is up to date (pulled from repo)"

# Clear and rebuild caches
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Restart queue workers (important for SSH job changes)
php artisan queue:restart

# Set correct permissions
chmod -R 775 storage bootstrap/cache
chown -R ploi:ploi storage bootstrap/cache

echo "✅ Deployment complete!"
echo ""
echo "📋 Post-deploy checklist:"
echo "   1. Ensure .env is configured (APP_KEY, DB, QUEUE_CONNECTION=database)"
echo "   2. Ensure queue worker is running: php artisan queue:work --tries=3"
echo "   3. Set WEBAUTHN_ID to your domain in .env for passkeys"
