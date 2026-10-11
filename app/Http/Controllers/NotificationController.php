<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The notification bell and the full list of the signed-in user's notifications. */
class NotificationController extends Controller
{
    /** Every notification, newest first; opening the page marks them as read. */
    public function index(Request $request)
    {
        $user = $request->user();
        $notifications = DB::table('notifications')->where('user_id', $user->user_id)
            ->orderByDesc('created_at')->orderByDesc('notification_id')
            ->paginate(20)->appends($request->query());
        $notifications->getCollection()->transform(function ($n) use ($user) {
            $n->link = $this->linkFor($n, $user);
            return $n;
        });

        $this->markAllRead($user->user_id);
        return view('notifications.index', ['items' => $notifications]);
    }

    /** Opening the notification bell marks the signed-in user's notifications as read. */
    public function markRead(Request $request)
    {
        $this->markAllRead($request->user()->user_id);
        return response()->json(['unread' => 0]);
    }

    private function markAllRead(int $userId): void
    {
        DB::table('notifications')->where('user_id', $userId)->where('is_read', false)->update(['is_read' => true]);
    }

    /** Where "View" takes the user: the page about that bill or payment. */
    private function linkFor(object $n, $user): ?array
    {
        if ($user->role === 'resident' && $n->bill_id) {
            return $n->type === 'payment_received'
                ? [route('resident.payment-history'), 'View Payments']
                : [route('resident.bill'), 'View Bill'];
        }
        if ($user->isAdmin() && $n->bill_id) {
            return str_contains($n->title, 'GCash')
                ? [route('admin.payments.index', ['tab' => 'pending']), 'View Payment']
                : [route('admin.bills.index'), 'View Bills'];
        }
        return null;
    }
}
