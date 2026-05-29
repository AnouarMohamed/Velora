#!/bin/bash

# Cache config and routes first for speed
php artisan config:cache
php artisan route:cache

# Run migrations and seeders (ignore errors if already seeded)
php artisan migrate --force || true
php artisan db:seed --force || true

# Start nginx in background
nginx

# Start php-fpm
php-fpm
