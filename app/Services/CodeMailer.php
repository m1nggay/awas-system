<?php

namespace App\Services;

/**
 * Issues a one-time code and emails it, returning what actually happened
 * ('sent' | 'cooldown' | 'limit' | 'failed' — see OtpService::issueAndSend()),
 * so pages only say "code sent" when the email really went out.
 */
class CodeMailer
{
    public function __construct(private Mailer $mailer)
    {
    }

    /** Email-address verification code (registration / membership application). */
    public function sendEmailVerification(int $userId, string $email, string $name, string $intro, ?string $next = null): string
    {
        $otp = OtpService::emailVerification();
        return $otp->issueAndSend($userId, fn (string $code) => $this->mailer->send(
            $email, $name, 'Verify your AGAS account', 'emails.verification-code',
            ['name' => $name, 'code' => $code, 'intro' => $intro, 'ttl' => $otp->ttlMinutes, 'next' => $next]
        ));
    }

    /** Password code: "Forgot Password" and signed-in "Change Password". */
    public function sendPasswordCode(int $userId, string $email, string $name, string $subject, string $purpose): string
    {
        $otp = OtpService::passwordReset();
        return $otp->issueAndSend($userId, fn (string $code) => $this->mailer->send(
            $email, $name, $subject, 'emails.password-reset-code',
            ['name' => $name, 'code' => $code, 'ttl' => $otp->ttlMinutes, 'purpose' => $purpose]
        ));
    }
}
