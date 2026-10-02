<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MembershipApplication;
use App\Services\ApplicationFiles;
use App\Services\BillingService;
use App\Services\Mailer;
use DateTime;
use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Membership application queue and review:
 *   pending_review --approve--> approved --assign meter--> active
 *   pending_review --reject (reason required)--> rejected
 * Face verification is a manual decision here — there is no biometric matching.
 */
class MembershipReviewController extends Controller
{
    public const STATUS_OPTIONS = [
        'pending_review'       => 'Pending review',
        'approved'             => 'Approved (awaiting meter)',
        'active'               => 'Active',
        'rejected'             => 'Rejected',
        'pending_verification' => 'Awaiting email verification',
        ''                     => 'All applications',
    ];

    public function index(Request $request)
    {
        $search = clean($request->query('q'));
        $statusFil = $request->has('status') ? clean($request->query('status')) : 'pending_review';

        $applications = DB::table('membership_applications as a')
            ->join('puroks as p', 'p.purok_id', '=', 'a.purok_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('a.full_name', like_operator(), "%$search%")
                ->orWhere('a.email', like_operator(), "%$search%")
                ->orWhere('a.reference_code', like_operator(), "%$search%")))
            ->when($statusFil !== '' && isset(self::STATUS_OPTIONS[$statusFil]), fn ($q) => $q->where('a.status', $statusFil))
            ->orderByDesc('a.created_at')
            ->select('a.*', 'p.purok_name')
            ->paginate(12)->withQueryString();

