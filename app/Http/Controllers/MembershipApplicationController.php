<?php

namespace App\Http\Controllers;

use App\Models\Consumer;
use App\Models\MembershipApplication;
use App\Models\Purok;
use App\Services\ApplicationFiles;
use App\Services\BillingService;
use App\Services\CodeMailer;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Public membership application for someone who does NOT yet have a water
 * account: personal/household details, Type of Consumer, a valid-ID photo,
 * and a live face check (the person must blink — a still photo can't pass).
 *
 * Flow: apply -> email verification -> admin review -> approval -> meter
 * assignment -> active. Until activation the login has role 'applicant'
 * and can only open the application status page.
 */
class MembershipApplicationController extends Controller
{
    private const FIELDS = [
        'full_name', 'birth_date', 'sex', 'contact_number', 'email', 'username', 'purok_id',
        'household_number', 'household_members', 'residence_type', 'consumer_type', 'id_type',
    ];

    /** The water system only serves this barangay, so these parts of the address are fixed. */
    public const BARANGAY = 'Adlay';
    public const MUNICIPALITY = 'Carrascal';
    public const PROVINCE = 'Surigao del Sur';

    public function create()
    {
        return view('apply', [
            'puroks' => Purok::orderBy('purok_name')->get(),
            'barangay' => self::BARANGAY,
            'municipality' => self::MUNICIPALITY,
            'province' => self::PROVINCE,
        ]);
    }

    public function store(Request $request, ApplicationFiles $files, BillingService $billing, CodeMailer $codes)
    {
        $old = [];
        foreach (self::FIELDS as $field) {
            $old[$field] = clean($request->input($field));
        }
        $password = (string)$request->input('password', '');
        $confirmPassword = (string)$request->input('confirm_password', '');
        $errors = [];

        // --- Personal information --------------------------------------------
        foreach (['full_name', 'birth_date', 'sex', 'contact_number', 'email', 'username', 'purok_id',
                  'household_members', 'residence_type', 'consumer_type', 'id_type'] as $field) {
            if ($old[$field] === '') {
                $errors[] = 'Please fill in all required fields.';
                break;
            }
        }
        if (mb_strlen($old['full_name']) > 150) {
            $errors[] = 'The full name is too long. Please shorten it.';
        }
        if ($old['birth_date'] !== '') {
            $dob = DateTime::createFromFormat('!Y-m-d', $old['birth_date']);
            $age = ($dob && $dob->format('Y-m-d') === $old['birth_date']) ? ageFromBirthDate($old['birth_date']) : null;
            if ($age === null || $dob > new DateTime('today') || $age > 120) {
                $errors[] = 'Please enter a valid date of birth.';
            } elseif ($age < 18) {
                $errors[] = 'Applicants must be at least 18 years old.';
            }
        }
        if ($old['sex'] !== '' && !in_array($old['sex'], ['male', 'female'], true)) {
            $errors[] = 'Please select a valid sex.';
        }
        if ($old['contact_number'] !== '' && !preg_match('/^[0-9+()\-\s]{7,20}$/', $old['contact_number'])) {
            $errors[] = 'Please enter a valid contact number.';
        }
        if ($old['email'] !== '' && (!filter_var($old['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($old['email']) > 150)) {
            $errors[] = 'Please enter a valid email address — we send a verification code to it.';
        }
        $purokName = $old['purok_id'] !== '' ? Purok::whereKey((int)$old['purok_id'])->value('purok_name') : null;
        if ($old['purok_id'] !== '' && $purokName === null) {
            $errors[] = 'Please select a valid purok.';
        }

        // --- Household information --------------------------------------------
        if ($old['household_members'] !== '' && (!ctype_digit($old['household_members']) || (int)$old['household_members'] < 1 || (int)$old['household_members'] > 50)) {
            $errors[] = 'Number of household members must be a whole number from 1 to 50.';
        }
        if ($old['household_number'] !== '' && !preg_match('/^[A-Za-z0-9\-\/ ]{1,30}$/', $old['household_number'])) {
            $errors[] = 'Household number may only contain letters, numbers, spaces, "-" and "/".';
        }
        if ($old['residence_type'] !== '' && !isset(MembershipApplication::RESIDENCE_TYPES[$old['residence_type']])) {
            $errors[] = 'Please select a valid type of residence.';
        }
        if ($old['consumer_type'] !== '' && !isset(Consumer::TYPES[$old['consumer_type']])) {
            $errors[] = 'Please select a valid Type of Consumer.';
        }
        if ($old['id_type'] !== '' && !in_array($old['id_type'], MembershipApplication::ID_TYPES, true)) {
            $errors[] = 'Please select the type of ID you are submitting.';
        }

        // --- Face verification (blink / liveness check done in the browser) ----
        $livenessPassed = $request->input('liveness_passed') === '1' && (string)$request->input('selfie_capture', '') !== '';
        $verifyInPerson = $request->boolean('verify_in_person');
        if (!$livenessPassed && !$verifyInPerson) {
            $errors[] = 'Please complete the face verification (position your face, then blink when asked), '
                . 'or tick "I will verify my face in person" if you cannot use a camera.';
        }

        // --- Account information ----------------------------------------------
        if ($old['username'] !== '' && !preg_match('/^[A-Za-z0-9._-]{4,30}$/', $old['username'])) {
            $errors[] = 'Username must be 4–30 characters: letters, numbers, dots (.), dashes (-) or underscores (_), with no spaces.';
        }
        if ($policy = passwordPolicyError($password)) {
            $errors[] = $policy;
        } elseif ($password !== $confirmPassword) {
            $errors[] = 'Passwords do not match.';
        }

        // --- Existing accounts / abuse limits -----------------------------------
        $staleUserIds = [];
        if (!$errors) {
            foreach (DB::table('users')->where('email', $old['email'])->get(['user_id', 'role', 'status']) as $existing) {
                // An earlier attempt with this email that never verified is safe
                // to replace; anything else means the email is already in use.
                if ($existing->role === 'applicant' && $existing->status === 'pending') {
                    $staleUserIds[] = (int)$existing->user_id;
                } else {
                    $errors[] = 'That email address is already registered. Please log in instead, or use a different email.';
                    break;
                }
            }
        }
        if (!$errors && DB::table('users')->whereRaw('LOWER(username) = ?', [mb_strtolower($old['username'])])
                ->whereNotIn('user_id', $staleUserIds ?: [0])->exists()) {
            $errors[] = 'That username is already taken. Please choose another.';
        }
        if (!$errors) {
            $recent = DB::table('membership_applications')->where('ip_address', $request->ip())
                ->where('created_at', '>', now()->subHour())->count();
            if ($recent >= MembershipApplication::MAX_PER_IP_PER_HOUR) {
                $errors[] = 'Too many applications were submitted from your connection recently. Please try again later.';
            }
        }

        // --- Valid ID (front and back, landscape) + the portrait frame captured
        // by the passed blink check (only stored once everything else is valid)
        $idFile = $idBackFile = $faceFile = null;
        if (!$errors) {
            $idFile = $files->store($request->file('id_file'), (string)$request->input('id_capture', ''),
                'front of your valid ID', true, $errors, 'landscape');
            $idBackFile = $files->store($request->file('id_back_file'), (string)$request->input('id_back_capture', ''),
                'back of your valid ID', true, $errors, 'landscape');
            if ($livenessPassed) {
                $faceFile = $files->store(null, (string)$request->input('selfie_capture', ''), 'face verification', true, $errors, 'portrait');
            }
            if ($errors) {
                $files->delete($idFile);
                $files->delete($idBackFile);
                $files->delete($faceFile);
            }
        }

        if ($errors) {
            return back()->withErrors($errors)->withInput($old)->with('filesDiscarded', true);
        }

        try {
            [$newUserId, $referenceCode] = DB::transaction(function () use ($staleUserIds, $old, $password, $idFile, $idBackFile, $faceFile, $livenessPassed, $purokName, $request, $files, $billing) {
                foreach ($staleUserIds as $staleId) {
                    foreach (DB::table('membership_applications')->where('user_id', $staleId)->get(['id_file', 'id_back_file', 'face_file']) as $f) {
                        $files->delete($f->id_file);
                        $files->delete($f->id_back_file);
                        $files->delete($f->face_file);
                    }
                    DB::table('membership_applications')->where('user_id', $staleId)->delete();
                    DB::table('users')->where('user_id', $staleId)->delete();
                }

                $userId = DB::table('users')->insertGetId([
                    'username'       => $old['username'],
                    'password_hash'  => Hash::make($password),
                    'full_name'      => $old['full_name'],
                    'email'          => $old['email'],
                    'contact_number' => $old['contact_number'],
                    'role'           => 'applicant',
                    'status'         => 'pending',
                ], 'user_id');

                $ref = $billing->generateApplicationReference();
                DB::table('membership_applications')->insert([
                    'reference_code' => $ref, 'user_id' => $userId, 'full_name' => $old['full_name'],
                    'birth_date' => $old['birth_date'], 'sex' => $old['sex'],
                    'address' => $purokName . ', ' . self::BARANGAY . ', ' . self::MUNICIPALITY . ', ' . self::PROVINCE,
                    'purok_id' => (int)$old['purok_id'], 'barangay' => self::BARANGAY,
                    'municipality' => self::MUNICIPALITY, 'province' => self::PROVINCE,
                    'contact_number' => $old['contact_number'], 'email' => $old['email'],
                    'household_number' => $old['household_number'] ?: null,
                    'household_members' => (int)$old['household_members'],
                    'residence_type' => $old['residence_type'], 'consumer_type' => $old['consumer_type'],
                    'id_type' => $old['id_type'], 'id_file' => $idFile, 'id_back_file' => $idBackFile, 'id_status' => 'submitted',
                    'face_file' => $faceFile, 'face_status' => $faceFile ? 'submitted' : 'not_submitted',
                    'liveness_status' => $livenessPassed ? 'passed' : 'not_performed',
                    'status' => 'pending_verification', 'ip_address' => $request->ip(),
                ]);
                return [$userId, $ref];
            });
        } catch (\Throwable $e) {
            $files->delete($idFile);
            $files->delete($idBackFile);
            $files->delete($faceFile);
            Log::error('Membership application error: ' . $e->getMessage());
            return back()->withErrors(['A system error occurred while submitting your application. Please try again.'])
                ->withInput($old)->with('filesDiscarded', true);
        }

        log_activity($newUserId, 'membership_application', "New membership application $referenceCode started (pending email verification)");

        $result = $codes->sendEmailVerification($newUserId, $old['email'], $old['full_name'],
            'Enter this code to verify your email address for your AGAS / AWAS membership:',
            'Go back to the verification page you were on and type the 6-digit code. '
                . 'Your application only goes to the barangay water office for review after your email is verified.');

        $request->session()->put('email_verify_user_id', $newUserId);
        $request->session()->put('email_verify_started_at', time());

        return redirect()->route('verify-email')->with('code_result', $result);
    }
}
