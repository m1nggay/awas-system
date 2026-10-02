@extends('layouts.app')

@section('title', 'My Dashboard')

@section('content')

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">Latest Consumption</div><div class="value">{{ $latestReading ? number_format($latestReading->consumption, 2) . ' m³' : '—' }}</div></div>
      <div class="stat-icon">💧</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card {{ $currentBill && $currentBill->status === 'overdue' ? 'danger' : 'warning' }}">
      <div class="stat-info"><div class="label">Unpaid Balance</div><div class="value">{{ formatCurrency($totalBalance) }}</div></div>
      <div class="stat-icon">🧾</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-card">
      <div class="stat-info"><div class="label">Due Date</div><div class="value fs-6">{{ $currentBill ? formatDate($currentBill->due_date) : 'No pending bill' }}</div></div>
      <div class="stat-icon">📅</div>
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
      <div class="stat-icon">✅</div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">My Consumption Trend</h3>
        <canvas id="myTrendChart" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100 mb-0">
      <div class="card-header"><h3>Current Bill</h3></div>
      <div class="card-body">
        @if (!$currentBill)
          <p class="text-muted">You have no outstanding bills. 🎉</p>
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

<div class="card">
  <div class="card-header"><h3>Recent Payments</h3><a href="{{ route('resident.payment-history') }}" class="btn btn-outline btn-sm">View All</a></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      @include('resident._payments-table', ['payments' => $recentPayments, 'emptyText' => 'No payments yet.'])
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById("myTrendChart"), {
  type: "bar",
  data: {
    labels: {{ Js::from($trend->map(fn ($r) => billingPeriodLabel($r->billing_period))) }},
    datasets: [{ label: "Consumption (m³)", data: {{ Js::from($trend->map(fn ($r) => (float)$r->consumption)) }}, backgroundColor: "#00b4d8" }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush
