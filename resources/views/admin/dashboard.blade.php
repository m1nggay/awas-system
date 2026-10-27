@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
@php
  $a = $analytics;
  $statCards = [
      ['Total Consumers', number_format($totalConsumers), 'ph-users-three', ''],
      ['Consumption (' . billingPeriodLabel($currentPeriod) . ')', number_format($totalConsumption, 1) . ' m³', 'ph-drop', ''],
      ["This Month's Bills", number_format($currentMonthBills), 'ph-invoice', ''],
      ['Paid Bills', number_format($billsByStatus['paid']), 'ph-check-circle', 'success'],
      ['Unpaid Bills', number_format($billsByStatus['unpaid'] + $billsByStatus['partially_paid']), 'ph-hourglass', 'warning'],
      ['Overdue Bills', number_format($billsByStatus['overdue']), 'ph-warning', 'danger'],
      ['Total Verified Payments', formatCurrency($totalPayments), 'ph-wallet', 'success'],
  ];
@endphp
<div class="row g-3 mb-4">
  @foreach ($statCards as $i => [$label, $value, $icon, $variant])
    {{-- 7 cards: the last one spans the full row on phones and tablets so nothing is left alone --}}
    <div class="{{ $loop->last ? 'col-12 col-xl-3' : 'col-6 col-md-4 col-xl-3' }}">
      <div class="stat-card h-100 {{ $variant }}">
        <div class="stat-info"><div class="label">{{ $label }}</div><div class="value">{{ $value }}</div></div>
        <div class="stat-icon"><i class="ph {{ $icon }}" aria-hidden="true"></i></div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-1">Consumption Trend — Last 12 Months</h3>
        <p class="chart-note">Total m³ used by all households each month.
          @if ($a['peak']) Peak: <strong>{{ billingPeriodLabel($a['peak']->period) }}</strong> ({{ number_format($a['peak']->total, 1) }} m³). @endif
        </p>
        <div class="chart-box"><canvas id="trendChart" role="img" aria-label="Bar chart of total water consumption per month for the last 12 months"></canvas></div>
      </div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-3">Bill Status — {{ billingPeriodLabel($currentPeriod) }}</h3>
        @if (array_sum($billsByStatus) > 0)
          <div class="chart-box"><canvas id="statusChart"></canvas></div>
        @else
          <div class="text-center text-muted py-5" style="font-size:13.5px;"><i class="ph ph-invoice" aria-hidden="true"></i> No bills yet for {{ billingPeriodLabel($currentPeriod) }}.<br>They appear here once the meter readers record this month's readings.</div>
        @endif
      </div>
    </div>
  </div>
</div>

