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