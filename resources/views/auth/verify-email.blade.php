@extends('layouts.auth', ['video' => true])

@section('title', __('Verify Email'))

@section('content')
  <h1>{{ __('Verify Your Email') }}</h1>

  @if (($success ?? false) && $isApplicant)
    <div class="alert alert-success">
      <strong>{{ __('Application Submitted Successfully') }}</strong><br>
      {{ __('Your AWAS membership application has been submitted and is currently waiting for administrator review. Please wait for the administrator to review your application. You will receive an email notification once your application has been approved or rejected.') }}
      @if (($applicationRef ?? '') !== '')<br><br>{{ __('Reference number:') }} <strong>{{ $applicationRef }}</strong>@endif
    </div>
    <a href="{{ route('login') }}" class="btn btn-primary w-100">{{ __('Log In to Check Application Status') }}</a>
  @elseif ($success ?? false)
    <div class="alert alert-success">{{ __('Email verified! Your account is now active — you may log in.') }}</div>
    <a href="{{ route('login', ['registered' => 1]) }}" class="btn btn-primary w-100">{{ __('Go to Login') }}</a>
  @else
    <p class="subtitle">{{ __('Enter the 6-digit code sent to') }} <strong>{{ $user->email }}</strong> {{ __('to :value.', ['value' => $isApplicant ? __('verify your email and submit your application') : __('activate your account')]) }}</p>

    @if ($codeNotice ?? null)
      <div class="alert alert-{{ $codeNotice[0] }}">{{ $codeNotice[1] }}</div>
    @endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('verify-email.verify') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="otp">{{ __('6-Digit Code') }}</label>
        <input type="text" id="otp" name="otp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="123456" data-digits-only autocomplete="one-time-code">
      </div>
      <button type="submit" class="btn btn-primary w-100">{{ $isApplicant ? __('Verify & Submit Application') : __('Verify & Activate Account') }}</button>
    </form>
    <form method="POST" action="{{ route('verify-email.resend') }}" style="margin-top:10px;">
      @csrf
      <button type="submit" class="btn btn-secondary w-100" data-countdown="{{ $resendIn ?? 0 }}" data-label="{{ __('Resend Code') }}">{{ __('Resend Code') }}</button>
    </form>
    <p class="text-muted text-center mt-2 mb-0" style="font-size:12px;">{{ __('The code expires in :ttl minutes. Didn\'t get it? Check your Spam or Promotions folder.', ['ttl' => $ttl]) }}</p>

    <div class="auth-footer-link">
      <a href="{{ route('login') }}">{{ __('← Back to Login') }}</a>
    </div>
  @endif
@endsection
