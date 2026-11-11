@extends('layouts.auth', ['video' => true])

@section('title', __('Login Code'))

@section('content')
  <h1>{{ __('Enter Your Login Code') }}</h1>
  <p class="subtitle">{{ __('For extra security, administrator sign-ins need a second step. We sent a 6-digit code to') }} <strong>{{ $email }}</strong>.</p>

  @if ($codeNotice)
    <div class="alert alert-{{ $codeNotice[0] }}">{{ $codeNotice[1] }}</div>
  @endif
  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

  <form method="POST" action="{{ route('two-factor.verify') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="otp">{{ __('6-Digit Code') }}</label>
      <input type="text" id="otp" name="otp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="123456" data-digits-only autocomplete="one-time-code">
    </div>
    <button type="submit" class="btn btn-primary w-100">{{ __('Verify & Sign In') }}</button>
  </form>
  <form method="POST" action="{{ route('two-factor.resend') }}" style="margin-top:10px;">
    @csrf
    <button type="submit" class="btn btn-secondary w-100" data-countdown="{{ $resendIn }}" data-label="{{ __('Resend Code') }}">{{ __('Resend Code') }}</button>
  </form>
  <p class="text-muted text-center mt-2 mb-0" style="font-size:12px;">{{ __('The code expires in :ttl minutes. Didn\'t get it? Check your Spam or Promotions folder.', ['ttl' => $ttl]) }}</p>

  <form method="POST" action="{{ route('two-factor.cancel') }}" class="auth-footer-link">
    @csrf
    <button type="submit" class="btn btn-link p-0">{{ __('← Back to Login') }}</button>
  </form>
@endsection
