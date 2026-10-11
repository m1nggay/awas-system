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
