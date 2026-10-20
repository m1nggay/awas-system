{{-- Meter Reader's Consumer List: built for phones in the field.
     Progress for the month, live search with a filter sheet, "Not yet read | Read | All" tabs,
     and compact cards — tap one to record its reading. --}}
@php
  $filters = array_filter(['q' => $search, 'purok' => $purokFil ?: null, 'type' => $typeFil, 'status' => $statusFil, 'read' => $readTab === 'todo' ? null : $readTab],
      fn ($v) => $v !== null && $v !== '');
  $link = fn (array $change) => route('admin.consumers.index', array_filter(array_merge($filters, $change), fn ($v) => $v !== null && $v !== ''));
  $purokName = $purokFil ? optional($puroks->firstWhere('purok_id', $purokFil))->purok_name : $puroks->pluck('purok_name')->join(', ');
  $pct = $progress['total'] > 0 ? round($progress['read'] / $progress['total'] * 100) : 0;
  $chips = collect([
      'purok'  => $purokFil ? optional($puroks->firstWhere('purok_id', $purokFil))->purok_name : null,
      'type'   => $typeFil !== '' ? (\App\Models\Consumer::TYPES[$typeFil] ?? null) : null,
      'status' => $statusFil !== '' ? ucfirst($statusFil) : null,
  ])->filter();
  $reading = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
@endphp

