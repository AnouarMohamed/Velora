#!/bin/bash

# Run migrations and seeders
php artisan migrate --force
php artisan db:seed --force

# Cache config and routes
php artisan config:cache
php artisan route:cache

# Start nginx in background
nginx

# Start php-fpm
php-fpm
