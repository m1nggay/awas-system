@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Recorded Readings' : 'Meter Readings')

@section('content')
@php $isReader = auth()->user()->isMeterReader(); $isAdmin = auth()->user()->isAdmin(); $canManage = $isReader; @endphp
{{-- Readings saved on this phone and their sending status (reading-outbox.js). --}}
@if ($isReader)<div id="outboxPanel"></div>@endif
<div class="card">
  @if ($isReader)
    <div class="card-header justify-content-end">
      <button class="btn btn-primary btn-sm btn-phone-block" data-bs-toggle="modal" data-bs-target="#addReadingModal">+ Record Reading</button>
    </div>
  @endif
  <div class="card-body">
    <form method="GET" action="{{ route('admin.readings.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3 filter-bar" id="readingFilter">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text"><i class="ph ph-magnifying-glass" aria-hidden="true"></i></span>
        <input type="search" name="q" class="form-control" placeholder="Search by Meter Number or Name..." value="{{ $search }}" aria-label="Search meter readings">
        <button type="submit" class="btn btn-primary">Search</button>
      </div>
      <select name="purok" class="form-select w-auto" onchange="this.form.submit()">
        <option value="0">All Puroks</option>
        @foreach ($puroks as $pk)
          <option value="{{ $pk->purok_id }}" @selected($purokFil === (int)$pk->purok_id)>{{ $pk->purok_name }}</option>
        @endforeach
      </select>
      <select name="status" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Status</option>
        @foreach (['unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $v => $l)
          <option value="{{ $v }}" @selected($statusFil === $v)>{{ $l }}</option>
        @endforeach
      </select>
      <select name="period" class="form-select w-auto" onchange="this.form.submit()">
        <option value="">All Periods</option>
        @foreach ($periods as $p)
          <option value="{{ $p }}" @selected($periodFil === $p)>{{ billingPeriodLabel($p) }}</option>
        @endforeach
      </select>
      @if ($search !== '' || $purokFil || $statusFil !== '' || $periodFil !== '')
        <a href="{{ route('admin.readings.index') }}" class="btn btn-outline btn-sm">Clear</a>
      @endif
    </form>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0 data-table {{ $isReader ? 'table-stack' : '' }}" @if ($isAdmin && $readings->currentPage() === 1 && $search === '' && !$purokFil && $statusFil === '' && $periodFil === '') data-live-readings @endif>
        <thead>
          <tr>
            <th>No.</th><th>Meter Number</th><th>Name</th><th>Previous Reading</th><th>Present Reading</th>
            <th>Consumption</th><th>Total</th><th>Balance</th><th>Status</th><th>Date</th>
            @if ($canManage)<th>Actions</th>@endif
          </tr>
        </thead>
        <tbody>
        @forelse ($readings as $i => $r)
          @include('admin.readings._row', ['r' => $r, 'no' => $readings->firstItem() + $i, 'canManage' => $canManage])
        @empty
          <tr class="empty-row"><td colspan="{{ $canManage ? 11 : 10 }}">No meter readings found{{ ($search !== '' || $purokFil || $statusFil !== '' || $periodFil !== '') ? ' for this filter' : '' }}.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $readings])
  </div>
</div>

