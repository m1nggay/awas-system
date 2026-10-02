# AGAS — container image for Render (or any Docker host).
# PHP 8.1 + Apache serving Laravel's public/ folder. XAMPP is unaffected by this file.
FROM php:8.1-apache

# PostgreSQL + zip (Composer) extensions. mbstring, curl, openssl and fileinfo are built in.
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install pdo_pgsql pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Apache: serve public/, allow .htaccess rewrites (pretty URLs).
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf \
    && a2enmod rewrite headers

# Uploads (ID photos, receipts, QR) up to 5 MB each; the membership form sends two photos.
RUN { echo 'upload_max_filesize=8M'; echo 'post_max_size=20M'; echo 'memory_limit=256M'; echo 'expose_php=Off'; } \
    > /usr/local/etc/php/conf.d/agas.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Dependencies first (cached between builds), then the app.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/applications storage/app/gcash storage/app/payment-receipts \
               storage/framework/cache storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/start.sh

CMD ["docker/start.sh"]
