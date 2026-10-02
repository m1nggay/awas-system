@extends('layouts.app')

@section('title', 'Meter Readings')

@section('content')
@php $isReader = auth()->user()->isMeterReader(); $isAdmin = auth()->user()->isAdmin(); @endphp
<div class="card">
  <div class="card-header">
    <h3>Meter Readings</h3>
    @if ($isReader)
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addReadingModal">+ Record Reading</button>
    @endif
  </div>
  <div class="card-body">
    <form method="GET" action="{{ route('admin.readings.index') }}" class="d-flex flex-wrap gap-2 align-items-center mb-3" id="readingFilter">
      <div class="input-group flex-grow-1" style="min-width:200px;">
        <span class="input-group-text">🔍</span>
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
      <table class="table table-hover align-middle mb-0 data-table">
        <thead>
          <tr>
            <th>No.</th><th>Meter Number</th><th>Name</th><th>Previous Reading</th><th>Present Reading</th>
            <th>Consumption</th><th>Total</th><th>Balance</th><th>Status</th><th>Date</th>
            @if ($isAdmin)<th>Actions</th>@endif
          </tr>
        </thead>
        <tbody>
        @forelse ($readings as $i => $r)
          <tr>
            <td>{{ $readings->firstItem() + $i }}</td>
            <td><strong>{{ $r->meter_number ?: '—' }}</strong></td>
            <td>{{ $r->full_name }}</td>
            <td>{{ number_format($r->previous_reading, 2) }}</td>
            <td>{{ number_format($r->current_reading, 2) }}</td>
            <td class="consumption-value">{{ number_format($r->consumption, 2) }} m³</td>
            <td>{{ $r->bill_total !== null ? formatCurrency($r->bill_total) : '—' }}</td>
            <td>{{ $r->bill_total !== null ? formatCurrency(max(0, $r->bill_total - $r->bill_paid)) : '—' }}</td>
            <td>
              @if ($r->bill_status)
                <span class="badge {{ billStatusBadgeClass($r->bill_status) }}">{{ str_replace('_', ' ', $r->bill_status) }}</span>
              @else — @endif
            </td>
            <td>{{ formatDate($r->reading_date) }}<div class="text-muted" style="font-size:11px;">{{ billingPeriodLabel($r->billing_period) }}</div></td>
            @if ($isAdmin)
              <td class="actions">
                <button class="btn btn-secondary btn-sm" onclick="openEditReading({{ json_encode($r) }}, '{{ route('admin.readings.update', $r->reading_id) }}')">Correct</button>
              </td>
            @endif
          </tr>
        @empty
          <tr class="empty-row"><td colspan="{{ $isAdmin ? 11 : 10 }}">No meter readings found{{ ($search !== '' || $purokFil || $statusFil !== '' || $periodFil !== '') ? ' for this filter' : '' }}.</td></tr>
        @endforelse
        </tbody>
      </table>
    </div>

    @include('partials.pagination', ['paginator' => $readings])
  </div>
</div>

@if ($isReader)
<div class="modal fade" id="addReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('admin.readings.store') }}" id="readingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Record Meter Reading</h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf
        <div class="modal-body">
          <div class="mb-3">
            <label for="meter_number" class="form-label">Meter Number *</label>
            <input type="text" id="meter_number" name="meter_number" class="form-control form-control-lg" required
                   inputmode="numeric" pattern="\d+" maxlength="20" data-digits-only autocomplete="off" placeholder="e.g. 1001">
            <div id="meterInfo" class="mt-2" style="font-size:13px;"></div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label for="previous_reading" class="form-label">Previous Reading *</label>
              <input type="number" step="0.01" min="0" id="previous_reading" name="previous_reading" class="form-control" required>
            </div>
            <div class="col-6">
              <label for="current_reading" class="form-label">Present Reading *</label>
              <input type="number" step="0.01" min="0" id="current_reading" name="current_reading" class="form-control" required>
            </div>
          </div>

          <div class="p-3 mb-3" style="background:#f0fbff;border:1px solid var(--border);border-radius:10px;">
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Consumption</span><strong id="calcConsumption">0.00 m³</strong></div>
            <div class="d-flex justify-content-between mb-1"><span class="text-muted">Total</span><strong id="calcTotal" style="font-size:18px;color:var(--primary-dark);">₱0.00</strong></div>
            <div class="d-flex justify-content-between"><span class="text-muted">Unpaid Balance</span><strong id="calcBalance">—</strong></div>
            <div class="text-muted mt-2" style="font-size:11.5px;">
              Calculated automatically with the official water rate: {{ formatCurrency($tariff['minimumCharge']) }} minimum for the first
              {{ rtrim(rtrim(number_format($tariff['includedCum'], 2), '0'), '.') }} m³, then {{ formatCurrency($tariff['excessRate']) }} per m³
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
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveReadingBtn" disabled>Save Reading</button>
        </div>
        <p class="text-muted px-3 pb-3 mb-0" style="font-size:12px;">Saving sends the reading and the calculated bill to the administrator automatically.</p>
      </form>
    </div>
  </div>
</div>
@endif

@if ($isAdmin)
<div class="modal fade" id="editReadingModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="" id="editReadingForm">
        <div class="modal-header"><h3 class="h6 mb-0">Correct Meter Reading — <span id="er_meter"></span></h3><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        @csrf @method('PUT')
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label">Previous Reading</label><input type="number" step="0.01" min="0" name="previous_reading" id="er_prev" class="form-control" required></div>
            <div class="col-md-6"><label class="form-label">Present Reading</label><input type="number" step="0.01" min="0" name="current_reading" id="er_curr" class="form-control" required></div>
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
    const excess = Math.max(0, consumption - tariff.includedCum);
    const subtotal = Math.round((tariff.minimumCharge + Math.round(excess * tariff.excessRate * 100) / 100) * 100) / 100;
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

  function lookup() {
    const meter = $('meter_number').value.trim();
    consumer = null;
    $('calcBalance').textContent = '—';
    if (!/^\d+$/.test(meter)) { $('meterInfo').innerHTML = ''; recalc(); return; }
    $('meterInfo').innerHTML = '<span class="text-muted">Looking up…</span>';
    fetch(lookupUrl + '?meter=' + encodeURIComponent(meter) + '&period=' + encodeURIComponent($('billing_period').value), { headers: { 'Accept': 'application/json' } })
      .then(r => r.json())
      .then(d => {
        if (meter !== $('meter_number').value.trim()) return; // typed on meanwhile
        if (!d.found) { $('meterInfo').innerHTML = '<span class="text-danger">' + d.message + '</span>'; recalc(); return; }
        consumer = d;
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
      })
      .catch(() => { $('meterInfo').innerHTML = '<span class="text-danger">Could not look up the meter. Please try again.</span>'; });
  }

  $('meter_number').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(lookup, 350); });
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
})();
</script>
@endpush
@endif
