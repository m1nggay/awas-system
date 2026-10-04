@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
  $statCards = [
      ['Total Consumers', number_format($totalConsumers), '👤', ''],
      ['Consumption (' . billingPeriodLabel($currentPeriod) . ')', number_format($totalConsumption, 1) . ' m³', '💧', ''],
      ["This Month's Bills", number_format($currentMonthBills), '🧾', ''],
      ['Paid Bills', number_format($billsByStatus['paid']), '✅', 'success'],
      ['Unpaid Bills', number_format($billsByStatus['unpaid'] + $billsByStatus['partially_paid']), '⏳', 'warning'],
      ['Overdue Bills', number_format($billsByStatus['overdue']), '⚠️', 'danger'],
      ['Total Verified Payments', formatCurrency($totalPayments), '💳', 'success'],
  ];
@endphp
<div class="row g-3 mb-4">
  @foreach ($statCards as $i => [$label, $value, $icon, $variant])
    {{-- 7 cards: the last one spans the full row on phones and tablets so nothing is left alone --}}
    <div class="{{ $loop->last ? 'col-12 col-xl-3' : 'col-6 col-md-4 col-xl-3' }}">
      <div class="stat-card h-100 {{ $variant }}">
        <div class="stat-info"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
        <div class="stat-icon">{{ $icon }}</div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Consumption Trend (Last 6 Periods)</h3>
        <canvas id="trendChart" height="220"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Bill Status — {{ billingPeriodLabel($currentPeriod) }}</h3>
        @if (array_sum($billsByStatus) > 0)
          <canvas id="statusChart" height="220"></canvas>
        @else
          <div class="text-center text-muted py-5" style="font-size:13.5px;">🧾 No bills yet for {{ billingPeriodLabel($currentPeriod) }}.<br>They appear here once the meter readers record this month's readings.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h3>Recent Transactions</h3>
    <a href="{{ route('admin.payments.index') }}" class="btn btn-outline btn-sm">View All</a>
  </div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Reference</th><th>Meter Number</th><th>Name</th><th>Period</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th></tr></thead>
        <tbody>
        @forelse ($recentPayments as $p)
          <tr>
            <td>{{ $p->payment_reference }}</td>
            <td><strong>{{ $p->meter_number }}</strong></td>
            <td>{{ $p->full_name }}</td>
            <td>{{ billingPeriodLabel($p->billing_period) }}</td>
            <td>{{ formatCurrency($p->amount_paid) }}</td>
            <td>{{ paymentMethodLabel($p->payment_method) }}</td>
            <td><span class="badge {{ paymentStatusBadgeClass($p->status) }}">{{ paymentStatusLabel($p->status) }}</span></td>
            <td>{{ formatDateTime($p->payment_date) }}</td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="8">No transactions yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById("trendChart"), {
  type: "line",
  data: {
    labels: {{ Js::from($trend->map(fn ($r) => billingPeriodLabel($r->billing_period))) }},
    datasets: [{ label: "Consumption (m³)", data: {{ Js::from($trend->map(fn ($r) => (float)$r->total_consumption)) }}, borderColor: "#0077b6", backgroundColor: "rgba(0,180,216,0.15)", fill: true, tension: 0.35 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
if (document.getElementById("statusChart")) new Chart(document.getElementById("statusChart"), {
  type: "doughnut",
  data: {
    labels: ["Paid", "Unpaid", "Partially Paid", "Overdue"],
    datasets: [{ data: {{ Js::from([$billsByStatus['paid'], $billsByStatus['unpaid'], $billsByStatus['partially_paid'], $billsByStatus['overdue']]) }},
      backgroundColor: ["#2a9d8f", "#e9a23b", "#3a86ff", "#e5533d"] }]
  },
  options: { plugins: { legend: { position: "bottom" } } }
});
</script>
@endpush
