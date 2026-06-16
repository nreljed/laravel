#!/bin/bash
# Production deployment script
# Run on server after code is pulled

echo "Starting deployment..."

# Pull latest code
git pull origin main

# Install/update dependencies
composer install --no-dev --optimize-autoloader

# Build frontend assets (if Node.js available)
if command -v npm &> /dev/null; then
    npm ci --ignore-scripts
    npm run build
fi

# Run migrations
php artisan migrate --force

# Clear and cache config
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Clear cache
php artisan cache:clear

echo "Deployment complete!"