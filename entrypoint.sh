#!/bin/bash

# Exit on error
set -e

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