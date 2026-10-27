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
{{-- Record Meter Reading: one photo of the meter fills in everything. Nothing is typed. --}}
<div class="modal fade" id="addReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.readings.store') }}" id="readingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Record Meter Reading</h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        @csrf
        <input type="hidden" name="photo" id="photo">
        <input type="hidden" name="expect_meter" id="expect_meter">
        <input type="hidden" name="billing_period" id="billing_period" value="{{ currentBillingPeriod() }}">
        <input type="hidden" name="reading_date" id="reading_date" value="{{ date('Y-m-d') }}">
        <div class="modal-body">
          <div id="savedNote" class="alert alert-success py-2 small" role="status" hidden></div>
          <div id="expectNote" class="alert alert-info py-2 small" hidden></div>

          <div class="meter-photo" data-photo="record">
            <img alt="Photo of the meter" data-photo-preview hidden>
            <div class="meter-photo-empty" data-photo-empty>
              <i class="ph ph-camera" aria-hidden="true"></i>
              <span>Take a clear photo of the meter dial. The <strong>black numbers</strong> and the <strong>meter number</strong> must both show.</span>
            </div>
            <div class="meter-photo-busy" data-photo-busy hidden><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Reading the meter…</div>
          </div>
          <input type="file" accept="image/*" capture="environment" hidden data-photo-input="record">
          <button type="button" class="btn btn-primary btn-lg w-100 mt-2" data-photo-take="record"><i class="ph ph-camera" aria-hidden="true"></i> <span>Take Photo of Meter</span></button>
          <div class="meter-photo-msg" data-photo-msg="record" role="status"></div>

          {{-- Everything below is filled in from the photo and cannot be typed over. --}}
          <div class="meter-result" id="meterResult" hidden>
            <div class="meter-consumer">
              <div class="label">Consumer</div>
              <div class="name" id="consumerName"></div>
              <div class="meta" id="consumerMeta"></div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label for="previous_reading" class="form-label">Previous Reading</label>
                <input type="text" id="previous_reading" class="form-control" readonly tabindex="-1">
              </div>
              <div class="col-6">
                <label for="current_reading" class="form-label">Present Reading <i class="ph ph-camera text-muted" title="Read from the photo" aria-label="read from the photo"></i></label>
                <input type="text" id="current_reading" class="form-control fw-bold" readonly tabindex="-1">
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
          </div>

          <div class="row g-3 mt-1">
            <div class="col-6">
              <label class="form-label" for="periodShown">Billing Period</label>
              <input type="text" id="periodShown" class="form-control" value="{{ billingPeriodLabel(currentBillingPeriod()) }}" readonly tabindex="-1">
            </div>
            <div class="col-6">
              <label class="form-label" for="dateShown">Reading Date</label>
              <input type="text" id="dateShown" class="form-control" value="{{ formatDate(date('Y-m-d')) }}" readonly tabindex="-1">
            </div>
          </div>
          <div class="mt-3">
            <label for="remarks" class="form-label">Remarks</label>
            <input type="text" id="remarks" name="remarks" class="form-control" maxlength="255" placeholder="Optional notes (e.g. meter leaking, gate locked)">
          </div>
          <p class="text-muted mt-3 mb-0" style="font-size:12px;">Saving keeps the reading on this phone right away and sends it in the background — even with slow or no internet. Without internet, the photo is read once it is sent.</p>
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

