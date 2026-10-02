@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
  <h1>Forgot Password</h1>
  <p class="subtitle">Enter your username or email — we'll send a 6-digit code to the email on file.</p>

  @if (session('sent'))
    <div class="alert alert-success">If an account matches, we've emailed a 6-digit verification code to the address on file. It expires in {{ $ttl }} minutes. Check your Spam or Promotions folder if you don't see it.</div>
    <a href="{{ route('password.reset') }}" class="btn btn-primary w-100">Enter the Code</a>
  @else
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('password.send-code') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="identifier">Username or Email</label>
        <input type="text" id="identifier" name="identifier" class="form-control" required autofocus value="{{ old('identifier') }}">
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Code</button>
    </form>
  @endif

  <div class="auth-footer-link">
    <a href="{{ route('login') }}">&larr; Back to Login</a>
  </div>
@endsection
