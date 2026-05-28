FROM php:8.4-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    libzip-dev \
    oniguruma-dev \
    linux-headers \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install MongoDB extension
RUN pecl install mongodb && docker-php-ext-enable mongodb

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Setup permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Copy nginx configuration
COPY nginx.conf /etc/nginx/http.d/default.conf

# Expose port 8080
EXPOSE 8080

# Start script
COPY entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["entrypoint.sh"]
