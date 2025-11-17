FROM webdevops/php-nginx-dev:8.4

WORKDIR /var/www/html

# Copy app files
COPY ./html /var/www/html

# Install Composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions for storage & cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

#Expose port 80
EXPOSE 80