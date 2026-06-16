#!/bin/sh
set -e

# Fix: ensure only one MPM is loaded (prefork is needed for mod_php)
a2dismod --force mpm_event mpm_worker 2>/dev/null || true
a2enmod --force mpm_prefork 2>/dev/null || true

# Configure Apache to listen on Railway's PORT
sed -i "s/Listen 80/Listen ${PORT:-80}/g" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT:-80}>/g" /etc/apache2/sites-available/*.conf

# Laravel optimizations (tolerate missing env vars during first boot)
php artisan optimize || true
php artisan storage:link --force || true

# Run migrations (don't crash if DB isn't ready yet)
php artisan migrate --force || true

exec apache2-foreground
