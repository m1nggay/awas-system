@extends('layouts.legal')

@section('title', __('Terms of Service'))

@php
  $m3 = fn ($v) => rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
  $ordinal = fn ($n) => $n . (in_array($n % 100, [11, 12, 13]) ? 'th' : (['th', 'st', 'nd', 'rd'][$n % 10] ?? 'th'));
@endphp

@section('content')
<h1>{{ __('Terms of Service') }}</h1>
<p class="legal-date">{{ __('Effective :effective', ['effective' => $effective]) }}</p>

<p>{{ __('By applying for membership, activating an account or logging in to AWAS, you agree to these terms and the') }} <a href="{{ route('privacy') }}">{{ __('Privacy Policy') }}</a>.</p>

<h2>{{ __('Membership and accounts') }}</h2>
<ul>
  <li>{{ __('You must be 18 or older and live in :barangay.', ['barangay' => $barangay]) }}</li>
  <li>{{ __('Apply online with a valid government ID, a selfie and an email address. The water office reviews each application and assigns your meter.') }}</li>
  <li>{{ __('Existing members link their meter to an online login through Activate Account. Only the registered account holder may do this.') }}</li>
  <li>{{ __('Give true information. Fake IDs or false details lead to rejection or account closure.') }}</li>
  <li>{{ __('Keep your password private. Your account locks after repeated failed logins; reset it through Forgot your password.') }}</li>
</ul>

<h2>{{ __('Meter reading and billing') }}</h2>
<ul>
  <li>{{ __('Field staff read your meter every month. Consumption is the present reading minus the previous reading, in cubic meters (m³).') }}</li>
  <li>{{ __('Your bill follows the barangay\'s rates, the same for every household:') }}</li>
</ul>
{{-- Live from System Settings (BillingService::tariff()). --}}
<table class="table table-sm table-bordered" style="max-width:420px;font-size:14px;">
  <thead><tr><th>{{ __('Water used') }}</th><th class="text-end">{{ __('Charge') }}</th></tr></thead>
  <tbody>
    <tr><td>{{ __('First :m3 m³ (minimum charge)', ['m3' => $m3($tariff['includedCum'])]) }}</td><td class="text-end">{{ formatCurrency($tariff['minimumCharge']) }}</td></tr>
    @foreach ($tariff['tiers'] as $t)
      <tr>
        <td>{{ $t['to'] === null ? __('Each m³ above :from', ['from' => $m3($t['lower'])]) : __('Each m³ above :from up to :to', ['from' => $m3($t['lower']), 'to' => $m3($t['to'])]) }}</td>
        <td class="text-end">{{ formatCurrency($t['rate']) }}</td>
      </tr>
    @endforeach
  </tbody>
</table>
<ul>
  <li>{{ __('If your reading looks wrong or you see a leak, contact the water office before paying. They can check your meter and correct the reading.') }}</li>
</ul>

<h2>{{ __('Payment') }}</h2>
<ul>
  <li>{!! __('Bills are due on the <strong>:day of the month after the reading</strong>.', ['day' => e(app()->getLocale() === 'tl' ? 'ika-' . $dueDay : $ordinal($dueDay))]) !!}</li>
  <li>{{ __('Pay online with GCash through Pay Bill, by scanning the barangay\'s GCash QR and uploading your receipt, or in cash at the water office.') }}</li>
  <li>{{ __('Uploaded receipts are checked by staff before your bill is marked Paid.') }}</li>
  @if ($latePct > 0)
    <li>{!! __('Late payments get a <strong>:pct% penalty</strong>.', ['pct' => e($m3($latePct))]) !!}</li>
  @endif
</ul>

@if ($tariff['seniorPct'] > 0)
<h2>{{ __('Senior citizen discount') }}</h2>
<p>{!! __('Senior citizens (60 and older) get a <strong>:pct% discount</strong>. The account must be in the senior\'s name, and the water office must approve the senior citizen ID.', ['pct' => e($m3($tariff['seniorPct']))]) !!}</p>
@endif

<h2>{{ __('Disconnection') }}</h2>
<ul>
  <li>{!! $discMonths === 1
      ? __('Accounts unpaid <strong>1 month after the due date</strong> may be disconnected.')
      : __('Accounts unpaid <strong>:n months after the due date</strong> may be disconnected.', ['n' => $discMonths]) !!}</li>
  <li>{{ __('Meter tampering or illegal connections may be disconnected right away.') }}</li>
  <li>{{ __('To reconnect, pay the full balance.') }}</li>
</ul>

<h2>{{ __('Not allowed') }}</h2>
<p>{{ __('Tampering with meters, illegal connections, using someone else\'s account, uploading fake IDs or receipts, or trying to hack or disrupt AWAS.') }}</p>

<h2>{{ __('Other terms') }}</h2>
<ul>
  <li>{{ __('Rates, due dates and penalties may change when the barangay updates them. Changes will be posted in AWAS.') }}</li>
  <li>{{ __('Water service may stop for repairs, drought or disasters. We will post notices when we can.') }}</li>
  <li>{{ __('The water office\'s records are the official record. Errors in AWAS will be corrected.') }}</li>
  <li>{{ __('Disputes go first to barangay conciliation (Katarungang Pambarangay), under Philippine law.') }}</li>
</ul>

<h2>{{ __('Contact') }}</h2>
<p>{{ __('AWAS Water Office, :barangay, Carrascal, Surigao del Sur', ['barangay' => $barangay]) }} · <a href="mailto:{{ $email }}">{{ $email }}</a></p>
@endsection
