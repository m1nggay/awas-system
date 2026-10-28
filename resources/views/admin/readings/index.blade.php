@extends('layouts.app')

@section('title', auth()->user()->isAdmin() ? 'Recorded Readings' : 'Meter Readings')

@section('content')
@php $isReader = auth()->user()->isMeterReader(); $isAdmin = auth()->user()->isAdmin(); $canManage = $isReader; @endphp
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
        @foreach (['unpaid' => 'Unpaid', 'partially_paid' => 'Partially Paid', 'paid' => 'Paid', 'overdue' => 'Overdue', 'review' => 'Needs review (photo)'] as $v => $l)
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
          <div class="meter-result" id="meterResult">
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label for="meterShown" class="form-label">Meter Number</label>
                <input type="text" id="meterShown" class="form-control" readonly tabindex="-1" placeholder="From photo">
              </div>
              <div class="col-6">
                <label for="consumerName" class="form-label">Consumer Name</label>
                <input type="text" id="consumerName" class="form-control fw-bold" readonly tabindex="-1" placeholder="From photo">
              </div>
              <div class="col-12 small text-muted mt-1" id="consumerMeta"></div>
            </div>
            <div class="row g-3 mb-3">
              <div class="col-6">
                <label for="previous_reading" class="form-label">Previous Reading</label>
                <input type="text" id="previous_reading" class="form-control" readonly tabindex="-1" placeholder="From photo">
              </div>
              <div class="col-6">
                <label for="current_reading" class="form-label">Present Reading <i class="ph ph-camera text-muted" title="Read from the photo" aria-label="read from the photo"></i></label>
                <input type="text" id="current_reading" class="form-control fw-bold" readonly tabindex="-1" placeholder="From photo">
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
              <label class="form-label" for="periodShown">Month of</label>
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
          <p class="text-muted mt-3 mb-0" style="font-size:12px;">All fields are filled in automatically from the photo and cannot be typed. To change them, retake the photo.</p>
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
  const checkUrl = {{ Js::from(route('admin.readings.check')) }};
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
   * How good the camera picture inside the square is, from a small grey copy of it:
   * light, glare on the glass, something to read (contrast), and sharpness.
   * Sharpness is compared with the sharpest frame seen so far, so it works on any phone camera.
   */
  const CHECK = 160;
  function checkFrame(ctx, best) {
    const px = ctx.getImageData(0, 0, CHECK, CHECK).data;
    const g = new Float32Array(CHECK * CHECK);
    let sum = 0, sq = 0, glare = 0;
    for (let i = 0, j = 0; i < px.length; i += 4, j++) {
      const v = 0.299 * px[i] + 0.587 * px[i + 1] + 0.114 * px[i + 2];
      g[j] = v; sum += v; sq += v * v; if (v >= 250) glare++;
    }
    const n = g.length, mean = sum / n, contrast = Math.sqrt(Math.max(0, sq / n - mean * mean));
    let lap = 0, lapSq = 0, m = 0;
    for (let y = 1; y < CHECK - 1; y++) {
      for (let x = 1; x < CHECK - 1; x++) {
        const k = y * CHECK + x;
        const l = 4 * g[k] - g[k - 1] - g[k + 1] - g[k - CHECK] - g[k + CHECK];
        lap += l; lapSq += l * l; m++;
      }
    }
    const sharp = lapSq / m - (lap / m) * (lap / m);
    best.sharp = Math.max(best.sharp * 0.995, sharp);   // slowly forget, in case the light changes

    if (mean < 55) return 'Too dark. Turn on the light or move out of the shadow.';
    if (glare / n > 0.03) return 'Glare on the glass. Tilt the phone a little.';
    if (contrast < 22) return 'Point the camera at the meter dial.';
    if (sharp < 15 || sharp < best.sharp * 0.35) return 'Blurry. Hold the phone steady.';
    return '';
  }

  /**
   * One "take a photo → read it on the server" box. onRead(data) gets what was read.
   * The phone camera is shown live with a square: green when the picture is clear and a meter with
   * readable numbers is in it, red (with what to fix) when not. Only what is inside the square is sent to be read.
   * Phones without a live camera here use the camera app instead (the file input).
   */
  function photoBox(key, opts) {
    const box = document.querySelector('[data-photo="' + key + '"]');
    const input = document.querySelector('[data-photo-input="' + key + '"]');
    const btn = document.querySelector('[data-photo-take="' + key + '"]');
    const msg = document.querySelector('[data-photo-msg="' + key + '"]');
    const preview = box.querySelector('[data-photo-preview]'), empty = box.querySelector('[data-photo-empty]'), busy = box.querySelector('[data-photo-busy]');
    const label = btn.querySelector('span');

    box.insertAdjacentHTML('beforeend', '<video class="meter-cam" playsinline muted hidden></video>'
      + '<div class="meter-cam-square" hidden></div>'
      + '<div class="meter-cam-hint" hidden></div>'
      + '<button type="button" class="meter-cam-light" hidden aria-label="Flashlight"><i class="ph ph-flashlight" aria-hidden="true"></i></button>');
    const video = box.querySelector('.meter-cam'), square = box.querySelector('.meter-cam-square'),
      hint = box.querySelector('.meter-cam-hint'), light = box.querySelector('.meter-cam-light');
    const small = document.createElement('canvas'); small.width = small.height = CHECK;
    const smallCtx = small.getContext('2d', { willReadFrequently: true });

    let request = 0, stream = null, timer = null, good = false, recent = [], redSince = 0, torch = false;
    let ai = {};
    let useCameraApp = !(navigator.mediaDevices && navigator.mediaDevices.getUserMedia && window.isSecureContext);
    const best = { sharp: 0 };

    const say = (html, kind) => { msg.className = 'meter-photo-msg' + (kind ? ' is-' + kind : ''); msg.innerHTML = html || ''; };
    const mark = state => { box.classList.remove('is-good', 'is-bad'); if (state) box.classList.add('is-' + state); };

    function stop() {
      clearTimeout(timer); timer = null;
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = null; torch = false;
      video.srcObject = null;
      [video, square, hint, light].forEach(el => { el.hidden = true; });
      box.classList.remove('is-live');
      btn.classList.remove('btn-success');
    }
    function reset() {
      request++;
      stop(); mark(null);
      preview.hidden = true; preview.removeAttribute('src'); empty.hidden = false; busy.hidden = true;
      label.textContent = opts.takeLabel; btn.disabled = false; say('');
    }

    /** The visible square of the camera picture (the box shows the middle of it). */
    function squareOf() {
      const w = video.videoWidth, h = video.videoHeight, side = Math.min(w, h);
      return { x: (w - side) / 2, y: (h - side) / 2, side };
    }

    /**
     * Is a meter with readable numbers inside the square? A small copy of the square is asked on the
     * server (at most every 2 seconds, only while the picture is clear). If that can't be asked
     * (no internet, service busy), the square goes by picture quality alone.
     */
    function askMeter(s, inner) {
      ai.busy = true;
      const sent = Date.now(), mine = stream;
      const c = document.createElement('canvas'); c.width = c.height = 384;
      c.getContext('2d').drawImage(video, s.x + (s.side - inner) / 2, s.y + (s.side - inner) / 2, inner, inner, 0, 0, 384, 384);
      fetch(checkUrl, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({ photo_data: c.toDataURL('image/jpeg', 0.75) }),
      }).then(r => r.json()).then(d => {
        if (mine !== stream) return;
        if (!d || d.available === false) { ai.off = true; return; }
        ai.ok = !!d.ok; ai.hint = d.hint || ''; ai.at = sent; ai.fails = 0;
      }).catch(() => { if (mine === stream && ++ai.fails >= 2) ai.off = true; })
        .finally(() => { ai.busy = false; ai.sent = sent; });
    }

    function watch() {
      if (!stream) return;
      if (video.videoWidth) {
        const s = squareOf(), inner = s.side * 0.8;   // the drawn square is 80% of the picture
        smallCtx.drawImage(video, s.x + (s.side - inner) / 2, s.y + (s.side - inner) / 2, inner, inner, 0, 0, CHECK, CHECK);
        const problem = checkFrame(smallCtx, best);
        recent.push(problem); if (recent.length > 4) recent.shift();
        // The picture is clear after 3 good checks in a row; one bad check is enough to lose it.
        const clear = recent.length >= 3 && recent.slice(-3).every(p => p === '');
        const now = Date.now();
        if (clear && !ai.off && !ai.busy && now - ai.sent > 2000) askMeter(s, inner);
        const known = ai.at && now - ai.at < 4000;   // the meter check is about the last few seconds only
        // Green only when the picture is clear AND a meter with readable numbers is in the square.
        good = clear && (ai.off || (known && ai.ok));
        if (good) redSince = 0; else if (!redSince) redSince = now;
        mark(good ? 'good' : 'bad');
        hint.textContent = !clear ? (problem || recent.find(p => p) || 'Hold steady…')
          : ai.off ? 'Clear. Make sure the meter numbers are inside the square.'
          : !known ? 'Looking for the meter numbers…'
          : ai.ok ? 'Meter numbers readable. Tap Capture.' : (ai.hint || 'No meter numbers in view.');
        btn.classList.toggle('btn-success', good);
        // Never stuck: after 6 seconds of red, capturing is allowed anyway.
        const anyway = !good && redSince && Date.now() - redSince > 6000;
        btn.disabled = !good && !anyway;
        label.textContent = good ? 'Capture' : anyway ? 'Capture Anyway' : 'Capture';
      }
      timer = setTimeout(watch, 200);
    }

    async function startCamera() {
      const mine = ++request;
      opts.onStart();
      say(''); mark(null);
      preview.hidden = true; empty.hidden = true;
      btn.disabled = true; label.textContent = 'Starting camera…';
      try {
        stream = await navigator.mediaDevices.getUserMedia({ audio: false,
          video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } } });
      } catch (e) {
        stream = null;
        if (mine !== request) return;
        useCameraApp = true;
        empty.hidden = false; btn.disabled = false; label.textContent = opts.takeLabel;
        say('The camera could not be opened here. Tap the button again to use the phone\'s camera app.', 'warn');
        return;
      }
      if (mine !== request) { stream.getTracks().forEach(t => t.stop()); stream = null; return; }
      const track = stream.getVideoTracks()[0];
      track.applyConstraints({ advanced: [{ focusMode: 'continuous' }] }).catch(() => {});
      const caps = track.getCapabilities ? track.getCapabilities() : {};
      light.hidden = !caps.torch;
      video.srcObject = stream;
      video.hidden = false; square.hidden = false; hint.hidden = false;
      box.classList.add('is-live');
      hint.textContent = 'Fit the whole meter dial inside the square.';
      recent = []; good = false; redSince = 0; best.sharp = 0;
      ai = { ok: false, hint: '', at: 0, sent: 0, busy: false, off: false, fails: 0 };
      await video.play().catch(() => {});
      watch();
    }

    light.addEventListener('click', () => {
      if (!stream) return;
      torch = !torch;
      stream.getVideoTracks()[0].applyConstraints({ advanced: [{ torch }] }).catch(() => {});
      light.classList.toggle('is-on', torch);
    });

    /** What is inside the square, as a JPEG (up to 1280 px: sharp enough for the numbers). */
    function capture() {
      const s = squareOf(), out = Math.min(1280, Math.round(s.side));
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = out;
      canvas.getContext('2d').drawImage(video, s.x, s.y, s.side, s.side, 0, 0, out, out);
      return canvas.toDataURL('image/jpeg', 0.9);
    }

    btn.addEventListener('click', () => {
      if (stream) { const dataUrl = capture(); stop(); send(dataUrl); return; }
      if (useCameraApp) { input.click(); return; }
      startCamera();
    });
    input.addEventListener('change', async () => {
      const file = input.files[0];
      input.value = '';
      if (!file) return;
      opts.onStart();
      let dataUrl;
      try { dataUrl = await shrink(file); } catch (e) { say('This picture could not be opened. Please take the photo again.', 'error'); return; }
      send(dataUrl);
    });

    async function send(dataUrl) {
      const mine = ++request;
      say(''); mark(null);
      preview.src = dataUrl; preview.hidden = false; empty.hidden = true;
      label.textContent = 'Retake Photo';
      busy.hidden = false; btn.disabled = true;
      let res = null;
      if (navigator.onLine !== false) {
        // Slow internet: give up after a while instead of leaving the reader waiting.
        const ctrl = window.AbortController ? new AbortController() : null;
        const t = ctrl ? setTimeout(() => ctrl.abort(), 25000) : null;
        try {
          const r = await fetch(scanUrl, {
            method: 'POST', credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined,
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: JSON.stringify(Object.assign({ photo_data: dataUrl }, opts.extra())),
          });
          res = { status: r.status, data: await r.json().catch(() => null) };
        } catch (e) { res = null; }
        if (t) clearTimeout(t);
      }
      if (mine !== request) return;
      busy.hidden = true; btn.disabled = false;

      if (res && res.status === 200 && res.data && res.data.ok) { mark('good'); opts.onRead(res.data); return; }
      // No internet (or the reading service is down): the form may let the reader type it instead.
      const offline = !res || res.status >= 500 || (res.data && res.data.retry);
      if (offline && opts.onOffline && opts.onOffline(dataUrl, !res)) { mark(null); return; }
      mark('bad');
      opts.onFail && opts.onFail();
      if (res && (res.status === 401 || res.status === 419)) { say('Your session ended. Please log in again.', 'error'); return; }
      const why = res && res.status < 500 && res.data && res.data.message;
      say('<i class="ph ph-warning" aria-hidden="true"></i> ' + esc(why || 'The photo could not be read right now. Please take the photo again.'), 'error');
    }

    // Closing the form turns the camera off.
    const modal = box.closest('.modal');
    if (modal) modal.addEventListener('hidden.bs.modal', reset);

    /** Throw the photo away and open the camera again. */
    function retake() {
      reset();
      opts.onStart();
      if (useCameraApp) input.click(); else startCamera();
    }
    return { reset, say, retake };
  }

  // ======================= Record Meter Reading =======================
  const outbox = window.AwasOutbox && window.AwasOutbox.available ? window.AwasOutbox : null;
  const params = new URLSearchParams(location.search);
  const back = params.get('back');
  const expectMeter = (params.get('meter') || '').replace(/\D/g, '');
  let scanned = null, saving = false, typedPhoto = null;

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
    scanned = null;
    typedPhoto = null;
    ['meterShown', 'current_reading'].forEach(id => { $(id).readOnly = true; $(id).tabIndex = -1; $(id).placeholder = 'From photo'; });
    $('meterResult').classList.remove('is-typed');
    $('photo').value = '';
    ['meterShown', 'consumerName', 'previous_reading', 'current_reading'].forEach(id => { $(id).value = ''; });
    $('consumerMeta').innerHTML = '';
    $('calcConsumption').textContent = '0.00 m³';
    $('calcTotal').textContent = '₱0.00';
    $('calcBalance').textContent = '—';
    $('calcBalance').style.color = '';
    $('saveReadingBtn').disabled = true;
    $('saveReadingBtn').textContent = 'Save Reading';
  }

  function showConsumer(d) {
    $('consumerName').value = d.name;
    $('consumerMeta').innerHTML = esc(d.purok) + ' · ' + esc(d.consumer_type)
      + (d.is_senior ? ' · <span class="badge badge-info">Senior</span>' : '')
      + (d.last_period ? '<div class="text-muted">Last reading: ' + esc(d.last_period) + '</div>' : '');
    $('previous_reading').value = num(d.previous_reading);
    $('calcBalance').textContent = d.balance > 0 ? peso(d.balance) : '₱0.00 (no unpaid bills)';
    $('calcBalance').style.color = d.balance > 0 ? '#e5533d' : '';
  }
  function showTotals(d) {
    const used = d.present_reading - d.previous_reading;
    $('calcConsumption').textContent = used.toFixed(2) + ' m³';
    $('calcTotal').textContent = peso(billTotal(used, d.is_senior));
  }

  /**
   * Weak or no internet: the photo can't be read now. The reader is asked whether to type the reading
   * instead (Yes) or retake the photo (No). Typing needs the meters on the phone (Download my puroks);
   * the photo is kept with the typed reading and checked against it on the server once it is sent.
   */
  function startTyping(dataUrl, noAnswer) {
    const why = navigator.onLine === false ? 'No internet' : noAnswer ? 'Weak internet' : 'The photo reader is busy';
    if (!outbox || !outbox.meterData()) {
      record.say('<i class="ph ph-wifi-slash" aria-hidden="true"></i> ' + why + ', so the photo can\'t be read, and the meters of your puroks '
        + 'aren\'t on this phone yet. Retake the photo when the signal is better.', 'error');
      return true;
    }
    record.say('<i class="ph ph-wifi-slash" aria-hidden="true"></i> <strong>' + why + '</strong> — the photo can\'t be read right now. Type the reading instead?'
      + '<div class="d-flex gap-2 mt-2"><button type="button" class="btn btn-primary btn-sm flex-fill" data-type-yes>Yes, type it</button>'
      + '<button type="button" class="btn btn-outline btn-sm flex-fill" data-type-no>No, retake photo</button></div>', 'warn');
    const msg = document.querySelector('[data-photo-msg="record"]');
    msg.querySelector('[data-type-yes]').addEventListener('click', () => enterTyping(dataUrl, why));
    msg.querySelector('[data-type-no]').addEventListener('click', () => record.retake());
    return true;
  }

  function enterTyping(dataUrl, why) {
    typedPhoto = dataUrl;
    $('meterResult').classList.add('is-typed');
    ['meterShown', 'current_reading'].forEach(id => { $(id).readOnly = false; $(id).tabIndex = 0; });
    $('meterShown').placeholder = 'Type the meter number';
    $('current_reading').placeholder = 'Black numbers';
    if (expectMeter) $('meterShown').value = expectMeter;
    record.say('<i class="ph ph-keyboard" aria-hidden="true"></i> <strong>' + why + '</strong> — type the meter number and the '
      + '<strong>black numbers</strong>. The photo is kept and checked against them when the internet is better.', 'warn');
    typedChanged();
    $(expectMeter ? 'current_reading' : 'meterShown').focus();
  }

  /** The downloaded meter with this number (a leading 0 printed on the dial, or missing, still matches). */
  function downloadedMeter(meter) {
    const core = meter.replace(/^0+/, '');
    const data = outbox.meterData();
    return core && data ? data.consumers.find(c => c.meter.replace(/^0+/, '') === core) || null : null;
  }

  function typedChanged() {
    if (!typedPhoto) return;
    scanned = null;
    $('saveReadingBtn').disabled = true;
    ['consumerName', 'previous_reading'].forEach(id => { $(id).value = ''; });
    $('calcConsumption').textContent = '0.00 m³';
    $('calcTotal').textContent = '₱0.00';
    $('calcBalance').textContent = '—';
    const meter = $('meterShown').value.replace(/\D/g, '');
    const wrong = html => { $('consumerMeta').innerHTML = '<span class="text-danger">' + html + '</span>'; };
    if (!meter) { $('consumerMeta').innerHTML = ''; return; }

    const c = downloadedMeter(meter);
    if (!c) return wrong('No meter ' + esc(meter) + ' in your puroks.');
    if (expectMeter && c.meter.replace(/^0+/, '') !== expectMeter.replace(/^0+/, '')) return wrong('You are recording meter ' + esc(expectMeter) + '.');
    const period = $('billing_period').value;
    const d = { typed: true, meter: c.meter, name: c.name, purok: c.purok, consumer_type: c.consumer_type, is_senior: c.is_senior,
      previous_reading: Number(c.previous_reading), last_period: c.last_period, balance: c.balance, usual_consumption: c.usual_consumption };
    showConsumer(d);
    if (!c.active) return wrong(esc(c.name) + '\'s account is not active.');
    if (c.read_periods.includes(period)) return wrong(esc(c.name) + ' already has a reading for ' + esc(outbox.periodLabel(period)) + '.');

    const typed = $('current_reading').value.replace(/,/g, '').trim();
    if (typed === '' || !/^\d+(\.\d{1,2})?$/.test(typed)) return;
    d.present_reading = Number(typed);
    if (d.present_reading < d.previous_reading) {
      $('consumerMeta').insertAdjacentHTML('beforeend', '<div class="text-danger">Lower than the previous reading (' + num(d.previous_reading) + ').</div>');
      return;
    }
    showTotals(d);
    scanned = d;
    $('saveReadingBtn').disabled = false;
  }
  ['meterShown', 'current_reading'].forEach(id => $(id).addEventListener('input', typedChanged));

  function savedOnPhone(item, typed) {
    if (back && back.indexOf(location.origin + '/') === 0) { saving = true; location.href = back; return; }
    $('savedNote').innerHTML = '<i class="ph ph-check-circle" aria-hidden="true"></i> Saved: <strong>Meter ' + esc(item.meter) + '</strong> ('
      + esc(item.name) + '), ' + (item.curr - item.prev).toFixed(2) + ' m³.'
      + (typed ? ' It is sent, and its photo checked, when you\'re back online.' : '') + ' Take the next meter\'s photo.';
    $('savedNote').hidden = false;
    $('remarks').value = '';
    record.reset();
    clearResult();
  }

  const record = photoBox('record', {
    takeLabel: 'Take Photo of Meter',
    extra: () => ({ billing_period: $('billing_period').value, expect_meter: expectMeter }),
    onStart: clearResult,
    onFail: clearResult,
    onOffline: startTyping,
    onRead(d) {
      scanned = d;
      $('photo').value = d.photo;
      $('meterShown').value = d.meter;
      $('current_reading').value = num(d.present_reading);
      showConsumer(d);
      showTotals(d);
      $('saveReadingBtn').disabled = false;
      if (d.turning) {
        record.say('<i class="ph ph-warning" aria-hidden="true"></i> A number wheel was turning between two digits. Check that the present reading <strong>'
          + num(d.present_reading) + '</strong> matches the meter, then save. If not, retake the photo.', 'warn');
      } else {
        record.say('<i class="ph ph-check-circle" aria-hidden="true"></i> Read from the photo. Check that this is <strong>' + esc(d.name) + '</strong>\'s meter, then save.', 'ok');
      }
    },
  });

  $('readingForm').addEventListener('submit', e => {
    if (!scanned) { e.preventDefault(); return; }

    // Unusual reading? Ask before saving: no water used at all, or far more than usual.
    const used = scanned.present_reading - scanned.previous_reading, usual = scanned.usual_consumption || 0;
    let ask = '';
    if (used === 0) ask = 'This reading shows 0 m³ used (same as last time).';
    else if (usual > 0 && used > Math.max(usual * 2, usual + 10)) {
      ask = 'This reading shows ' + used.toFixed(2) + ' m³ used — much more than usual (about ' + usual.toFixed(2) + ' m³ a month).';
    }
    if (ask && !confirm(ask + '\n\nCheck the meter (and for leaks). Save this reading anyway?')) { e.preventDefault(); return; }

    const reading = {
      meter: scanned.meter, name: scanned.name, period: $('billing_period').value, prev: scanned.previous_reading, curr: scanned.present_reading,
      date: $('reading_date').value, remarks: $('remarks').value.trim(), photo: scanned.photo,
    };
    // Typed without internet: kept on the phone with its photo until it can be sent.
    if (scanned.typed) {
      e.preventDefault();
      $('saveReadingBtn').disabled = true;
      outbox.addTyped(reading, typedPhoto).then(item => {
        if (item) { savedOnPhone(item, true); return; }
        $('saveReadingBtn').disabled = false;
        record.say('<i class="ph ph-warning" aria-hidden="true"></i> This phone could not keep the photo (storage full or blocked). Free up space, or record this meter when you have internet.', 'error');
      });
      return;
    }

    // Save on the phone at once; it is sent in the background.
    const item = outbox && outbox.add(reading);
    if (item) {
      e.preventDefault();
      savedOnPhone(item, false);
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
