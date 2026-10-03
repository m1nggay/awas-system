<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BillingService;
use App\Services\CodeMailer;
use App\Services\Mailer;
use App\Services\OtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Final step of resident self-registration and of the membership
 * application, and the landing page for a "pending" account that tries to
 * log in. The account stays status 'pending' — and so can't log in — until
 * the emailed 6-digit code is entered.
 */
class EmailVerificationController extends Controller
{
    /** The verification session expires on its own so an old tab can't be reused forever. */
    private const SESSION_TTL_SECONDS = 60 * 60;

    public function show(Request $request, CodeMailer $codes)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }

        // register/apply send a code, but the login "pending" redirect does not.
        $otp = OtpService::emailVerification();
        $result = session('code_result');
        if (!$result && !$otp->hasLiveCode($user->user_id)) {
            $result = $this->sendCode($user, $codes);
        }

        return view('auth.verify-email', [
            'user'        => $user,
            'isApplicant' => $user->role === 'applicant',
            'ttl'         => $otp->ttlMinutes,
            'codeNotice'  => $result ? OtpService::resultMessage($result, $otp->secondsUntilResend($user->user_id)) : null,
            'resendIn'    => $otp->secondsUntilResend($user->user_id),
        ]);
    }

    public function resend(Request $request, CodeMailer $codes)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }
        return redirect()->route('verify-email')->with('code_result', $this->sendCode($user, $codes));
    }

    public function verify(Request $request, Mailer $mailer, BillingService $billing)
    {
        $user = $this->pendingUser($request);
        if (!$user instanceof User) {
            return $user;
        }

        $code = clean($request->input('otp'));
        if ($code === '') {
            return back()->with('error', 'Please enter the 6-digit code.');
        }

        $otp = OtpService::emailVerification();
        $result = $otp->verify($user->user_id, $code);
        if ($result !== 'ok') {
            return back()->with('error', match ($result) {
                'expired' => 'That code has expired or is no longer valid. Please request a new one.',
                'locked'  => 'Too many incorrect attempts. Please request a new code.',
                default   => 'Incorrect code. Please try again.',
            });
        }

        $user->forceFill(['status' => 'active'])->save();
        $otp->invalidate($user->user_id);
        log_activity($user->user_id, 'email_verified', 'Email verified, account activated');
        $request->session()->forget(['email_verify_user_id', 'email_verify_started_at']);

        $isApplicant = $user->role === 'applicant';
        $applicationRef = '';

        if ($isApplicant) {
            $application = DB::table('membership_applications')
                ->where('user_id', $user->user_id)->where('status', 'pending_verification')->first();

            if ($application) {
                DB::table('membership_applications')->where('application_id', $application->application_id)->update([
                    'status'            => 'pending_review',
                    'email_verified_at' => now(),
                    'submitted_at'      => now(),
                    'face_status'       => $application->face_file ? 'for_review' : 'not_submitted',
                ]);
                $applicationRef = $application->reference_code;
                log_activity($user->user_id, 'application_submitted', "Application $applicationRef submitted after email verification");

                $staffIds = DB::table('users')->where('role', 'admin')->where('status', 'active')->pluck('user_id');
                foreach ($staffIds as $staffId) {
                    $billing->createNotification(
                        (int)$staffId, null, 'New membership application',
                        "{$application->full_name} submitted a membership application ($applicationRef) for review."
                    );
                }

                $mailer->send($user->email, $user->full_name, "AWAS membership application received — $applicationRef",
                    'emails.application-received', ['name' => $user->full_name, 'reference' => $applicationRef]);
            }
        }

        return view('auth.verify-email', [
            'user' => $user, 'isApplicant' => $isApplicant, 'ttl' => $otp->ttlMinutes,
            'success' => true, 'applicationRef' => $applicationRef,
        ]);
    }

    /** The pending account being verified, or a redirect when there is none. */
    private function pendingUser(Request $request)
    {
        $userId = $request->session()->get('email_verify_user_id');
        $startedAt = (int)$request->session()->get('email_verify_started_at', 0);

        if (!$userId || (time() - $startedAt) > self::SESSION_TTL_SECONDS) {
            $request->session()->forget(['email_verify_user_id', 'email_verify_started_at']);
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (!$user) {
            $request->session()->forget(['email_verify_user_id', 'email_verify_started_at']);
            return redirect()->route('login');
        }

        // Already verified (e.g. a second tab left open after finishing).
        if ($user->status !== 'pending') {
            $request->session()->forget(['email_verify_user_id', 'email_verify_started_at']);
            return redirect()->route('login', ['registered' => 1]);
        }

        return $user;
    }

    /** @return string 'sent' | 'cooldown' | 'limit' | 'failed' */
    private function sendCode(User $user, CodeMailer $codes): string
    {
        $isApplicant = $user->role === 'applicant';
        return $codes->sendEmailVerification(
            $user->user_id, (string)$user->email, $user->full_name,
            $isApplicant
                ? 'Enter this code to verify your email address for your AWAS membership:'
                : 'Enter this code to verify your email address and activate your AWAS account:',
            $isApplicant
                ? 'Go back to the verification page you were on and type the 6-digit code. '
                    . 'Your application only goes to the barangay water office for review after your email is verified.'
                : null
        );
    }
}
