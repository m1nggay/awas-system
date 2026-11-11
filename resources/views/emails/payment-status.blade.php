@php
  $badge = ['submitted' => ['Pending Verification', '#b26a00'], 'received' => ['Paid', '#1e7e34'], 'rejected' => ['Rejected', '#c62828']][$status];
@endphp
<p>{{ __('Hi :name,', ['name' => $name]) }}</p>

@if ($admin && $status === 'submitted')
  <p><strong>{{ $consumer }}</strong> {{ __('(Meter :meter) submitted an online GCash payment with a receipt screenshot. Please check it and verify or reject it.', ['meter' => $meter]) }}</p>
@elseif ($admin)
  <p><strong>{{ $consumer }}</strong> {{ __('(Meter :meter) paid online. PayMongo confirmed the payment and it was recorded automatically — nothing to verify.', ['meter' => $meter]) }}</p>
@elseif ($status === 'submitted')
  <p>{{ __('We received your payment submission. The barangay water office will check your GCash receipt; you will get another email once it is verified.') }}</p>
@elseif ($status === 'received')
  <p>{{ __('Your payment was received and recorded. Thank you!') }}</p>
@else
  <p>{{ __('Your payment could not be accepted. Your bill was') }} <strong>{{ __('not') }}</strong> {{ __('paid — please pay again or visit the barangay water office.') }}</p>
@endif

<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;border:1px solid #ddd;">
  <tr><td style="color:#666;">{{ __('Status') }}</td><td><strong style="color:{{ $badge[1] }};">{{ __($badge[0]) }}</strong></td></tr>
  <tr><td style="color:#666;">{{ __('Payment reference') }}</td><td><strong>{{ $reference }}</strong></td></tr>
  <tr><td style="color:#666;">{{ __('Amount') }}</td><td><strong>{{ $amount }}</strong></td></tr>
  <tr><td style="color:#666;">{{ __('Method') }}</td><td>{{ $method }}</td></tr>
  @if ($gatewayRef)
    <tr><td style="color:#666;">{{ __('GCash Ref. No.') }}</td><td>{{ $gatewayRef }}</td></tr>
  @endif
  <tr><td style="color:#666;">{{ __('Bill') }}</td><td>{{ $period }} — Meter {{ $meter }}</td></tr>
  <tr><td style="color:#666;">{{ __('Date') }}</td><td>{{ $date }}</td></tr>
  @if ($status === 'received')
    <tr><td style="color:#666;">{{ __('Bill status') }}</td><td>{{ $billPaid ? __('Fully paid') : __('Remaining balance: ') . $balance }}</td></tr>
  @endif
  @if ($status === 'rejected' && $reason)
    <tr><td style="color:#666;">{{ __('Reason') }}</td><td>{{ $reason }}</td></tr>
  @endif
</table>

<p style="margin-top:16px;">
  @if ($admin)
    {{ __('Open Payments in AWAS:') }} <a href="{{ $url }}">{{ $url }}</a>
  @elseif ($status === 'received')
    {{ __('View or download your receipt (log in first):') }} <a href="{{ $url }}">{{ $url }}</a>
  @else
    {{ __('See your bill in AWAS:') }} <a href="{{ $url }}">{{ $url }}</a>
  @endif
</p>
<p style="color:#888;font-size:12px;">{{ __('This is an automatic message from AWAS — Adlay Water Augmentation System. Please do not reply to this email.') }}</p>
