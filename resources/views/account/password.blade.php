@extends('layouts.' . $layout)

@section('title', 'Change Password')

@section('content')
<div class="card" style="max-width:520px;">
  <div class="card-header"><h3>🔑 Change Password</h3></div>
  <div class="card-body">
    @if (session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <ol class="d-flex gap-3 list-unstyled mb-4" style="font-size:12.5px;">
      @foreach (['request' => '1. Get a code', 'code' => '2. Enter the code', 'new' => '3. New password'] as $key => $label)
        <li class="{{ $step === $key ? 'fw-bold text-primary' : 'text-muted' }}">{{ $label }}</li>
      @endforeach
    </ol>

    @if ($step === 'request')
      <p>For your security, we'll email a 6-digit code to
        <strong>{{ $user->email ?: 'your email address' }}</strong>. You'll set the new password after entering it.</p>
      <form method="POST" action="{{ route('account.password.send-code') }}">
        @csrf
        <button type="submit" class="btn btn-primary w-100" data-countdown="{{ $resendIn }}" data-label="Send Code to My Email" @disabled(!$user->email)>Send Code to My Email</button>
      </form>
      @unless ($user->email)
        <p class="text-danger mt-2 mb-0" style="font-size:12.5px;">Your account has no email address. Please ask the administrator to add one.</p>
      @endunless

    @elseif ($step === 'code')
      <p>Enter the 6-digit code we sent to <strong>{{ $user->email }}</strong>. It expires in {{ $ttl }} minutes.
        Check your Spam or Promotions folder if you don't see it.</p>
      <form method="POST" action="{{ route('account.password.verify') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label" for="otp">6-Digit Code</label>
          <input type="text" id="otp" name="otp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="123456" data-digits-only autocomplete="one-time-code">
        </div>
        <button type="submit" class="btn btn-primary w-100">Verify Code</button>
      </form>
      <form method="POST" action="{{ route('account.password.send-code') }}" class="mt-2">
        @csrf
        <button type="submit" class="btn btn-secondary w-100" data-countdown="{{ $resendIn }}" data-label="Resend Code">Resend Code</button>
      </form>

    @else
      <form method="POST" action="{{ route('account.password.update') }}">
        @csrf
        <div class="mb-3">
          <label class="form-label" for="new_password">New Password</label>
          <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password" autofocus>
          @include('partials.password-hint', ['for' => 'new_password'])
        </div>
        <div class="mb-3">
          <label class="form-label" for="confirm_password">Confirm New Password</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-primary w-100">Change Password</button>
      </form>
    @endif
  </div>
</div>
@endsection
