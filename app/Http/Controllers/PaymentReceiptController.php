<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Payment receipt (view / download as PDF) — administrators: any payment; consumers: only their own. */
class PaymentReceiptController extends Controller
{
    public function __invoke(Request $request, int $payment)
    {
        $row = DB::table('payments as pay')
            ->join('water_bills as b', 'b.bill_id', '=', 'pay.bill_id')
            ->join('consumers as c', 'c.consumer_id', '=', 'pay.consumer_id')
            ->join('puroks as p', 'p.purok_id', '=', 'c.purok_id')
            ->leftJoin('meter_readings as mr', 'mr.reading_id', '=', 'b.reading_id')
            ->where('pay.payment_id', $payment)
            ->first(['pay.*', 'b.billing_period', 'b.total_amount', 'b.amount_paid as bill_amount_paid', 'b.status as bill_status',
                'b.consumption', 'b.due_date', 'c.full_name', 'c.meter_number', 'c.user_id', 'p.purok_name',
                'mr.previous_reading', 'mr.current_reading']);

        abort_if(!$row, 404, 'Payment not found.');

        $user = $request->user();
        if (!$user->isAdmin() && (int)$row->user_id !== (int)$user->user_id) {
            abort(403, 'You are not authorized to view this receipt.');
        }

        return view('payments.receipt', [
            'p'            => $row,
            'barangayName' => setting('barangay_name', 'Barangay Adlay'),
            'balance'      => max(0, round((float)$row->total_amount - (float)$row->bill_amount_paid, 2)),
            'autoDownload' => $request->boolean('download'),
            'backUrl'      => $user->isAdmin() ? route('admin.payments.index') : route('resident.payment-history'),
        ]);
    }
}
