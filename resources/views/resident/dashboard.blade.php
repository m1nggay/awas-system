@extends('layouts.app')

@section('title', __('My Dashboard'))

@section('content')
@php $a = $analytics; @endphp

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">{{ __('Latest Consumption') }}</div><div class="value">{{ $latestReading ? number_format($latestReading->consumption, 2) . ' m³' : '—' }}</div></div>
      <div class="stat-icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card {{ $currentBill && $currentBill->status === 'overdue' ? 'danger' : 'warning' }}">
      <div class="stat-info"><div class="label">{{ __('Unpaid Balance') }}</div><div class="value">{{ formatCurrency($totalBalance) }}</div></div>
      <div class="stat-icon"><i class="ph peso-icon" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">{{ __('Due Date') }}</div><div class="value fs-6">{{ $currentBill ? formatDate($currentBill->due_date) : __('No pending bill') }}</div></div>
      <div class="stat-icon"><i class="ph ph-calendar-blank" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card success">
      <div class="stat-info"><div class="label">{{ __('Payment Status') }}</div><div class="value fs-6">
        @if ($currentBill)
          @if ($currentPending)
            <span class="badge badge-warning">{{ __('Pending Verification') }}</span>
          @else
            <span class="badge {{ billStatusBadgeClass($currentBill->status) }}">{{ str_replace('_', ' ', $currentBill->status) }}</span>
          @endif
        @else
          <span class="badge badge-success">{{ __('All Paid') }}</span>
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
        <h3 class="h6 mb-1">{{ __('My Water Usage — Last 12 Months') }}</h3>
        <p class="chart-note">{{ __('The darker bar is your highest month; the dashed line is your average. A sudden jump may mean a leak.') }}</p>
        @if ($a['usage']->isNotEmpty())
          <div class="chart-box"><canvas id="myTrendChart" role="img" aria-label="{{ __('Bar chart of your water usage per month for the last 12 months') }}"></canvas></div>
        @else
          <div class="text-center text-muted py-5">{{ __('No meter readings yet.') }}</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100 mb-0">
      <div class="card-header"><h3>{{ __('Current Bill') }}</h3></div>
      <div class="card-body">
        @if (!$currentBill)
          <p class="text-muted">{{ __('You have no outstanding bills.') }}</p>
        @else
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Meter Number') }}</span><strong>{{ $consumer->meter_number }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Billing Period') }}</span><strong>{{ billingPeriodLabel($currentBill->billing_period) }}</strong></div>
          <div class="d-flex justify-content-between mb-2"><span class="text-muted">{{ __('Total Amount') }}</span><strong>{{ formatCurrency($currentBill->total_amount) }}</strong></div>
          <div class="d-flex justify-content-between mb-3"><span class="text-muted">{{ __('Balance') }}</span><strong class="text-danger">{{ formatCurrency($currentBill->total_amount - $currentBill->amount_paid) }}</strong></div>
          <a href="{{ route('resident.bill') }}" class="btn btn-primary w-100">{{ __('View & Pay Current Bills') }}</a>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ===== My Usage & Payments (this household only) ===== --}}
<h2 class="section-title">{{ __('My Usage & Payments') }}</h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">{{ __('Usage:value', ['value' => $a['latest'] ? ' — ' . billingPeriodLabel($a['latest']->period) : '']) }}</div>
        <div class="value">{{ $a['latest'] ? number_format($a['latest']->usage, 1) . ' m³' : '—' }}</div>
        @include('partials.change-note', ['pct' => $a['change'], 'vs' => $a['previous'] ? localizeMonths(date('F', strtotime($a['previous']->period . '-01'))) : ''])
      </div>
      <div class="stat-icon"><i class="ph ph-drop" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">{{ __('My Peak Month') }}</div>
        <div class="value">{{ $a['peak'] ? billingPeriodLabel($a['peak']->period) : '—' }}</div>
        <div class="change-note">{{ $a['peak'] ? __(':value m³ — my highest in 12 months', ['value' => number_format($a['peak']->usage, 1)]) : __('No readings yet') }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-trend-up" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">{{ __('My Monthly Average') }}</div>
        <div class="value">{{ $a['average'] !== null ? number_format($a['average'], 1) . ' m³' : '—' }}</div>
        <div class="change-note">{{ __('Over the last :value :value2', ['value' => $a['usage']->count(), 'value2' => __(Str::plural('month', $a['usage']->count()))]) }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-chart-bar" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100 success">
      <div class="stat-info">
        <div class="label">{{ __('Paid in :value', ['value' => date('Y')]) }}</div>
        <div class="value">{{ formatCurrency($a['paidThisYear']) }}</div>
        <div class="change-note">{{ __('Total of my confirmed payments') }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-wallet" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-5">
    <div class="card">
      <div class="card-body">
        <h3 class="h6 mb-1">{{ __('My Bills vs Payments') }}</h3>
        <p class="chart-note">{{ __('Each month\'s bill and how much of it is paid.') }}</p>
        @if ($a['bills']->isNotEmpty())
          <div class="chart-box"><canvas id="myBillsChart" role="img" aria-label="{{ __('Bar chart comparing your bill and amount paid per month') }}"></canvas></div>
        @else
          <div class="text-center text-muted py-5">{{ __('No bills yet.') }}</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('My Payment Record') }}</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>{{ __('Month') }}</th><th class="text-end">{{ __('Bill') }}</th><th class="text-end">{{ __('Paid') }}</th><th>{{ __('Paid on') }}</th><th>{{ __('Status') }}</th></tr></thead>
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
                <td><span class="timing {{ $tClass }}"><i class="ph {{ $tIcon }}" aria-hidden="true"></i> {{ __($tLabel) }}</span></td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="5">{{ __('No bills yet.') }}</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>{{ __('Recent Payments') }}</h3><a href="{{ route('resident.payment-history') }}" class="btn btn-outline btn-sm">{{ __('View All') }}</a></div>
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
  const short = p => new Date(p + '-01T00:00:00').toLocaleDateString(document.documentElement.lang === 'tl' ? 'fil-PH' : 'en-PH', { month: 'short', year: '2-digit' });

  const usage = {{ Js::from($a['usage']) }};
  if (document.getElementById('myTrendChart')) {
    A.usage(document.getElementById('myTrendChart'), usage.map(r => short(r.period)), usage.map(r => r.usage),
      { average: {{ Js::from($a['average'] !== null ? round($a['average'], 2) : null) }} });
  }

  const bills = {{ Js::from($a['bills']) }};
  if (document.getElementById('myBillsChart')) {
    A.pair(document.getElementById('myBillsChart'), bills.map(b => short(b.period)), bills.map(b => b.billed), bills.map(b => b.paid),
      [{{ Js::from(__('Bill')) }}, {{ Js::from(__('Paid')) }}], { footer: i => bills[i].paid >= bills[i].billed - 0.009 ? {{ Js::from(__('Fully paid')) }} : {{ Js::from(__('Balance:')) }} + ' ' + A.peso(bills[i].billed - bills[i].paid) });
  }
})();
</script>
@endpush
