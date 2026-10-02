@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
@include('partials.errors')

<div class="row g-3">
  <div class="col-md-6">
  <div class="card h-100">
    <div class="card-header"><h3>Account Information</h3></div>
    <div class="card-body">
      @if ($consumer)
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Meter Number</span><strong>{{ $consumer->meter_number ?: '—' }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Name</span><strong>{{ $consumer->full_name }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Address</span><strong>{{ $consumer->address }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Purok</span><strong>{{ $consumer->purok_name }}</strong></div>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Type of Consumer</span><strong>{{ consumerTypeLabel($consumer->consumer_type) }}</strong></div>
        <div class="d-flex justify-content-between"><span class="text-muted">Status</span><span class="badge {{ $consumer->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $consumer->status }}</span></div>
      @else
        <p class="text-muted">Your login is not linked to a water account yet.</p>
      @endif
    </div>
  </div>
  </div>

  <div class="col-md-6">
  <div class="card h-100">
    <div class="card-header"><h3>Update Contact Info</h3></div>
    <div class="card-body">
      <form method="POST" action="{{ route('resident.profile.contact') }}">
        @csrf
        <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $user->email }}"></div>
        <div class="mb-3"><label class="form-label">Contact Number</label><input type="text" name="contact_number" class="form-control" value="{{ $user->contact_number }}"></div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
    </div>
  </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Change Password</h3></div>
  <div class="card-body">
    <p class="text-muted mb-3">For your security, changing your password needs a 6-digit code sent to your registered email.</p>
    <a href="{{ route('account.password') }}" class="btn btn-primary">Change Password</a>
  </div>
</div>
@endsection
