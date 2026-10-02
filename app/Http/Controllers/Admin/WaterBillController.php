<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaterBillController extends Controller
{
    private const STATUSES = ['unpaid', 'paid', 'overdue', 'partially_paid'];

    public function index(Request $request, BillingService $billing)
    {
        $billing->refreshOverdueBills();

        $search = clean($request->query('q'));
        $statusFil = clean($request->query('status'));
        $periodFil = clean($request->query('period'));

        $bills = DB::table('water_bills as b')
            ->join('consumers as c', 'c.consumer_id', '=', 'b.consumer_id')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('c.full_name', like_operator(), "%$search%")
                ->orWhere('c.meter_number', like_operator(), "%$search%")))
            ->when(in_array($statusFil, self::STATUSES, true), fn ($q) => $q->where('b.status', $statusFil))
            ->when($periodFil !== '', fn ($q) => $q->where('b.billing_period', $periodFil))
            ->orderByDesc('b.bill_date')->orderByDesc('b.bill_id')
            ->select('b.*', 'c.full_name', 'c.meter_number')
            ->paginate(15)->withQueryString();

        $periods = DB::table('water_bills')->distinct()->orderByDesc('billing_period')->pluck('billing_period');

        // Bills with an online GCash payment waiting for verification.
        $pendingBillIds = DB::table('payments')->where('status', 'pending')
            ->whereIn('bill_id', collect($bills->items())->pluck('bill_id'))->pluck('bill_id')->flip();

        return view('admin.bills.index', compact('bills', 'periods', 'search', 'statusFil', 'periodFil', 'pendingBillIds'));
    }
}
