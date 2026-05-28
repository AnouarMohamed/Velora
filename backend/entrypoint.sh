#!/bin/bash

# Cache config and routes
php artisan config:cache
php artisan route:cache

# Start nginx in background
nginx

# Start php-fpm
php-fpm
