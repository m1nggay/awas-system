<?php

/*
 * View/formatting helpers carried over from the original includes/functions.php
 * and includes/applications.php. Loaded through composer.json "autoload.files".
 */

use App\Services\Settings;
use Illuminate\Support\Facades\DB;

if (!function_exists('clean')) {
    function clean(?string $value): string
    {
        return trim(strip_tags($value ?? ''));
    }
}

if (!function_exists('formatCurrency')) {
    function formatCurrency($amount): string
    {
        return '₱' . number_format((float)$amount, 2);
    }
}

if (!function_exists('formatDate')) {
    function formatDate(?string $date, string $format = 'M d, Y'): string
    {
        if (empty($date) || $date === '0000-00-00') return '—';
        $ts = strtotime($date);
        return $ts ? localizeMonths(date($format, $ts)) : '—';
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime(?string $datetime, string $format = 'M d, Y g:i A'): string
    {
        if (empty($datetime)) return '—';
        $ts = strtotime($datetime);
        return $ts ? localizeMonths(date($format, $ts)) : '—';
    }
}

if (!function_exists('currentBillingPeriod')) {
    function currentBillingPeriod(): string
    {
        return date('Y-m');
    }
}

if (!function_exists('billingPeriodLabel')) {
    function billingPeriodLabel(string $period): string
    {
        $ts = strtotime($period . '-01');
        return $ts ? localizeMonths(date('F Y', $ts)) : $period;
    }
}

if (!function_exists('like_operator')) {
    /** Case-insensitive LIKE for search boxes: MySQL's LIKE already is, PostgreSQL needs ILIKE. */
    function like_operator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
    }
}

if (!function_exists('setting')) {
    /** Reads a system_settings value (cached for the request). */
    function setting(string $key, $default = null)
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (!function_exists('flash')) {
    /** One-shot banner shown at the top of the next page (see layouts/partials/flash). */
    function flash(string $type, string $message): void
    {
        session()->flash('flash', ['type' => $type, 'message' => $message]);
    }
}

if (!function_exists('log_activity')) {
    /** Audit trail entry — never lets a logging failure break the request. */
    function log_activity(?int $userId, string $action, ?string $details = null): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id'    => $userId,
                'action'     => $action,
                'details'    => $details !== null ? mb_substr($details, 0, 500) : null,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

if (!function_exists('isValidMeterNumber')) {
    /** Meter Number: digits only, e.g. "1001" — never "ADL-1001" or "MTR-1001". */
    function isValidMeterNumber(?string $value): bool
    {
        return is_string($value) && preg_match('/^\d{1,20}$/', $value) === 1;
    }
}

if (!function_exists('passwordPolicyError')) {
    /** Shared password rule; returns the problem, or null when the password is acceptable. */
    function passwordPolicyError(string $password): ?string
    {
        if (strlen($password) < 8
            || !preg_match('/[A-Z]/', $password)
            || !preg_match('/\d/', $password)
            || !preg_match('/[^A-Za-z0-9]/', $password)) {
            return __('Password must contain at least 8 characters, including an uppercase letter, a number, and a symbol.');
        }
        return null;
    }
}

if (!function_exists('consumerTypeLabel')) {
    function consumerTypeLabel(?string $type): string
    {
        return isset(\App\Models\Consumer::TYPES[$type]) ? __(\App\Models\Consumer::TYPES[$type]) : '—';
    }
}

if (!function_exists('roleLabel')) {
    /** Display name of a user role (stored codes: admin, staff, resident, applicant). */
    function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin'     => __('AWAS Administrator'),
            'staff'     => __('Meter Reader'),
            'resident'  => __('Consumer'),
            'applicant' => __('Applicant'),
            default     => ucfirst((string)$role),
        };
    }
}

if (!function_exists('billStatusBadgeClass')) {
    function billStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'paid'           => 'badge-success',
            'unpaid'         => 'badge-warning',
            'partially_paid' => 'badge-info',
            'overdue'        => 'badge-danger',
            default          => 'badge-secondary',
        };
    }
}

if (!function_exists('paymentStatusBadgeClass')) {
    function paymentStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'verified' => 'badge-success',
            'pending'  => 'badge-warning',
            'failed', 'rejected' => 'badge-danger',
            'refunded' => 'badge-info',
            default    => 'badge-secondary',
        };
    }
}

if (!function_exists('paymentStatusLabel')) {
    /** Stored payment status → what people see: Pending Verification / Paid / Rejected. */
    function paymentStatusLabel(string $status): string
    {
        return match ($status) {
            'pending'            => __('Pending Verification'),
            'verified'           => __('Paid'),
            'rejected', 'failed' => __('Rejected'),
            'refunded'           => __('Refunded'),
            default              => ucfirst($status),
        };
    }
}

if (!function_exists('paymentMethodLabel')) {
    /** $wallet: for PayMongo payments, which e-wallet was used (payments.wallet). */
    function paymentMethodLabel(string $method, ?string $wallet = null): string
    {
        return match ($method) {
            'cash'          => __('Cash'),
            'gcash'         => 'GCash QR',
            'paymongo'      => \App\Services\PayMongo::walletName($wallet) . ' (PayMongo)',
            'bank_transfer' => __('Bank Transfer'),
            'online'        => __('Online'),
            default         => ucfirst(str_replace('_', ' ', $method)),
        };
    }
}

if (!function_exists('paymentChannelLabel')) {
    function paymentChannelLabel(?string $channel): string
    {
        return $channel === 'online' ? __('Online') : __('At the barangay');
    }
}