{{-- Correct a reading: a new photo of the same meter replaces the present reading. --}}
<div class="modal fade" id="editReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen-sm-down">
    <div class="modal-content">
      <form method="POST" action="" id="editReadingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Correct Reading — <span id="er_meter"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        @csrf @method('PUT')
        <input type="hidden" name="photo" id="er_photo">
        <div class="modal-body">
          <p class="small text-muted mb-2">Take a new photo of this meter. Its reading replaces the present reading; nothing is typed.</p>
          <div class="d-flex justify-content-between small mb-3 p-2" style="background:var(--bg);border-radius:8px;">
            <span>Previous: <strong id="er_prev"></strong></span><span>Present now: <strong id="er_curr"></strong></span>
          </div>
          <div class="meter-photo" data-photo="correct">
            <img alt="New photo of the meter" data-photo-preview hidden>
            <div class="meter-photo-empty" data-photo-empty><i class="ph ph-camera" aria-hidden="true"></i><span>New photo of the meter dial</span></div>
            <div class="meter-photo-busy" data-photo-busy hidden><span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Reading the meter…</div>
          </div>
          <input type="file" accept="image/*" capture="environment" hidden data-photo-input="correct">
          <button type="button" class="btn btn-primary w-100 mt-2" data-photo-take="correct"><i class="ph ph-camera" aria-hidden="true"></i> <span>Take New Photo</span></button>
          <div class="meter-photo-msg" data-photo-msg="correct" role="status"></div>
          <div class="mb-3 mt-3"><label class="form-label" for="er_remarks">Remarks</label><input type="text" name="remarks" id="er_remarks" class="form-control" maxlength="255"></div>
          <p class="text-muted mb-0" style="font-size:12px;">If the bill has no payments yet, it is re-calculated automatically.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="er_save" disabled>Update Reading</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endif
@endsection

