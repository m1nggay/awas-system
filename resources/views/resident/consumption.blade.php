@extends('layouts.app')

@section('title', 'My Consumption')

@section('content')
<div class="card mb-3">
  <div class="card-body">
    <h3 class="h6 mb-3">Consumption History</h3>
    <canvas id="consumptionChart" height="240"></canvas>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Meter Reading Records</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr><th>Period</th><th>Previous</th><th>Current</th><th>Consumption</th><th>Reading Date</th></tr></thead>
        <tbody>
        @forelse ($readings as $r)
          <tr>
            <td>{{ billingPeriodLabel($r->billing_period) }}</td>
            <td>{{ number_format($r->previous_reading, 2) }}</td>
            <td>{{ number_format($r->current_reading, 2) }}</td>
            <td class="consumption-value">{{ number_format($r->consumption, 2) }} m³</td>
            <td>{{ formatDate($r->reading_date) }}</td>
          </tr>
        @empty
          <tr class="empty-row"><td colspan="5">No meter readings recorded yet.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endsection

@push('scripts')
@php $chartData = $readings->reverse()->values(); @endphp
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById("consumptionChart"), {
  type: "line",
  data: {
    labels: {{ Js::from($chartData->map(fn ($r) => billingPeriodLabel($r->billing_period))) }},
    datasets: [{ label: "Consumption (m³)", data: {{ Js::from($chartData->map(fn ($r) => (float)$r->consumption)) }}, borderColor: "#0077b6", backgroundColor: "rgba(0,180,216,0.15)", fill: true, tension: 0.35 }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
</script>
@endpush