@push('styles')
<style>
  /* Full width: one column of cards on phones, two on tablets, three or four on laptops/desktops. */
  .rd{--rd-ink:#0f2d3d;--rd-sub:#3d5563;--rd-line:#d7e4ea;max-width:1500px;}
  .rd-progress{background:#fff;border:1px solid var(--rd-line);border-radius:14px;padding:14px 16px;margin-bottom:10px;}
  .rd-progress .top{display:flex;justify-content:space-between;align-items:baseline;gap:10px;color:var(--rd-ink);font-size:14px;}
  .rd-progress .top strong{font-size:15px;}
  .rd-bar{height:10px;border-radius:99px;background:#e3edf2;margin-top:10px;overflow:hidden;}
  .rd-bar span{display:block;height:100%;border-radius:99px;background:linear-gradient(90deg,#0a9f6e,#2ecc71);}

  .rd-search{position:sticky;top:var(--topbar-h,62px);z-index:30;background:var(--bg);padding:8px 0;display:flex;gap:8px;}
  .rd-search-box{flex:1;position:relative;}
  .rd-search-box i{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:19px;color:var(--rd-sub);pointer-events:none;}
  .rd-search-box input{width:100%;height:48px;border:1.5px solid var(--rd-line);border-radius:12px;padding:0 14px 0 42px;font-size:15px;background:#fff;color:var(--rd-ink);}
  .rd-search-box input:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(0,119,182,.15);}
  .rd-filter-btn{position:relative;width:48px;height:48px;flex:none;border:1.5px solid var(--rd-line);border-radius:12px;background:#fff;color:var(--rd-ink);font-size:21px;display:flex;align-items:center;justify-content:center;}
  .rd-filter-btn .dot{position:absolute;top:6px;right:6px;min-width:17px;height:17px;border-radius:99px;background:var(--primary);color:#fff;font-size:10.5px;font-weight:700;display:flex;align-items:center;justify-content:center;padding:0 4px;}
  .rd-chips{display:flex;flex-wrap:wrap;gap:6px;margin:2px 0 8px;}
  .rd-chip{display:inline-flex;align-items:center;gap:6px;min-height:34px;padding:4px 12px;border-radius:99px;background:#e1f0f8;color:var(--primary-dark);font-size:13px;font-weight:600;text-decoration:none;}
  .rd-chip i{font-size:14px;}

  .rd-tabs{display:flex;background:#e6eff3;border-radius:12px;padding:4px;margin:4px 0 12px;}
  .rd-tabs a{flex:1;min-height:42px;display:flex;align-items:center;justify-content:center;border-radius:9px;font-size:14px;font-weight:600;color:var(--rd-sub);text-decoration:none;}
  .rd-tabs a.active{background:#fff;color:var(--rd-ink);box-shadow:0 1px 3px rgba(0,0,0,.12);}

  /* min(): never wider than a small phone's screen. */
  .rd-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(320px,100%),1fr));gap:10px;}
  .rd-card{display:block;background:#fff;border:1px solid var(--rd-line);border-radius:12px;padding:11px 14px;color:var(--rd-ink);text-decoration:none;min-height:48px;}
  a.rd-card:hover,a.rd-card:focus-visible{border-color:var(--primary);color:var(--rd-ink);}
  a.rd-card:active{background:#f0f8fc;}
  .rd-card .r1{display:flex;justify-content:space-between;align-items:center;gap:10px;}
  /* Long joint names wrap to a second line instead of being cut off. */
  .rd-card .name{font-weight:700;font-size:15.5px;line-height:1.3;min-width:0;overflow:hidden;overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;}
  .rd-card .r1{align-items:flex-start;}
  .rd-card .r1 .rd-state{margin-top:2px;}
  .rd-card .r2{font-size:13px;color:var(--rd-sub);margin-top:1px;}
  .rd-card .r3{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;color:var(--rd-ink);margin-top:4px;}
  .rd-card .r3 i{font-size:18px;color:var(--rd-sub);}
  .rd-state{flex:none;display:inline-flex;align-items:center;gap:5px;font-size:12.5px;font-weight:700;}
  .rd-state.todo{color:#a85d00;} .rd-state.todo::before{content:"";width:9px;height:9px;border-radius:50%;background:#f0a020;}
  .rd-state.done{color:#08805a;} .rd-state.done i{font-size:17px;}
  .rd-pill{display:inline-block;padding:1px 9px;border-radius:99px;font-size:11.5px;font-weight:700;margin-left:4px;}
  .rd-pill.disconnected{background:#fde3e0;color:#b4261a;} .rd-pill.inactive{background:#e7ebee;color:#46555e;}
  .rd-card.off{background:#f7f9fa;}
  .rd-empty{background:#fff;border:1px dashed var(--rd-line);border-radius:12px;padding:30px 16px;text-align:center;color:var(--rd-sub);}
  .rd-empty i{font-size:34px;color:#0a9f6e;display:block;margin-bottom:6px;}

  /* Filter sheet (slides up from the bottom) */
  #rdFilters{height:auto;max-height:85vh;border-radius:18px 18px 0 0;}
  #rdFilters .form-select{height:48px;font-size:15px;}
  #rdFilters .btn{min-height:48px;flex:1;}

  /* Laptops/desktops: tabs a comfortable width, and the filter sheet a centered panel instead of full-screen width. */
  @media (min-width: 992px) {
    .rd-tabs{max-width:560px;}
    #rdFilters{left:50%;right:auto;width:520px;margin-left:-260px;}
  }
  /* Small phones: slightly smaller text so the tabs and progress line fit. */
  @media (max-width: 380px) {
    .rd-progress .top{font-size:13px;}
    .rd-tabs a{font-size:13px;}
    .rd-card{padding:10px 12px;}
  }
</style>
@endpush

<div class="rd">
  @if ($noPuroks)
    <div class="alert alert-warning">No puroks are assigned to your account yet, so there are no consumers to read. Please ask the administrator to assign your puroks.</div>
  @endif

  <div class="rd-progress">
    <div class="top"><strong>{{ $purokName ?: 'My puroks' }}</strong><span><strong>{{ number_format($progress['read']) }}</strong> of {{ number_format($progress['total']) }} read</span></div>
    <div class="rd-bar" role="progressbar" aria-label="Meters read in {{ billingPeriodLabel($currentPeriod) }}" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100"><span style="width:{{ $pct }}%"></span></div>
  </div>

  <form method="GET" action="{{ route('admin.consumers.index') }}" class="rd-search" id="rdSearch" role="search">
    @foreach (['purok' => $purokFil ?: '', 'type' => $typeFil, 'status' => $statusFil, 'read' => $readTab === 'todo' ? '' : $readTab] as $k => $v)
      @if ($v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
    @endforeach
    <div class="rd-search-box">
      <i class="ph ph-magnifying-glass" aria-hidden="true"></i>
      <input type="search" name="q" value="{{ $search }}" placeholder="Search name or meter number" aria-label="Search consumers" autocomplete="off" enterkeyhint="search">
    </div>
    <button type="button" class="rd-filter-btn" data-bs-toggle="offcanvas" data-bs-target="#rdFilters" aria-controls="rdFilters" aria-label="Filters">
      <i class="ph ph-funnel" aria-hidden="true"></i>@if ($chips->isNotEmpty())<span class="dot">{{ $chips->count() }}</span>@endif
    </button>
  </form>

  @if ($chips->isNotEmpty())
    <div class="rd-chips">
      @foreach ($chips as $key => $label)
        <a class="rd-chip" href="{{ $link([$key => null]) }}" aria-label="Remove filter {{ $label }}">{{ $label }} <i class="ph ph-x" aria-hidden="true"></i></a>
      @endforeach
    </div>
  @endif

  <div id="rdResults">
    <nav class="rd-tabs" aria-label="Reading status">
      <a href="{{ $link(['read' => null]) }}" class="{{ $readTab === 'todo' ? 'active' : '' }}">Not yet read</a>
      <a href="{{ $link(['read' => 'done']) }}" class="{{ $readTab === 'done' ? 'active' : '' }}">Read</a>
      <a href="{{ $link(['read' => 'all']) }}" class="{{ $readTab === 'all' ? 'active' : '' }}">All</a>
    </nav>

    @if ($consumers->isEmpty())
      <div class="rd-empty">
        @if ($readTab === 'todo' && $search === '' && $chips->isEmpty())
          <i class="ph ph-check-circle" aria-hidden="true"></i><strong>All meters are read</strong> for {{ billingPeriodLabel($currentPeriod) }}.
        @else
          No consumers found{{ $search !== '' ? ' for "' . $search . '"' : '' }}.
        @endif
      </div>
    @else
      <div class="rd-list">
        @foreach ($consumers as $c)
          @php
            $last = $lastReadings[$c->consumer_id] ?? null;
            $done = $last && $last->billing_period === $currentPeriod;
            $active = $c->status === 'active';
            $href = $done ? route('admin.readings.index', ['q' => $c->meter_number])
                : ($active ? route('admin.readings.index', ['record' => 1, 'meter' => $c->meter_number, 'back' => request()->fullUrl()]) : null);
          @endphp
          <{{ $href ? 'a' : 'div' }} @if ($href) href="{{ $href }}" @endif class="rd-card {{ $active ? '' : 'off' }}"
            @if ($href) aria-label="{{ $done ? 'View reading of' : 'Record reading for' }} {{ $c->display_name }}, meter {{ $c->meter_number }}" @endif>
            <div class="r1">
              <span class="name">{{ $c->display_name }}</span>
              @if ($done)
                <span class="rd-state done"><i class="ph-fill ph-check-circle" aria-hidden="true"></i>Read</span>
              @elseif ($active)
                <span class="rd-state todo">Not read</span>
              @endif
            </div>
            <div class="r2">
              {{ $c->meter_number ?: 'No meter' }} · {{ $c->purok_name }}
              @unless ($active)<span class="rd-pill {{ $c->status === 'disconnected' ? 'disconnected' : 'inactive' }}">{{ ucfirst($c->status) }}</span>@endunless
            </div>
            <div class="r3">
              <span>
                @if ($done)
                  This month: <strong>{{ $reading($last->current_reading) }} m³</strong> ({{ date('M j', strtotime($last->reading_date)) }})
                @elseif ($last)
                  Last: <strong>{{ $reading($last->current_reading) }} m³</strong> ({{ date('M j', strtotime($last->reading_date)) }})
                @elseif ($c->initial_meter_reading !== null)
                  Starting reading: <strong>{{ $reading($c->initial_meter_reading) }} m³</strong>
                @else
                  No reading yet
                @endif
              </span>
              @if ($href)<i class="ph ph-caret-right" aria-hidden="true"></i>@endif
            </div>
          </{{ $href ? 'a' : 'div' }}>
        @endforeach
      </div>
    @endif

    @include('partials.pagination', ['paginator' => $consumers])
  </div>
</div>

{{-- Filters: a sheet that slides up from the bottom --}}
<div class="offcanvas offcanvas-bottom" tabindex="-1" id="rdFilters" aria-labelledby="rdFiltersTitle">
  <div class="offcanvas-header">
    <h3 class="offcanvas-title h6 mb-0" id="rdFiltersTitle">Filter consumers</h3>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body pt-0">
    <form method="GET" action="{{ route('admin.consumers.index') }}">
      @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif
      @if ($readTab !== 'todo')<input type="hidden" name="read" value="{{ $readTab }}">@endif
      <label class="form-label" for="f_purok">Purok</label>
      <select id="f_purok" name="purok" class="form-select mb-3">
        <option value="">All my puroks</option>
        @foreach ($puroks as $pk)
          <option value="{{ $pk->purok_id }}" @selected($purokFil === (int)$pk->purok_id)>{{ $pk->purok_name }}</option>
        @endforeach
      </select>
      <label class="form-label" for="f_type">Type of consumer</label>
      <select id="f_type" name="type" class="form-select mb-3">
        <option value="">All types</option>
        @foreach (\App\Models\Consumer::TYPES as $v => $l)
          <option value="{{ $v }}" @selected($typeFil === $v)>{{ $l }}</option>
        @endforeach
      </select>
      <label class="form-label" for="f_status">Account status</label>
      <select id="f_status" name="status" class="form-select mb-3">
        <option value="">All</option>
        <option value="active" @selected($statusFil === 'active')>Active</option>
        <option value="disconnected" @selected($statusFil === 'disconnected')>Disconnected</option>
        <option value="inactive" @selected($statusFil === 'inactive')>Inactive</option>
      </select>
      <div class="d-flex gap-2">
        <a href="{{ route('admin.consumers.index', array_filter(['q' => $search, 'read' => $readTab === 'todo' ? null : $readTab])) }}" class="btn btn-outline">Reset</a>
        <button type="submit" class="btn btn-primary">Show results</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
  // Keep the search bar just under the top bar while scrolling.
  const bar = document.querySelector('.topbar');
  const setTop = () => document.documentElement.style.setProperty('--topbar-h', (bar ? bar.offsetHeight : 0) + 'px');
  setTop(); window.addEventListener('resize', setTop);

  // Live search: the list updates as you type (no Search button).
  const form = document.getElementById('rdSearch'), input = form.querySelector('input[name="q"]');
  let timer = null, ctrl = null;
  const load = () => {
    const params = new URLSearchParams(new FormData(form));
    if (!params.get('q')) params.delete('q');
    const url = form.action + (params.toString() ? '?' + params : '');
    if (ctrl) ctrl.abort();
    ctrl = new AbortController();
    fetch(url, { signal: ctrl.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(r => r.text())
      .then(html => {
        const next = new DOMParser().parseFromString(html, 'text/html').getElementById('rdResults');
        if (!next) return;
        document.getElementById('rdResults').innerHTML = next.innerHTML;
        history.replaceState(null, '', url);
      })
      .catch(() => {});
  };
  input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(load, 300); });
  form.addEventListener('submit', e => { e.preventDefault(); clearTimeout(timer); load(); input.blur(); });
})();
</script>
