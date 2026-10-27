@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')
@php $a = $analytics; @endphp

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">Latest Consumption</div><div class="value">{{ $latestReading ? number_format($latestReading->consumption, 2) . ' m³' : '—' }}</div></div>
      <div class="stat-icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card {{ $currentBill && $currentBill->status === 'overdue' ? 'danger' : 'warning' }}">
      <div class="stat-info"><div class="label">Unpaid Balance</div><div class="value">{{ formatCurrency($totalBalance) }}</div></div>
      <div class="stat-icon"><i class="ph ph-invoice" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">Due Date</div><div class="value fs-6">{{ $currentBill ? formatDate($currentBill->due_date) : 'No pending bill' }}</div></div>
      <div class="stat-icon"><i class="ph ph-calendar-blank" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card success">
      <div class="stat-info"><div class="label">Payment Status</div><div class="value fs-6">
        @if ($currentBill)
          @if ($currentPending)
            <span class="badge badge-warning">Pending Verification</span>
          @else
            <span class="badge {{ billStatusBadgeClass($currentBill->status) }}">{{ str_replace('_', ' ', $currentBill->status) }}</span>
          @endif
        @else
          <span class="badge badge-success">All Paid</span>
        @endif
      </div></div>
      <div class="stat-icon"><i class="ph ph-check-circle" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-1">My Water Usage — Last 12 Months</h3>
        <p class="chart-note">The darker bar is your highest month; the dashed line is your average. A sudden jump may mean a leak.</p>
        @if ($a['usage']->isNotEmpty())
          <div class="chart-box"><canvas id="myTrendChart" role="img" aria-label="Bar chart of your water usage per month for the last 12 months"></canvas></div>
        @else
          <div class="text-center text-muted py-5">No meter readings yet.</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100 mb-0">
      <div class="card-header"><h3>Current Bill</h3></div>
      <div class="card-body">
        @if (!$currentBill)
          <p class="text-muted">You have no outstanding bills.</p>
        @else
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Meter Number</span><strong>{{ $consumer->meter_number }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Billing Period</span><strong>{{ billingPeriodLabel($currentBill->billing_period) }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">Total Amount</span><strong>{{ formatCurrency($currentBill->total_amount) }}</strong></div>
          <div class="d-flex justify-content-between mb-3"><span class="text-muted">Balance</span><strong class="text-danger">{{ formatCurrency($currentBill->total_amount - $currentBill->amount_paid) }}</strong></div>
          <a href="{{ route('resident.bill') }}" class="btn btn-primary w-100">View &amp; Pay Current Bills</a>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ===== My Usage & Payments (this household only) ===== --}}
<h2 class="section-title">My Usage &amp; Payments</h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">Usage{{ $a['latest'] ? ' — ' . billingPeriodLabel($a['latest']->period) : '' }}</div>
        <div class="value">{{ $a['latest'] ? number_format($a['latest']->usage, 1) . ' m³' : '—' }}</div>
        @include('partials.change-note', ['pct' => $a['change'], 'vs' => $a['previous'] ? date('F', strtotime($a['previous']->period . '-01')) : ''])
      </div>
      <div class="stat-icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">My Peak Month</div>
        <div class="value">{{ $a['peak'] ? billingPeriodLabel($a['peak']->period) : '—' }}</div>
        <div class="change-note">{{ $a['peak'] ? number_format($a['peak']->usage, 1) . ' m³ — my highest in 12 months' : 'No readings yet' }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-trend-up" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">My Monthly Average</div>
        <div class="value">{{ $a['average'] !== null ? number_format($a['average'], 1) . ' m³' : '—' }}</div>
        <div class="change-note">Over the last {{ $a['usage']->count() }} {{ Str::plural('month', $a['usage']->count()) }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-chart-bar" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100 success">
      <div class="stat-info">
        <div class="label">Paid in {{ date('Y') }}</div>
        <div class="value">{{ formatCurrency($a['paidThisYear']) }}</div>
        <div class="change-note">Total of my confirmed payments</div>
      </div>
      <div class="stat-icon"><i class="ph ph-wallet" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-1">My Bills vs Payments</h3>
        <p class="chart-note">Each month's bill and how much of it is paid.</p>
        @if ($a['bills']->isNotEmpty())
          <div class="chart-box"><canvas id="myBillsChart" role="img" aria-label="Bar chart comparing your bill and amount paid per month"></canvas></div>
        @else
          <div class="text-center text-muted py-5">No bills yet.</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h3>My Payment Record</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Month</th><th class="text-end">Bill</th><th class="text-end">Paid</th><th>Paid on</th><th>Status</th></tr></thead>
            <tbody>
            @php
              $timing = [
                  'on_time' => ['On time', 'ph-check-circle', 'text-success'],
                  'late'    => ['Paid late', 'ph-clock-counter-clockwise', 'text-warning'],
                  'pending' => ['Pending verification', 'ph-hourglass', 'text-muted'],
                  'partial' => ['Partly paid', 'ph-circle-half', 'text-warning'],
                  'unpaid'  => ['Unpaid', 'ph-circle', 'text-muted'],
                  'overdue' => ['Overdue', 'ph-warning', 'text-danger'],
              ];
            @endphp
            @forelse ($a['record'] as $r)
              @php [$tLabel, $tIcon, $tClass] = $timing[$r->timing]; @endphp
              <tr>
                <td>{{ billingPeriodLabel($r->period) }}</td>
                <td class="text-end">{{ formatCurrency($r->billed) }}</td>
                <td class="text-end">{{ formatCurrency($r->paid) }}</td>
                <td>{{ $r->date ? formatDate($r->date) : '—' }}@if ($r->method)<div class="text-muted small">{{ $r->method }}</div>@endif</td>
                <td><span class="timing {{ $tClass }}"><i class="ph {{ $tIcon }}" aria-hidden="true"></i> {{ $tLabel }}</span></td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="5">No bills yet.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Recent Payments</h3><a href="{{ route('resident.payment-history') }}" class="btn btn-outline btn-sm">View All</a></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      @include('resident._payments-table', ['payments' => $recentPayments, 'emptyText' => 'No payments yet.', 'receiptPopup' => true])
    </div>
  </div>
</div>
@include('resident._receipt-modal')
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/dashboard-charts.js') }}?v={{ @filemtime(public_path('assets/js/dashboard-charts.js')) ?: '1' }}"></script>
<script>
(function () {
  const A = AwasCharts;
  const short = p => new Date(p + '-01T00:00:00').toLocaleDateString('en-PH', { month: 'short', year: '2-digit' });

  const usage = {{ Js::from($a['usage']) }};
  if (document.getElementById('myTrendChart')) {
    A.usage(document.getElementById('myTrendChart'), usage.map(r => short(r.period)), usage.map(r => r.usage),
      { average: {{ Js::from($a['average'] !== null ? round($a['average'], 2) : null) }} });
  }

  const bills = {{ Js::from($a['bills']) }};
  if (document.getElementById('myBillsChart')) {
    A.pair(document.getElementById('myBillsChart'), bills.map(b => short(b.period)), bills.map(b => b.billed), bills.map(b => b.paid),
      ['Bill', 'Paid'], { footer: i => bills[i].paid >= bills[i].billed - 0.009 ? 'Fully paid' : 'Balance: ' + A.peso(bills[i].billed - bills[i].paid) });
  }
})();
</script>
@endpush
