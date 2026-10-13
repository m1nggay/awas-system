<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purok;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Announcements from the water office (e.g. a scheduled water interruption):
 * sent to every consumer's notification bell, or only to selected puroks,
 * and optionally by email.
 */
class AnnouncementController extends Controller
{
    /**
     * Ready-made announcements the administrator can pick and then adjust.
     * Parts in [brackets] are filled in before sending.
     */
    public const TEMPLATES = [
        'Water Service' => [
            'Scheduled water interruption' => [
                'title'   => 'Scheduled Water Interruption on [date]',
                'message' => 'Please be advised that there will be no water service on [date] from [start time] to [end time] due to [reason, e.g. pipeline maintenance]. Affected area: [puroks/streets]. Please store enough water ahead of time. Thank you for your understanding.',
            ],
            'Emergency water interruption' => [
                'title'   => 'Emergency Water Interruption',
                'message' => 'Water service is temporarily interrupted in [puroks/streets] due to [reason, e.g. a broken main pipe]. Our team is already working on it, and we expect service to return by [time/date]. We apologize for the inconvenience.',
            ],
            'Water service restored' => [
                'title'   => 'Water Service Restored',
                'message' => 'Water service in [puroks/streets] has been restored. If your water looks cloudy at first, let the tap run for a few minutes until it clears. Thank you for your patience.',
            ],
            'Low water pressure' => [
                'title'   => 'Low Water Pressure Advisory',
                'message' => 'Some households in [puroks/streets] may experience low water pressure on [date/period] because of [reason, e.g. high demand or repairs]. We are working to bring it back to normal. Thank you for your patience.',
            ],
            'Water quality advisory' => [
                'title'   => 'Water Quality Advisory',
                'message' => 'As a precaution, please boil your drinking water for at least one minute until further notice. This applies to [puroks/streets] while we [reason, e.g. clean the water tank]. We will announce once the water is safe to drink again.',
            ],
            'Save water reminder' => [
                'title'   => 'Please Save Water',
                'message' => 'Because of [reason, e.g. the dry season / low water supply], we ask everyone to use water wisely: fix leaking faucets, turn off taps when not in use, and avoid washing vehicles with running hoses. Thank you for helping keep water available for all.',
            ],
        ],
        'Billing & Payments' => [
            'Bills are ready' => [
                'title'   => 'Your [month] Water Bill Is Ready',
                'message' => 'Your water bill for [month] is now available in AWAS. You can view it under Current Bills and pay online with GCash or Maya, or at the water office. Please pay on or before the due date to avoid a penalty.',
            ],
            'Payment due reminder' => [
                'title'   => 'Payment Reminder: Due on [due date]',
                'message' => 'This is a reminder that water bills for [month] are due on [due date]. Bills not paid by then will get a late penalty. Pay online with GCash or Maya in AWAS, or at the water office. Please ignore this if you have already paid.',
            ],
            'Disconnection notice' => [
                'title'   => 'Notice of Disconnection for Unpaid Bills',
                'message' => 'Accounts with unpaid bills of [number] months or more may be disconnected starting [date]. Please settle your balance before then to keep your water service. Already paid? Please disregard this notice. For concerns, visit the water office.',
            ],
            'New water rates' => [
                'title'   => 'New Water Rates Starting [month]',
                'message' => 'Please be informed that new water rates will take effect starting with the [month] bill: [short summary of the new rates]. You can see how your bill is computed in Current Bills. For questions, please visit the water office.',
            ],
        ],
        'Office & Schedules' => [
            'Meter reading schedule' => [
                'title'   => 'Meter Reading Schedule for [month]',
                'message' => 'Our meter reader will visit [puroks/streets] on [date(s)] to read water meters. Please make sure your meter is easy to reach and not blocked. Thank you for your cooperation.',
            ],
            'Office closed' => [
                'title'   => 'Water Office Closed on [date]',
                'message' => 'The water office will be closed on [date] for [reason, e.g. a holiday]. Office hours resume on [date]. You can still view and pay your bills online in AWAS with GCash or Maya.',
            ],
            'Online payment available' => [
                'title'   => 'Pay Your Water Bill Online',
                'message' => 'You can now pay your water bill online through AWAS using GCash or Maya — no need to visit the office. Open Current Bills, tap Pay Bill, and choose your e-wallet. Your bill is marked Paid right after you pay.',
            ],
        ],
    ];

    public function index()
    {
        $puroks = Purok::orderBy('purok_name')->get();
        $announcements = DB::table('announcements as a')->leftJoin('users as u', 'u.user_id', '=', 'a.created_by')
            ->orderByDesc('a.created_at')->select('a.*', 'u.full_name as sender')->paginate(10);
        $purokNames = $puroks->pluck('purok_name', 'purok_id');

        return view('admin.announcements.index', [
            'puroks'        => $puroks,
            'announcements' => $announcements,
            'purokNames'    => $purokNames,
            'accountCount'  => $this->recipients([])->count(),
            'templates'     => self::TEMPLATES,
        ]);
    }

    public function store(Request $request, BillingService $billing)
    {
        $title = clean($request->input('title'));
        $message = clean($request->input('message'));
        $purokIds = collect($request->input('puroks', []))->map(fn ($id) => (int)$id)->filter()->unique()->values()->all();
        $toPuroks = $request->input('audience') === 'puroks';

        $error = match (true) {
            $title === '' || $message === '' => 'Please enter a title and a message.',
            mb_strlen($title) > 150 => 'The title is too long (150 characters at most).',
            mb_strlen($message) > 500 => 'The message is too long (500 characters at most).',
            $toPuroks && !$purokIds => 'Please choose at least one purok.',
            default => null,
        };
        if ($error) {
            flash('danger', $error);
            return back()->withInput();
        }

        $userIds = $this->recipients($toPuroks ? $purokIds : [])->all();
        if (!$userIds) {
            flash('warning', 'No consumer accounts to send to — none of these consumers have signed up online yet.');
            return back()->withInput();
        }

        $email = $request->boolean('email');
        DB::transaction(function () use ($userIds, $title, $message, $toPuroks, $purokIds, $email, $request) {
            foreach (array_chunk($userIds, 500) as $chunk) {
                DB::table('notifications')->insert(array_map(fn ($id) => [
                    'user_id' => $id, 'bill_id' => null, 'title' => $title, 'message' => $message, 'type' => 'announcement',
                ], $chunk));
            }
            DB::table('announcements')->insert([
                'title' => $title, 'message' => $message, 'purok_ids' => $toPuroks ? implode(',', $purokIds) : null,
                'recipient_count' => count($userIds), 'emailed' => $email, 'created_by' => $request->user()->user_id,
            ]);
        });
        if ($email) {
            foreach ($userIds as $id) {
                $billing->emailNotification((int)$id, $title, $message);
            }
        }

        log_activity($request->user()->user_id, 'announcement_send', "Sent announcement \"$title\" to " . count($userIds) . ' consumer account(s)'
            . ($email ? ' (also by email)' : ''));
        flash('success', 'Announcement sent to ' . count($userIds) . ' consumer account(s)' . ($email ? ', and emailed.' : '.'));
        return redirect()->route('admin.announcements.index');
    }

    /** Active consumer logins, optionally only those in the given puroks. */
    private function recipients(array $purokIds)
    {
        return DB::table('users as u')->join('consumers as c', 'c.user_id', '=', 'u.user_id')
            ->where('u.role', 'resident')->where('u.status', 'active')
            ->when($purokIds, fn ($q) => $q->whereIn('c.purok_id', $purokIds))
            ->distinct()->pluck('u.user_id');
    }
}
