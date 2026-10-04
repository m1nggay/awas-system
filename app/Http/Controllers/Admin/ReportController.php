<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Purok;
use App\Services\BillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Reports. The default is the Billing Report grouped by Purok. Downloads:
 * CSV (opens in Excel) and a print-ready page for "Save as PDF" — using the
 * browser's own PDF printing, so no extra PDF library is needed.
 */
class ReportController extends Controller
{
    public function index(Request $request, BillingService $billing)
    {
        [$types, $type, $period, $purokId] = $this->params($request);
        $allowed = $request->user()->assignedPurokIds();

        $data = ['columns' => [], 'rows' => []];
        $groups = [];
        if ($type === 'purok') {
            $groups = $billing->getPurokReport($period, $purokId, $allowed);
        } else {
            $data = $billing->getReportData($type, $period, $allowed);
        }

        log_activity($request->user()->user_id, 'report_view', 'Viewed report: ' . $types[$type]
            . (in_array($type, ['purok', 'consumption'], true) ? ' — ' . billingPeriodLabel($period) : ''));

        return view($request->boolean('print') ? 'admin.reports.print' : 'admin.reports.index', [
            'reportTypes' => $types,
            'reportType'  => $type,
            'period'      => $period,
            'periods'     => $this->periods(),
            'puroks'      => Purok::when($allowed !== null, fn ($q) => $q->whereIn('purok_id', $allowed ?: [0]))->orderBy('purok_name')->get(),
            'purokId'     => $purokId,
            'groups'      => $groups,
            'columns'     => $data['columns'],
            'rows'        => $data['rows'],
            'barangay'    => setting('barangay_name', 'Barangay Adlay'),
        ]);
    }

    public function export(Request $request, BillingService $billing)
    {
        [$types, $type, $period, $purokId] = $this->params($request);
        $allowed = $request->user()->assignedPurokIds();

        log_activity($request->user()->user_id, 'report_export', 'Downloaded report (CSV): ' . $types[$type]
            . (in_array($type, ['purok', 'consumption'], true) ? ' — ' . billingPeriodLabel($period) : ''));

        $filename = 'agas_report_' . $type . ($type === 'purok' || $type === 'consumption' ? '_' . $period : '') . '_' . date('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($billing, $type, $period, $purokId, $types, $allowed) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 marker so Excel shows ₱ and ñ correctly

            if ($type === 'purok') {
                fputcsv($out, [$types['purok'] . ' — ' . billingPeriodLabel($period)]);
                foreach ($billing->getPurokReport($period, $purokId, $allowed) as $group) {
                    fputcsv($out, []);
                    fputcsv($out, [mb_strtoupper($group['purok'])]);
                    fputcsv($out, ['No.', 'Meter Number', 'Name', 'Present Reading', 'Previous Reading', 'Consumption (m3)', 'Total', 'Balance', 'Status', 'Date Paid']);
                    foreach ($group['rows'] as $i => $r) {
                        fputcsv($out, [
                            $i + 1, $r->meter_number, $r->full_name,
                            $r->present !== null ? number_format($r->present, 2, '.', '') : '',
                            $r->previous !== null ? number_format($r->previous, 2, '.', '') : '',
                            $r->consumption !== null ? number_format($r->consumption, 2, '.', '') : '',
                            $r->total !== null ? number_format($r->total, 2, '.', '') : '',
                            number_format($r->balance, 2, '.', ''),
                            $r->status === 'disconnected' ? 'Disconnected (unpaid before disconnection)' : ucwords(str_replace('_', ' ', $r->status)),
                            $r->date_paid ?? 'N/A',
                        ]);
                    }
                }
            } else {
                $data = $billing->getReportData($type, $period, $allowed);
                fputcsv($out, $data['columns']);
                foreach ($data['rows'] as $row) {
                    fputcsv($out, $row);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    /** @return array{0: array, 1: string, 2: string, 3: int} [allowed types, type, period, purok id] */
    private function params(Request $request): array
    {
        $types = BillingService::REPORT_TYPES;
        if ($request->user()->isMeterReader()) {
            $types = array_intersect_key($types, array_flip(BillingService::METER_READER_REPORTS));
        }

        $type = clean($request->query('type')) ?: 'purok';
        abort_unless(isset($types[$type]), 403, 'This report is not available to your account.');

        $period = clean($request->query('period'));
        if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
            $period = $this->periods()->first() ?? currentBillingPeriod();
        }

        return [$types, $type, $period, (int)$request->query('purok', 0)];
    }

    /** Billing periods that have readings or bills, newest first. */
    private function periods()
    {
        return DB::table('meter_readings')->select('billing_period')
            ->union(DB::table('water_bills')->select('billing_period'))
            ->get()->pluck('billing_period')->unique()->sortDesc()->values();
    }
}
