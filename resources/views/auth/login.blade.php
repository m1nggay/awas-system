@extends('layouts.auth', ['video' => true])

@section('title', 'Login')

@push('styles')
<style>
  .auth-logo img{width:86px;height:86px;}
  .locked-help{display:flex;flex-direction:column;gap:6px;background:#f2f8fb;border:1px solid var(--border);border-radius:10px;padding:12px 14px;font-size:13.5px;}
  .locked-help a,.locked-help span{display:flex;align-items:center;gap:8px;color:var(--text-dark);}
  .locked-help a{font-weight:600;color:var(--primary-dark);text-decoration:none;min-height:32px;}
  .locked-help i{font-size:17px;color:var(--primary);}
</style>
@endpush

@section('content')
  <h1>AWAS Login</h1>
  <p class="subtitle">Smart Water Management &amp; Billing System<br>Barangay Adlay</p>

  @if (setting('maintenance_mode', '0') === '1')<div class="alert alert-warning"><i class="ph ph-wrench" aria-hidden="true"></i> AWAS is under maintenance — only administrators can sign in right now.</div>@endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
  @if (session('locked'))
    {{-- Locked account: how to reach the water office (System Settings → Support email / number). --}}
    @php $phone = setting('contact_number'); $mail = setting('contact_email'); @endphp
    <div class="locked-help mb-3">
      <strong>Contact the AWAS administrator</strong>
      @if ($phone)<a href="tel:{{ preg_replace('/[^\d+]/', '', $phone) }}"><i class="ph ph-phone" aria-hidden="true"></i> {{ $phone }}</a>@endif
      @if ($mail)<a href="mailto:{{ $mail }}?subject={{ rawurlencode('AWAS account locked: ' . old('username')) }}"><i class="ph ph-envelope-simple" aria-hidden="true"></i> {{ $mail }}</a>@endif
      <span><i class="ph ph-map-pin" aria-hidden="true"></i> Or visit the AWAS water office in {{ setting('barangay_name', 'Barangay Adlay') }}</span>
    </div>
  @endif
  @if ($timeoutMsg)<div class="alert alert-warning">{{ $timeoutMsg }}</div>@endif
  @if ($registeredMsg)<div class="alert alert-success">{{ $registeredMsg }}</div>@endif

  <form method="POST" action="{{ route('login.attempt') }}">
    @csrf
    <div class="mb-3">
      <label for="username" class="form-label">Username or Email</label>
      <input type="text" id="username" name="username" class="form-control" required autofocus value="{{ old('username') }}">
    </div>
    <div class="mb-3">
      <label for="password" class="form-label">Password</label>
      <input type="password" id="password" name="password" class="form-control" required>
    </div>
    <div class="text-end mb-3">
      <a href="{{ route('password.forgot') }}">Forgot your password?</a>
    </div>
    <button type="submit" class="btn btn-primary w-100">Login</button>
  </form>

  <div class="auth-footer-link">
    New resident? <a href="{{ route('register') }}">Create an account</a>
  </div>
  <div class="auth-footer-link">
    <a href="{{ url('/') }}"><i class="ph ph-arrow-left" aria-hidden="true"></i> Back to Home</a>
  </div>
@endsection
