@extends('layouts.auth', ['video' => true])

@section('title', 'Login')

@section('content')
  <h1>AGAS Login</h1>
  <p class="subtitle">Smart Water Management &amp; Billing System<br>Barangay Adlay</p>

  @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
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
    <a href="{{ route('home') }}">&larr; Back to Dashboard</a>
  </div>
@endsection
