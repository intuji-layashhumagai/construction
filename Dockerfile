# Build arguments
ARG PHP_IMAGE_TAG=8.4-fpm-nginx

# Use PHP with Nginx Unit as the base image
FROM serversideup/php:${PHP_IMAGE_TAG}

# Switch to root user to perform system-level operations
USER root

# Build arguments
ARG USER_ID
ARG GROUP_ID

# Install required PHP extensions
RUN install-php-extensions bcmath gd intl exif

# Configure user permissions and ownership for the web server
RUN docker-php-serversideup-set-id www-data ${USER_ID}:${GROUP_ID}

# Create and configure Composer cache directory
RUN mkdir -p /composer/cache && chown -R www-data:www-data /composer/cache

# Ensure log directory has the correct permissions
RUN mkdir -p /var/log/nginx && chown -R www-data:www-data /var/log/nginx

# Ensure site-opts.d directory has the right permissions
RUN mkdir -p /etc/nginx/site-opts.d && chown -R www-data:www-data /etc/nginx/site-opts.d

# Ensure the cache directory has the right permissions
RUN mkdir -p /var/cache/nginx/client_temp && chown -R www-data:www-data /var/cache/nginx

# Ensure the sites-available directory has the right permissions
RUN mkdir -p /etc/nginx/sites-available && chown -R www-data:www-data /etc/nginx/sites-available

# Ensure the conf.d directory has the right permissions
RUN mkdir -p /etc/nginx/conf.d && chown -R www-data:www-data /etc/nginx/conf.d

# Copy local entrypoint scripts
COPY --chmod=755 ./entrypoint.sh /etc/entrypoint.d/


# Set the working directory for the application
WORKDIR /var/www/html
