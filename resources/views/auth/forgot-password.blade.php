@extends('layouts.auth')

@section('title', 'Forgot Password')

@section('content')
  <h1>Forgot Password</h1>
  <p class="subtitle">Enter the Gmail address of your AWAS account — we'll send a 6-digit code to it.</p>

  @if (session('sent'))
    <div class="alert alert-success">We've emailed a 6-digit verification code to your Gmail. It expires in {{ $ttl }} minutes. Check your Spam or Promotions folder if you don't see it.</div>
    <a href="{{ route('password.reset') }}" class="btn btn-primary w-100">Enter the Code</a>
  @else
    @if (session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('password.send-code') }}">
      @csrf
      <div class="mb-3">
        <label class="form-label" for="email">Gmail Address</label>
        <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror" required autofocus
               value="{{ old('email') }}" placeholder="juan.delacruz@gmail.com" autocomplete="email"
               pattern="[A-Za-z0-9.]+@gmail\.com" title="Enter a Gmail address, e.g. juan.delacruz@gmail.com">
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
      </div>
      <button type="submit" class="btn btn-primary w-100">Send Reset Code</button>
    </form>
  @endif

  <div class="auth-footer-link">
    <a href="{{ route('login') }}">&larr; Back to Login</a>
  </div>
@endsection
