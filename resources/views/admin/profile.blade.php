@extends('layouts.app')

@section('title', 'Profile')

@section('content')
@include('partials.errors')
@include('partials.profile-header', ['user' => $user])

<div class="row g-3 align-items-start">
  <div class="col-md-6 d-flex flex-column gap-3">
  <div class="card mb-0">
    <div class="card-header"><h3>Account Information</h3></div>
    <div class="card-body">
      <div class="d-flex justify-content-between mb-2"><span class="text-muted">Username</span><strong>{{ $user->username }}</strong></div>
      <div class="d-flex justify-content-between mb-2"><span class="text-muted">Role</span><strong>{{ roleLabel($user->role) }}</strong></div>
      @if ($puroks !== null)
        <div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted">Assigned Puroks</span><strong class="text-end">{{ $puroks->isNotEmpty() ? $puroks->join(', ') : 'None yet' }}</strong></div>
      @endif
      <div class="d-flex justify-content-between mb-2"><span class="text-muted">Status</span><span class="badge {{ $user->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $user->status }}</span></div>
      <div class="d-flex justify-content-between"><span class="text-muted">Last Sign-in</span><strong>{{ $user->last_login ? formatDateTime($user->last_login) : '—' }}</strong></div>
    </div>
  </div>

  <div class="card mb-0">
    <div class="card-header"><h3>Change Password</h3></div>
    <div class="card-body">
      <p class="text-muted mb-3">For your security, changing your password needs a 6-digit code sent to your registered email.</p>
      <a href="{{ route('account.password') }}" class="btn btn-primary">Change Password</a>
    </div>
  </div>
  </div>

  <div class="col-md-6">
  <div class="card mb-0">
    <div class="card-header"><h3>Update Contact Info</h3></div>
    <div class="card-body">
      <form method="POST" action="{{ route('admin.profile.contact') }}">
        @csrf
        <div class="mb-3"><label class="form-label" for="email">Email</label><input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}"></div>
        <div class="mb-3"><label class="form-label" for="contact_number">Contact Number</label><input type="text" id="contact_number" name="contact_number" class="form-control" value="{{ old('contact_number', $user->contact_number) }}"></div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
      </form>
      <p class="text-muted small mt-3 mb-0">Your email is where password codes are sent, so keep it up to date.</p>
    </div>
  </div>
  </div>
</div>
@endsection
