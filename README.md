# AGAS — Smart Water Management and Billing System (Laravel)

Barangay Adlay's water meter reading, billing, online payment and membership
system, ported from the original plain-PHP `agas-system` to **Laravel 10**
(PHP 8.1+). Features, rules and screens are the same as the original; the
original SQL files are kept for reference in `database/legacy-sql/`.

Works with **PostgreSQL** (current `.env`) or **MySQL/MariaDB** (XAMPP) —
switch with `DB_CONNECTION` in `.env`.

## Setup

```bash
composer install                 # only if vendor/ is missing
cp .env.example .env             # then fill in DB_* and the AGAS keys
php artisan key:generate         # only for a new .env
php artisan migrate:fresh --seed # new/empty database: creates all tables + sample data
```

- **Existing `awas_db` from the old PHP app (MySQL)?** Run `php artisan migrate --seed`
  instead (no `fresh`). Existing tables are kept, missing columns are added (the
  old `migration_*.sql` steps), and the seeder only inserts sample accounts
  when the `users` table is empty.
- Open **http://localhost/awas-system** (XAMPP — the root `.htaccess` redirects
  into `public/`), or run `php artisan serve` and open http://127.0.0.1:8000.

Sample logins (password `Password123!`): `admin` (Administrator), `meterreader` (Meter Reader), `resident1` and `resident2` (Consumers).
Change or remove them before real use.

- **Upgrading an existing PostgreSQL `awas_db`** (keeps your data): `php artisan migrate`.
  This renames `meter_serial_number` → `meter_number` (digits only), removes
  `account_number`, adds Type of Consumer and the face-check status, and drops
  the `BILL-` prefix from bill references. Back up first (`pg_dump`).
- **Fresh install from pgAdmin instead of artisan:** run `database/awas_db_pgsql.sql`
  (drops and recreates the tables).

## Roles

| Role | Can use |
|---|---|
| Administrator | Everything: consumers, applications, meter readings (view + correct), water bills, payments, reports, users, activity logs, settings |
| Meter Reader | Dashboard, Meter Readings (record: Meter Number, previous and present reading — consumption, total and balance are calculated and the bill is sent to the admin automatically), reading reports, own activity logs, change password |
| Consumer | Dashboard, Current Bills (unpaid, pay online by GCash QR), Billing History (paid), consumption, payments, profile, change password |

Passwords must have at least 8 characters with letters, numbers and symbols.
Changing a password (any role) needs a 6-digit code sent to the account's email.

## Payments (Cash and GCash QR)

**Setup (required for online payment):** Admin → System Settings → **GCash QR
Payment** — upload the barangay's official GCash QR code and enter the GCash
account name and number. Until a QR code is uploaded, consumers are told to pay
at the barangay.

- **At the barangay** (Admin → Payments → *Record Payment*): choose
  **Cash** or **GCash QR**. For GCash QR, show the QR to the consumer, check the
  payment in the barangay GCash account and enter its reference number. The bill
  is marked **Paid** right away.
- **Online** (Consumer → Current Bills → **Pay Bill**): GCash QR only. The
  consumer scans the QR, pays the exact amount, enters the GCash reference
  number (receipt screenshot optional) and taps **I Have Paid**. The payment
  becomes **Pending Verification** and the admins are notified. Admin →
  Payments → *Pending Verification* → **Verify** marks it Paid and updates the
  bill; **Reject** (with a reason) notifies the consumer, who can pay again.

Statuses: Unpaid → Pending Verification → Paid, or Rejected. A bill can have only
one pending payment, and each GCash reference number can be used only once.

## Email codes not arriving?

Codes are sent through Brevo. The app now shows an error when an email truly
fails, instead of claiming it was sent. If Brevo reports "delivered" but the
person can't find it, it is almost always in **Spam/Promotions**: a
`@gmail.com` sender address sent through Brevo fails Gmail's sender checks.
For reliable inbox delivery, authenticate your own domain in Brevo
(Senders, Domains & Dedicated IPs → Domains → add the DKIM/SPF DNS records)
and set `MAIL_FROM_EMAIL` to an address on that domain.

## Configuration (`.env`)

| Key | Purpose |
|---|---|
| `BREVO_API_KEY`, `MAIL_FROM_EMAIL` | Emails with OTP codes and application updates (Brevo API). `MAIL_FROM_EMAIL` must be a verified sender in Brevo. Without a key, emails fail and the user sees an error. |
| `PAYMONGO_SECRET_KEY`, `PAYMONGO_WEBHOOK_SECRET` | Optional, legacy. Consumers now pay by the barangay GCash QR (see Payments); only the old PayMongo webhook remains for past checkouts. |
| `CHATBOT_AI_API_KEY`, `CHATBOT_AI_MODEL` | Optional Claude fallback for the resident chatbot; also enable it under Admin → Chatbot FAQs. |
| `AGAS_IDLE_TIMEOUT` | Minutes of inactivity before sign-out (default 30). |

Billing tariff, due day, grace period and discounts are edited in the app
under **Admin → System Settings**.

Legacy PayMongo webhook URL (Dashboard → Developers → Webhooks, event
`checkout_session.payment.paid`): `https://<your-domain>/api/paymongo/webhook`.
The old `/api/paymongo_webhook.php` path still works too.

## Where things are

| Original file | Laravel |
|---|---|
| `includes/functions.php` (billing, reports, numbering) | `app/Services/BillingService.php`, `app/helpers.php` |
| `includes/auth.php`, `csrf.php` | Laravel auth + `app/Http/Middleware/EnsureRole.php`, `IdleTimeout.php` |
| `includes/otp.php`, `email_verification.php` | `app/Services/OtpService.php` |
| `includes/mailer.php` | `app/Services/Mailer.php` + `resources/views/emails/` |
| `includes/paymongo.php` | `app/Services/PayMongo.php` |
| `includes/chatbot.php` | `app/Services/Chatbot.php` |
| `includes/applications.php` | `app/Services/ApplicationFiles.php`, `app/Models/MembershipApplication.php` |
| `admin/*.php` | `app/Http/Controllers/Admin/*` + `resources/views/admin/*` |
| `resident/*.php` | `app/Http/Controllers/Resident/*` + `resources/views/resident/*` |
| `auth/*.php`, `apply_membership.php`, `applicant/status.php` | `app/Http/Controllers/Auth/*`, `MembershipApplicationController`, `ApplicantStatusController` |
| `api/*.php` | `ChatbotController`, `PayMongoWebhookController` (`routes/api.php`), `Admin\ReportController@export` |
| `config/*.php`, `secrets.local.php` | `.env` + `config/agas.php` |
| `assets/` | `public/assets/` |
| `storage/applications/` (ID photos, selfies) | `storage/app/applications/` (outside `public/`, admin-only route) |

All routes are listed in `routes/web.php` (`php artisan route:list`).
