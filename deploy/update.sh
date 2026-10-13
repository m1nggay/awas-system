#!/usr/bin/env bash
# update.sh — pulls the latest code from GitHub and refreshes the live site.
# Run on the server as root:  awas-update
set -euo pipefail
cd /var/www/awas

echo "==> Pulling latest code"
git pull --ff-only

echo "==> Installing PHP packages"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Database changes"
sudo -u www-data php artisan migrate --force

echo "==> Refreshing caches"
chown -R www-data:www-data storage bootstrap/cache
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
systemctl reload php8.3-fpm

echo
echo "Updated to: $(git log -1 --format='%h %s')"
