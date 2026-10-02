#!/bin/sh
# Container start-up (Render): listen on Render's port, cache config, run migrations, start Apache.
set -e

# Render tells the app which port to listen on (default 10000).
PORT="${PORT:-10000}"
sed -i "s/Listen 80\$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Brings the database schema up to date; does nothing when it already is.
php artisan migrate --force

exec apache2-foreground
