@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="stat-card">
      <div class="stat-info"><div class="label">Read this period ({{ billingPeriodLabel($period) }})</div><div class="value">{{ number_format($readThisPeriod) }} / {{ number_format($activeCount) }}</div></div>
      <div class="stat-icon">🧮</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="stat-card warning">
      <div class="stat-info"><div class="label">Meters still to read</div><div class="value">{{ number_format(max(0, $activeCount - $readThisPeriod)) }}</div></div>
      <div class="stat-icon">⏳</div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat-card success">
      <div class="stat-info"><div class="label">My readings today</div><div class="value">{{ number_format($myToday) }}</div></div>
      <div class="stat-icon">✅</div>
    </div>
  </div>
</div>

<div class="mb-4">
  <a href="{{ route('admin.readings.index', ['record' => 1]) }}" class="btn btn-primary">+ Record Meter Reading</a>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><h3>Still to read — {{ billingPeriodLabel($period) }}</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table">
            <thead><tr><th>Meter Number</th><th>Name</th><th>Purok</th></tr></thead>
            <tbody>
            @forelse ($toRead as $c)
              <tr><td><strong>{{ $c->meter_number ?: '—' }}</strong></td><td>{{ $c->full_name }}</td><td>{{ $c->purok_name }}</td></tr>
            @empty
              <tr class="empty-row"><td colspan="3">All active meters have been read this period. 🎉</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h3>My Recent Readings</h3><a href="{{ route('admin.readings.index') }}" class="btn btn-outline btn-sm">View All</a></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table">
            <thead><tr><th>Meter Number</th><th>Name</th><th>Previous</th><th>Present</th><th>Consumption</th><th>Total</th><th>Date</th></tr></thead>
            <tbody>
            @forelse ($myReadings as $r)
              <tr>
                <td><strong>{{ $r->meter_number }}</strong></td>
                <td>{{ $r->full_name }}</td>
                <td>{{ number_format($r->previous_reading, 2) }}</td>
                <td>{{ number_format($r->current_reading, 2) }}</td>
                <td>{{ number_format($r->consumption, 2) }} m³</td>
                <td>{{ $r->total_amount !== null ? formatCurrency($r->total_amount) : '—' }}</td>
                <td>{{ formatDate($r->reading_date) }}</td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="7">You haven't recorded any readings yet.</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
