#!/usr/bin/env bash
# Hostinger Post-Deployment Script for e-clearance Laravel Application

set -e

echo "=== Starting Hostinger Deployment ==="

# Enter maintenance mode
php artisan down || true

# Pull latest changes if running from SSH
if [ -d ".git" ]; then
    echo "Pulling latest code from Git..."
    git pull origin main || git pull origin master
fi

# Install PHP production dependencies
echo "Installing Composer dependencies..."
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader

# Build Frontend Assets
if command -v npm &> /dev/null; then
    echo "Building frontend assets..."
    npm ci
    npm run build
else
    echo "Notice: Node.js/NPM not available in CLI environment. Ensure public/build assets are compiled before deploying."
fi

# Run Database Migrations
echo "Running database migrations..."
php artisan migrate --force

# Optimize Laravel Cache
echo "Caching Laravel configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Ensure storage link exists
php artisan storage:link || true

# Bring application out of maintenance mode
php artisan up

echo "=== Deployment to Hostinger completed successfully! ==="
