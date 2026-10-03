@extends('layouts.auth', ['video' => true, 'cardStyle' => 'max-width:480px;'])

@section('title', 'Create Account')

@section('content')
  <h1>Create Resident Account</h1>
  <p class="subtitle">Link your existing water account to an online login.</p>

  @include('partials.errors')

  <form method="POST" action="{{ route('register.store') }}">
    @csrf
    <div class="mb-3">
      <label class="form-label" for="meter_number">Meter Number *</label>
      <input type="text" id="meter_number" name="meter_number" class="form-control" placeholder="e.g. 1001" required
             inputmode="numeric" pattern="\d+" maxlength="20" data-digits-only title="Numbers only" value="{{ old('meter_number') }}">
      <div class="text-muted" style="font-size:12px;">Numbers only — exactly as printed on your meter or bill.</div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="full_name">Name (as registered with the barangay) *</label>
      <input type="text" id="full_name" name="full_name" class="form-control" required value="{{ old('full_name') }}">
    </div>
    <div class="row gx-3">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="purok_id">Purok *</label>
        <select id="purok_id" name="purok_id" class="form-select" required>
          <option value="">Select purok</option>
          @foreach ($puroks as $pk)
            <option value="{{ $pk->purok_id }}" @selected((string)old('purok_id') === (string)$pk->purok_id)>{{ $pk->purok_name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-6 mb-3">
        @include('partials.consumer-type-select', ['selected' => old('consumer_type', 'residential')])
      </div>
    </div>
    <div class="row gx-3">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="email">Email Address *</label>
        <input type="email" id="email" name="email" class="form-control" required value="{{ old('email') }}">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="contact_number">Contact Number</label>
        <input type="text" id="contact_number" name="contact_number" class="form-control" value="{{ old('contact_number') }}">
      </div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="username">Choose a Username *</label>
      <input type="text" id="username" name="username" class="form-control" required value="{{ old('username') }}">
    </div>
    <div class="row gx-3">
      <div class="col-md-6 mb-3">
        <label class="form-label" for="password">Password *</label>
        <input type="password" id="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label" for="confirm_password">Confirm Password *</label>
        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
      </div>
      <div class="col-12 mb-3" style="margin-top:-8px;">@include('partials.password-hint', ['for' => 'password'])</div>
    </div>
    <button type="submit" class="btn btn-primary w-100">Create Account</button>
  </form>

  <div class="auth-footer-link">
    Already have an account? <a href="{{ route('login') }}">Login</a>
  </div>
  <div class="auth-footer-link">
    Don't have a water account yet? <a href="{{ route('apply') }}">Apply for membership</a>
  </div>
@endsection
