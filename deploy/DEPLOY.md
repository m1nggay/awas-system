# Deploying AWAS to the Hostinger VPS

Server: Hostinger KVM 1, Ubuntu 24.04 LTS (26.04 has no PHP 8.3 packages), IP `201.18.210.94`.

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
If `pg_dump` is not found, use its full path, e.g. `& "C:\Program Files\PostgreSQL\18\bin\pg_dump.exe"`.
The server runs PostgreSQL 16 and the PC runs 18, so use a plain SQL dump. Before uploading, delete any
`\restrict`, `\unrestrict` and `SET transaction_timeout` lines from the file.

```
pg_dump -U postgres --no-owner --no-privileges -d awas_db -f awas_db.sql
scp awas_db.sql root@201.18.210.94:/root/
scp -r storage/app/applications storage/app/gcash storage/app/payment-receipts storage/app/profile-photos storage/app/public root@201.18.210.94:/var/www/awas/storage/app/
```

Then on the server:

```
(echo "SET ROLE awas;"; cat /root/awas_db.sql) | sudo -u postgres psql -d awas_db -v ON_ERROR_STOP=1
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

PayMongo webhooks are created through its API. Run this on the server. It registers
`https://awas-system.online/api/paymongo/webhook` and saves the returned `whsk_...` secret into `.env`:

```
cd /var/www/awas && SK=$(grep '^PAYMONGO_SECRET_KEY=' .env | cut -d= -f2- | tr -d '"') && RESP=$(curl -s https://api.paymongo.com/v1/webhooks -u "$SK:" -H 'Content-Type: application/json' -d '{"data":{"attributes":{"url":"https://awas-system.online/api/paymongo/webhook","events":["checkout_session.payment.paid","payment.paid"]}}}') && WH=$(echo "$RESP" | php -r '$j=json_decode(stream_get_contents(STDIN),true); echo $j["data"]["attributes"]["secret_key"] ?? "";') && if [ -n "$WH" ]; then sed -i "s|^PAYMONGO_WEBHOOK_SECRET=.*|PAYMONGO_WEBHOOK_SECRET=$WH|" .env && sudo -u www-data php artisan config:cache && echo "Webhook created and saved"; else echo "$RESP"; fi
```

AWAS uses the PayMongo sandbox only: use the `sk_test_` / `pk_test_` keys. A live key (`sk_live_`) is refused and turns online payments off.

## Updating later

```
cd /var/www/awas
git pull
COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan config:cache && sudo -u www-data php artisan route:cache && sudo -u www-data php artisan view:cache
```

## Backups

Administrators can back up, download, upload and restore the database in AWAS itself: **Administration → Backup & Restore**.
Those backups are saved in `/var/www/awas/storage/app/backups`; an automatic one is made every night at 2:00 AM (the newest 14 are kept).
A restore first saves the current data as a "Before restore" backup, so it can be undone from the same page.

Separately, the server keeps its own copies:

Backups run every night at 2:30 AM and are stored in `/var/backups/awas`. Each night saves a database dump and the uploaded files, and the last 14 days are kept.
To restore a database dump: `sudo -u postgres pg_restore --clean --no-owner --role=awas -d awas_db /var/backups/awas/db-YYYY-MM-DD.dump`
