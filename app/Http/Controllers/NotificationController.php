<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Opening the notification bell marks the signed-in user's notifications as read. */
class NotificationController extends Controller
{
    public function markRead(Request $request)
    {
        DB::table('notifications')->where('user_id', $request->user()->user_id)->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['unread' => 0]);
    }
}
