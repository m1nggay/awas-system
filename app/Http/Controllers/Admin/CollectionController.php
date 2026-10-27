<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Analytics;
use Illuminate\Http\Request;

/**
 * Collection report for today / this week / this month (from the dashboard's
 * Collection Monitoring): every paid payment received in that period, with
 * the total collected and a subtotal per method — printable or as CSV.
 */
class CollectionController extends Controller
{
    private function range(Request $request): string
    {
        $range = clean($request->query('range'));
        return array_key_exists($range, Analytics::COLLECTION_RANGES) ? $range : 'day';
    }

    public function print(Request $request, Analytics $analytics)
    {
        $range = $this->range($request);
        $report = $analytics->collectionRecords($range);
        $label = Analytics::collectionRangeLabel($range, $report['from'], $report['to']);
        log_activity($request->user()->user_id, 'report_view', "Viewed collection report — $label");

        return view('admin.collections.print', $report + [
            'range'    => $range,
            'title'    => 'Collection Report — ' . Analytics::COLLECTION_RANGES[$range]['current'],
            'label'    => $label,
            'barangay' => setting('barangay_name', 'Barangay Adlay'),
        ]);
    }

    public function export(Request $request, Analytics $analytics)
    {
        $range = $this->range($request);
        $report = $analytics->collectionRecords($range);
        $label = Analytics::collectionRangeLabel($range, $report['from'], $report['to']);
        log_activity($request->user()->user_id, 'report_export', "Downloaded collection report (CSV) — $label");

        $filename = 'awas_collection_' . $range . '_' . $report['from']->format('Ymd') . '.csv';
        return response()->streamDownload(function () use ($report, $label) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows ₱ and ñ correctly
            fputcsv($out, ["Collection Report — $label"]);
            fputcsv($out, []);
            fputcsv($out, ['Reference', 'Meter Number', 'Name', 'Bill Period', 'Amount', 'Method', 'Paid At', 'GCash Ref. No.', 'Received By', 'Date']);
            foreach ($report['rows'] as $r) {
                fputcsv($out, [
                    $r->payment_reference, $r->meter_number, $r->full_name, billingPeriodLabel($r->billing_period),
                    number_format($r->amount_paid, 2, '.', ''), paymentMethodLabel($r->payment_method, $r->wallet),
                    paymentChannelLabel($r->channel), $r->payment_method === 'gcash' ? $r->payment_gateway_txn_id : '',
                    $r->received_by ?? ($r->channel === 'online' ? 'Online (automatic)' : '—'), $r->payment_date,
                ]);
            }
            fputcsv($out, []);
            foreach ($report['byMethod'] as $method => $m) {
                fputcsv($out, ['', '', '', "Subtotal — $method ({$m['count']})", number_format($m['amount'], 2, '.', '')]);
            }
            fputcsv($out, ['', '', '', "TOTAL COLLECTION ({$report['count']} payments)", number_format($report['total'], 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
