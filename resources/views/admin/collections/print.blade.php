<!DOCTYPE html>
<html lang="en">
<head>
<link rel="stylesheet" href="https://unpkg.com/@phosphor-icons/web@2.1.1/src/regular/style.css">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }} — {{ $label }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<style>
  body{background:#fff;padding:24px;}
  .report-head{display:flex;align-items:center;gap:12px;border-bottom:2px solid var(--primary-dark);padding-bottom:10px;margin-bottom:16px;}
  .report-head img{width:46px;height:46px;object-fit:contain;}
  table{font-size:12px;}
  .actions-bar{margin-bottom:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:center;}
  .totals td{font-weight:600;}
  .signatures{display:flex;gap:48px;margin-top:40px;font-size:12px;}
  .signatures div{flex:1;border-top:1px solid #333;padding-top:4px;text-align:center;}
  @media print { .actions-bar{display:none;} body{padding:0;} @page{size:A4 landscape;margin:12mm;} }
</style>
</head>
<body>
  <div class="actions-bar">
    <button class="btn btn-primary" onclick="window.print()"><i class="ph ph-printer" aria-hidden="true"></i> Print / Save as PDF</button>
    <a class="btn btn-outline-secondary" href="{{ route('admin.collections.export', ['range' => $range]) }}"><i class="ph ph-file-csv" aria-hidden="true"></i> Download CSV (Excel)</a>
    <span class="text-muted" style="font-size:12px;">In the print window, choose <strong>Save as PDF</strong> to download.</span>
  </div>

  <div class="report-head">
    <img src="{{ asset('assets/img/logo-round.png') }}" alt="AWAS logo">
    <div>
      <div style="font-weight:700;font-size:18px;">AWAS — {{ $barangay }}</div>
      <div>{{ $title }} — {{ $label }}</div>
      <div class="text-muted" style="font-size:12px;">Paid payments only, by the date the money was received · Generated {{ formatDateTime(now()->toDateTimeString()) }}</div>
    </div>
  </div>

  <table class="table table-bordered">
    <thead><tr><th>#</th><th>Reference</th><th>Meter Number</th><th>Name</th><th>Bill Period</th><th class="text-end">Amount</th><th>Method</th><th>Paid At</th><th>GCash Ref. No.</th><th>Received By</th><th>Date</th></tr></thead>
    <tbody>
    @forelse ($rows as $i => $r)
      <tr>
        <td>{{ $i + 1 }}</td>
        <td>{{ $r->payment_reference }}</td>
        <td>{{ $r->meter_number }}</td>
        <td>{{ $r->full_name }}</td>
        <td>{{ billingPeriodLabel($r->billing_period) }}</td>
        <td class="text-end">{{ formatCurrency($r->amount_paid) }}</td>
        <td>{{ paymentMethodLabel($r->payment_method, $r->wallet) }}</td>
        <td>{{ paymentChannelLabel($r->channel) }}</td>
        <td>{{ $r->payment_method === 'gcash' ? $r->payment_gateway_txn_id : '' }}</td>
        <td>{{ $r->received_by ?? ($r->channel === 'online' ? 'Online (automatic)' : '—') }}</td>
        <td>{{ formatDateTime($r->payment_date) }}</td>
      </tr>
    @empty
      <tr><td colspan="11" class="text-center text-muted">No payments were collected in this period.</td></tr>
    @endforelse
    </tbody>
    <tfoot class="totals">
      @foreach ($byMethod as $method => $m)
        <tr><td colspan="5" class="text-end">Subtotal — {{ $method }} ({{ $m['count'] }})</td><td class="text-end">{{ formatCurrency($m['amount']) }}</td><td colspan="5"></td></tr>
      @endforeach
      <tr><td colspan="5" class="text-end">TOTAL COLLECTION ({{ $count }} {{ Str::plural('payment', $count) }})</td><td class="text-end">{{ formatCurrency($total) }}</td><td colspan="5"></td></tr>
    </tfoot>
  </table>

  <div class="signatures">
    <div>Prepared by</div>
    <div>Received / Checked by (Treasurer)</div>
  </div>

<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
