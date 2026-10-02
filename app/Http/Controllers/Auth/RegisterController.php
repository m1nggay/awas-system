<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Consumer;
use App\Models\Purok;
use App\Services\CodeMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Links an EXISTING consumer account (matched by Meter Number + name +
 * purok) to a new online login. Someone without a water account yet uses
 * the membership application (MembershipApplicationController) instead.
 */
class RegisterController extends Controller
{
    public function show()
    {
        return view('auth.register', ['puroks' => Purok::orderBy('purok_id')->get()]);
    }

    public function store(Request $request, CodeMailer $codes)
    {
        $old = [];
        foreach (['meter_number', 'full_name', 'purok_id', 'consumer_type', 'email', 'contact_number', 'username'] as $field) {
            $old[$field] = clean($request->input($field));
        }
        $password = (string)$request->input('password', '');
        $confirmPassword = (string)$request->input('confirm_password', '');

        $errors = [];
        if ($old['meter_number'] === '' || $old['full_name'] === '' || $old['purok_id'] === '' || $old['email'] === '' || $old['username'] === '' || $password === '') {
            $errors[] = 'Please fill in all required fields.';
        }
        if ($old['meter_number'] !== '' && !isValidMeterNumber($old['meter_number'])) {
            $errors[] = 'Meter Number must contain numbers only (e.g. 1001) — no letters or "ADL-" prefix.';
        }
        if (!isset(Consumer::TYPES[$old['consumer_type']])) {
            $errors[] = 'Please select your Type of Consumer.';
        }
        if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address — it is used to verify your account.';
        }
        if ($old['purok_id'] !== '' && !Purok::whereKey((int)$old['purok_id'])->exists()) {
            $errors[] = 'Please select a valid purok.';
        }
        if ($password !== '' && ($policy = passwordPolicyError($password))) {
            $errors[] = $policy;
        }
        if ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        // The consumer account must exist and not be linked to a login yet.
        $consumer = null;
        if (!$errors) {
            $consumer = DB::table('consumers')->where('meter_number', $old['meter_number'])->first();
            if (!$consumer) {
                $errors[] = 'No consumer account found with that Meter Number. Please contact the barangay water office.';
            } elseif (strcasecmp(trim($consumer->full_name), trim($old['full_name'])) !== 0) {
                $errors[] = 'The full name does not match our records for this Meter Number.';
            } elseif ((int)$consumer->purok_id !== (int)$old['purok_id']) {
                $errors[] = 'The purok does not match our records for this Meter Number.';
            } elseif (!empty($consumer->user_id)) {
                // A stale, never-verified signup shouldn't permanently block
                // this meter number from being registered again.
                $linkedStatus = DB::table('users')->where('user_id', $consumer->user_id)->value('status');
                if ($linkedStatus === 'pending') {
                    DB::table('users')->where('user_id', $consumer->user_id)->delete();
                } else {
                    $errors[] = 'This consumer account is already linked to a login. Please sign in instead.';
                }
            }
        }

        if (!$errors && DB::table('users')->where('username', $old['username'])->exists()) {
            $errors[] = 'That username is already taken. Please choose another.';
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput($old);
        }

        try {
            $newUserId = DB::transaction(function () use ($old, $password, $consumer) {
                $id = DB::table('users')->insertGetId([
                    'username'       => $old['username'],
                    'password_hash'  => Hash::make($password),
                    'full_name'      => $old['full_name'],
                    'email'          => $old['email'],
                    'contact_number' => $old['contact_number'] ?: null,
                    'role'           => 'resident',
                    'status'         => 'pending',
                ], 'user_id');
                DB::table('consumers')->where('consumer_id', $consumer->consumer_id)
                    ->update(['user_id' => $id, 'consumer_type' => $old['consumer_type']]);
                return $id;
            });
        } catch (\Throwable $e) {
            Log::error('Registration error: ' . $e->getMessage());
            return back()->withErrors(['A system error occurred while creating your account. Please try again.'])->withInput($old);
        }

        log_activity($newUserId, 'register', "Consumer self-registered (pending email verification) for meter {$old['meter_number']}");

        $result = $codes->sendEmailVerification($newUserId, $old['email'], $old['full_name'],
            'Thanks for creating an AGAS account. Enter this code to verify your email and activate your account:');

        $request->session()->put('email_verify_user_id', $newUserId);
        $request->session()->put('email_verify_started_at', time());

        return redirect()->route('verify-email')->with('code_result', $result);
    }
}
