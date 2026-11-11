@extends('layouts.legal')

@section('title', __('Privacy Policy'))

@section('content')
<h1>{{ __('Privacy Policy') }}</h1>
<p class="legal-date">{{ __('Effective :effective', ['effective' => $effective]) }}</p>

<p>{{ __('AWAS (Adlay Water Augmentation System) is the online water management and billing system of the AWAS Water Office, :barangay, Carrascal, Surigao del Sur. We protect your information under the Data Privacy Act of 2012 (RA 10173).', ['barangay' => $barangay]) }}</p>

<h2>{{ __('What we collect') }}</h2>
<ul>
  <li><strong>{{ __('When you apply:') }}</strong> {{ __('full name, date of birth, sex, contact number, email, purok and address, household number, number of household members, type of residence and type of consumer') }}</li>
  <li><strong>{{ __('To verify your identity:') }}</strong> {{ __('photos of the front and back of one valid government ID, and a live selfie') }}</li>
  <li><strong>{{ __('Your login:') }}</strong> {{ __('username and password (stored encrypted), or your Google account name and email if you use "Continue with Google"') }}</li>
  <li><strong>{{ __('Your water account:') }}</strong> {{ __('meter number, monthly meter readings, consumption, bills, penalties and discounts') }}</li>
  <li><strong>{{ __('Your payments:') }}</strong> {{ __('GCash payment confirmations, uploaded GCash receipts, and payments made at the office') }}</li>
  <li><strong>{{ __('Senior citizen ID') }}</strong>{{ __(', if you claim the senior discount') }}</li>
</ul>

<h2>{{ __('How we use it') }}</h2>
<ul>
  <li>{{ __('Review your membership application and assign your meter') }}</li>
  <li>{{ __('Confirm you are a real person and the rightful account owner') }}</li>
  <li>{{ __('Record meter readings and compute your bill using the barangay\'s rates') }}</li>
  <li>{{ __('Process GCash and office payments and keep your payment history and receipts') }}</li>
  <li>{{ __('Apply the senior citizen discount (your date of birth shows if you are 60 or older)') }}</li>
  <li>{{ __('Send your 6-digit email code, password reset codes, bill reminders and notices by email and in AWAS') }}</li>
  <li>{{ __('Lock accounts after repeated failed logins to stop break-ins') }}</li>
</ul>
<p>{{ __('We use your information only for the water service. We never sell it or use it for ads.') }}</p>

<h2>{{ __('Who can see it') }}</h2>
<ul>
  <li><strong>{{ __('AWAS Water Office staff') }}</strong> {{ __('with their own admin accounts. Meter readers see only what they need to record readings.') }}</li>
  <li><strong>{{ __('GCash') }}</strong> {{ __('and') }} <strong>{{ __('PayMongo') }}</strong>{{ __(', to process your online payments') }}</li>
  <li><strong>{{ __('Brevo') }}</strong>{{ __(', to send emails such as your codes, bill notices and receipts') }}</li>
  <li><strong>{{ __('Google') }}</strong>{{ __(', only if you sign in with Google, and our email and hosting providers that run AWAS') }}</li>
  <li><strong>{{ __('Government authorities') }}</strong>{{ __(', only when the law requires it') }}</li>
</ul>

<h2>{{ __('How long we keep it') }}</h2>
<ul>
  <li>{{ __('ID photos and selfie: deleted :days days after your application is approved or rejected', ['days' => $photoDays]) }}</li>
  <li>{{ __('Account, billing and payment records: while your account is active, then 5 years') }}</li>
</ul>

<h2>{{ __('How we protect it') }}</h2>
<p>{{ __('AWAS uses HTTPS encryption. Passwords are stored encrypted, ID photos and selfies are kept in private storage, and only authorized staff can open your records.') }}</p>

<h2>{{ __('Your rights') }}</h2>
<p>{{ __('You can ask to see, correct or delete your information, or withdraw your consent. You can also file a complaint with the National Privacy Commission (privacy.gov.ph). If your data is ever leaked, we will tell you and the Commission within 72 hours.') }}</p>

<h2>{{ __('Contact') }}</h2>
<p>{{ __('Privacy contact: AWAS Water Office, :barangay, Carrascal, Surigao del Sur', ['barangay' => $barangay]) }}<br>
  {{ __('Email:') }} <a href="mailto:{{ $email }}">{{ $email }}</a></p>
@endsection
