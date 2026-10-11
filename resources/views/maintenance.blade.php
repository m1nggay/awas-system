@extends('layouts.auth')

@section('title', 'Under Maintenance')

@push('styles')
<style>.auth-logo img{width:86px;height:86px;}</style>
@endpush

@section('content')
  <div class="text-center" style="font-size:44px;line-height:1;" aria-hidden="true">🔧</div>
  <h1>Under Maintenance</h1>
  <p class="subtitle">AWAS — Barangay Adlay</p>

  <div class="alert alert-info" style="white-space:pre-line;">{{ $message }}</div>
  @if ($until)
    <p class="text-center mb-3">Expected to be back by <strong>{{ $until->format('M j, Y g:i A') }}</strong>.</p>
  @endif
  <p class="text-center text-muted small mb-3">Your bills and payments are safe. Payments already made online are still recorded.</p>

  <div class="text-center">
    <a href="{{ route('login') }}" class="small">Administrator sign in</a>
  </div>
@endsection
