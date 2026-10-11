@extends('layouts.app')

@section('title', 'System Settings')

@push('styles')
<style>
  .set-card .card-body{padding:20px 22px;}
  .set-title{font-weight:700;font-size:15px;margin:0;}
  .set-sub{font-size:12.5px;color:var(--text-muted);margin:2px 0 14px;}
  .set-card .form-label{font-size:12.5px;font-weight:600;margin-bottom:4px;}
  .set-box{background:#f4fafc;border:1px solid var(--border);border-radius:12px;padding:14px 16px;}
  .tier-table{width:100%;border-collapse:collapse;}
  .tier-table th{font-size:11px;letter-spacing:.04em;text-transform:uppercase;color:var(--text-muted);font-weight:600;padding:8px 10px;border-bottom:1px solid var(--border);}
  .tier-table td{padding:8px 10px;border-bottom:1px solid var(--border);vertical-align:middle;}
  .tier-table .tier-name{font-weight:600;color:#9a6a14;white-space:nowrap;}
  .tier-table .tier-from{color:#9a6a14;}
  .tier-del{width:34px;height:34px;padding:0;border-radius:8px;}
  .sample-row{display:flex;justify-content:space-between;gap:12px;font-size:13px;padding:3px 0;}
  .sample-total{font-weight:700;font-size:15px;border-top:1px solid var(--border);margin-top:6px;padding-top:8px;}
  .timeline-note{background:#f4fafc;border-radius:10px;padding:10px 14px;font-size:12.5px;color:var(--text-muted);}
  @media (max-width:640px){
    .tier-table th:nth-child(2), .tier-table td:nth-child(2){display:none;}
    .tier-table td, .tier-table th{padding:6px 4px;}
  }
</style>
@endpush

@section('content')
@php
  $v = fn (string $key) => old($key, $values[$key] ?? '');
  $n = fn ($x) => $x === null || $x === '' ? '' : rtrim(rtrim(number_format((float)$x, 2, '.', ''), '0'), '.');
  // After a failed save the form shows what was typed; otherwise the saved tiers.
  $tierRows = old('tier_rate') !== null
      ? collect(old('tier_rate'))->map(fn ($rate, $i) => ['to' => old('tier_to')[$i] ?? '', 'rate' => $rate])->values()->all()
      : collect($tiers)->map(fn ($t) => ['to' => $n($t['to']), 'rate' => $n($t['rate'])])->all();
@endphp

<form method="POST" action="{{ route('admin.settings.update') }}" id="settingsForm">
  @csrf
  @include('partials.errors')

  {{-- 1. General --}}
  <div class="card set-card">
    <div class="card-body">
      <h3 class="set-title">1. General</h3>
      <p class="set-sub">Shown on printed bills and the consumer portal.</p>
      <div class="row g-3">
        <div class="col-md-3 col-sm-6"><label class="form-label" for="barangay_name">Barangay name</label>
          <input type="text" class="form-control" id="barangay_name" name="barangay_name" value="{{ $v('barangay_name') }}" required maxlength="100"></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="contact_email">Support email</label>
          <input type="email" class="form-control" id="contact_email" name="contact_email" value="{{ $v('contact_email') }}" maxlength="150"></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="contact_number">Support contact number</label>
          <input type="text" class="form-control" id="contact_number" name="contact_number" value="{{ $v('contact_number') }}" maxlength="20" inputmode="tel"></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="currency_symbol">Currency label</label>
          <input type="text" class="form-control" id="currency_symbol" name="currency_symbol" value="{{ $v('currency_symbol') }}" required maxlength="10"></div>
      </div>
    </div>
  </div>

  {{-- 2. Water Rates --}}
  <div class="card set-card">
    <div class="card-body">
      <h3 class="set-title">2. Water Rates</h3>
      <p class="set-sub">Minimum charge covers the first block. Each tier charges only the cubic meters that fall inside it.</p>

      <div class="set-box mb-3">
        <div class="row g-3">
          <div class="col-sm-4"><label class="form-label" for="minimum_charge">Minimum charge (₱, always charged)</label>
            <input type="number" step="0.01" min="0" class="form-control" id="minimum_charge" name="minimum_charge" value="{{ $n($v('minimum_charge')) }}" required></div>
          <div class="col-sm-4"><label class="form-label" for="minimum_cubic_meters">Covers the first (m³)</label>
            <input type="number" step="0.01" min="0.01" class="form-control" id="minimum_cubic_meters" name="minimum_cubic_meters" value="{{ $n($v('minimum_cubic_meters')) }}" required></div>
          <div class="col-sm-4"><label class="form-label" for="rates_effective_from">Rates effective from</label>
            <input type="date" class="form-control" id="rates_effective_from" name="rates_effective_from" value="{{ $v('rates_effective_from') }}" required></div>
        </div>
      </div>

      <div class="form-label">Excess rate tiers</div>
      <div class="table-responsive">
        <table class="tier-table">
          <thead><tr><th>Tier</th><th>From (m³)</th><th>To (m³)</th><th>Rate per m³ (₱)</th><th></th></tr></thead>
          <tbody id="tierRows">
            @foreach ($tierRows as $i => $t)
              <tr>
                <td class="tier-name">Tier {{ $i + 2 }}</td>
                <td class="tier-from"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tier_to[]" value="{{ $t['to'] }}" placeholder="No limit" aria-label="To (m³)"></td>
                <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tier_rate[]" value="{{ $t['rate'] }}" required aria-label="Rate per m³"></td>
                <td class="text-end"><button type="button" class="btn btn-outline btn-sm tier-del text-danger" title="Remove this tier" aria-label="Remove this tier">🗑</button></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-2">
        <button type="button" class="btn btn-outline btn-sm" id="addTier">+ Add tier</button>
        <span class="text-muted" style="font-size:11.5px;">"From" fills in automatically from the tier above. Leave the last "To" blank for no limit.</span>
      </div>

      <div class="set-box mt-3">
        <div class="row g-3">
          <div class="col-md-5">
            <div class="form-label">Sample bill check</div>
            <label class="form-label fw-normal" for="sampleCum">Consumption (m³)</label>
            <input type="number" step="0.01" min="0" class="form-control mb-2" id="sampleCum" value="25" style="max-width:200px;">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="sampleSenior">
              <label class="form-check-label" for="sampleSenior" style="font-size:13px;">Senior citizen account</label>
            </div>
          </div>
          <div class="col-md-7" id="sampleLines" aria-live="polite"></div>
        </div>
      </div>
    </div>
  </div>

  {{-- 3. Due Dates & Penalties --}}
  <div class="card set-card">
    <div class="card-body">
      <h3 class="set-title">3. Due Dates &amp; Penalties</h3>
      <p class="set-sub">Applies to every account, regardless of tier.</p>
      <div class="row g-3">
        <div class="col-md-3 col-sm-6"><label class="form-label" for="due_day_of_month">Due day (of the month after billing)</label>
          <input type="number" min="1" max="31" step="1" class="form-control" id="due_day_of_month" name="due_day_of_month" value="{{ $v('due_day_of_month') }}" required></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="overdue_grace_days">Grace period (days after due)</label>
          <input type="number" min="0" max="90" step="1" class="form-control" id="overdue_grace_days" name="overdue_grace_days" value="{{ $v('overdue_grace_days') }}" required></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="late_penalty_percent">Late penalty (% of unpaid bill)</label>
          <input type="number" min="0" max="100" step="0.01" class="form-control" id="late_penalty_percent" name="late_penalty_percent" value="{{ $n($v('late_penalty_percent')) }}" required></div>
        <div class="col-md-3 col-sm-6"><label class="form-label" for="disconnection_months">Disconnect after (months unpaid)</label>
          <input type="number" min="1" max="24" step="1" class="form-control" id="disconnection_months" name="disconnection_months" value="{{ $v('disconnection_months') }}" required></div>
      </div>
      <div class="timeline-note mt-3" id="timelineNote"></div>
    </div>
  </div>

  {{-- 4. Discounts --}}
  <div class="card set-card">
    <div class="card-body">
      <h3 class="set-title">4. Discounts</h3>
      <p class="set-sub">Deducted after the water charge is computed.</p>
      <label class="form-label" for="senior_discount_percent">Senior citizen discount (%)</label>
      <input type="number" min="0" max="100" step="0.01" class="form-control" id="senior_discount_percent" name="senior_discount_percent" value="{{ $n($v('senior_discount_percent')) }}" required>
    </div>
  </div>

  <div class="d-flex justify-content-end mb-4">
    <button type="submit" class="btn btn-primary">Save Settings</button>
  </div>
</form>

<template id="tierTemplate">
  <tr>
    <td class="tier-name"></td>
    <td class="tier-from"></td>
    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tier_to[]" placeholder="No limit" aria-label="To (m³)"></td>
    <td><input type="number" step="0.01" min="0" class="form-control form-control-sm" name="tier_rate[]" required aria-label="Rate per m³"></td>
    <td class="text-end"><button type="button" class="btn btn-outline btn-sm tier-del text-danger" title="Remove this tier" aria-label="Remove this tier">🗑</button></td>
  </tr>
</template>

<script>
(function () {
  const $ = id => document.getElementById(id);
  const rows = $('tierRows');
  const num = el => parseFloat(el.value);
  const fmt = x => (Math.round(x * 100) / 100).toString();
  const peso = x => '₱' + x.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const ordinal = d => d + (['th', 'st', 'nd', 'rd'][(d % 100 > 10 && d % 100 < 14) ? 0 : (d % 10 < 4 ? d % 10 : 0)] || 'th');

  // Tier names, "From" values and the delete buttons (at least one tier stays).
  function refreshTiers() {
    let lower = num($('minimum_cubic_meters')) || 0;
    const list = [...rows.rows];
    list.forEach((tr, i) => {
      tr.querySelector('.tier-name').textContent = 'Tier ' + (i + 2);
      tr.querySelector('.tier-from').textContent = fmt(lower + 1);
      const to = parseFloat(tr.querySelector('[name="tier_to[]"]').value);
      tr.querySelector('.tier-del').disabled = list.length === 1;
      if (!isNaN(to)) lower = to;
    });
    refreshSample();
  }

  // Same formula as the server (BillingService::computeBillAmount).
  function refreshSample() {
    const cum = Math.max(0, num($('sampleCum')) || 0);
    const included = num($('minimum_cubic_meters')) || 0;
    const minCharge = num($('minimum_charge')) || 0;
    let html = '<div class="sample-row"><span>Minimum charge (first ' + fmt(included) + ' m³)</span><span>' + peso(minCharge) + '</span></div>';
    let subtotal = minCharge, lower = included;
    for (const tr of rows.rows) {
      const toVal = tr.querySelector('[name="tier_to[]"]').value, rate = parseFloat(tr.querySelector('[name="tier_rate[]"]').value) || 0;
      const to = toVal === '' ? null : parseFloat(toVal);
      const top = to === null ? cum : Math.min(cum, to);
      const inTier = Math.max(0, Math.round((top - lower) * 100) / 100);
      if (inTier > 0) {
        const amount = Math.round(inTier * rate * 100) / 100;
        subtotal += amount;
        html += '<div class="sample-row"><span>' + tr.querySelector('.tier-name').textContent + ': ' + fmt(inTier) + ' m³ × ' + peso(rate) + '</span><span>' + peso(amount) + '</span></div>';
      }
      if (to === null) break;
      lower = to;
    }
    subtotal = Math.round(subtotal * 100) / 100;
    if ($('sampleSenior').checked) {
      const discount = Math.round(subtotal * (num($('senior_discount_percent')) || 0)) / 100;
      html += '<div class="sample-row"><span>Senior citizen discount</span><span>− ' + peso(discount) + '</span></div>';
      subtotal = Math.round((subtotal - discount) * 100) / 100;
    }
    $('sampleLines').innerHTML = html + '<div class="sample-row sample-total"><span>Amount due</span><span>' + peso(subtotal) + '</span></div>';
  }

  function refreshTimeline() {
    const due = parseInt($('due_day_of_month').value, 10), grace = parseInt($('overdue_grace_days').value, 10);
    const pct = $('late_penalty_percent').value, months = parseInt($('disconnection_months').value, 10);
    $('timelineNote').textContent = 'Timeline: bill due on the ' + (isNaN(due) ? '?' : ordinal(due))
      + ' → ' + (pct || 0) + '% penalty added after day ' + (isNaN(grace) ? '?' : grace) + ' of grace'
      + ' → disconnection notice after ' + (isNaN(months) ? '?' : months) + ' unpaid month' + (months === 1 ? '' : 's') + '.';
  }

  $('addTier').addEventListener('click', () => {
    const last = rows.rows[rows.rows.length - 1];
    rows.appendChild($('tierTemplate').content.cloneNode(true));
    refreshTiers();
    // The previous last tier now needs an upper limit.
    if (last && last.querySelector('[name="tier_to[]"]').value === '') last.querySelector('[name="tier_to[]"]').focus();
    else rows.rows[rows.rows.length - 1].querySelector('[name="tier_to[]"]').focus();
  });
  rows.addEventListener('click', e => {
    const del = e.target.closest('.tier-del');
    if (del && rows.rows.length > 1) { del.closest('tr').remove(); refreshTiers(); }
  });
  document.getElementById('settingsForm').addEventListener('input', e => {
    if (['due_day_of_month', 'overdue_grace_days', 'late_penalty_percent', 'disconnection_months'].includes(e.target.id)) refreshTimeline();
    else refreshTiers();
  });
  $('sampleSenior').addEventListener('change', refreshSample);
  refreshTiers();
  refreshTimeline();
})();
</script>

@include('admin.faqs._section')

@php
  $maintOn = setting('maintenance_mode', '0') === '1';
  $maintUntil = (string)setting('maintenance_until', '');
@endphp
<div class="card" id="maintenance" style="scroll-margin-top:80px;">
  <div class="card-header">
    <h3>🔧 Maintenance</h3>
    <span class="badge {{ $maintOn ? 'badge-warning' : 'badge-success' }}">{{ $maintOn ? 'Maintenance mode is ON' : 'System is running normally' }}</span>
  </div>
  <div class="card-body">
    <p class="text-muted mb-3" style="font-size:13px;">
      Turn this on while updating the system or fixing data. Only <strong>administrators</strong> can use AWAS while it is on —
      consumers and meter readers see a maintenance page with your message and are signed out. Online payments already made are still confirmed.
    </p>
    <form method="POST" action="{{ route('admin.settings.maintenance') }}" id="maintenanceForm">
      @csrf
      <input type="hidden" name="maintenance_mode" value="{{ $maintOn ? '0' : '1' }}">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label" for="maintenance_message">Message shown to users</label>
          <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="2" maxlength="500"
                    placeholder="e.g. We are updating the water rates. AWAS will be back shortly.">{{ setting('maintenance_message', '') }}</textarea>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="maintenance_until">Expected back by (optional)</label>
          <input type="datetime-local" class="form-control" id="maintenance_until" name="maintenance_until"
                 value="{{ $maintUntil && strtotime($maintUntil) ? date('Y-m-d\TH:i', strtotime($maintUntil)) : '' }}">
        </div>
      </div>
      <button type="submit" class="btn {{ $maintOn ? 'btn-success' : 'btn-warning' }} mt-3">
        {{ $maintOn ? '✅ Turn Off Maintenance Mode' : '🔧 Turn On Maintenance Mode' }}
      </button>
    </form>
  </div>
</div>
<script>
document.getElementById('maintenanceForm').addEventListener('submit', function (e) {
  const turningOn = this.querySelector('[name="maintenance_mode"]').value === '1';
  if (turningOn && !confirm('Turn on maintenance mode?\n\nConsumers and meter readers will be signed out and see the maintenance page until you turn it off.')) {
    e.preventDefault();
  }
});
</script>
@endsection
