@extends('layouts.auth')

@section('title', 'Reset Password')

@section('content')
  <h1>Reset Password</h1>
  <p class="subtitle">Enter the 6-digit code we emailed you and choose a new password.</p>

  @if ($success ?? false)
    <div class="alert alert-success">Password reset! You may now log in with your new password.</div>
    <a href="{{ route('login') }}" class="btn btn-primary w-100">Go to Login</a>
  @elseif ($lockedError ?? false)
    <div class="alert alert-danger">{{ $lockedError }}</div>
    <a href="{{ route('password.forgot') }}" class="btn btn-primary w-100">Request a New Code</a>
  @else
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('password.update') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="otp">6-Digit Code</label>
        <input type="text" id="otp" name="otp" class="form-control" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="123456">
      </div>
      <div class="mb-3">
        <label class="form-label" for="new_password">New Password</label>
        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
        @include('partials.password-hint', ['for' => 'new_password'])
      </div>
      <div class="mb-3">
        <label class="form-label" for="confirm_password">Confirm New Password</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8">
      </div>
      <button type="submit" class="btn btn-primary w-100">Reset Password</button>
    </form>
    <div class="auth-footer-link"><a href="{{ route('password.forgot') }}">Didn't get a code? Request another</a></div>
  @endif

  <div class="auth-footer-link">
    <a href="{{ route('login') }}">&larr; Back to Login</a>
  </div>
@endsection