if (!function_exists('applicationStatusBadgeClass')) {
    function applicationStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'active'               => 'badge-success',
            'approved'             => 'badge-info',
            'rejected'             => 'badge-danger',
            'pending_verification' => 'badge-secondary',
            default                => 'badge-warning', // pending_review
        };
    }
}

if (!function_exists('applicationStatusLabel')) {
    function applicationStatusLabel(string $status): string
    {
        return match ($status) {
            'pending_verification' => __('Awaiting email verification'),
            'pending_review'       => __('Pending review'),
            'approved'             => __('Approved — awaiting meter'),
            'active'               => __('Active'),
            'rejected'             => __('Rejected'),
            default                => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

if (!function_exists('verificationStatusLabel')) {
    function verificationStatusLabel(string $status): string
    {
        return match ($status) {
            'not_submitted' => __('Not submitted'),
            'for_review'    => __('For review'),
            default         => __(ucfirst(str_replace('_', ' ', $status))),
        };
    }
}

if (!function_exists('verificationStatusBadgeClass')) {
    function verificationStatusBadgeClass(string $status): string
    {
        return match ($status) {
            'verified'   => 'badge-success',
            'failed'     => 'badge-danger',
            'for_review' => 'badge-warning',
            'submitted'  => 'badge-info',
            default      => 'badge-secondary',
        };
    }
}

if (!function_exists('ageFromBirthDate')) {
    function ageFromBirthDate(string $birthDate): ?int
    {
        try {
            return (new DateTimeImmutable($birthDate))->diff(new DateTimeImmutable('today'))->y;
        } catch (\Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('localizeMonths')) {
    /** Month and day names in Tagalog when the page is in Tagalog ("November 2026" → "Nobyembre 2026", "Nov 11" → "Nob 11"). */
    function localizeMonths(string $text): string
    {
        if (app()->getLocale() !== 'tl') {
            return $text;
        }
        static $months = [
            'January' => 'Enero', 'February' => 'Pebrero', 'March' => 'Marso', 'April' => 'Abril', 'June' => 'Hunyo',
            'July' => 'Hulyo', 'August' => 'Agosto', 'September' => 'Setyembre', 'October' => 'Oktubre',
            'November' => 'Nobyembre', 'December' => 'Disyembre', 'May' => 'Mayo',
            'Jan' => 'Ene', 'Feb' => 'Peb', 'Apr' => 'Abr', 'Jun' => 'Hun', 'Jul' => 'Hul', 'Aug' => 'Ago',
            'Sep' => 'Set', 'Oct' => 'Okt', 'Nov' => 'Nob', 'Dec' => 'Dis',
            'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miyerkules', 'Thursday' => 'Huwebes',
            'Friday' => 'Biyernes', 'Saturday' => 'Sabado', 'Sunday' => 'Linggo',
        ];
        return preg_replace_callback('/\b(' . implode('|', array_keys($months)) . ')\b/', fn ($m) => $months[$m[1]], $text);
    }
}

if (!function_exists('userLocale')) {
    /** Language a user picked with the EN | TL switch (users.locale), for notifications and emails sent to them. */
    function userLocale(?int $userId): string
    {
        $locale = $userId ? DB::table('users')->where('user_id', $userId)->value('locale') : null;
        return in_array($locale, \App\Http\Middleware\SetLocale::LOCALES, true) ? $locale : 'en';
    }
}

if (!function_exists('inLocale')) {
    /** Runs $callback with the page language switched to $locale (e.g. to write a notification in its recipient's language). */
    function inLocale(string $locale, callable $callback)
    {
        $previous = app()->getLocale();
        if ($previous === $locale) {
            return $callback();
        }
        app()->setLocale($locale);
        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }
}

if (!function_exists('translateStored')) {
    /**
     * Shows text saved in English (notifications made before the Tagalog switch existed) in the
     * page's language: each sentence is matched against the English messages in lang/tl.json and
     * rebuilt from its Tagalog version. Text that matches nothing (e.g. an announcement someone
     * typed) is shown as it was saved.
     */
    function translateStored(?string $text): string
    {
        $text = (string)$text;
        if ($text === '' || app()->getLocale() === 'en') {
            return $text;
        }
        static $patterns = null;
        if ($patterns === null) {
            $patterns = [];
            $keys = array_keys(json_decode((string)@file_get_contents(lang_path(app()->getLocale() . '.json')), true) ?: []);
            usort($keys, fn ($a, $b) => strlen($b) - strlen($a));   // longest (most specific) first
            foreach ($keys as $key) {
                if (strlen($key) < 8 || !preg_match('/^[A-Z:🎉]/u', $key) || str_contains($key, '<')) {
                    continue;
                }
                $names = [];
                $regex = preg_replace_callback('/:([a-z][a-z0-9_]*)|[^:]+|:/', function ($m) use (&$names) {
                    if (!empty($m[1])) {
                        $names[] = $m[1];
                        return '(.+?)';
                    }
                    return preg_quote($m[0], '/');
                }, $key);
                $patterns[] = ['/\G' . $regex . '(?=\s|$)/u', $key, $names];
            }
        }

        $out = [];
        $pos = 0;
        $len = strlen($text);
        while ($pos < $len) {
            foreach ($patterns as [$regex, $key, $names]) {
                if (preg_match($regex, $text, $m, 0, $pos)) {
                    $values = [];
                    foreach ($names as $i => $name) {
                        $values[$name] = localizeMonths($m[$i + 1]);
                    }
                    $out[] = __($key, $values);
                    $pos += strlen($m[0]);
                    $pos += strspn($text, " \t\n", $pos);
                    continue 2;
                }
            }
            return $text;   // part of it is not a known message: leave it as saved
        }
        return implode(' ', $out);
    }
}
