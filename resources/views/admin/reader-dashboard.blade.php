@extends('layouts.app')

@section('title', __('Dashboard'))

@section('content')
@include('partials.reader-puroks')
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="stat-card">
      <div class="stat-info"><div class="label">{{ __('Read this period (:period)', ['period' => billingPeriodLabel($period)]) }}</div><div class="value">{{ number_format($readThisPeriod) }} / {{ number_format($activeCount) }}</div></div>
      <div class="stat-icon"><i class="ph ph-gauge" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="stat-card warning">
      <div class="stat-info"><div class="label">{{ __('Meters still to read') }}</div><div class="value">{{ number_format(max(0, $activeCount - $readThisPeriod)) }}</div></div>
      <div class="stat-icon"><i class="ph ph-hourglass" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat-card success">
      <div class="stat-info"><div class="label">{{ __('My readings today') }}</div><div class="value">{{ number_format($myToday) }}</div></div>
      <div class="stat-icon"><i class="ph ph-check-circle" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="mb-4">
  <a href="{{ route('admin.readings.index', ['record' => 1]) }}" class="btn btn-primary btn-phone-block">{{ __('+ Record Meter Reading') }}</a>
</div>

<div class="row g-3">
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('Still to read — :period', ['period' => billingPeriodLabel($period)]) }}</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table table-stack">
            <thead><tr><th>{{ __('Meter Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Purok') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($toRead as $c)
              <tr><td class="stack-title"><strong>{{ $c->meter_number ?: '—' }}</strong></td><td>{{ $c->full_name }}</td><td>{{ $c->purok_name }}</td><td class="actions"><a class="btn btn-outline btn-sm" href="{{ route('admin.readings.index', ['record' => 1, 'meter' => $c->meter_number]) }}">{{ __('Record') }}</a></td></tr>
            @empty
              <tr class="empty-row"><td colspan="4">{{ __('All active meters have been read this period.') }}</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('My Recent Readings') }}</h3><a href="{{ route('admin.readings.history') }}" class="btn btn-outline btn-sm">{{ __('View All') }}</a></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table table-stack">
            <thead><tr><th>{{ __('Meter Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Previous') }}</th><th>{{ __('Present') }}</th><th>{{ __('Consumption') }}</th><th>{{ __('Total') }}</th><th>{{ __('Date') }}</th></tr></thead>
            <tbody>
            @forelse ($myReadings as $r)
              <tr>
                <td class="stack-title"><strong>{{ $r->meter_number }}</strong></td>
                <td>{{ $r->full_name }}</td>
                <td>{{ number_format($r->previous_reading, 2) }}</td>
                <td>{{ number_format($r->current_reading, 2) }}</td>
                <td>{{ number_format($r->consumption, 2) }} m³</td>
                <td>{{ $r->total_amount !== null ? formatCurrency($r->total_amount) : '—' }}</td>
                <td>{{ formatDate($r->reading_date) }}</td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="7">{{ __('You haven\'t recorded any readings yet.') }}</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
