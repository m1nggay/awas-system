@extends('layouts.app')

@section('title', __('Recorded Readings'))

@section('content')
@php $latest = $summary->first(); @endphp
<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="stat-card">
      <div class="stat-info"><div class="label">{{ __('All my readings') }}</div><div class="value">{{ number_format($totalAll) }}</div></div>
      <div class="stat-icon"><i class="ph ph-clock-counter-clockwise" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="stat-card success">
      <div class="stat-info"><div class="label">{{ __('Recorded today') }}</div><div class="value">{{ number_format($today) }}</div></div>
      <div class="stat-icon"><i class="ph ph-check-circle" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-12 col-md-4">
    <div class="stat-card warning">
      <div class="stat-info">
        <div class="label">{{ __('Latest period:value', ['value' => $latest ? ' (' . billingPeriodLabel($latest->billing_period) . ')' : '']) }}</div>
        <div class="value">{{ $latest ? number_format($latest->total) . ' meters' : '—' }}</div>
      </div>
      <div class="stat-icon"><i class="ph ph-gauge" aria-hidden="true"></i></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('By Billing Period') }}</h3></div>
      <div class="card-body no-pad">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table">
            <thead><tr><th>{{ __('Period') }}</th><th>{{ __('Meters Read') }}</th><th>{{ __('Water Used') }}</th></tr></thead>
            <tbody>
            @forelse ($summary as $s)
              <tr>
                <td><a href="{{ route('admin.readings.history', ['period' => $s->billing_period]) }}">{{ billingPeriodLabel($s->billing_period) }}</a></td>
                <td>{{ number_format($s->total) }}</td>
                <td>{{ number_format($s->consumption, 2) }} m³</td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="3">{{ __('No readings yet.') }}</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('My Readings') }}</h3></div>
      <div class="card-body">
        <form method="GET" action="{{ route('admin.readings.history') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3 filter-bar">
          <div class="input-group flex-grow-1" style="min-width:200px;">
            <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
            <input type="search" name="q" class="form-control" placeholder="{{ __('Search by Meter Number or Name...') }}" value="{{ $search }}" aria-label="{{ __('Search my readings') }}">
            <button type="submit" class="btn btn-primary">{{ __('Search') }}</button>
          </div>
          <select name="period" class="form-select w-auto" onchange="this.form.submit()" aria-label="{{ __('Billing period') }}">
            <option value="">{{ __('All Periods') }}</option>
            @foreach ($periods as $p)
              <option value="{{ $p }}" @selected($periodFil === $p)>{{ billingPeriodLabel($p) }}</option>
            @endforeach
          </select>
          @if ($search !== '' || $periodFil !== '')
            <a href="{{ route('admin.readings.history') }}" class="btn btn-outline btn-sm">{{ __('Clear') }}</a>
          @endif
        </form>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table table-stack">
            <thead>
              <tr><th>{{ __('Meter Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Purok') }}</th><th>{{ __('Previous') }}</th><th>{{ __('Present') }}</th><th>{{ __('Consumption') }}</th><th>{{ __('Total') }}</th><th>{{ __('Status') }}</th><th>{{ __('Date') }}</th></tr>
            </thead>
            <tbody>
            @forelse ($readings as $r)
              <tr>
                <td class="stack-title"><strong>{{ $r->meter_number ?: '—' }}</strong></td>
                <td>{{ $r->full_name }}</td>
                <td>{{ $r->purok_name ?: '—' }}</td>
                <td>{{ number_format($r->previous_reading, 2) }}</td>
                <td>{{ number_format($r->current_reading, 2) }}</td>
                <td class="consumption-value">{{ number_format($r->consumption, 2) }} m³</td>
                <td>{{ $r->bill_total !== null ? formatCurrency($r->bill_total) : '—' }}</td>
                <td>
                  @if ($r->bill_status)
                    <span class="badge {{ billStatusBadgeClass($r->bill_status) }}">{{ str_replace('_', ' ', $r->bill_status) }}</span>
                  @else — @endif
                </td>
                <td>
                  {{ formatDate($r->reading_date) }}<div class="text-muted" style="font-size:11px;">{{ billingPeriodLabel($r->billing_period) }}</div>
                  @if ($r->remarks)<div class="text-muted" style="font-size:11px;">{{ $r->remarks }}</div>@endif
                </td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="9">{{ __('No readings found:value.', ['value' => ($search !== '' || $periodFil !== '') ? __(' for this filter') : '']) }}</td></tr>
            @endforelse
            </tbody>
          </table>
        </div>

        @include('partials.pagination', ['paginator' => $readings])
      </div>
    </div>
  </div>
</div>
@endsection
