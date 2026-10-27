@php
  $badge = ['submitted' => ['Pending Verification', '#b26a00'], 'received' => ['Paid', '#1e7e34'], 'rejected' => ['Rejected', '#c62828']][$status];
@endphp
<p>Hi {{ $name }},</p>

@if ($admin && $status === 'submitted')
  <p><strong>{{ $consumer }}</strong> (Meter {{ $meter }}) submitted an online GCash payment with a receipt screenshot. Please check it and verify or reject it.</p>
@elseif ($admin)
  <p><strong>{{ $consumer }}</strong> (Meter {{ $meter }}) paid online. PayMongo confirmed the payment and it was recorded automatically — nothing to verify.</p>
@elseif ($status === 'submitted')
  <p>We received your payment submission. The barangay water office will check your GCash receipt; you will get another email once it is verified.</p>
@elseif ($status === 'received')
  <p>Your payment was received and recorded. Thank you!</p>
@else
  <p>Your payment could not be accepted. Your bill was <strong>not</strong> paid — please pay again or visit the barangay water office.</p>
@endif

<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;font-size:14px;border:1px solid #ddd;">
  <tr><td style="color:#666;">Status</td><td><strong style="color:{{ $badge[1] }};">{{ $badge[0] }}</strong></td></tr>
  <tr><td style="color:#666;">Payment reference</td><td><strong>{{ $reference }}</strong></td></tr>
  <tr><td style="color:#666;">Amount</td><td><strong>{{ $amount }}</strong></td></tr>
  <tr><td style="color:#666;">Method</td><td>{{ $method }}</td></tr>
  @if ($gatewayRef)
    <tr><td style="color:#666;">GCash Ref. No.</td><td>{{ $gatewayRef }}</td></tr>
  @endif
  <tr><td style="color:#666;">Bill</td><td>{{ $period }} — Meter {{ $meter }}</td></tr>
  <tr><td style="color:#666;">Date</td><td>{{ $date }}</td></tr>
  @if ($status === 'received')
    <tr><td style="color:#666;">Bill status</td><td>{{ $billPaid ? 'Fully paid' : 'Remaining balance: ' . $balance }}</td></tr>
  @endif
  @if ($status === 'rejected' && $reason)
    <tr><td style="color:#666;">Reason</td><td>{{ $reason }}</td></tr>
  @endif
</table>

<p style="margin-top:16px;">
  @if ($admin)
    Open Payments in AWAS: <a href="{{ $url }}">{{ $url }}</a>
  @elseif ($status === 'received')
    View or download your receipt (log in first): <a href="{{ $url }}">{{ $url }}</a>
  @else
    See your bill in AWAS: <a href="{{ $url }}">{{ $url }}</a>
  @endif
</p>
<p style="color:#888;font-size:12px;">This is an automatic message from AWAS — Adlay Water Augmentation System. Please do not reply to this email.</p>
