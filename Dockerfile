FROM php:8.2-apache

# Install SQLite dependencies and build database extensions
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copy workspace directories into the Apache DocumentRoot
COPY . /var/www/html/

# Exclude sandboxed local PHP binary folder from building in container
RUN rm -rf /var/www/html/.php /var/www/html/php.zip

# Create data folder for database volume and set correct permissions
RUN mkdir -p /data && chown -R www-data:www-data /data /var/www/html

# Expose HTTP port 80
EXPOSE 80
