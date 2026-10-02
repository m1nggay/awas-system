@extends('layouts.app')

@section('title', 'Reports')

@section('content')
@php
  $query = array_filter(['type' => $reportType, 'period' => $period, 'purok' => $purokId ?: null]);
@endphp
<div class="card">
  <div class="card-header">
    <h3>Reports</h3>
    <div class="d-flex gap-2">
      <a class="btn btn-outline btn-sm" href="{{ route('admin.reports.export', $query) }}">⬇ Download Excel (CSV)</a>
      <a class="btn btn-primary btn-sm" href="{{ route('admin.reports.index', $query + ['print' => 1]) }}" target="_blank" rel="noopener">⬇ Download PDF / Print</a>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('admin.reports.index') }}" class="d-flex flex-wrap gap-2 align-items-center">
      <select name="type" class="form-select flex-grow-1" style="min-width:240px;" onchange="this.form.submit()">
        @foreach ($reportTypes as $key => $label)
          <option value="{{ $key }}" @selected($reportType === $key)>{{ $label }}</option>
        @endforeach
      </select>
      @if (in_array($reportType, ['purok', 'consumption'], true))
        <select name="period" class="form-select w-auto" onchange="this.form.submit()">
          @forelse ($periods as $p)
            <option value="{{ $p }}" @selected($period === $p)>{{ billingPeriodLabel($p) }}</option>
          @empty
            <option value="{{ $period }}">{{ billingPeriodLabel($period) }}</option>
          @endforelse
        </select>
      @endif
      @if ($reportType === 'purok')
        <select name="purok" class="form-select w-auto" onchange="this.form.submit()">
          <option value="0">All Puroks</option>
          @foreach ($puroks as $pk)
            <option value="{{ $pk->purok_id }}" @selected($purokId === (int)$pk->purok_id)>{{ $pk->purok_name }}</option>
          @endforeach
        </select>
      @endif
      <button type="submit" class="btn btn-secondary btn-sm">Generate</button>
    </form>
    <p class="text-muted mb-0 mt-2" style="font-size:12px;">"Download PDF / Print" opens a print-ready page — choose <strong>Save as PDF</strong> as the printer to download it as a PDF file.</p>
  </div>
</div>

@if ($reportType === 'purok')
  @include('admin.reports._purok-tables')
@else
<div class="card">
  <div class="card-header"><h3>{{ $reportTypes[$reportType] }}{{ $reportType === 'consumption' ? ' — ' . billingPeriodLabel($period) : '' }}</h3></div>
  <div class="card-body no-pad">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table">
        <thead><tr>@foreach ($columns as $col)<th>{{ $col }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($rows as $row)
          <tr>@foreach ($row as $val)<td>{{ $val }}</td>@endforeach</tr>
        @empty
          <tr class="empty-row"><td colspan="{{ count($columns) }}">No records found for this report.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div>
@endif
@endsection
