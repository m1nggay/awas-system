#!/usr/bin/env bash
# setup-server.sh — prepares a fresh Ubuntu 24.04 VPS (Hostinger KVM 1) for AWAS.
#
# Run on the VPS as root:
#   curl -fsSL https://raw.githubusercontent.com/m1nggay/awas-system/main/deploy/setup-server.sh -o setup-server.sh
#   bash setup-server.sh yourdomain.com
#
# Installs Nginx, PHP 8.3, PostgreSQL, Composer; creates the database; clones
# the app to /var/www/awas; sets up the scheduler cron and nightly backups.
# Safe to run again (it skips what already exists).
set -euo pipefail

DOMAIN="${1:-}"
APP_DIR=/var/www/awas
REPO=https://github.com/m1nggay/awas-system.git
DB_NAME=awas_db
DB_USER=awas
PHP=8.3

if [ "$(id -u)" -ne 0 ]; then echo "Run as root."; exit 1; fi
SERVER_NAME="${DOMAIN:-_}"
[ -n "$DOMAIN" ] && SERVER_NAME="$DOMAIN www.$DOMAIN"

echo "==> Installing packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get upgrade -y
# Laravel 10 supports PHP up to 8.3; newer Ubuntu releases (e.g. 26.04) ship a
# newer PHP, so fetch 8.3 from the ondrej/php PPA when it isn't in the base repos.
if ! apt-cache show php$PHP-fpm >/dev/null 2>&1; then
  apt-get install -y software-properties-common
  add-apt-repository -y ppa:ondrej/php
  apt-get update -y
fi
apt-get install -y nginx postgresql git unzip curl ufw certbot python3-certbot-nginx \
  php$PHP-fpm php$PHP-cli php$PHP-pgsql php$PHP-mbstring php$PHP-xml php$PHP-curl \
  php$PHP-zip php$PHP-gd php$PHP-intl php$PHP-bcmath

if ! command -v composer >/dev/null; then
  curl -fsSL https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> PHP limits (uploads up to 8 MB, same as the Docker image)"
cat > /etc/php/$PHP/fpm/conf.d/99-awas.ini <<'INI'
upload_max_filesize=8M
post_max_size=20M
memory_limit=256M
expose_php=Off
date.timezone=Asia/Manila
INI
systemctl restart php$PHP-fpm

echo "==> PostgreSQL database"
CRED_FILE=/root/awas-db-credentials.txt
if [ ! -f "$CRED_FILE" ]; then
  DB_PASS=$(openssl rand -hex 16)
  sudo -u postgres psql -v ON_ERROR_STOP=1 <<SQL
CREATE USER $DB_USER WITH PASSWORD '$DB_PASS';
CREATE DATABASE $DB_NAME OWNER $DB_USER;
SQL
  printf 'DB_DATABASE=%s\nDB_USERNAME=%s\nDB_PASSWORD=%s\n' "$DB_NAME" "$DB_USER" "$DB_PASS" > "$CRED_FILE"
  chmod 600 "$CRED_FILE"
fi
DB_PASS=$(grep DB_PASSWORD "$CRED_FILE" | cut -d= -f2)

echo "==> App code"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone "$REPO" "$APP_DIR"
else
  git -C "$APP_DIR" pull --ff-only
fi
cd "$APP_DIR"
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader --no-interaction

if [ ! -f .env ]; then
  cp .env.example .env
  set_env() { if grep -q "^$1=" .env; then sed -i "s|^$1=.*|$1=$2|" .env; else echo "$1=$2" >> .env; fi; }
  set_env APP_NAME AGAS
  set_env APP_ENV production
  set_env APP_DEBUG false
  set_env APP_URL "https://${DOMAIN:-201.18.210.94}"
  set_env LOG_LEVEL warning
  set_env DB_CONNECTION pgsql
  set_env DB_HOST 127.0.0.1
  set_env DB_PORT 5432
  set_env DB_DATABASE "$DB_NAME"
  set_env DB_USERNAME "$DB_USER"
  set_env DB_PASSWORD "$DB_PASS"
  set_env QUEUE_CONNECTION sync
  set_env SESSION_DRIVER file
  set_env BROADCAST_DRIVER log
  # Fill these in afterwards with:  nano /var/www/awas/.env
  for k in BREVO_API_KEY MAIL_FROM_EMAIL MAIL_FROM_NAME_AWAS PAYMONGO_SECRET_KEY \
           PAYMONGO_PUBLIC_KEY PAYMONGO_WEBHOOK_SECRET CHATBOT_AI_API_KEY; do
    grep -q "^$k=" .env || echo "$k=" >> .env
  done
  grep -q '^CHATBOT_AI_MODEL=' .env || echo 'CHATBOT_AI_MODEL=claude-haiku-4-5-20251001' >> .env
  grep -q '^AGAS_IDLE_TIMEOUT=' .env || echo 'AGAS_IDLE_TIMEOUT=30' >> .env
  php artisan key:generate --force
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "==> Nginx"
cat > /etc/nginx/sites-available/awas <<NGINX
server {
    listen 80;
    server_name $SERVER_NAME;
    root $APP_DIR/public;
    index index.php;
    client_max_body_size 20M;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php$PHP-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
NGINX
ln -sf /etc/nginx/sites-available/awas /etc/nginx/sites-enabled/awas
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

echo "==> Firewall"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "==> Scheduler (bill reminders) + nightly backups"
mkdir -p /var/backups/awas
cat > /usr/local/bin/awas-backup <<'SH'
#!/usr/bin/env bash
# Nightly: database dump + uploaded files; keeps 14 days.
set -e
d=$(date +%F)
sudo -u postgres pg_dump -Fc awas_db > /var/backups/awas/db-$d.dump
tar -czf /var/backups/awas/uploads-$d.tar.gz -C /var/www/awas/storage app
find /var/backups/awas -type f -mtime +14 -delete
SH
chmod +x /usr/local/bin/awas-backup
cat > /etc/cron.d/awas <<CRON
* * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1
30 2 * * * root /usr/local/bin/awas-backup
CRON

echo
echo "Done. Next:"
echo "  1. nano $APP_DIR/.env      (fill in Brevo, PayMongo and chatbot keys)"
echo "  2. Import the database or run: cd $APP_DIR && sudo -u www-data php artisan migrate --force"
echo "  3. sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache"
[ -n "$DOMAIN" ] && echo "  4. certbot --nginx -d $DOMAIN -d www.$DOMAIN   (after DNS points here)"
echo "DB credentials are saved in $CRED_FILE"
