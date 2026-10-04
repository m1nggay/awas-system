<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Automatic checking (polling) for the administrator: the open page asks
 * about every 20 seconds for meter readings and notifications newer than
 * the ones it already has, so new readings show up without refreshing.
 */
class LiveUpdateController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $sinceReading = max(0, (int)$request->query('reading', 0));
        $sinceNotif = max(0, (int)$request->query('notif', 0));

        $readings = DB::table('meter_readings as mr')
            ->join('consumers as c', 'c.consumer_id', '=', 'mr.consumer_id')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->leftJoin('water_bills as b', 'b.reading_id', '=', 'mr.reading_id')
            ->leftJoin('users as u', 'u.user_id', '=', 'mr.recorded_by')
            ->where('mr.reading_id', '>', $sinceReading)
            ->orderBy('mr.reading_id')->limit(50)
            ->get(['mr.*', 'c.full_name', 'c.meter_number', 'p.purok_name', 'u.full_name as reader_name',
                'b.total_amount as bill_total', 'b.amount_paid as bill_paid', 'b.status as bill_status']);

        $notifications = DB::table('notifications')->where('user_id', $user->user_id)
            ->where('notification_id', '>', $sinceNotif)->orderBy('notification_id')->limit(20)
            ->get(['notification_id', 'title', 'message', 'created_at']);

        return response()->json([
            'reading'  => (int)($readings->max('reading_id') ?? $sinceReading),
            'notif'    => (int)($notifications->max('notification_id') ?? $sinceNotif),
            'unread'   => DB::table('notifications')->where('user_id', $user->user_id)->where('is_read', false)->count(),
            'readings' => $readings->map(fn ($r) => [
                'id'    => (int)$r->reading_id,
                'text'  => "Meter {$r->meter_number} — {$r->full_name} ({$r->purok_name}): "
                    . number_format((float)$r->consumption, 2) . ' m³'
                    . ($r->bill_total !== null ? ', bill ' . formatCurrency($r->bill_total) : '')
                    . ($r->reader_name ? " · by {$r->reader_name}" : ''),
                'row'   => view('admin.readings._row', [
                    'r' => $r, 'isAdmin' => true, 'no' => '<span class="badge badge-info">New</span>',
                ])->render(),
            ])->values(),
            'notifications' => $notifications->map(fn ($n) => [
                'title' => $n->title, 'message' => $n->message, 'time' => formatDateTime($n->created_at),
            ])->values(),
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
