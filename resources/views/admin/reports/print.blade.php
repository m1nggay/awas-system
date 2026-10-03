<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>{{ $reportTypes[$reportType] }}{{ in_array($reportType, ['purok', 'consumption'], true) ? ' — ' . billingPeriodLabel($period) : '' }}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="{{ asset('assets/css/style.css') }}?v={{ @filemtime(public_path('assets/css/style.css')) ?: '1' }}">
<style>
  body{background:#fff;padding:24px;}
  .report-head{display:flex;align-items:center;gap:12px;border-bottom:2px solid var(--primary-dark);padding-bottom:10px;margin-bottom:16px;}
  .report-head img{width:46px;height:46px;object-fit:contain;}
  .card{box-shadow:none;border:1px solid var(--border);break-inside:avoid;margin-bottom:14px;}
  table{font-size:12px;}
  .actions-bar{margin-bottom:14px;}
  @media print { .actions-bar{display:none;} body{padding:0;} @page{size:A4 landscape;margin:12mm;} }
</style>
</head>
<body>
  <div class="actions-bar">
    <button class="btn btn-primary" onclick="window.print()">🖨️ Print / Save as PDF</button>
    <span class="text-muted ms-2" style="font-size:12px;">In the print window, choose <strong>Save as PDF</strong> to download.</span>
  </div>

  <div class="report-head">
    <img src="{{ asset('assets/img/logo.png') }}" alt="AWAS logo">
    <div>
      <div style="font-weight:700;font-size:18px;">AWAS — {{ $barangay }}</div>
      <div>{{ $reportTypes[$reportType] }}{{ in_array($reportType, ['purok', 'consumption'], true) ? ' — ' . billingPeriodLabel($period) : '' }}</div>
      <div class="text-muted" style="font-size:12px;">Generated {{ formatDateTime(now()->toDateTimeString()) }}</div>
    </div>
  </div>

  @if ($reportType === 'purok')
    @include('admin.reports._purok-tables')
  @else
    <table class="table table-bordered">
      <thead><tr>@foreach ($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
      <tbody>
      @forelse ($rows as $row)
        <tr>@foreach ($row as $val)<td>{{ $val }}</td>@endforeach</tr>
      @empty
        <tr><td colspan="{{ count($columns) }}" class="text-center text-muted">No records found for this report.</td></tr>
      @endforelse
      </tbody>
    </table>
  @endif

<script>window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });</script>
</body>
</html>
