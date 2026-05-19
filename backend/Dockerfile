FROM php:8.4-cli-alpine3.20

RUN apk add --no-cache \
    git \
    curl \
    bash \
    zlib-dev \
    libzip-dev \
    libxml2-dev \
    openssl-dev \
    g++ \
    make \
    autoconf \
    pkgconf \
    oniguruma-dev
RUN apk upgrade --no-cache

RUN docker-php-ext-install zip
RUN pecl install mongodb redis && docker-php-ext-enable mongodb redis

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY entrypoint.sh /usr/local/bin/gestionev-backend-entrypoint
RUN chmod +x /usr/local/bin/gestionev-backend-entrypoint

EXPOSE 8000

ENTRYPOINT ["gestionev-backend-entrypoint"]
CMD ["php", "-d", "upload_max_filesize=12M", "-d", "post_max_size=16M", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
