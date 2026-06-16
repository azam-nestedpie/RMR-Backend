#!/bin/sh
set -e

# Configure Apache to listen on Railway's PORT
sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT:-80}>/g" /etc/apache2/sites-available/*.conf

# Laravel optimizations
php artisan optimize
php artisan storage:link --force

# Run migrations (safe to run every deploy)
php artisan migrate --force

exec apache2-foreground
