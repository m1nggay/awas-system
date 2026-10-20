@extends('layouts.legal')

@section('title', 'Privacy Policy')

@section('content')
<h1>Privacy Policy</h1>
<p class="legal-date">Effective {{ $effective }}</p>

<p>The Adlay Water Augmentation System (<strong>AWAS</strong>) is the water service and billing system of {{ $barangay }},
Carrascal, Surigao del Sur. This policy explains what personal information AWAS collects, why, and how it is protected,
in line with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>.</p>

<h2>1. Information we collect</h2>
<ul>
  <li><strong>Membership application:</strong> full name, birth date, sex, purok and address, contact number, email address,
    household number and members, and type of residence.</li>
  <li><strong>Identity check:</strong> photos of the front and back of a valid government ID, and a selfie taken during the
    application to confirm that the applicant is a real person.</li>
  <li><strong>Water account:</strong> meter number, meter readings, bills, payments, receipts and notices.</li>
  <li><strong>Payments:</strong> the amount, date, reference number and method (GCash or cash). Payments made with the
    "Open GCash app" button are processed by PayMongo; AWAS never receives or stores your e-wallet PIN or card details. If you pay by scanning the barangay's
    QR code, the screenshot of your receipt that you upload is kept as proof of payment.</li>
  <li><strong>Your account:</strong> username, a securely hashed password (never stored as plain text), profile picture if you add one,
    and — if you choose "Continue with Google" — your Google account ID and email.</li>
  <li><strong>Activity records:</strong> sign-ins, important actions and the IP address they came from, to keep accounts secure.</li>
  <li><strong>Chat assistant:</strong> the questions you type into the AWAS Assistant.</li>
</ul>

<h2>2. Why we use it</h2>
<ul>
  <li>To review membership applications and confirm identity and residence.</li>
  <li>To read meters, compute bills (including senior citizen discounts), record payments and issue receipts.</li>
  <li>To send bill notices, due-date reminders, announcements and one-time verification codes by email and in-app notifications.</li>
  <li>To protect accounts (for example, locking an account after repeated wrong passwords) and to prevent fraud.</li>
  <li>To prepare reports required for the operation of the barangay water system.</li>
</ul>
<p>We do not sell your information or use it for advertising.</p>

<h2>3. Who we share it with</h2>
<p>Only the barangay's authorized AWAS administrators and meter readers can see your records, and only what their work needs
(a meter reader sees the consumers in their assigned puroks). Some services help run AWAS and receive only what they need:</p>
<ul>
  <li><strong>PayMongo</strong> — to process GCash payments made with the "Open GCash app" button.</li>
  <li><strong>Brevo</strong> — to deliver emails such as codes, notices and receipts.</li>
  <li><strong>Google</strong> — only if you use "Continue with Google", to confirm your sign-in.</li>
  <li><strong>Anthropic (Claude)</strong> — to answer questions in the AWAS Assistant, and to read the reference number and amount
    from uploaded GCash receipt screenshots.</li>
  <li><strong>Hostinger</strong> — the server where AWAS and its database are hosted.</li>
</ul>
<p>We may also disclose information when required by law or by a lawful order of a government authority.</p>

<h2>4. How we protect it</h2>
<ul>
  <li>The website uses an encrypted connection (HTTPS).</li>
  <li>Passwords are hashed; changing a password or email needs a code sent to your email.</li>
  <li>ID photos, selfies and receipts are stored privately and can be opened only by signed-in administrators.</li>
  <li>Access is limited by role, sessions end after inactivity, and important actions are logged.</li>
</ul>

<h2>5. How long we keep it</h2>
<p>We keep your information while you are a member of the water system and afterwards only as long as needed for billing records,
audits and legal requirements. Records of applications that were not approved are kept only as long as needed to handle them.</p>

<h2>6. Your rights</h2>
<p>Under the Data Privacy Act you have the right to be informed, to access your information, to have wrong information corrected,
to object to or withdraw consent for processing, to ask for deletion when the information is no longer needed, to data portability,
to damages, and to file a complaint with the <strong>National Privacy Commission</strong> (privacy.gov.ph).</p>
<p>You can view and update your contact details, password and profile in <strong>My Profile</strong>. For anything else, contact us below.</p>

<h2>7. Contact us</h2>
<p>AWAS Water Office, {{ $barangay }}, Carrascal, Surigao del Sur
  @if ($email)<br>Email: <a href="mailto:{{ $email }}">{{ $email }}</a>@endif
  @if ($phone)<br>Phone: <a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}">{{ $phone }}</a>@endif
</p>

<h2>8. Changes to this policy</h2>
<p>If we change this policy, we will post the new version on this page with a new effective date.</p>
@endsection
