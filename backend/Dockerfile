FROM php:8.4-fpm-alpine

ARG MONGODB_EXT_VERSION=2.3.3
ARG REDIS_EXT_VERSION=6.3.0

RUN apk add --no-cache \
        bash \
        curl \
        libpng-dev \
        libxml2-dev \
        libzip-dev \
        linux-headers \
        nginx \
        oniguruma-dev \
        openssl-dev \
        unzip \
        zip \
        $PHPIZE_DEPS \
    && docker-php-ext-install mbstring exif pcntl bcmath gd zip \
    && printf "no\nno\nno\nno\nauto\nauto\nauto\nno\nauto\nno\nbundled\n" | pecl install mongodb-${MONGODB_EXT_VERSION} \
    && printf "\n\n\n\n\n\n\n\n" | pecl install redis-${REDIS_EXT_VERSION} \
    && docker-php-ext-enable mongodb redis \
    && rm -rf /tmp/pear ~/.pearrc

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --prefer-dist \
        --no-autoloader \
        --no-interaction \
        --no-progress \
        --no-scripts

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && php artisan package:discover --ansi \
    && mkdir -p storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && (php artisan storage:link || true) \
    && chown -R www-data:www-data storage bootstrap/cache public/storage

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["entrypoint.sh"]
CMD ["web"]