        return view('admin.applications.index', [
            'applications'  => $applications,
            'statusOptions' => self::STATUS_OPTIONS,
            'search'        => $search,
            'statusFil'     => $statusFil,
        ]);
    }

    public function show(MembershipApplication $application)
    {
        $application->purok_name = DB::table('puroks')->where('purok_id', $application->purok_id)->value('purok_name');

        return view('admin.applications.show', ['app' => $application]);
    }

    public function verification(Request $request, MembershipApplication $application)
    {
        if (!in_array($application->status, ['pending_review', 'approved'], true)) {
            return $this->unavailable($application);
        }

        $idStatus = $request->input('id_status');
        $faceStatus = $request->input('face_status');
        if (in_array($idStatus, ['submitted', 'verified', 'failed'], true) && in_array($faceStatus, ['for_review', 'verified', 'failed'], true)) {
            $application->update(['id_status' => $idStatus, 'face_status' => $faceStatus]);
            log_activity($request->user()->user_id, 'application_verification', "Set ID=$idStatus, face=$faceStatus on {$application->reference_code}");
            flash('success', 'Verification statuses updated.');
        } else {
            flash('danger', 'Invalid verification status.');
        }
        return redirect()->route('admin.applications.show', $application);
    }

    public function approve(Request $request, MembershipApplication $application)
    {
        if ($application->status !== 'pending_review') {
            return $this->unavailable($application);
        }

        MembershipApplication::whereKey($application->application_id)->where('status', 'pending_review')->update([
            'status' => 'approved', 'reviewed_by' => $request->user()->user_id, 'reviewed_at' => now(), 'review_notes' => null,
        ]);
        log_activity($request->user()->user_id, 'application_approve', "Approved application {$application->reference_code} — awaiting meter assignment");
        flash('success', 'Application approved. Now assign a water meter to activate the account.');
        return redirect()->route('admin.applications.show', $application);
    }

    public function reject(Request $request, MembershipApplication $application, Mailer $mailer)
    {
        if ($application->status !== 'pending_review') {
            return $this->unavailable($application);
        }

        $reason = clean($request->input('rejection_reason'));
        if ($reason === '') {
            flash('danger', 'Please provide the reason for rejecting this application.');
            return redirect()->route('admin.applications.show', $application);
        }
        $reason = mb_substr($reason, 0, 500);

        MembershipApplication::whereKey($application->application_id)->where('status', 'pending_review')->update([
            'status' => 'rejected', 'reviewed_by' => $request->user()->user_id, 'reviewed_at' => now(), 'review_notes' => $reason,
        ]);
        log_activity($request->user()->user_id, 'application_reject', "Rejected application {$application->reference_code}");

        if (!empty($application->email)) {
            $mailer->send($application->email, $application->full_name, 'AWAS Membership Application Update', 'emails.application-rejected', [
                'name' => $application->full_name, 'reference' => $application->reference_code, 'reason' => $reason,
            ]);
        }
        flash('success', 'Application rejected and the applicant was notified by email.');
        return redirect()->route('admin.applications.show', $application);
    }

    /** Creates the consumer account, assigns the meter and turns the applicant into a resident. */
    public function assignMeter(Request $request, MembershipApplication $application, BillingService $billing, Mailer $mailer)
    {
        if ($application->status !== 'approved') {
            return $this->unavailable($application);
        }

        $meter = [];
        foreach (['meter_number', 'installation_date', 'initial_reading', 'meter_status', 'household_number'] as $field) {
            $meter[$field] = clean($request->input($field));
        }

        $errors = [];
        if (!isValidMeterNumber($meter['meter_number'])) {
            $errors[] = 'Meter Number must contain numbers only (e.g. 1001) — no letters, spaces, or "ADL-" prefix.';
        }
        $inst = DateTime::createFromFormat('!Y-m-d', $meter['installation_date']);
        if (!$inst || $inst->format('Y-m-d') !== $meter['installation_date']) {
            $errors[] = 'Enter a valid installation date.';
        }
        if ($meter['initial_reading'] === '' || !is_numeric($meter['initial_reading']) || (float)$meter['initial_reading'] < 0 || (float)$meter['initial_reading'] > 99999999) {
            $errors[] = 'Enter a valid initial meter reading (0 or more).';
        }
        if (!in_array($meter['meter_status'], ['active', 'inactive', 'maintenance'], true)) {
            $errors[] = 'Select a valid meter status.';
        }
        if ($meter['household_number'] !== '' && !preg_match('/^[A-Za-z0-9\-\/ ]{1,30}$/', $meter['household_number'])) {
            $errors[] = 'Household number may only contain letters, numbers, spaces, "-" and "/".';
        }
        if ($errors) {
            return back()->withErrors($errors, 'meter')->withInput();
        }

        try {
            DB::transaction(function () use ($request, $application, $meter) {
                // Re-read under a lock: another admin may have handled it meanwhile.
                $locked = MembershipApplication::whereKey($application->application_id)->lockForUpdate()->first();
                if (!$locked || $locked->status !== 'approved') {
                    throw new \RuntimeException('not-approved');
                }
                if (DB::table('consumers')->where('meter_number', $meter['meter_number'])->exists()) {
                    throw new \RuntimeException('duplicate-meter');
                }

                $consumerId = DB::table('consumers')->insertGetId([
                    'user_id' => $locked->user_id, 'full_name' => $locked->full_name,
                    'address' => $locked->address, 'purok_id' => $locked->purok_id, 'contact_number' => $locked->contact_number,
                    'email' => $locked->email, 'meter_number' => $meter['meter_number'],
                    'consumer_type' => $locked->consumer_type ?: 'residential',
                    'meter_status' => $meter['meter_status'], 'initial_meter_reading' => (float)$meter['initial_reading'],
                    'household_number' => $meter['household_number'] ?: null, 'connection_date' => $meter['installation_date'],
                    'status' => 'active', 'created_by' => $request->user()->user_id,
                ], 'consumer_id');

                $locked->update([
                    'status' => 'active', 'consumer_id' => $consumerId, 'household_number' => $meter['household_number'] ?: null,
                ]);

                if ($locked->user_id) {
                    DB::table('users')->where('user_id', $locked->user_id)->where('role', 'applicant')
                        ->update(['role' => 'resident', 'status' => 'active']);
                }
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'duplicate-meter') {
                return back()->withErrors(['That meter number is already assigned to another consumer. Meter numbers must be unique.'], 'meter')->withInput();
            }
            if ($e->getMessage() === 'not-approved') {
                flash('danger', 'This application is no longer waiting for a meter assignment.');
                return redirect()->route('admin.applications.show', $application);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Meter assignment error: ' . $e->getMessage());
            return back()->withErrors(['A system error occurred while activating the account. Please try again.'], 'meter')->withInput();
        }

        log_activity($request->user()->user_id, 'application_activate', "Activated {$application->reference_code} — meter {$meter['meter_number']}");
        if (!empty($application->user_id)) {
            $billing->createNotification((int)$application->user_id, null, 'Membership approved', "Your AWAS account is active. Meter Number: {$meter['meter_number']}.");
        }
        if (!empty($application->email)) {
            $mailer->send($application->email, $application->full_name, 'AWAS Membership Application Approved', 'emails.application-approved', [
                'name' => $application->full_name, 'reference' => $application->reference_code,
                'meterNumber' => $meter['meter_number'], 'loginUrl' => route('login'),
            ]);
        }
        flash('success', "Meter assigned and account activated. Meter Number: {$meter['meter_number']}");
        return redirect()->route('admin.applications.show', $application);
    }

    /** The only way to see an applicant's ID or selfie; every view is audit-logged. */
    public function file(Request $request, MembershipApplication $application, string $type, ApplicationFiles $files)
    {
        $path = $files->path($type === 'id' ? $application->id_file : $application->face_file);
        abort_if(!$path || !is_file($path), 404, 'File not found.');

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(isset(ApplicationFiles::ALLOWED_TYPES[$mime]), 404, 'File not found.');

        log_activity($request->user()->user_id, 'application_file_view', "Viewed $type image of application {$application->reference_code}");

        return response()->file($path, [
            'Content-Type'           => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition'    => 'inline; filename="' . $type . '-' . $application->reference_code . '.' . ApplicationFiles::ALLOWED_TYPES[$mime] . '"',
            'Cache-Control'          => 'private, no-store, max-age=0',
            'Pragma'                 => 'no-cache',
        ]);
    }

    private function unavailable(MembershipApplication $application)
    {
        flash('danger', 'That action is not available for this application in its current status.');
        return redirect()->route('admin.applications.show', $application);
    }
}
