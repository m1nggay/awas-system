@extends('layouts.auth', ['video' => true])

@section('title', __('Verify Email'))

@section('content')
  <h1>{{ __('Verify Your Email') }}</h1>

  @if (($success ?? false) && $isApplicant)
    {{-- Confirmation: the reference number, how long the review takes, and how they'll hear back. --}}
    <div class="text-center mb-3">
      <i class="ph-fill ph-check-circle" style="font-size:46px;color:#1a7a68;" aria-hidden="true"></i>
      <h2 class="h5 mt-1 mb-1" style="color:#062a4a;">{{ __('Application Submitted Successfully') }}</h2>
      <p class="text-muted mb-0" style="font-size:13.5px;">{{ __('Your email is verified and your application is now with the barangay water office.') }}</p>
    </div>
    @if (($applicationRef ?? '') !== '')
      <div class="text-center mb-3" style="background:#f0fbff;border:1px solid var(--border);border-radius:12px;padding:14px;">
        <div class="text-muted" style="font-size:12px;text-transform:uppercase;letter-spacing:.06em;">{{ __('Your reference number') }}</div>
        <div style="font-size:24px;font-weight:700;letter-spacing:.04em;color:#062a4a;">{{ $applicationRef }}</div>
        <div class="text-muted" style="font-size:12px;">{{ __('Write it down or take a screenshot. We also emailed it to you.') }}</div>
      </div>
    @endif
    <div style="font-size:13.5px;" class="mb-3">
      <strong>{{ __('What happens next') }}</strong>
      <ol class="mt-2 mb-0" style="padding-left:20px;line-height:1.6;">
        <li>{{ __('The water office reviews your details, ID and photo — usually within :days working days.', ['days' => config('agas.application_review_days')]) }}</li>
        <li>{{ __('We email you when your application is approved or rejected. You can also log in anytime to see its status.') }}</li>
        <li>{{ __('Once approved, the water office assigns your water meter and your account becomes active.') }}</li>
      </ol>
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
