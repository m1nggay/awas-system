@extends('layouts.auth', ['video' => true])

@section('title', __('Login'))

@push('styles')
<style>
  /* Compact so the whole card fits a 1080p screen without scrolling */
  .auth-card{padding:26px 32px 22px;}
  .auth-logo{margin-bottom:4px;}
  .auth-logo img{width:62px;height:62px;}
  .auth-card p.subtitle{margin-bottom:14px;}
  .auth-card .form-label{margin-bottom:4px;}
  .auth-card .auth-footer-link{margin-top:10px;}
  .pw-label-row{display:flex;align-items:baseline;justify-content:space-between;gap:10px;}
  .pw-label-row a{font-size:13px;}
  .login-remember{margin:4px 0 14px;font-size:13.5px;}
  .login-remember .form-check-label{color:var(--text-dark);}
  .btn .spinner-border{width:16px;height:16px;border-width:2px;}
  .locked-help{display:flex;flex-direction:column;gap:6px;background:#f2f8fb;border:1px solid var(--border);border-radius:10px;padding:12px 14px;font-size:13.5px;}
  .locked-help a,.locked-help span{display:flex;align-items:center;gap:8px;color:var(--text-dark);}
  .locked-help a{font-weight:600;color:var(--primary-dark);text-decoration:none;min-height:32px;}
  .locked-help i{font-size:17px;color:var(--primary);}
  .social-or{display:flex;align-items:center;gap:12px;margin:12px 0 10px;color:var(--text-muted);font-size:12.5px;}
  .social-or::before,.social-or::after{content:"";flex:1;height:1px;background:var(--border);}
  .social-btns{display:flex;flex-direction:column;gap:10px;}
  .social-btn{display:flex;align-items:center;justify-content:center;gap:10px;min-height:44px;border-radius:8px;font-weight:600;font-size:14px;text-decoration:none;transition:.15s;}
  .social-btn i{font-size:20px;}
  .social-btn.google{background:#fff;border:1px solid #d6dde2;color:#1f1f1f;}
  .social-btn.google i{color:#4285f4;}
  .social-btn.google:hover{background:#f6f8fa;color:#1f1f1f;}
  .social-btn.facebook{background:#1877f2;border:1px solid #1877f2;color:#fff;}
  .social-btn.facebook:hover{background:#166fe0;color:#fff;}
</style>
@endpush

@section('content')
  <h1>{{ __('AWAS Login') }}</h1>
  <p class="subtitle">{{ __('Smart Water Management & Billing System') }}<br>{{ __('Barangay Adlay') }}</p>

  @if (setting('maintenance_mode', '0') === '1')<div class="alert alert-warning"><i class="ph ph-wrench" aria-hidden="true"></i> {{ __('AWAS is under maintenance — only administrators can sign in right now.') }}</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('locked'))
    {{-- Locked account: how to reach the water office (System Settings → Support email / number). --}}
    @php $phone = setting('contact_number'); $mail = setting('contact_email'); @endphp
    <div class="locked-help mb-3">
      <strong>{{ __('Contact the AWAS administrator') }}</strong>
      @if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}"><i class="ph ph-phone" aria-hidden="true"></i> {{ $phone }}</a>@endif
      @if ($mail)<a href="mailto:{{ $mail }}?subject={{ rawurlencode('AWAS account locked: ' . old('username')) }}"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $mail }}</a>@endif
      <span><i class="ph ph-map-pin" aria-hidden="true"></i> {{ __('Or visit the AWAS water office in :value', ['value' => setting('barangay_name', 'Barangay Adlay')]) }}</span>
    </div>
  @endif
  @if ($timeoutMsg)<div class="alert alert-warning">{{ $timeoutMsg }}</div>@endif
  @if ($registeredMsg)<div class="alert alert-success">{{ $registeredMsg }}</div>@endif

  <form method="POST" action="{{ route('login.attempt') }}" id="loginForm" data-loading-form>
    @csrf
    <div class="mb-3">
      <label for="username" class="form-label">{{ __('Username or Email') }}</label>
      <input type="text" id="username" name="username" class="form-control" required autofocus autocomplete="username"
             placeholder="{{ __('e.g. juan.delacruz or you@email.com') }}" value="{{ old('username') }}">
    </div>
    <div class="mb-2">
      <div class="pw-label-row">
        <label for="password" class="form-label">{{ __('Password') }}</label>
        <a href="{{ route('password.forgot') }}">{{ __('Forgot password?') }}</a>
      </div>
      <input type="password" id="password" name="password" class="form-control" required autocomplete="current-password"
             placeholder="{{ __('Enter your password') }}">
    </div>
    <div class="form-check login-remember">
      <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
      <label class="form-check-label" for="remember">{{ __('Remember me for 30 days') }}</label>
    </div>
    <button type="submit" class="btn btn-primary w-100" data-loading-text="{{ __('Logging in…') }}">{{ __('Login') }}</button>
  </form>

  {{-- Google / Facebook: only the ones set up in .env (GOOGLE_CLIENT_ID, FACEBOOK_CLIENT_ID, ...) --}}
  @php $socials = \App\Http\Controllers\Auth\SocialLoginController::enabled(); @endphp
  @if ($socials)
    <div class="social-or"><span>{{ __('or') }}</span></div>
    <div class="social-btns">
      @isset($socials['google'])
        <a href="{{ route('social.redirect', 'google') }}" class="social-btn google">@include('partials.google-logo') {{ __('Continue with Google') }}</a>
      @endisset
      @isset($socials['facebook'])
        <a href="{{ route('social.redirect', 'facebook') }}" class="social-btn facebook"><i class="ph-fill ph-facebook-logo" aria-hidden="true"></i> {{ __('Continue with Facebook') }}</a>
      @endisset
    </div>
  @endif

  <div class="auth-footer-link">
    {{ __('Already a member?') }} <a href="{{ route('activate') }}">{{ __('Activate your account') }}</a>
  </div>
  <div class="auth-footer-link">
    <a href="{{ url('/') }}"><i class="ph ph-arrow-left" aria-hidden="true"></i> {{ __('Back to Home') }}</a>
  </div>
  <div class="auth-footer-link" style="font-size:12px;margin-top:8px;">
    <a href="{{ route('privacy') }}" style="font-weight:500;">{{ __('Privacy Policy') }}</a> · <a href="{{ route('terms') }}" style="font-weight:500;">{{ __('Terms of Service') }}</a>
  </div>
@endsection

@push('scripts')
<script>
  // "Remember my username": kept in this browser only (never the password).
  (function () {
    var box = document.getElementById('rememberUsername'), user = document.getElementById('username'), key = 'awas.username';
    var saved = null;
    try { saved = localStorage.getItem(key); } catch (e) {}
    if (saved) {
      box.checked = true;
      if (!user.value) { user.value = saved; document.getElementById('password').focus(); }
    }
    document.getElementById('loginForm').addEventListener('submit', function () {
      try {
        if (box.checked && user.value.trim()) localStorage.setItem(key, user.value.trim());
        else localStorage.removeItem(key);
      } catch (e) {}
    });
  })();
</script>
@endpush
