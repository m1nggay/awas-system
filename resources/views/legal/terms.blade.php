@extends('layouts.legal')

@section('title', 'Terms of Service')

@section('content')
<h1>Terms of Service</h1>
<p class="legal-date">Effective {{ $effective }}</p>

<p>These terms apply to the Adlay Water Augmentation System (<strong>AWAS</strong>) of {{ $barangay }}, Carrascal, Surigao del Sur.
By creating an account or using AWAS, you agree to them and to our <a href="{{ route('privacy') }}">Privacy Policy</a>.</p>

<h2>1. Your account</h2>
<ul>
  <li>Membership applications must be true and complete, with a valid government ID of the applicant. The water office may
    reject an application or close an account with false information.</li>
  <li>Keep your password private. You are responsible for what is done with your account. After 5 wrong passwords in a row the
    account is locked until you reset your password or the administrator unlocks it.</li>
  <li>If you sign in with Google, keep that Google account secure as well.</li>
</ul>

<h2>2. Meter readings and bills</h2>
<ul>
  <li>Bills are computed from your meter readings using the water rates set by the barangay, which may change after notice.</li>
  <li>If you believe a reading or bill is wrong, report it to the water office before the due date so it can be checked.</li>
</ul>

<h2>3. Payments</h2>
<ul>
  <li>Pay on or before the due date. Late payments may get a penalty, and accounts with unpaid bills may be disconnected,
    as announced by the water office.</li>
  <li>Online payments are made with GCash. Payments through the "Open GCash app" button are processed by PayMongo and are
    confirmed automatically. Payments made by scanning the barangay's GCash QR are confirmed by the water office after it checks
    the receipt you upload; the bill shows Pending until then. A payment is final once confirmed; for a payment made by mistake,
    contact the water office.</li>
  <li>A receipt screenshot you upload must be your own, real receipt. Fake or edited receipts will be rejected and may be
    reported.</li>
</ul>

<h2>4. Acceptable use</h2>
<p>Do not try to access other people's accounts, interfere with the system, upload harmful files, or use AWAS for anything unlawful.
The AWAS Assistant gives general help only; the water office's records and decisions are what count.</p>

<h2>5. Availability</h2>
<p>We try to keep AWAS available at all times, but it may be unavailable during maintenance or because of problems beyond our
control. You can always pay and ask questions at the water office.</p>

<h2>6. Changes</h2>
<p>We may update these terms. The new version will be posted on this page with a new effective date.</p>

<h2>7. Contact us</h2>
<p>AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur
  @if ($email)<br>Email: <a href="mailto:{{ $email }}">{{ $email }}</a>@endif
  @if ($phone)<br>Phone: <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@endif
</p>
@endsection
