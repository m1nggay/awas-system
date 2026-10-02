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
        return $ts ? date($format, $ts) : '—';
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime(?string $datetime, string $format = 'M d, Y g:i A'): string
    {
        if (empty($datetime)) return '—';
        $ts = strtotime($datetime);
        return $ts ? date($format, $ts) : '—';
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
        return $ts ? date('F Y', $ts) : $period;
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
            return 'Password must contain at least 8 characters, including a capital letter, a number, and a symbol.';
        }
        return null;
    }
}

if (!function_exists('consumerTypeLabel')) {
    function consumerTypeLabel(?string $type): string
    {
        return \App\Models\Consumer::TYPES[$type] ?? '—';
    }
}

if (!function_exists('roleLabel')) {
    /** Display name of a user role (stored codes: admin, staff, resident, applicant). */
    function roleLabel(?string $role): string
    {
        return match ($role) {
            'admin'     => 'Administrator',
            'staff'     => 'Meter Reader',
            'resident'  => 'Consumer',
            'applicant' => 'Applicant',
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
            'pending'            => 'Pending Verification',
            'verified'           => 'Paid',
            'rejected', 'failed' => 'Rejected',
            'refunded'           => 'Refunded',
            default              => ucfirst($status),
        };
    }
}

if (!function_exists('paymentMethodLabel')) {
    function paymentMethodLabel(string $method): string
    {
        return match ($method) {
            'cash'          => 'Cash',
            'gcash'         => 'GCash QR',
            'paymongo'      => 'PayMongo (old)',
            'bank_transfer' => 'Bank Transfer',
            'online'        => 'Online',
            default         => ucfirst(str_replace('_', ' ', $method)),
        };
    }
}

if (!function_exists('paymentChannelLabel')) {
    function paymentChannelLabel(?string $channel): string
    {
        return $channel === 'online' ? 'Online' : 'At the barangay';
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
            'pending_verification' => 'Awaiting email verification',
            'pending_review'       => 'Pending review',
            'approved'             => 'Approved — awaiting meter',
            'active'               => 'Active',
            'rejected'             => 'Rejected',
            default                => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

if (!function_exists('verificationStatusLabel')) {
    function verificationStatusLabel(string $status): string
    {
        return match ($status) {
            'not_submitted' => 'Not submitted',
            'for_review'    => 'For review',
            default         => ucfirst(str_replace('_', ' ', $status)),
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
