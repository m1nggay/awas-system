@extends('layouts.app')

@section('title', __('Dashboard'))

@push('styles')
<style>
  /* 1. Banner: greeting, this month's progress ring and the main action */
  .reader-hero{position:relative;overflow:hidden;display:flex;align-items:center;gap:26px;flex-wrap:wrap;
    background:linear-gradient(135deg,#062a4a 0%,#0b3b66 60%,#0077b6 100%);color:#fff;border-radius:16px;padding:24px 28px;box-shadow:0 10px 28px rgba(6,42,74,.25);}
  .reader-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:220px;height:220px;border-radius:50%;background:rgba(0,180,216,.18);pointer-events:none;}
  .ring{--p:0;flex:none;width:118px;height:118px;border-radius:50%;display:grid;place-items:center;
    background:conic-gradient(#00b4d8 calc(var(--p) * 1%), rgba(255,255,255,.16) 0);}
  .ring.done{background:conic-gradient(#2ecc71 calc(var(--p) * 1%), rgba(255,255,255,.16) 0);}
  .ring-inner{width:92px;height:92px;border-radius:50%;background:#0b3b66;display:flex;flex-direction:column;align-items:center;justify-content:center;line-height:1.1;}
  .ring-inner b{font-size:26px;}
  .ring-inner small{font-size:11px;color:#c7dbea;}
  .hero-text{flex:1 1 240px;position:relative;z-index:1;}
  .hero-text .greet{font-size:13.5px;color:#c7dbea;}
  .hero-text h2{font-size:22px;font-weight:700;margin:2px 0 6px;color:#fff;}
  .hero-text p{margin:0;font-size:14px;color:#dbe8f2;}
  .hero-text p strong{color:#fff;}
  .reader-hero .btn-hero{position:relative;z-index:1;background:#fff;color:#062a4a;font-weight:700;border-radius:10px;padding:12px 22px;}
  .reader-hero .btn-hero:hover{background:#e3f4fa;color:#062a4a;}
  @media (max-width:576px){ .reader-hero{padding:20px;gap:16px;} .reader-hero .btn-hero{width:100%;justify-content:center;} }

  /* 4. Lists */
  .list-tools{display:flex;gap:8px;flex-wrap:wrap;padding:12px 16px;border-bottom:1px solid var(--border);}
  .list-tools input{flex:1 1 180px;min-width:0;}
  .list-tools select{flex:0 1 170px;}
  .scroll-list{max-height:430px;overflow-y:auto;}
  .mini-item{display:flex;justify-content:space-between;gap:10px;padding:11px 16px;border-bottom:1px solid var(--border);font-size:13.5px;}
  .mini-item:last-child{border-bottom:0;}
  .mini-item .sub{font-size:12px;color:var(--text-muted);}
  .check-reason{font-size:12.5px;color:#9a5b00;margin-top:2px;}
  .check-reason i{color:#e07a2f;}
  .all-good{display:flex;align-items:center;gap:10px;padding:16px;font-size:13.5px;color:#1a7a68;}
  .all-good i{font-size:24px;}
  .chart-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;color:var(--text-muted);pointer-events:none;}
</style>
@endpush

@section('content')
@include('partials.reader-puroks')

@php
  $pct = $activeCount > 0 ? (int)round($readThisPeriod / $activeCount * 100) : 0;
  $left = max(0, $activeCount - $readThisPeriod);
  $hour = (int)now()->format('G');
  $greeting = $hour < 12 ? __('Good morning') : ($hour < 18 ? __('Good afternoon') : __('Good evening'));
  $firstName = strtok(trim((string)auth()->user()->full_name), ' ') ?: auth()->user()->username;
  $hasPace = collect($daily)->sum('count') > 0;
@endphp

{{-- 1. Banner --}}
<div class="reader-hero mb-4">
  <div class="ring{{ $activeCount > 0 && $left === 0 ? ' done' : '' }}" style="--p:{{ $pct }}" role="img"
       aria-label="{{ __(':pct% of meters read this month', ['pct' => $pct]) }}">
    <div class="ring-inner"><b>{{ $pct }}%</b><small>{{ __('read') }}</small></div>
  </div>
  <div class="hero-text">
    <div class="greet">{{ $greeting }}, {{ $firstName }} 👋</div>
    <h2>{{ __('Reading round — :period', ['period' => billingPeriodLabel($period)]) }}</h2>
    <p>
      @if ($activeCount > 0 && $left === 0)
        {{ __('All :total meters are read. Great work!', ['total' => number_format($activeCount)]) }}
      @else
        {!! __('<strong>:read</strong> of <strong>:total</strong> meters read — <strong>:left</strong> to go.', [
          'read' => number_format($readThisPeriod), 'total' => number_format($activeCount), 'left' => number_format($left)]) !!}
      @endif
    </p>
  </div>
  <a href="{{ route('admin.readings.index', ['record' => 1]) }}" class="btn btn-hero"><i class="ph ph-camera" aria-hidden="true"></i> {{ __('Record Meter Reading') }}</a>
</div>

{{-- 2. Quick numbers --}}
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="stat-card warning h-100">
      <div class="stat-info"><div class="label">{{ __('Left to read') }}</div><div class="value">{{ number_format($left) }}</div></div>
      <div class="stat-icon"><i class="ph ph-hourglass" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card success h-100">
      <div class="stat-info"><div class="label">{{ __('Read today') }}</div><div class="value">{{ number_format($myToday) }}</div></div>
      <div class="stat-icon"><i class="ph ph-check-circle" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="stat-card h-100">
      <div class="stat-info"><div class="label">{{ __('Read this week') }}</div><div class="value">{{ number_format($myWeek) }}</div></div>
      <div class="stat-icon"><i class="ph ph-calendar-check" aria-hidden="true"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <a href="#toCheck" class="text-decoration-none">
      <div class="stat-card {{ $toCheck->isNotEmpty() ? 'danger' : 'success' }} h-100">
        <div class="stat-info"><div class="label">{{ __('To double-check') }}</div><div class="value">{{ number_format($toCheck->count()) }}</div></div>
        <div class="stat-icon"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></div>
      </div>
    </a>
  </div>
</div>

{{-- 3. Charts --}}
<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('Progress by Purok') }}</h3></div>
      <div class="card-body">
        <p class="chart-note">{{ __('Meters read (green) and still to read (blue) in each of your puroks.') }}</p>
        <div class="chart-box" style="height:{{ max(160, 70 + 46 * $purokProgress->count()) }}px;">
          <canvas id="purokChart" role="img" aria-label="{{ __('Bar chart of meters read and still to read in each purok') }}"></canvas>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('My Readings — Last 14 Days') }}</h3></div>
      <div class="card-body">
        <p class="chart-note">{{ __('Meters you recorded each day. The darker bar is today.') }}</p>
        <div class="chart-box position-relative" style="height:220px;">
          <canvas id="dailyChart" role="img" aria-label="{{ __('Bar chart of the meters you recorded each day for the last 14 days') }}"></canvas>
          @unless ($hasPace)<div class="chart-empty">{{ __('No readings in the last 14 days yet.') }}</div>@endunless
        </div>
      </div>
    </div>
  </div>
</div>

{{-- 4. Work area --}}
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header"><h3>{{ __('Still to read — :period', ['period' => billingPeriodLabel($period)]) }}</h3><span class="badge text-bg-light" id="toReadCount">{{ number_format($toRead->count()) }}</span></div>
      @if ($toRead->isNotEmpty())
        <div class="list-tools">
          <input type="search" class="form-control form-control-sm" id="toReadSearch" placeholder="{{ __('Search name or meter number') }}" aria-label="{{ __('Search name or meter number') }}">
          @if ($purokProgress->count() > 1)
            <select class="form-select form-select-sm" id="toReadPurok" aria-label="{{ __('Purok') }}">
              <option value="">{{ __('All my puroks') }}</option>
              @foreach ($purokProgress as $p)<option value="{{ $p->name }}">{{ $p->name }}</option>@endforeach
            </select>
          @endif
        </div>
      @endif
      <div class="card-body no-pad scroll-list">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 data-table table-stack" id="toReadTable">
            <thead><tr><th>{{ __('Meter Number') }}</th><th>{{ __('Name') }}</th><th>{{ __('Purok') }}</th><th></th></tr></thead>
            <tbody>
            @forelse ($toRead as $c)
              <tr data-purok="{{ $c->purok_name }}" data-search="{{ mb_strtolower($c->meter_number . ' ' . $c->full_name) }}">
                <td class="stack-title"><strong>{{ $c->meter_number ?: '—' }}</strong></td><td>{{ $c->full_name }}</td><td>{{ $c->purok_name }}</td>
                <td class="actions"><a class="btn btn-outline btn-sm" href="{{ route('admin.readings.index', ['record' => 1, 'meter' => $c->meter_number]) }}">{{ __('Record') }}</a></td>
              </tr>
            @empty
              <tr class="empty-row"><td colspan="4">{{ __('All active meters have been read this period.') }}</td></tr>
            @endforelse
              <tr class="empty-row" id="toReadNone" hidden><td colspan="4">{{ __('No meter matches your search.') }}</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-5 d-flex flex-column gap-3">
    <div class="card" id="toCheck">
      <div class="card-header"><h3>{{ __('Readings to Double-Check') }}</h3>@if ($toCheck->isNotEmpty())<span class="badge text-bg-warning">{{ $toCheck->count() }}</span>@endif</div>
      <div class="card-body no-pad">
        @forelse ($toCheck as $r)
          <div class="mini-item">
            <div>
              <a href="{{ route('admin.readings.history', ['q' => $r->meter_number]) }}"><strong>{{ $r->meter_number }}</strong> · {{ $r->full_name }}</a>
              <div class="check-reason"><i class="ph ph-warning" aria-hidden="true"></i> {{ $r->reason }}</div>
            </div>
            <span class="text-nowrap">{{ number_format($r->consumption, 2) }} m³</span>
          </div>
        @empty
          <div class="all-good"><i class="ph ph-check-circle" aria-hidden="true"></i> {{ __('Nothing to double-check — your readings this month look normal.') }}</div>
        @endforelse
      </div>
    </div>

    <div class="card flex-grow-1">
      <div class="card-header"><h3>{{ __('My Recent Readings') }}</h3><a href="{{ route('admin.readings.history') }}" class="btn btn-outline btn-sm">{{ __('View All') }}</a></div>
      <div class="card-body no-pad">
        @forelse ($myReadings as $r)
          <div class="mini-item">
            <div><strong>{{ $r->full_name }}</strong><div class="sub">{{ __('Meter') }} {{ $r->meter_number }} · {{ formatDate($r->reading_date) }}</div></div>
            <div class="text-end"><strong>{{ number_format($r->consumption, 2) }} m³</strong><div class="sub">{{ $r->total_amount !== null ? formatCurrency($r->total_amount) : '—' }}</div></div>
          </div>
        @empty
          <div class="all-good" style="color:var(--text-muted);"><i class="ph ph-clipboard-text" aria-hidden="true"></i> {{ __('You haven\'t recorded any readings yet.') }}</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script src="{{ asset('assets/js/dashboard-charts.js') }}?v={{ @filemtime(public_path('assets/js/dashboard-charts.js')) ?: '1' }}"></script>
<script>
(function () {
  // Charts (Chart.js may be unavailable offline: the rest of the page still works).
  if (window.AwasCharts) {
    const puroks = {{ Js::from($purokProgress->values()) }};
    if (document.getElementById('purokChart')) {
      AwasCharts.progress(document.getElementById('purokChart'), puroks.map(p => p.name), puroks.map(p => p.read),
        puroks.map(p => Math.max(0, p.total - p.read)), [{{ Js::from(__('Read')) }}, {{ Js::from(__('Still to read')) }}]);
    }
    const daily = {{ Js::from($daily) }};
    if (document.getElementById('dailyChart')) {
      AwasCharts.counts(document.getElementById('dailyChart'), daily.map(d => d.label), daily.map(d => d.count), {{ Js::from(__('Meters read')) }});
    }
  }

  // Still to read: search and purok filter.
  const search = document.getElementById('toReadSearch'), purok = document.getElementById('toReadPurok');
  const rows = Array.from(document.querySelectorAll('#toReadTable tbody tr[data-search]'));
  const none = document.getElementById('toReadNone'), count = document.getElementById('toReadCount');
  function filter() {
    const q = (search ? search.value : '').trim().toLowerCase(), p = purok ? purok.value : '';
    let shown = 0;
    rows.forEach(tr => {
      const ok = (!q || tr.dataset.search.includes(q)) && (!p || tr.dataset.purok === p);
      tr.hidden = !ok;
      if (ok) shown++;
    });
    if (none) none.hidden = shown > 0 || !rows.length;
    if (count) count.textContent = shown.toLocaleString();
  }
  if (search) search.addEventListener('input', filter);
  if (purok) purok.addEventListener('change', filter);
})();
</script>
@endpush