@if ($isReader)
@push('scripts')
<script>
(function () {
  const tariff = {{ Js::from($tariff) }};
  const scanUrl = {{ Js::from(route('admin.readings.scan')) }};
  const lookupUrl = {{ Js::from(route('admin.readings.lookup')) }};
  const csrf = document.querySelector('meta[name="csrf-token"]').content;
  const peso = n => '₱' + Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const num = n => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const $ = id => document.getElementById(id);
  const TIPS = 'Wipe the meter glass, avoid glare, and keep the black numbers and the meter number in the photo.';

  // Same formula as the server (BillingService::computeBillAmount).
  function billTotal(consumption, isSenior) {
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

  /** The phone's photo, made smaller (sharp enough to read the numbers) as a JPEG data URL. */
  function shrink(file) {
    return new Promise((resolve, reject) => {
      const url = URL.createObjectURL(file);
      const img = new Image();
      img.onload = () => {
        const max = 1280, scale = Math.min(1, max / Math.max(img.naturalWidth, img.naturalHeight));
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(img.naturalWidth * scale);
        canvas.height = Math.round(img.naturalHeight * scale);
        canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
        URL.revokeObjectURL(url);
        resolve(canvas.toDataURL('image/jpeg', 0.8));
      };
      img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('image')); };
      img.src = url;
    });
  }

  /**
   * One "take a photo → read it on the server" box. onRead(data) gets what was read;
   * onOffline(dataUrl) is called when there is no internet (or the reader is busy).
   */
  function photoBox(key, opts) {
    const box = document.querySelector('[data-photo="' + key + '"]');
    const input = document.querySelector('[data-photo-input="' + key + '"]');
    const btn = document.querySelector('[data-photo-take="' + key + '"]');
    const msg = document.querySelector('[data-photo-msg="' + key + '"]');
    const preview = box.querySelector('[data-photo-preview]'), empty = box.querySelector('[data-photo-empty]'), busy = box.querySelector('[data-photo-busy]');
    let request = 0;

    const say = (html, kind) => { msg.className = 'meter-photo-msg' + (kind ? ' is-' + kind : ''); msg.innerHTML = html || ''; };
    function reset() {
      request++;
      preview.hidden = true; preview.removeAttribute('src'); empty.hidden = false; busy.hidden = true;
      btn.querySelector('span').textContent = opts.takeLabel; btn.disabled = false; say('');
    }

    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', async () => {
      const file = input.files[0];
      input.value = '';
      if (!file) return;
      const mine = ++request;
      opts.onStart();
      say('');
      let dataUrl;
      try { dataUrl = await shrink(file); } catch (e) { say('This picture could not be opened. Please take the photo again.', 'error'); return; }
      if (mine !== request) return;
      preview.src = dataUrl; preview.hidden = false; empty.hidden = true;
      btn.querySelector('span').textContent = 'Retake Photo';

      if (navigator.onLine === false) { opts.onOffline && opts.onOffline(dataUrl); return; }
      busy.hidden = false; btn.disabled = true;
      let res = null;
      try {
        const r = await fetch(scanUrl, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
          body: JSON.stringify(Object.assign({ photo_data: dataUrl }, opts.extra())),
        });
        res = { status: r.status, data: await r.json().catch(() => null) };
      } catch (e) { res = null; }
      if (mine !== request) return;
      busy.hidden = true; btn.disabled = false;

      if (res && res.status === 200 && res.data && res.data.ok) { opts.onRead(res.data); return; }
      if (res && (res.status === 401 || res.status === 419)) { say('Your session ended. Please log in again.', 'error'); return; }
      if (!res || res.status === 503 || res.status >= 500) {
        // No internet or the reader is busy: keep the photo on the phone and read it when it is sent.
        if (opts.onOffline) { opts.onOffline(dataUrl); return; }
        say('The photo could not be read right now. Check your internet and take the photo again.', 'error');
        return;
      }
      say('<i class="ph ph-warning" aria-hidden="true"></i> ' + esc((res.data && res.data.message) || 'The photo could not be read.') , 'error');
      opts.onFail && opts.onFail();
    });
    return { reset, say };
  }

  // ======================= Record Meter Reading =======================
  const outbox = window.AwasOutbox && window.AwasOutbox.available ? window.AwasOutbox : null;
  const params = new URLSearchParams(location.search);
  const back = params.get('back');
  const expectMeter = (params.get('meter') || '').replace(/\D/g, '');
  let scanned = null, offlinePhoto = null, saving = false;

  function showExpected() {
    if (!expectMeter) return;
    $('expect_meter').value = expectMeter;
    const note = name => { $('expectNote').innerHTML = '<i class="ph ph-info" aria-hidden="true"></i> Recording <strong>Meter ' + esc(expectMeter) + '</strong>'
      + (name ? ' — ' + esc(name) : '') + '. Take the photo of this meter.'; $('expectNote').hidden = false; };
    const local = outbox ? outbox.findMeter(expectMeter) : null;
    if (local) { note(local.name); return; }
    note('');
    fetch(lookupUrl + '?meter=' + encodeURIComponent(expectMeter), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json()).then(d => { if (d.found) note(d.name); }).catch(() => {});
  }

  function clearResult() {
    scanned = null; offlinePhoto = null;
    $('photo').value = '';
    $('meterResult').hidden = true;
    $('saveReadingBtn').disabled = true;
    $('saveReadingBtn').textContent = 'Save Reading';
  }

  const record = photoBox('record', {
    takeLabel: 'Take Photo of Meter',
    extra: () => ({ billing_period: $('billing_period').value, expect_meter: expectMeter }),
    onStart: clearResult,
    onFail: clearResult,
    onRead(d) {
      scanned = d;
      $('photo').value = d.photo;
      $('consumerName').textContent = d.name;
      $('consumerMeta').innerHTML = 'Meter <strong>' + esc(d.meter) + '</strong> · ' + esc(d.purok) + ' · ' + esc(d.consumer_type)
        + (d.is_senior ? ' · <span class="badge badge-info">Senior</span>' : '')
        + (d.last_period ? '<div class="text-muted">Last reading: ' + esc(d.last_period) + '</div>' : '');
      const used = d.present_reading - d.previous_reading;
      $('previous_reading').value = num(d.previous_reading);
      $('current_reading').value = num(d.present_reading);
      $('calcConsumption').textContent = used.toFixed(2) + ' m³';
      $('calcTotal').textContent = peso(billTotal(used, d.is_senior));
      $('calcBalance').textContent = d.balance > 0 ? peso(d.balance) : '₱0.00 (no unpaid bills)';
      $('calcBalance').style.color = d.balance > 0 ? '#e5533d' : '';
      $('meterResult').hidden = false;
      $('saveReadingBtn').disabled = false;
      record.say('<i class="ph ph-check-circle" aria-hidden="true"></i> Read from the photo. Check that this is <strong>' + esc(d.name) + '</strong>\'s meter, then save.', 'ok');
    },
    onOffline(dataUrl) {
      if (!outbox) { record.say('No internet, and this phone cannot keep readings. Take the photo again when you have signal.', 'error'); return; }
      offlinePhoto = dataUrl;
      $('saveReadingBtn').disabled = false;
      $('saveReadingBtn').textContent = 'Save Photo on Phone';
      record.say('<i class="ph ph-wifi-slash" aria-hidden="true"></i> No internet right now. Save the photo on this phone — the meter is read automatically when it is sent. '
        + 'If it cannot be read then, it shows under <strong>Readings on this phone</strong> and you will need to retake it.', 'warn');
    },
  });

  $('readingForm').addEventListener('submit', e => {
    if (!scanned && !offlinePhoto) { e.preventDefault(); return; }

    // Unusual reading? Ask before saving: no water used at all, or far more than usual.
    if (scanned) {
      const used = scanned.present_reading - scanned.previous_reading, usual = scanned.usual_consumption || 0;
      let ask = '';
      if (used === 0) ask = 'This reading shows 0 m³ used (same as last time).';
      else if (usual > 0 && used > Math.max(usual * 2, usual + 10)) {
        ask = 'This reading shows ' + used.toFixed(2) + ' m³ used — much more than usual (about ' + usual.toFixed(2) + ' m³ a month).';
      }
      if (ask && !confirm(ask + '\n\nCheck the meter (and for leaks). Save this reading anyway?')) { e.preventDefault(); return; }
    }

    // Save on the phone at once; it is sent in the background.
    const item = outbox && outbox.add(scanned ? {
      meter: scanned.meter, name: scanned.name, period: $('billing_period').value, prev: scanned.previous_reading, curr: scanned.present_reading,
      date: $('reading_date').value, remarks: $('remarks').value.trim(), photo: scanned.photo,
    } : {
      meter: expectMeter, name: '', period: $('billing_period').value, prev: null, curr: null,
      date: $('reading_date').value, remarks: $('remarks').value.trim(), photoData: offlinePhoto, expectMeter,
    });
    if (item) {
      e.preventDefault();
      if (back && back.indexOf(location.origin + '/') === 0) { saving = true; location.href = back; return; }
      $('savedNote').innerHTML = '<i class="ph ph-check-circle" aria-hidden="true"></i> Saved on this phone: '
        + (item.curr != null ? '<strong>Meter ' + esc(item.meter) + '</strong> (' + esc(item.name) + '), ' + (item.curr - item.prev).toFixed(2) + ' m³.'
          : 'meter photo — it is read when sent.') + ' Take the next meter\'s photo.';
      $('savedNote').hidden = false;
      $('remarks').value = '';
      record.reset();
      clearResult();
      return;
    }
    if (offlinePhoto) {
      e.preventDefault();
      record.say('This phone\'s storage is full. Connect to the internet so the readings on this phone are sent, then try again.', 'error');
      return;
    }
    saving = true;
    $('saveReadingBtn').disabled = true;
    $('saveReadingBtn').textContent = 'Saving…';
  });

  $('addReadingModal').addEventListener('hidden.bs.modal', () => { $('savedNote').hidden = true; });
  if (params.get('record') === '1') {
    showExpected();
    document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance($('addReadingModal')).show());
  }
  // Opened from the Consumer List: closing the form goes back there.
  if (back && back.indexOf(location.origin + '/') === 0) {
    $('addReadingModal').addEventListener('hidden.bs.modal', () => { if (!saving) location.href = back; });
  }

  // ======================= Correct a reading =======================
  let correcting = null;
  const correct = photoBox('correct', {
    takeLabel: 'Take New Photo',
    extra: () => ({ reading_id: correcting ? correcting.reading_id : null }),
    onStart: () => { $('er_photo').value = ''; $('er_save').disabled = true; },
    onFail: () => { $('er_photo').value = ''; $('er_save').disabled = true; },
    onRead(d) {
      $('er_photo').value = d.photo;
      $('er_save').disabled = false;
      correct.say('<i class="ph ph-check-circle" aria-hidden="true"></i> New reading from the photo: <strong>' + num(d.present_reading) + '</strong> (was '
        + num(correcting.current_reading) + '), ' + (d.present_reading - d.previous_reading).toFixed(2) + ' m³ used.', 'ok');
    },
  });

  window.openEditReading = function (r, action) {
    correcting = r;
    $('editReadingForm').action = action;
    $('er_meter').textContent = r.meter_number ? 'Meter ' + r.meter_number : '';
    $('er_prev').textContent = num(r.previous_reading);
    $('er_curr').textContent = num(r.current_reading);
    $('er_remarks').value = r.remarks || '';
    $('er_photo').value = '';
    $('er_save').disabled = true;
    correct.reset();
    bootstrap.Modal.getOrCreateInstance($('editReadingModal')).show();
  };
})();
</script>
@endpush
@endif