{{-- ===== Payment & Consumption Analytics ===== --}}
<h2 class="section-title">Payment &amp; Consumption Analytics</h2>
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100 success">
      <div class="stat-info">
        <div class="label">Collected — {{ $a['thisMonthLabel'] }}</div>
        <div class="value">{{ formatCurrency($a['collectedThisMonth']) }}</div>
        @include('partials.change-note', ['pct' => \App\Services\Analytics::change($a['collectedThisMonth'], $a['collectedLastMonth']), 'vs' => $a['lastMonthLabel']])
      </div>
      <div class="stat-icon"><i class="ph ph-hand-coins" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">Collection Rate{{ $a['ratePeriod'] ? ' — ' . billingPeriodLabel($a['ratePeriod']) . ' bills' : '' }}</div>
        <div class="value">{{ $a['rate'] !== null ? rtrim(rtrim(number_format($a['rate'], 1), '0'), '.') . '%' : '—' }}</div>
        @include('partials.change-note', ['pct' => $a['rate'] !== null && $a['ratePrev'] !== null ? round($a['rate'] - $a['ratePrev'], 1) : null,
          'vs' => $a['ratePrevPeriod'] ? date('F', strtotime($a['ratePrevPeriod'] . '-01')) : '', 'unit' => ' pts'])
      </div>
      <div class="stat-icon"><i class="ph ph-percent" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">Peak Consumption Month</div>
        <div class="value">{{ $a['peak'] ? billingPeriodLabel($a['peak']->period) : '—' }}</div>
        <div class="change-note">{{ $a['peak'] ? number_format($a['peak']->total, 1) . ' m³ — highest in the last 12 months' : 'No readings yet' }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-trend-up" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="stat-card h-100">
      <div class="stat-info">
        <div class="label">Average per Household{{ $a['latestPeriod'] ? ' — ' . billingPeriodLabel($a['latestPeriod']) : '' }}</div>
        <div class="value">{{ $a['avgPerHousehold'] !== null ? number_format($a['avgPerHousehold'], 1) . ' m³' : '—' }}</div>
        @include('partials.change-note', ['pct' => \App\Services\Analytics::change($a['avgPerHousehold'], $a['avgPerHouseholdPrev']),
          'vs' => $a['previousPeriod'] ? date('F', strtotime($a['previousPeriod'] . '-01')) : ''])
      </div>
      <div class="stat-icon"><i class="ph ph-house-line" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-1">Billed vs Collected — Last 12 Months</h3>
        <p class="chart-note">Total of each month's bills, and how much of it has been paid. Hover a month for its collection rate.</p>
        @if ($a['billing']->isNotEmpty())
          <div class="chart-box"><canvas id="collectionChart" role="img" aria-label="Bar chart comparing amount billed and amount collected per month"></canvas></div>
        @else
          <div class="text-center text-muted py-5">No bills yet.</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-1">How Consumers Pay</h3>
        <p class="chart-note">Number of paid payments by method.</p>
        @if ($a['methods']->isNotEmpty())
          <div class="chart-box chart-box-sm"><canvas id="methodChart" role="img" aria-label="Doughnut chart of payments by method"></canvas></div>
          @php $chTotal = max(1, $a['channels']->sum()); @endphp
          <div class="channel-split">
            <div><span>At the barangay</span><strong>{{ round(($a['channels']['counter'] ?? 0) / $chTotal * 100) }}%</strong></div>
            <div><span>Online</span><strong>{{ round(($a['channels']['online'] ?? 0) / $chTotal * 100) }}%</strong></div>
          </div>
        @else
          <div class="text-center text-muted py-5">No paid payments yet.</div>
        @endif
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-body">
        <h3 class="h6 mb-1">Consumption by Purok{{ $a['latestPeriod'] ? ' — ' . billingPeriodLabel($a['latestPeriod']) : '' }}</h3>
        <p class="chart-note">Which purok used the most water.</p>
        @if ($a['byPurok']->isNotEmpty())
          <div class="chart-box" style="height:{{ max(200, $a['byPurok']->count() * 34 + 40) }}px;"><canvas id="purokChart" role="img" aria-label="Bar chart of water consumption by purok"></canvas></div>
        @else
          <div class="text-center text-muted py-5">No readings yet.</div>
        @endif
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3>Top 5 Consumers{{ $a['latestPeriod'] ? ' — ' . billingPeriodLabel($a['latestPeriod']) : '' }}</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr><th>Consumer</th><th class="text-end">Usage</th><th class="text-end">Usual</th><th class="text-end">Change</th></tr></thead>
            <tbody>
            @forelse ($a['topConsumers'] as $t)
              <tr>
                <td><strong>{{ $t->name }}</strong><div class="text-muted small">Meter {{ $t->meter }}</div></td>
                <td class="text-end">{{ number_format($t->usage, 1) }} m³</td>
                <td class="text-end text-muted">{{ $t->usual !== null ? number_format($t->usual, 1) . ' m³' : '—' }}</td>
                <td class="text-end">
                  @if ($t->change === null) <span class="text-muted">—</span>
                  @else {{ $t->change > 0 ? '▲' : ($t->change < 0 ? '▼' : '') }} {{ rtrim(rtrim(number_format(abs($t->change), 1), '0'), '.') }}%
                  @endif
                </td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="4">No readings yet.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
        <p class="chart-note px-3 py-2 mb-0">Usual = their average over up to 6 earlier months. A big jump may mean a leak or a reading to double-check.</p>
      </div>
    </div>
  </div>
</div>

{{-- The same numbers as the charts, as a table. --}}
<details class="card mb-4 analytics-table">
  <summary class="card-header"><h3>Monthly Figures (table)</h3></summary>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Month</th><th class="text-end">Consumption</th><th class="text-end">Billed</th><th class="text-end">Collected</th><th class="text-end">Collection Rate</th></tr></thead>
        <tbody>
        @php $byPeriod = $a['billing']->keyBy('period'); @endphp
        @foreach ($a['consumption']->pluck('period')->merge($a['billing']->pluck('period'))->unique()->sortDesc() as $period)
          @php $c = $a['consumption']->firstWhere('period', $period); $b = $byPeriod[$period] ?? null; @endphp
          <tr>
            <td>{{ billingPeriodLabel($period) }}</td>
            <td class="text-end">{{ $c ? number_format($c->total, 1) . ' m³' : '—' }}</td>
            <td class="text-end">{{ $b ? formatCurrency($b->billed) : '—' }}</td>
            <td class="text-end">{{ $b ? formatCurrency($b->collected) : '—' }}</td>
            <td class="text-end">{{ $b && $b->rate !== null ? $b->rate . '%' : '—' }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  </div>
</details>

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
            <td>{{ paymentMethodLabel($p->payment_method, $p->wallet ?? null) }}</td>
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
<script src="{{ asset('assets/js/dashboard-charts.js') }}?v={{ @filemtime(public_path('assets/js/dashboard-charts.js')) ?: '1' }}"></script>
<script>
(function () {
  const A = AwasCharts;
  const short = p => new Date(p + '-01T00:00:00').toLocaleDateString('en-PH', { month: 'short', year: '2-digit' });

  const usage = {{ Js::from($a['consumption']) }};
  A.usage(document.getElementById('trendChart'), usage.map(r => short(r.period)), usage.map(r => r.total), {
    extra: i => [usage[i].households + ' households', 'Average ' + A.m3(usage[i].total / Math.max(1, usage[i].households)) + ' each'],
  });

  const billing = {{ Js::from($a['billing']) }};
  if (document.getElementById('collectionChart')) {
    A.pair(document.getElementById('collectionChart'), billing.map(r => short(r.period)), billing.map(r => r.billed), billing.map(r => r.collected),
      ['Billed', 'Collected'], { footer: i => billing[i].rate === null ? '' : 'Collection rate: ' + billing[i].rate + '%' });
  }

  const methods = {{ Js::from($a['methods']) }};
  if (document.getElementById('methodChart')) {
    const total = methods.reduce((s, m) => s + m.count, 0);
    A.doughnut(document.getElementById('methodChart'), methods.map(m => m.label), methods.map(m => m.count),
      i => methods[i].count + ' payments (' + Math.round(methods[i].count / total * 100) + '%) — ' + A.peso(methods[i].amount));
  }

  const puroks = {{ Js::from($a['byPurok']) }};
  if (document.getElementById('purokChart')) {
    A.ranked(document.getElementById('purokChart'), puroks.map(p => p.purok), puroks.map(p => p.total),
      i => puroks[i].households + ' households · ' + A.m3(puroks[i].total / Math.max(1, puroks[i].households)) + ' average');
  }
})();
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