@if ($isReader)
<div class="modal fade" id="addReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.readings.store') }}" id="readingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Record Meter Reading</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <div id="savedNote" class="alert alert-success py-2 small" role="status" hidden></div>
          <div class="mb-3">
            <label for="meter_number" class="form-label">Meter Number or Name *</label>
            <div class="position-relative">
              <input type="text" id="meter_number" name="meter_number" class="form-control form-control-lg" required
                     maxlength="100" autocomplete="off" placeholder="Type a meter number or name"
                     role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="meterSuggest">
              <div id="meterSuggest" class="list-group position-absolute w-100 shadow-sm" role="listbox" hidden
                   style="z-index:1060;top:100%;max-height:260px;overflow-y:auto;"></div>
            </div>
            <div id="meterInfo" class="mt-2" style="font-size:13px;"></div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label for="previous_reading" class="form-label">Previous Reading *</label>
              <input type="number" step="0.01" min="0" inputmode="decimal" id="previous_reading" name="previous_reading" class="form-control" required>
            </div>
            <div class="col-6">
              <label for="current_reading" class="form-label">Present Reading *</label>
              <input type="number" step="0.01" min="0" inputmode="decimal" id="current_reading" name="current_reading" class="form-control" required>
            </div>
          </div>

          <div class="p-3 mb-3" style="background:#f0fbff;border:1px solid var(--border);border-radius:10px;">
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Consumption</span><strong id="calcConsumption">0.00 m³</strong></div>
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total</span><strong id="calcTotal" style="font-size:18px;color:var(--primary-dark);">₱0.00</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Unpaid Balance</span><strong id="calcBalance">—</strong></div>
            <div class="text-muted mt-2" style="font-size:11.5px;">
              Calculated automatically with the official water rate: {{ formatCurrency($tariff['minimumCharge']) }} minimum for the first
              {{ rtrim(rtrim(number_format($tariff['includedCum'], 2), '0'), '.') }} m³, then
              {{ collect($tariff['tiers'])->map(fn ($t) => formatCurrency($t['rate']) . '/m³ ' . ($t['to'] !== null
                    ? 'up to ' . rtrim(rtrim(number_format($t['to'], 2), '0'), '.') . ' m³' : 'beyond'))->join(', ') }}
              (senior citizens get {{ rtrim(rtrim(number_format($tariff['seniorPct'], 2), '0'), '.') }}% off).
            </div>
          </div>

          <div class="row g-3">
            <div class="col-md-6">
              <label for="billing_period" class="form-label">Billing Period *</label>
              <input type="month" id="billing_period" name="billing_period" class="form-control" required value="{{ date('Y-m') }}" pattern="\d{4}-\d{2}" placeholder="YYYY-MM">
            </div>
            <div class="col-md-6">
              <label for="reading_date" class="form-label">Reading Date *</label>
              <input type="date" id="reading_date" name="reading_date" class="form-control" required value="{{ date('Y-m-d') }}">
            </div>
          </div>
          <div class="mt-3">
            <label for="remarks" class="form-label">Remarks</label>
            <input type="text" id="remarks" name="remarks" class="form-control" placeholder="Optional notes (e.g. high usage, meter issue)">
          </div>
          <p class="text-muted mt-3 mb-0" style="font-size:12px;">Saving keeps the reading on this phone right away and sends it in the background — even with slow or no internet. The bill is made once it is sent.</p>
        </div>
        {{-- Opened from the Consumer List: go back there after saving. --}}
        <input type="hidden" name="back" value="{{ request('back') }}">
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveReadingBtn" disabled>Save Reading</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif

@if ($canManage)
<div class="modal fade" id="editReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="" id="editReadingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Edit Meter Reading —<span id="er_meter"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Previous Reading</label><input type="number" step="0.01" min="0" inputmode="decimal" name="previous_reading" id="er_prev" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Present Reading</label><input type="number" step="0.01" min="0" inputmode="decimal" name="current_reading" id="er_curr" class="form-control" required></div>
          </div>
          <div class="mb-3 mt-3"><label class="form-label">Reading Date</label><input type="date" name="reading_date" id="er_date" class="form-control" required></div>
          <div class="mb-3"><label class="form-label">Remarks</label><input type="text" name="remarks" id="er_remarks" class="form-control"></div>
          <p class="text-muted mb-0" style="font-size:12px;">If the bill has no payments yet, it is re-calculated automatically.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Update Reading</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
function openEditReading(r, action) {
  document.getElementById('editReadingForm').action = action;
  document.getElementById('er_meter').textContent = r.meter_number ? 'Meter ' + r.meter_number : '';
  document.getElementById('er_prev').value = r.previous_reading;
  document.getElementById('er_curr').value = r.current_reading;
  document.getElementById('er_date').value = r.reading_date;
  document.getElementById('er_remarks').value = r.remarks || '';
  bootstrap.Modal.getOrCreateInstance(document.getElementById('editReadingModal')).show();
}
</script>
@endif
@endsection

