# Deploying AWAS to the Hostinger VPS

Server: Hostinger KVM 1, Ubuntu 26.04, IP `201.18.210.94`.

## 0. Before you start

- Commit and push your latest code to GitHub (`main`). The server downloads the code from there.
- Claim the free domain in hPanel. Under **Domains → DNS**, add two **A records** pointing to `201.18.210.94`: one for `@` and one for `www`.

## 1. Connect to the server

On Windows, open PowerShell:

```
ssh root@201.18.210.94
```

Type `yes` the first time, then enter the root password you set in hPanel. The password stays hidden while you type.
If you forgot it, go to **hPanel → VPS → Settings → Change root password**.

## 2. Run the setup script

Run these two commands:

```
curl -fsSL https://raw.githubusercontent.com/m1nggay/awas-system/main/deploy/setup-server.sh -o setup-server.sh
bash setup-server.sh awas-system.online
```

It takes about 5 minutes. The database password it creates is saved in `/root/awas-db-credentials.txt`.

## 3. Fill in the secret keys

```
nano /var/www/awas/.env
```

Copy these values from your local `.env`: `BREVO_API_KEY`, `MAIL_FROM_EMAIL`, `MAIL_FROM_NAME_AWAS`,
`PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, `PAYMONGO_WEBHOOK_SECRET`, `CHATBOT_AI_API_KEY`.
Save with `Ctrl+O`, `Enter`, then exit with `Ctrl+X`.

## 4. Load the data

Choose **one** option.

**A. Copy your local data** (keeps existing consumers, bills and payments). On your Windows PC, in the project folder.
If `pg_dump` is not found, use its full path, e.g. `& "C:\Program Files\PostgreSQL\16\bin\pg_dump.exe"`:

```
pg_dump -U postgres -Fc awas_db -f awas.dump
scp awas.dump root@201.18.210.94:/root/
scp -r storage/app root@201.18.210.94:/var/www/awas/storage/
```

Then on the server:

```
sudo -u postgres pg_restore --no-owner --role=awas -d awas_db /root/awas.dump
chown -R www-data:www-data /var/www/awas/storage
```

**B. Start fresh:**

```
cd /var/www/awas && sudo -u www-data php artisan migrate --seed --force
```

## 5. Finish

```
cd /var/www/awas
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
certbot --nginx -d awas-system.online -d www.awas-system.online
```

`certbot` turns on HTTPS. Run it only after the domain points to the server. Enter your email and agree when it asks.

## 6. PayMongo webhook

In the PayMongo dashboard, set the webhook URL to `https://awas-system.online/api/paymongo/webhook`.
Then put its signing secret in `PAYMONGO_WEBHOOK_SECRET` and run `php artisan config:cache` again.

## Updating later

```
cd /var/www/awas
git pull
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache && sudo -u www-data php artisan view:cache
```

## Backups

Backups run every night at 2:30 AM and are stored in `/var/backups/awas`. Each night saves a database dump and the uploaded files, and the last 14 days are kept.
To restore a database dump: `sudo -u postgres pg_restore --clean --no-owner --role=awas -d awas_db /var/backups/awas/db-YYYY-MM-DD.dump`
