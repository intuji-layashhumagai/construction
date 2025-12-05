#!/bin/bash

# Exit on error
set -e

# Set permissions for storage and cache directories
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create the log files
touch /var/www/html/storage/logs/laravel.log
touch /var/www/html/storage/logs/query.log

# Install dependencies
composer install --no-interaction --no-progress --optimize-autoloader --prefer-dist

# Symlink the storage directory
php artisan storage:link >/dev/null


php artisan optimize:clear

# Set the application key
php artisan key:generate

# Run migrations
php artisan migrate

# Clear any stale jobs from previous runs
echo "Clearing stale queue jobs..."
php artisan queue:clear
php artisan queue:flush

# Note: Redis data clearing is handled by Laravel's queue commands above
# The queue:clear and queue:flush commands remove jobs from Redis queues

# Start the queue worker in the background for async job processing
echo "Starting queue worker..."
php artisan queue:work --queue=high_performance_sync --sleep=1 --tries=3 --max-jobs=1000 --timeout=3600 >/dev/null 2>&1 &