@if ($isReader)
@push('scripts')
<script>
(function () {
  const tariff = {{ Js::from($tariff) }};
  const lookupUrl = {{ Js::from(route('admin.readings.lookup')) }};
  const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const $ = id => document.getElementById(id);
  let consumer = null, timer = null;

  // Same formula as the server (BillingService::computeBillAmount).
  function billTotal(consumption, isSenior) {
    // Each tier charges only the cubic meters inside it.
    let excess = 0;
    tariff.tiers.forEach(t => {
      const top = t.to === null ? consumption : Math.min(consumption, t.to);
      const cum = Math.max(0, Math.round((top - t.lower) * 100) / 100);
      excess += Math.round(cum * t.rate * 100) / 100;
    });
    const subtotal = Math.round((tariff.minimumCharge + Math.round(excess * 100) / 100) * 100) / 100;
    const discount = isSenior ? Math.round(subtotal * tariff.seniorPct) / 100 : 0;
    return Math.round((subtotal - discount) * 100) / 100;
  }

  function recalc() {
    const prev = parseFloat($('previous_reading').value), curr = parseFloat($('current_reading').value);
    const ready = consumer && consumer.active && !consumer.already_read && !isNaN(prev) && !isNaN(curr);
    if (!isNaN(prev) && !isNaN(curr) && curr < prev) {
      $('calcConsumption').textContent = 'Present must not be lower than Previous';
      $('calcConsumption').style.color = '#e5533d';
      $('calcTotal').textContent = '—';
      $('saveReadingBtn').disabled = true;
      return;
    }
    $('calcConsumption').style.color = '';
    const consumption = (!isNaN(prev) && !isNaN(curr)) ? curr - prev : 0;
    $('calcConsumption').textContent = consumption.toFixed(2) + ' m³';
    $('calcTotal').textContent = (!isNaN(prev) && !isNaN(curr)) ? peso(billTotal(consumption, consumer && consumer.is_senior)) : '₱0.00';
    $('saveReadingBtn').disabled = !ready;
  }

  // Readings are saved on the phone and sent in the background (reading-outbox.js);
  // without browser storage the form is posted the old way.
  const outbox = window.AwasOutbox && window.AwasOutbox.available ? window.AwasOutbox : null;

  function showConsumer(d) {
    consumer = d;
    hideSuggest();
    const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    let html = '<strong>' + esc(d.name) + '</strong> · ' + esc(d.purok) + ' · ' + esc(d.consumer_type)
      + (d.is_senior ? ' · <span class="badge badge-info">Senior</span>' : '');
    if (!d.active) html += '<div class="text-danger">This account is not active.</div>';
    if (d.already_read) html += '<div class="text-danger">This meter already has a reading for the selected billing period.</div>';
    if (d.last_period) html += '<div class="text-muted">Last reading: ' + esc(d.last_period) + '</div>';
    $('meterInfo').innerHTML = html;
    $('previous_reading').value = d.previous_reading.toFixed(2);
    $('calcBalance').textContent = d.balance > 0 ? peso(d.balance) : '₱0.00 (no unpaid bills)';
    $('calcBalance').style.color = d.balance > 0 ? '#e5533d' : '';
    recalc();
    $('current_reading').focus();
  }

  function lookup() {
    const meter = $('meter_number').value.trim();
    consumer = null;
    $('calcBalance').textContent = '—';
    if (!/^\d+$/.test(meter)) { $('meterInfo').innerHTML = ''; recalc(); return; }

    // Downloaded meters: found at once, no internet needed.
    const local = outbox ? outbox.findMeter(meter) : null;
    if (local) {
      showConsumer(Object.assign({ found: true }, local, { already_read: local.read_periods.includes($('billing_period').value) }));
      return;
    }

    $('meterInfo').innerHTML = '<span class="text-muted">Looking up…</span>';
    fetch(lookupUrl + '?meter=' + encodeURIComponent(meter) + '&period=' + encodeURIComponent($('billing_period').value), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(d => {
        if (meter !== $('meter_number').value.trim()) return; // typed on meanwhile
        if (!d.found) { $('meterInfo').innerHTML = '<span class="text-danger">' + d.message + '</span>'; recalc(); return; }
        showConsumer(d);
      })
      .catch(() => {
        $('meterInfo').innerHTML = '<span class="text-danger">' + (outbox && !outbox.meterData()
          ? 'No internet, and your meters are not downloaded yet. Tap "Download my puroks" when you have signal.'
          : 'Could not look up meter ' + meter + '. Check the number, or try again when the internet is better.') + '</span>';
      });
  }

  // ---- Suggestions: type a name or part of a meter number, then pick the consumer ----
  const searchUrl = {{ Js::from(route('admin.readings.search')) }};
  const box = $('meterSuggest'), field = $('meter_number');
  const escHtml = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  let active = -1;

  function hideSuggest() { box.hidden = true; box.innerHTML = ''; active = -1; field.setAttribute('aria-expanded', 'false'); }
  function pick(meter) {
    field.value = meter;
    hideSuggest();
    lookup();
  }
  function showSuggest(q, list) {
    if (q !== field.value.trim()) return;               // typed on meanwhile
    if (consumer && consumer.found && /^\d+$/.test(q)) { hideSuggest(); return; }   // exact meter already found
    if (!list.length) {
      box.innerHTML = /^\d+$/.test(q) ? '' : '<div class="list-group-item text-muted small">No consumer in your puroks matches "' + escHtml(q) + '".</div>';
      box.hidden = !box.innerHTML; field.setAttribute('aria-expanded', String(!box.hidden));
      return;
    }
    box.innerHTML = list.map(c => '<button type="button" class="list-group-item list-group-item-action" role="option" data-meter="' + escHtml(c.meter) + '">'
      + '<strong>' + escHtml(c.name) + '</strong> <span class="text-muted small">· Meter ' + escHtml(c.meter) + ' · ' + escHtml(c.purok)
      + (c.active ? '' : ' · <span class="text-danger">not active</span>') + '</span></button>').join('');
    box.hidden = false; active = -1; field.setAttribute('aria-expanded', 'true');
  }

  function suggest() {
    const q = field.value.trim();
    if (q.length < 2) { hideSuggest(); return; }
    const local = outbox ? outbox.searchMeters(q) : null;   // downloaded meters: no internet needed
    if (local) { showSuggest(q, local); return; }
    fetch(searchUrl + '?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(list => showSuggest(q, list))
      .catch(() => hideSuggest());
  }

  box.addEventListener('mousedown', e => e.preventDefault());   // keep focus in the field while clicking
  box.addEventListener('click', e => { const b = e.target.closest('[data-meter]'); if (b) pick(b.dataset.meter); });
  field.addEventListener('keydown', e => {
    const items = [...box.querySelectorAll('[data-meter]')];
    if (box.hidden || !items.length) return;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
      items.forEach((b, i) => b.classList.toggle('active', i === active));
      items[active].scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter') {
      e.preventDefault();                                     // pick, don't submit the form
      pick((items[active] || items[0]).dataset.meter);
    } else if (e.key === 'Escape') {
      e.stopPropagation(); hideSuggest();
    }
  });
  field.addEventListener('blur', () => setTimeout(hideSuggest, 150));

  // Digits: look the meter up directly (as before). Letters: only suggestions.
  field.addEventListener('input', () => {
    clearTimeout(timer);
    timer = setTimeout(() => { lookup(); suggest(); }, 300);
  });
  $('billing_period').addEventListener('change', lookup);
  $('previous_reading').addEventListener('input', recalc);
  $('current_reading').addEventListener('input', recalc);

  // Opened from the dashboard's or the Consumer List's "Record Reading" button.
  const params = new URLSearchParams(location.search);
  if (params.get('record') === '1') {
    const meter = (params.get('meter') || '').replace(/\D/g, '');
    if (meter) { $('meter_number').value = meter; lookup(); }
    document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance($('addReadingModal')).show());
  }
  $('addReadingModal').addEventListener('shown.bs.modal', () => ($('meter_number').value ? $('current_reading') : $('meter_number')).focus());

  // Unusual reading? Ask before saving: no water used at all, or far more than usual.
  let saving = false;
  $('readingForm').addEventListener('submit', e => {
    const used = parseFloat($('current_reading').value) - parseFloat($('previous_reading').value);
    const usual = consumer ? consumer.usual_consumption : 0;
    let ask = '';
    if (used === 0) ask = 'This reading shows 0 m³ used (same as last time).';
    else if (usual > 0 && used > Math.max(usual * 2, usual + 10)) {
      ask = 'This reading shows ' + used.toFixed(2) + ' m³ used — much more than usual (about ' + usual.toFixed(2) + ' m³ a month).';
    }
    if (ask && !confirm(ask + '\n\nPlease double-check the meter. Save this reading anyway?')) { e.preventDefault(); return; }

    // Save on the phone at once; it is sent in the background.
    const item = outbox && outbox.add({
      meter: $('meter_number').value.trim(), name: consumer ? consumer.name : '',
      period: $('billing_period').value, prev: parseFloat($('previous_reading').value), curr: parseFloat($('current_reading').value),
      date: $('reading_date').value, remarks: $('remarks').value.trim(),
    });
    if (item) {
      e.preventDefault();
      if (back && back.indexOf(location.origin + '/') === 0) { saving = true; location.href = back; return; }
      $('savedNote').innerHTML = '<i class="ph ph-check-circle" aria-hidden="true"></i> Saved on this phone: <strong>Meter ' + escHtml(item.meter) + '</strong>'
        + (item.name ? ' (' + escHtml(item.name) + ')' : '') + ', ' + (item.curr - item.prev).toFixed(2) + ' m³. Sending in the background — record the next meter.';
      $('savedNote').hidden = false;
      ['meter_number', 'previous_reading', 'current_reading', 'remarks'].forEach(id => { $(id).value = ''; });
      $('meterInfo').innerHTML = '';
      consumer = null;
      $('calcBalance').textContent = '—';
      $('calcBalance').style.color = '';
      recalc();
      $('meter_number').focus();
      return;
    }

    saving = true;
    $('saveReadingBtn').disabled = true;
    $('saveReadingBtn').textContent = 'Saving…';
  });
  $('addReadingModal').addEventListener('hidden.bs.modal', () => { $('savedNote').hidden = true; });

  // Opened from the Consumer List: closing the form goes back there.
  const back = params.get('back');
  if (back && back.indexOf(location.origin + '/') === 0) {
    $('addReadingModal').addEventListener('hidden.bs.modal', () => { if (!saving) location.href = back; });
  }
})();
</script>
@endpush
@endif
