<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 6-digit one-time codes (originally includes/otp.php and
 * includes/email_verification.php). Each purpose has its own table, so a
 * code issued for one flow can never be replayed for the other. Codes are
 * stored only as a SHA-256 hash, expire, and lock after too many wrong guesses.
 */
class OtpService
{
    public function __construct(
        private string $table,
        public readonly int $ttlMinutes,
        private int $maxAttempts,
        private int $resendCooldownSeconds,
        private ?int $maxPerHour = null,
    ) {
    }

    /** Password codes: "Forgot Password" and signed-in "Change Password". */
    public static function passwordReset(): self
    {
        return new self('password_reset_otps', 10, 5, 60, 5);
    }

    /** Registration / membership-application email verification codes. */
    public static function emailVerification(): self
    {
        return new self('email_verification_otps', 30, 5, 60, 5);
    }

    /**
     * Creates and stores a new code, unless one was requested within the
     * resend cooldown (or the hourly cap is hit) — then null, and the caller
     * keeps showing the same "check your email" state instead of re-sending.
     */
    public function issue(int $userId): ?string
    {
        $last = DB::table($this->table)->where('user_id', $userId)->orderByDesc('created_at')->value('created_at');
        if ($last && (time() - strtotime($last)) < $this->resendCooldownSeconds) {
            return null;
        }

        if ($this->maxPerHour !== null) {
            $recent = DB::table($this->table)->where('user_id', $userId)
                ->where('created_at', '>', now()->subHour()->format('Y-m-d H:i:s'))->count();
            if ($recent >= $this->maxPerHour) {
                return null;
            }
        }

        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        DB::table($this->table)->insert([
            'user_id'    => $userId,
            'otp_hash'   => hash('sha256', $code),
            'expires_at' => now()->addMinutes($this->ttlMinutes)->format('Y-m-d H:i:s'),
            'created_at' => now()->format('Y-m-d H:i:s'),
        ]);

        return $code;
    }

    /** Seconds before another code may be requested (0 = allowed now). */
    public function secondsUntilResend(int $userId): int
    {
        $last = DB::table($this->table)->where('user_id', $userId)->orderByDesc('created_at')->value('created_at');
        return $last ? max(0, $this->resendCooldownSeconds - (time() - strtotime($last))) : 0;
    }

    public function resendCooldownSeconds(): int
    {
        return $this->resendCooldownSeconds;
    }

    /**
     * Issues a code and hands it to $send (which emails it and returns true
     * on success). Returns what really happened, so the page never claims a
     * code was sent when it wasn't:
     *   'sent'     — emailed
     *   'cooldown' — asked again too soon (see secondsUntilResend())
     *   'limit'    — too many codes this hour
     *   'failed'   — the email could not be sent (the unsent code is discarded)
     */
    public function issueAndSend(int $userId, callable $send): string
    {
        if ($this->secondsUntilResend($userId) > 0) {
            return 'cooldown';
        }
        $code = $this->issue($userId);
        if ($code === null) {
            return 'limit';
        }
        if (!$send($code)) {
            // Remove the unsent code so the person can retry immediately.
            DB::table($this->table)->where('user_id', $userId)->whereNull('used_at')
                ->where('otp_hash', hash('sha256', $code))->delete();
            return 'failed';
        }
        return 'sent';
    }

    /** User-facing message for an issueAndSend() result. */
    public static function resultMessage(string $result, int $waitSeconds = 0): array
    {
        return match ($result) {
            'sent'     => ['success', 'A 6-digit code was sent to your email. If you don\'t see it within a minute, check your Spam or Promotions folder.'],
            'cooldown' => ['warning', "Please wait {$waitSeconds} seconds before requesting another code."],
            'limit'    => ['danger', 'Too many codes were requested in the last hour. Please try again later.'],
            default    => ['danger', 'We could not send the email right now. Please try again in a moment, or contact the barangay water office.'],
        };
    }

    /**
     * Checks $code against the latest unused code. Returns 'ok' (now marked
     * used), 'invalid' (attempt counted), 'expired', or 'locked'.
     */
    public function verify(int $userId, string $code): string
    {
        $otp = DB::table($this->table)->where('user_id', $userId)->whereNull('used_at')
            ->orderByDesc('created_at')->orderByDesc('id')->first();

        if (!$otp) return 'expired';
        if ((int)$otp->attempts >= $this->maxAttempts) return 'locked';
        if (strtotime($otp->expires_at) < time()) return 'expired';

        if (hash_equals($otp->otp_hash, hash('sha256', $code))) {
            DB::table($this->table)->where('id', $otp->id)->update(['used_at' => now()->format('Y-m-d H:i:s')]);
            return 'ok';
        }

        DB::table($this->table)->where('id', $otp->id)->increment('attempts');
        return ((int)$otp->attempts + 1 >= $this->maxAttempts) ? 'locked' : 'invalid';
    }

    /** Invalidates every outstanding code for the user (e.g. right after success). */
    public function invalidate(int $userId): void
    {
        DB::table($this->table)->where('user_id', $userId)->whereNull('used_at')
            ->update(['used_at' => now()->format('Y-m-d H:i:s')]);
    }

    public function hasLiveCode(int $userId): bool
    {
        return DB::table($this->table)->where('user_id', $userId)->whereNull('used_at')
            ->where('expires_at', '>', now()->format('Y-m-d H:i:s'))->exists();
    }
}
