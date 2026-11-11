@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:480px;'])

@section('title', __('Activate Account'))

@push('styles')
<style>
  .activate-form .form-control, .activate-form .form-select{font-size:14px;}
  .activate-form .row > .col-half{flex:0 0 auto;width:50%;}
  @media (max-width: 640px){ .activate-form .row > .col-half{width:100%;} }
  .form-section{font-size:12px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:var(--primary-dark);
    margin:4px 0 12px;padding-bottom:6px;border-bottom:1px solid var(--border);}
  .form-section:not(:first-of-type){margin-top:8px;}
  .field-help{font-size:12px;color:#5b6d77;margin-top:4px;display:flex;justify-content:space-between;gap:8px;flex-wrap:wrap;}
  .field-status{font-weight:600;}
  .field-status.ok{color:#1a7a68;}
  .field-status.bad{color:#c62828;}
</style>
@endpush

@section('content')
  <h1>{{ __('Activate Your Account') }}</h1>
  <p class="subtitle">{{ __('Link your existing water account to an online login.') }}</p>

  @include('partials.errors')

  <form method="POST" action="{{ route('activate.store') }}" class="activate-form">
    @csrf
    <h2 class="form-section">{{ __('Your water account') }}</h2>
    <div class="mb-3">
      <label class="form-label" for="meter_number">{{ __('Meter Number *') }}</label>
      <input type="text" id="meter_number" name="meter_number" class="form-control" placeholder="{{ __('e.g. 1001') }}" required
             inputmode="numeric" pattern="\d+" maxlength="20" data-digits-only title="{{ __('Numbers only') }}" value="{{ old('meter_number') }}">
      <div class="text-muted" style="font-size:12px;">{{ __('Numbers only — exactly as printed on your meter or bill.') }}</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="full_name">{{ __('Name (as registered with the barangay) *') }}</label>
      <input type="text" id="full_name" name="full_name" class="form-control" required placeholder="{{ __('e.g. Juan D. Dela Cruz') }}" value="{{ old('full_name') }}">
    </div>
    <div class="row gx-3">
      <div class="col-half mb-3">
        <label class="form-label" for="purok_id">{{ __('Purok *') }}</label>
        <select id="purok_id" name="purok_id" class="form-select" required>
          <option value="">{{ __('Select purok') }}</option>
          @foreach ($puroks as $pk)
            <option value="{{ $pk->purok_id }}" @selected((string)old('purok_id') === (string)$pk->purok_id)>{{ $pk->purok_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-half mb-3">
        {{-- Starts blank so the type is a real choice, not a skipped default. --}}
        @include('partials.consumer-type-select', ['selected' => old('consumer_type', ''), 'blank' => true])
      </div>
    </div>

    <h2 class="form-section">{{ __('Your login details') }}</h2>
    <div class="row gx-3">
      <div class="col-half mb-3">
        <label class="form-label" for="email">{{ __('Email Address *') }}</label>
        <input type="email" id="email" name="email" class="form-control" required placeholder="{{ __('you@email.com') }}" value="{{ old('email') }}">
      </div>
      <div class="col-half mb-3">
        <label class="form-label" for="contact_number">{{ __('Contact Number') }}</label>
        <input type="tel" id="contact_number" name="contact_number" class="form-control" inputmode="tel" placeholder="09XX XXX XXXX" value="{{ old('contact_number') }}">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="username">{{ __('Choose a Username *') }}</label>
      <input type="text" id="username" name="username" class="form-control" required maxlength="50" autocomplete="username"
             aria-describedby="username_help" data-username-check="{{ route('activate.username') }}" value="{{ old('username') }}">
      <div class="field-help" id="username_help">
        <span>{{ __('You\'ll use this to log in. Save it somewhere safe.') }}</span>
        <span class="field-status" data-username-status aria-live="polite"></span>
      </div>
    </div>
    <div class="row gx-3">
      <div class="col-half mb-3">
        <label class="form-label" for="password">{{ __('Password *') }}</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
      </div>
      <div class="col-half mb-3">
        <label class="form-label" for="confirm_password">{{ __('Confirm Password *') }}</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password"
               data-match="password">
        <div class="field-help"><span class="field-status" data-match-status="confirm_password" aria-live="polite"></span></div>
      </div>
      <div class="col-12 mb-3" style="margin-top:-8px;">@include('partials.password-hint', ['for' => 'password'])</div>
    </div>
    <button type="submit" class="btn btn-primary w-100">{{ __('Activate Account') }}</button>
  </form>

  <div class="auth-footer-link">
    {{ __('Already activated?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>
  </div>
  <div class="auth-footer-link">
    {{ __('Not a member yet?') }} <a href="{{ route('apply') }}">{{ __('Apply for Membership') }}</a>
  </div>
@endsection

@push('scripts')
<script>
  // Live "✓ Available / ✗ Taken" while typing the username (the server checks it again on submit).
  (function () {
    var input = document.querySelector('[data-username-check]'), out = document.querySelector('[data-username-status]');
    if (!input || !out || !window.fetch) return;
    var timer = null, last = '';
    function show(text, cls) { out.textContent = text; out.className = 'field-status' + (cls ? ' ' + cls : ''); }
    function check() {
      var value = input.value.trim();
      if (value === last) return;
      last = value;
      if (!value) { show(''); input.setCustomValidity(''); return; }
      show(__t('Checking…'));
      fetch(input.getAttribute('data-username-check') + '?username=' + encodeURIComponent(value), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || input.value.trim() !== value) return;
          if (d.available) { show('✓ ' + __t('Available'), 'ok'); input.setCustomValidity(''); }
          else { show('✗ ' + __t('Taken — please choose another'), 'bad'); input.setCustomValidity(__t('That username is already taken. Please choose another.')); }
        })
        .catch(function () { show(''); });
    }
    input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(check, 400); });
    if (input.value) check();
  })();
</script>
@endpush
